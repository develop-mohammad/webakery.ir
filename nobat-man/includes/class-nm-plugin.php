<?php
defined( 'ABSPATH' ) || exit;

/**
 * هسته افزونه — هوک‌های سبک و بارگذاری شرطی.
 */
class NM_Plugin {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->boot_license();
		$this->maybe_upgrade();

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'nm_daily_maintenance', array( $this, 'maintenance' ) );

		if ( ! wp_next_scheduled( 'nm_daily_maintenance' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'nm_daily_maintenance' );
		}
	}

	private function boot_license() {
		if ( ! class_exists( 'WB_License' ) ) {
			require_once NM_PATH . 'includes/class-wb-license.php';
		}
		WB_License::init( array(
			'product'    => NM_PRODUCT,
			'name'       => 'نوبت من — نسخه پرو',
			'price'      => '۵۹۹,۰۰۰ تومان',
			'file'       => NM_FILE,
			'version'    => NM_VERSION,
			'trial_days' => 7,
			'page'       => 'admin.php?page=nobat-man&tab=license',
			'features'   => array(
				'متخصصین و بیزینس‌های نامحدود',
				'تیکت، اشتراک ماهانه و پرداخت قسطی',
				'پیامک ایرانی + ایمیل + گوگل کلندر',
				'قیمت‌گذاری متغیر، فاکتور و خروجی مالی',
				'بدون محدودیت دامنه در دوره لایسنس',
			),
		) );
	}

	private function maybe_upgrade() {
		$v = get_option( 'nm_db_version' );
		if ( $v !== NM_DB_VERSION ) {
			NM_Install::create_tables();
			NM_Install::seed_defaults();
			update_option( 'nm_db_version', NM_DB_VERSION );
		}
	}

	public function init() {
		load_plugin_textdomain( 'nobat-man', false, dirname( plugin_basename( NM_FILE ) ) . '/languages' );

		NM_Shortcodes::register();
		NM_Ajax::register();
		NM_Assets::register();

		if ( is_admin() ) {
			NM_Admin::instance();
		}

		NM_Frontend::instance();

		if ( class_exists( 'WooCommerce' ) ) {
			NM_WooCommerce::instance();
		}

		NM_Notifications::instance();

		if ( NM_Pro::is_active() ) {
			NM_Google_Calendar::instance();
			NM_Tickets::instance();
			NM_Subscriptions::instance();
			NM_Installments::instance();
			NM_SMS::instance();
		}

		NM_Hesabdar::instance();
		NM_Invoice::instance();
		class_exists( 'NM_Admin_Export' ); // ثبت هوک خروجی
	}

	public function maintenance() {
		// پاکسازی رزروهای pending منقضی‌شده (سبک)
		global $wpdb;
		$table = $wpdb->prefix . 'nm_bookings';
		$ttl   = (int) NM_Settings::get( 'pending_ttl_hours', 24 );
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET status = 'expired', updated_at = %s
			 WHERE status = 'pending' AND created_at < %s",
			current_time( 'mysql' ),
			gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $ttl * HOUR_IN_SECONDS ) )
		) );
	}
}
