<?php
defined( 'ABSPATH' ) || exit;

/**
 * هسته افزونه.
 */
class WBGP_Plugin {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function activate() {
		$defaults = WBGP_Settings::defaults();
		if ( false === get_option( 'wbgp_settings', false ) ) {
			add_option( 'wbgp_settings', $defaults, '', false );
		}
		$opt = 'wbl_' . WBGP_PRODUCT . '_install_time';
		if ( ! get_option( $opt ) ) {
			add_option( $opt, time(), '', false );
		}
	}

	/** لایسنس، دمو یا دوره آزمایشی */
	public static function is_licensed() {
		if ( defined( 'WBGP_DEMO' ) && WBGP_DEMO ) {
			return true;
		}
		if ( ! class_exists( 'WB_License', false ) ) {
			return true;
		}
		return WB_License::is_active( WBGP_PRODUCT );
	}

	private function __construct() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'need_woo' ) );
			return;
		}

		$this->boot_license();

		WBGP_Settings::init();

		if ( self::is_licensed() ) {
			WBGP_Fees::init();
		} else {
			add_action( 'admin_notices', array( $this, 'license_locked_notice' ) );
		}

		add_filter( 'plugin_action_links_' . plugin_basename( WBGP_FILE ), array( $this, 'action_links' ) );
	}

	private function boot_license() {
		if ( ! class_exists( 'WB_License', false ) ) {
			return;
		}
		WB_License::init(
			array(
				'product'       => WBGP_PRODUCT,
				'name'          => 'قیمت‌گذاری درگاه | Gateway Pricing',
				'price'         => '۱۴۹,۰۰۰ تومان',
				'file'          => WBGP_FILE,
				'version'       => WBGP_VERSION,
				'trial_days'    => 3,
				'server'        => 'https://webakery.ir/license-server',
				'register_menu' => true,
				'page'          => 'admin.php?page=wbgp-settings&tab=license',
				'features'      => array(
					'کارمزد درگاه قسطی (درصد یا مبلغ ثابت)',
					'تخفیف درگاه نقدی زیبال / زرین‌پال',
					'سازگار با اسنپ‌پی، ترب‌پی و سایر درگاه‌ها',
					'به‌روزرسانی آنی مبلغ در تسویه',
					'به‌روزرسانی خودکار از webakery.ir',
				),
			)
		);
	}

	public function need_woo() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>قیمت‌گذاری درگاه:</strong> برای کار کردن این افزونه باید ووکامرس فعال باشد.</p></div>';
	}

	public function license_locked_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$url = admin_url( 'admin.php?page=wb-licenses' );
		echo '<div class="notice notice-error"><p><strong>قیمت‌گذاری درگاه:</strong> دوره آزمایشی تمام شده است. '
			. '<a href="' . esc_url( $url ) . '">لایسنس را فعال کنید</a>.</p></div>';
	}

	public function action_links( $links ) {
		$url  = admin_url( 'admin.php?page=wbgp-settings' );
		$pay  = 'https://webakery.ir/license-server/pay/?plugin=gateway-pricing';
		$custom = array(
			'<a href="' . esc_url( $url ) . '">تنظیمات</a>',
			'<a href="' . esc_url( $pay ) . '" style="color:#0d9488;font-weight:700" target="_blank" rel="noopener">خرید لایسنس</a>',
		);
		return array_merge( $custom, $links );
	}
}
