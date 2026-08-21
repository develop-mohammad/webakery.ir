<?php
defined( 'ABSPATH' ) || exit;

/**
 * راه‌انداز اصلی افزونه: تکسونومی، موتور تشخیص، زمان‌بند و پیشخوان.
 * این بیلد بدون لایسنس است؛ همه قابلیت‌ها آزادند.
 */
class WDP_Plugin {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function activate() {
		require_once WDP_PATH . 'includes/class-wdp-taxonomy.php';
		WDP_Taxonomy::register_taxonomy();

		if ( ! wp_next_scheduled( 'wdp_recalculate_all' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'wdp_recalculate_all' );
		}
		flush_rewrite_rules();
	}

	public static function deactivate() {
		$ts = wp_next_scheduled( 'wdp_recalculate_all' );
		if ( $ts ) {
			wp_unschedule_event( $ts, 'wdp_recalculate_all' );
		}
		flush_rewrite_rules();
	}

	private function __construct() {
		require_once WDP_PATH . 'includes/class-wdp-util.php';
		require_once WDP_PATH . 'includes/class-wdp-taxonomy.php';
		require_once WDP_PATH . 'includes/class-wdp-assigner.php';
		require_once WDP_PATH . 'includes/class-wdp-bulk.php';
		require_once WDP_PATH . 'includes/class-wdp-cron.php';

		WDP_Taxonomy::register();
		WDP_Assigner::register();
		WDP_Cron::register();

		if ( is_admin() ) {
			require_once WDP_PATH . 'includes/class-wdp-admin.php';
			WDP_Admin::instance();
		}

		require_once WDP_PATH . 'includes/class-wdp-frontend.php';
		WDP_Frontend::register();

		add_action( 'admin_notices', array( __CLASS__, 'woo_notice' ) );
	}

	/** همیشه true — نسخه بدون لایسنس. */
	public static function licensed() {
		return true;
	}

	public static function woo_available() {
		return class_exists( 'WooCommerce' );
	}

	public static function woo_notice() {
		if ( self::woo_available() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>افزونه <strong>صفحه‌های تخفیف هوشمند</strong> برای کار کردن به <strong>ووکامرس</strong> نیاز دارد. لطفاً ووکامرس را نصب و فعال کنید.</p></div>';
	}
}
