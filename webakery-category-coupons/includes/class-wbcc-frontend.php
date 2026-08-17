<?php
defined( 'ABSPATH' ) || exit;

/**
 * بخش کاربر: شورت‌کد «دریافت کد تخفیف» + ویجت المنتور.
 * مشتری روی دکمه می‌زند و یک کد اختصاصی همان لحظه ساخته می‌شود.
 */
class WBCC_Frontend {

	const SHORTCODE = 'webakery_coupon';
	const AJAX      = 'wbcc_claim';

	public static function register() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_ajax_' . self::AJAX, array( __CLASS__, 'ajax_claim' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX, array( __CLASS__, 'ajax_claim' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_elementor' ) );
	}

	public static function assets() {
		wp_enqueue_style( 'wbcc-front', WBCC_URL . 'assets/front.css', array(), WBCC_VERSION );
		wp_enqueue_script( 'wbcc-front', WBCC_URL . 'assets/front.js', array(), WBCC_VERSION, true );
		wp_localize_script( 'wbcc-front', 'WBCC', array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( self::AJAX ),
		) );
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'campaign' => 0,
			'title'    => '',
			'button'   => 'دریافت کد تخفیف',
			'note'     => '',
		), $atts, self::SHORTCODE );

		$campaign = WBCC_Campaigns::get( $atts['campaign'] );
		if ( ! $campaign ) {
			return current_user_can( 'manage_woocommerce' )
				? '<p class="wbcc-admin-hint">کمپین تخفیف با شناسه ' . esc_html( (string) $atts['campaign'] ) . ' پیدا نشد.</p>'
				: '';
		}
		if ( empty( $campaign['enabled'] ) || empty( $campaign['public_enabled'] ) ) {
			return current_user_can( 'manage_woocommerce' )
				? '<p class="wbcc-admin-hint">برای این کمپین گزینه «دریافت کد توسط مشتری» فعال نیست.</p>'
				: '';
		}

		self::assets();

		$title = $atts['title'] ? $atts['title'] : $campaign['name'];
		$cats  = WBCC_Campaigns::category_names( WBCC_Campaigns::ids( $campaign['categories'] ), 4 );
		$need_email = ! is_user_logged_in();

		ob_start();
		?>
		<div class="wbcc-card" data-campaign="<?php echo esc_attr( (string) $campaign['id'] ); ?>">
			<div class="wbcc-card-head">
				<span class="wbcc-badge"><?php echo esc_html( WBCC_Date::fa_digits( WBCC_Campaigns::amount_label( $campaign ) ) ); ?> تخفیف</span>
				<h3 class="wbcc-title"><?php echo esc_html( $title ); ?></h3>
			</div>

			<?php if ( $cats ) : ?>
				<p class="wbcc-cats">روی دسته‌بندی: <?php echo esc_html( implode( '، ', $cats ) ); ?></p>
			<?php endif; ?>

			<?php if ( $atts['note'] ) : ?>
				<p class="wbcc-note"><?php echo esc_html( $atts['note'] ); ?></p>
			<?php endif; ?>

			<div class="wbcc-form">
				<?php if ( $need_email ) : ?>
					<input type="email" class="wbcc-email" placeholder="ایمیل شما" autocomplete="email">
				<?php endif; ?>
				<button type="button" class="wbcc-btn"><?php echo esc_html( $atts['button'] ); ?></button>
			</div>

			<div class="wbcc-result" hidden>
				<div class="wbcc-code-row">
					<code class="wbcc-code"></code>
					<button type="button" class="wbcc-copy">کپی</button>
				</div>
				<p class="wbcc-expiry"></p>
			</div>

			<p class="wbcc-message" hidden></p>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function ajax_claim() {
		check_ajax_referer( self::AJAX, 'nonce' );

		$id       = isset( $_POST['campaign'] ) ? (int) $_POST['campaign'] : 0;
		$campaign = WBCC_Campaigns::get( $id );

		if ( ! $campaign || empty( $campaign['enabled'] ) || empty( $campaign['public_enabled'] ) ) {
			wp_send_json_error( array( 'message' => 'این کمپین تخفیف در دسترس نیست.' ) );
		}

		$email = '';
		if ( is_user_logged_in() ) {
			$user  = wp_get_current_user();
			$email = $user->user_email;
		} elseif ( isset( $_POST['email'] ) ) {
			$email = sanitize_email( wp_unslash( $_POST['email'] ) );
		}
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'ایمیل معتبر وارد کنید تا کد تخفیف برای شما ساخته شود.' ) );
		}

		$key  = self::claim_key( $campaign['id'], $email );
		$done = get_transient( $key );
		if ( is_array( $done ) && ! empty( $done['code'] ) ) {
			wp_send_json_success( array(
				'code'    => $done['code'],
				'expiry'  => $done['expiry'],
				'message' => 'کد تخفیف قبلی شما هنوز معتبر است.',
			) );
		}

		$args = array();
		if ( ! empty( $campaign['public_restrict_email'] ) ) {
			$args['email'] = $email;
		}
		$res = WBCC_Generator::generate( $campaign, 1, 'public', $args );
		if ( empty( $res['ok'] ) ) {
			wp_send_json_error( array( 'message' => $res['message'] ) );
		}

		$coupon = $res['coupons'][0];
		$expiry = '';
		if ( (int) $campaign['expires_days'] > 0 ) {
			$expiry = 'اعتبار تا ' . WBCC_Date::format_long( time() + (int) $campaign['expires_days'] * DAY_IN_SECONDS );
		}

		$cooldown = max( 1, (int) $campaign['public_cooldown'] ) * HOUR_IN_SECONDS;
		set_transient( $key, array(
			'code'   => $coupon['code'],
			'expiry' => $expiry,
		), $cooldown );

		wp_send_json_success( array(
			'code'    => $coupon['code'],
			'expiry'  => $expiry,
			'message' => 'کد تخفیف ' . WBCC_Date::fa_digits( WBCC_Campaigns::trim_zeros( $coupon['amount'] ) )
				. ( 'percent' === $campaign['type'] ? ' درصدی' : ' تومانی' ) . ' شما آماده است.',
		) );
	}

	protected static function claim_key( $campaign_id, $email ) {
		$who = is_user_logged_in() ? 'u' . get_current_user_id() : strtolower( $email );
		return 'wbcc_claim_' . (int) $campaign_id . '_' . md5( $who );
	}

	public static function register_elementor( $widgets_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}
		require_once WBCC_PATH . 'includes/class-wbcc-elementor-widget.php';
		$widgets_manager->register( new WBCC_Elementor_Widget() );
	}
}
