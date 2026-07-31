<?php
defined( 'ABSPATH' ) || exit;

class WBL_Frontend {

	/** @var bool */
	private static $localized = false;

	public static function hooks() {
		add_shortcode( 'webakery_login', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'wbl_login', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ) );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'login_message', array( __CLASS__, 'wp_login_inject' ) );
		add_action( 'login_init', array( __CLASS__, 'maybe_replace_wp_login' ) );
	}

	public static function register_assets() {
		wp_register_style( 'wbl-frontend', WBL_URL . 'assets/css/frontend.css', array(), WBL_VERSION );
		wp_register_style( 'wbl-templates', WBL_URL . 'assets/css/templates.css', array( 'wbl-frontend' ), WBL_VERSION );
		wp_register_style( 'wbl-motion', WBL_URL . 'assets/css/motion.css', array( 'wbl-templates' ), WBL_VERSION );
		wp_register_script( 'wbl-frontend', WBL_URL . 'assets/js/frontend.js', array(), WBL_VERSION, true );
	}

	public static function maybe_enqueue() {
		if ( self::page_has_shortcode() || self::is_elementor_page() ) {
			self::enqueue();
		}
	}

	public static function enqueue() {
		if ( ! wp_style_is( 'wbl-frontend', 'registered' ) ) {
			self::register_assets();
		}

		$s = WBL_Settings::all();
		wp_enqueue_style( 'wbl-frontend' );
		wp_enqueue_style( 'wbl-templates' );
		if ( 'none' !== ( $s['animation_style'] ?? 'hybrid' ) ) {
			wp_enqueue_style( 'wbl-motion' );
		}
		wp_add_inline_style( 'wbl-templates', self::inline_css( $s ) );
		wp_enqueue_script( 'wbl-frontend' );

		if ( ! self::$localized ) {
			self::$localized = true;
			wp_localize_script(
				'wbl-frontend',
				'WBL',
				array(
					'ajax'      => admin_url( 'admin-ajax.php' ),
					'nonce'     => wp_create_nonce( 'wbl_front' ),
					'google'    => WBL_Google::enabled() ? WBL_Google::auth_url() : '',
					'loggedIn'  => is_user_logged_in(),
					'animation' => $s['animation_style'],
					'i18n'      => array(
						'sending'   => 'در حال ارسال…',
						'verifying' => 'در حال بررسی…',
						'resend'    => 'ارسال مجدد',
						'wait'      => 'ارسال مجدد تا %s ثانیه',
						'enterCode' => 'کد تأیید را وارد کنید',
						'error'     => 'خطایی رخ داد. دوباره تلاش کنید.',
					),
				)
			);
		}
	}

	public static function inline_css( array $s ) {
		$css  = '.wbl-shell,.wbl-box{';
		$css .= '--wbl-primary:' . esc_attr( $s['primary_color'] ) . ';';
		$css .= '--wbl-accent:' . esc_attr( $s['primary_color'] ) . ';';
		$css .= '--wbl-glass-blur:' . (int) $s['glass_blur'] . 'px;';
		$css .= '--wbl-radius:' . (int) $s['glass_radius'] . 'px;';
		$css .= '--wbl-panel-a:' . esc_attr( $s['panel_color_a'] ) . ';';
		$css .= '--wbl-panel-b:' . esc_attr( $s['panel_color_b'] ) . ';';
		$css .= '}';
		if ( ! empty( $s['custom_css'] ) ) {
			$css .= "\n" . $s['custom_css'];
		}
		return $css;
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

	private static function is_elementor_page() {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! is_singular() ) {
			return false;
		}
		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return false;
		}
		return \Elementor\Plugin::$instance->documents->get( $post_id )
			&& \Elementor\Plugin::$instance->db->is_built_with_elementor( $post_id );
	}

	public static function shortcode( $atts = array() ) {
		if ( ! WBL_Plugin::is_usable() ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<div class="wbl-box wbl-error">لایسنس «ورود آسان» فعال نیست. از منوی لایسنس افزونه‌ها فعال کنید.</div>';
			}
			return '';
		}

		self::enqueue();
		$defaults = WBL_Settings::all();

		$atts = shortcode_atts(
			array(
				'redirect'   => '',
				'show_title' => '1',
				'title'      => '',
				'subtitle'   => '',
				'layout'     => $defaults['template_layout'],
				'animation'  => $defaults['animation_style'],
				'phone'      => $defaults['show_phone_visual'] ? '1' : '0',
			),
			$atts,
			'webakery_login'
		);

		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			ob_start();
			?>
			<div class="wbl-shell wbl-anim-<?php echo esc_attr( sanitize_html_class( $atts['animation'] ) ); ?>">
				<div class="wbl-box wbl-logged">
					<p>سلام، <?php echo esc_html( $user->display_name ); ?></p>
					<p class="wbl-actions">
						<a class="wbl-btn wbl-btn-primary" href="<?php echo esc_url( WBL_Auth::redirect_url() ); ?>">حساب کاربری</a>
						<a class="wbl-btn wbl-btn-ghost" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">خروج</a>
					</p>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		$s = $defaults;
		if ( '' !== $atts['title'] ) {
			$s['form_title'] = sanitize_text_field( $atts['title'] );
		}
		if ( '' !== $atts['subtitle'] ) {
			$s['form_subtitle'] = sanitize_text_field( $atts['subtitle'] );
		}

		$layout = sanitize_key( $atts['layout'] );
		if ( ! isset( WBL_Settings::layouts()[ $layout ] ) ) {
			$layout = 'split';
		}
		$animation = sanitize_key( $atts['animation'] );
		if ( ! isset( WBL_Settings::animations()[ $animation ] ) ) {
			$animation = 'hybrid';
		}

		$show_phone         = (int) $s['enable_phone'];
		$show_google        = WBL_Google::enabled();
		$error              = isset( $_GET['wbl_error'] ) ? sanitize_text_field( wp_unslash( $_GET['wbl_error'] ) ) : ''; // phpcs:ignore
		$redirect           = $atts['redirect'] ? esc_url_raw( $atts['redirect'] ) : '';
		$show_title         = ! in_array( (string) $atts['show_title'], array( '0', 'no', 'false', 'off' ), true );
		$show_phone_visual  = ! in_array( (string) $atts['phone'], array( '0', 'no', 'false', 'off' ), true );
		$uid                = 'wbl' . substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 8 );

		ob_start();
		include WBL_PATH . 'templates/login-shell.php';
		return ob_get_clean();
	}

	public static function wp_login_inject( $message ) {
		if ( ! WBL_Plugin::is_usable() ) {
			return $message;
		}
		if ( ! (int) WBL_Settings::get( 'enable_phone', 1 ) && ! WBL_Google::enabled() ) {
			return $message;
		}
		return self::shortcode( array( 'layout' => 'form' ) ) . $message;
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
