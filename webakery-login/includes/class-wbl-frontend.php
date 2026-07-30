<?php
defined( 'ABSPATH' ) || exit;

class WBL_Frontend {

	/** @var bool */
	private static $localized = false;

	public static function hooks() {
		add_shortcode( 'webakery_login', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'wbl_login', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ) );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'login_message', array( __CLASS__, 'wp_login_inject' ) );
		add_action( 'login_init', array( __CLASS__, 'maybe_replace_wp_login' ) );
	}

	public static function maybe_enqueue() {
		if ( self::page_has_shortcode() ) {
			self::enqueue();
		}
	}

	public static function enqueue() {
		$s = WBL_Settings::all();

		wp_register_style( 'wbl-frontend', WBL_URL . 'assets/css/frontend.css', array(), WBL_VERSION );
		wp_register_script( 'wbl-frontend', WBL_URL . 'assets/js/frontend.js', array(), WBL_VERSION, true );

		wp_enqueue_style( 'wbl-frontend' );
		wp_add_inline_style( 'wbl-frontend', ':root{--wbl-primary:' . esc_attr( $s['primary_color'] ) . ';}' );
		wp_enqueue_script( 'wbl-frontend' );

		if ( ! self::$localized ) {
			self::$localized = true;
			wp_localize_script(
				'wbl-frontend',
				'WBL',
				array(
					'ajax'     => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'wbl_front' ),
					'google'   => WBL_Google::enabled() ? WBL_Google::auth_url() : '',
					'loggedIn' => is_user_logged_in(),
					'i18n'     => array(
						'sending'   => 'در حال ارسال…',
						'verifying' => 'در حال بررسی…',
						'resend'    => 'ارسال مجدد کد',
						'wait'      => 'ارسال مجدد تا %s ثانیه',
						'enterCode' => 'کد تأیید را وارد کنید',
						'error'     => 'خطایی رخ داد. دوباره تلاش کنید.',
					),
				)
			);
		}
	}

	private static function page_has_shortcode() {
		if ( ! is_singular() ) {
			return false;
		}
		global $post;
		if ( ! ( $post instanceof WP_Post ) ) {
			return false;
		}
		return has_shortcode( $post->post_content, 'webakery_login' )
			|| has_shortcode( $post->post_content, 'wbl_login' );
	}

	public static function shortcode( $atts = array() ) {
		if ( ! WBL_Plugin::is_usable() ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<div class="wbl-box wbl-error">لایسنس «ورود آسان» فعال نیست. از منوی لایسنس افزونه‌ها فعال کنید.</div>';
			}
			return '';
		}

		self::enqueue();

		$atts = shortcode_atts(
			array(
				'redirect' => '',
			),
			$atts,
			'webakery_login'
		);

		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			ob_start();
			?>
			<div class="wbl-box wbl-logged">
				<p>سلام، <strong><?php echo esc_html( $user->display_name ); ?></strong></p>
				<p class="wbl-actions">
					<a class="wbl-btn" href="<?php echo esc_url( WBL_Auth::redirect_url() ); ?>">ورود به حساب</a>
					<a class="wbl-btn wbl-btn-ghost" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">خروج</a>
				</p>
			</div>
			<?php
			return ob_get_clean();
		}

		$s           = WBL_Settings::all();
		$show_phone  = (int) $s['enable_phone'];
		$show_google = WBL_Google::enabled();
		$error       = isset( $_GET['wbl_error'] ) ? sanitize_text_field( wp_unslash( $_GET['wbl_error'] ) ) : ''; // phpcs:ignore
		$redirect    = $atts['redirect'] ? esc_url_raw( $atts['redirect'] ) : '';

		ob_start();
		include WBL_PATH . 'templates/login-form.php';
		return ob_get_clean();
	}

	public static function wp_login_inject( $message ) {
		if ( ! WBL_Plugin::is_usable() ) {
			return $message;
		}
		if ( ! (int) WBL_Settings::get( 'enable_phone', 1 ) && ! WBL_Google::enabled() ) {
			return $message;
		}
		return self::shortcode() . $message;
	}

	public static function maybe_replace_wp_login() {
		if ( ! (int) WBL_Settings::get( 'replace_wp_login', 0 ) ) {
			return;
		}
		if ( ! empty( $_REQUEST['interim-login'] ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) { // phpcs:ignore
			return;
		}
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login'; // phpcs:ignore
		if ( in_array( $action, array( 'logout', 'rp', 'resetpass', 'lostpassword', 'postpass' ), true ) ) {
			return;
		}
		$page_id = (int) WBL_Settings::get( 'login_page_id', 0 );
		if ( ! $page_id ) {
			return;
		}
		$url = get_permalink( $page_id );
		if ( $url && ! is_user_logged_in() ) {
			wp_safe_redirect( $url );
			exit;
		}
	}
}
