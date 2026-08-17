<?php
defined( 'ABSPATH' ) || exit;

class WBCC_Plugin {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function activate() {
		if ( ! get_option( 'wbcc_campaigns' ) ) {
			add_option( 'wbcc_campaigns', array(), '', false );
		}
		if ( ! wp_next_scheduled( 'wbcc_auto_run' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'wbcc_auto_run' );
		}
	}

	public static function deactivate() {
		$ts = wp_next_scheduled( 'wbcc_auto_run' );
		if ( $ts ) {
			wp_unschedule_event( $ts, 'wbcc_auto_run' );
		}
	}

	private function __construct() {
		$this->boot_license();

		require_once WBCC_PATH . 'includes/class-wbcc-date.php';
		require_once WBCC_PATH . 'includes/class-wbcc-campaigns.php';
		require_once WBCC_PATH . 'includes/class-wbcc-generator.php';
		require_once WBCC_PATH . 'includes/class-wbcc-cron.php';

		WBCC_Cron::register();

		if ( is_admin() ) {
			require_once WBCC_PATH . 'includes/class-wbcc-admin.php';
			WBCC_Admin::instance();
		}

		require_once WBCC_PATH . 'includes/class-wbcc-frontend.php';
		WBCC_Frontend::register();

		add_action( 'admin_notices', array( __CLASS__, 'woo_notice' ) );
	}

	private function boot_license() {
		require_once WBCC_PATH . 'includes/class-wb-license.php';
		WB_License::init( array(
			'product'    => WBCC_PRODUCT,
			'name'       => 'کد تخفیف دسته‌بندی — webakery.ir',
			'price'      => '۲۹۹,۰۰۰ تومان',
			'file'       => WBCC_FILE,
			'version'    => WBCC_VERSION,
			'trial_days' => 7,
			'page'       => 'admin.php?page=' . WBCC_MENU . '&tab=license',
			'features'   => array(
				'کد تخفیف اختصاصی هر دسته‌بندی محصولات',
				'درصد تصادفی در بازه دلخواه (مثلاً ۴۰ تا ۵۰ درصد)',
				'ساخت خودکار زمان‌بندی‌شده و پاک‌سازی کدهای منقضی',
				'دریافت کد توسط مشتری با شورت‌کد و ویجت المنتور',
				'به‌روزرسانی خودکار از webakery.ir',
			),
		) );
	}

	/** آیا افزونه مجاز به کار است؟ (لایسنس معتبر یا دوره آزمایشی) */
	public static function licensed() {
		return class_exists( 'WB_License' ) && WB_License::is_active( WBCC_PRODUCT );
	}

	public static function woo_available() {
		return class_exists( 'WooCommerce' ) && class_exists( 'WC_Coupon' );
	}

	public static function woo_notice() {
		if ( self::woo_available() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>افزونه <strong>کد تخفیف دسته‌بندی</strong> برای کار کردن به <strong>ووکامرس</strong> نیاز دارد. لطفاً ووکامرس را نصب و فعال کنید.</p></div>';
	}
}
