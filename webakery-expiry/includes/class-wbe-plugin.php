<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WBE_Plugin {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * کلاس‌ها را همین‌جا لود کن — هوک فعال‌سازی وردپرس قبل از plugins_loaded اجرا می‌شود.
	 */
	public static function includes() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		require_once WBE_PATH . 'includes/class-wbe-jalali.php';
		require_once WBE_PATH . 'includes/class-wbe-engine.php';
		require_once WBE_PATH . 'includes/class-wbe-settings.php';
		require_once WBE_PATH . 'includes/class-wbe-product.php';
		require_once WBE_PATH . 'includes/class-wbe-stock.php';
		require_once WBE_PATH . 'includes/class-wbe-frontend.php';
		require_once WBE_PATH . 'includes/class-wbe-reports.php';
		require_once WBE_PATH . 'includes/class-wbe-export.php';
		require_once WBE_PATH . 'includes/class-wbe-sms.php';
		require_once WBE_PATH . 'includes/class-wbe-alerts.php';
	}

	public static function activate() {
		self::includes();
		if ( false === get_option( WBE_Settings::OPTION, false ) ) {
			add_option( WBE_Settings::OPTION, WBE_Settings::defaults(), '', false );
		}
		if ( ! wp_next_scheduled( 'wbe_daily_sync' ) ) {
			wp_schedule_event( time() + 120, 'daily', 'wbe_daily_sync' );
		}
	}

	public static function deactivate() {
		$ts = wp_next_scheduled( 'wbe_daily_sync' );
		if ( $ts ) {
			wp_unschedule_event( $ts, 'wbe_daily_sync' );
		}
	}

	private function __construct() {
		self::includes();
		if ( defined( 'WBE_EDITION' ) && 'pro' === WBE_EDITION ) {
			$this->boot_license();
		}

		WBE_Stock::register();
		WBE_Product::register();
		WBE_Frontend::register();
		WBE_Reports::register();
		WBE_Alerts::register();

		if ( is_admin() ) {
			require_once WBE_PATH . 'includes/class-wbe-admin.php';
			require_once WBE_PATH . 'includes/class-wbe-admin-product.php';
			WBE_Admin::instance();
			WBE_Admin_Product::instance();
		}

		add_action( 'wbe_daily_sync', array( __CLASS__, 'daily_sync' ) );
		add_action( 'admin_notices', array( __CLASS__, 'woo_notice' ) );
		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_wc_compat' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WBE_FILE ), array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	private function boot_license() {
		$file = WBE_PATH . 'includes/class-wb-license.php';
		if ( ! is_readable( $file ) ) {
			return;
		}
		require_once $file;
		if ( ! class_exists( 'WB_License', false ) ) {
			return;
		}
		WB_License::init(
			array(
				'product'       => WBE_PRODUCT,
				'name'          => 'انقضای کالا پرو — webakery.ir',
				'price'         => '۸۰۰٬۰۰۰ تومان',
				'price_sub'     => 'پرداخت یکباره — محمد حاجی مهدیخانی',
				'file'          => WBE_FILE,
				'version'       => WBE_VERSION,
				'trial_days'    => 3,
				'server'        => 'https://webakery.ir/license-server',
				'register_menu' => true,
				'page'          => 'admin.php?page=webakery-expiry-license',
				'features'      => array(
					'بچ قیمت، موجودی و تاریخ انقضا بدون سقف',
					'درصد تخفیف روی هر قیمت بچ',
					'تایمر مانده تا پایان کمپین با خاموش‌کردن آسان (تنظیمات یا خود محصول)',
					'مچ قیمت بچ با تغییر گروهی و محصول تازه‌ساخته ووکامرس',
					'سوییچ خودکار به رزرو پس از صفر شدن یا گذشتن انقضا',
					'نمایش شمسی یا میلادی روی صفحه محصول',
					'گزارش و خروجی اکسل فارسی راست‌چین',
					'هشدار پیشخوان و پیامک انقضای نزدیک',
					'به‌روزرسانی از webakery.ir',
				),
			)
		);
	}

	public static function licensed() {
		if ( ! defined( 'WBE_EDITION' ) || 'pro' !== WBE_EDITION ) {
			return true;
		}
		if ( ! class_exists( 'WB_License', false ) ) {
			return true;
		}
		return WB_License::is_active( WBE_PRODUCT );
	}

	public static function woo_available() {
		return class_exists( 'WooCommerce' );
	}

	public static function woo_notice() {
		if ( self::woo_available() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>افزونه <strong>انقضای کالا</strong> برای کار کردن به <strong>ووکامرس</strong> نیاز دارد.</p></div>';
	}

	public static function declare_wc_compat() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WBE_FILE, true );
		}
	}

	public static function daily_sync() {
		if ( ! self::woo_available() || ! self::licensed() ) {
			return;
		}
		foreach ( WBE_Product::configured_ids() as $id ) {
			WBE_Product::sync_wc( $id );
		}
		WBE_Alerts::flush();
		WBE_Alerts::notify_daily();
	}

	public function action_links( $links ) {
		$custom = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=webakery-expiry' ) ) . '">گزارش</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=webakery-expiry-settings' ) ) . '">تنظیمات</a>',
		);
		if ( defined( 'WBE_EDITION' ) && 'pro' === WBE_EDITION ) {
			$custom[] = '<a href="' . esc_url( admin_url( 'admin.php?page=webakery-expiry-license' ) ) . '">لایسنس</a>';
		}
		return array_merge( $custom, $links );
	}

	public function row_meta( $links, $file ) {
		if ( plugin_basename( WBE_FILE ) !== $file ) {
			return $links;
		}
		$links[] = '<a href="https://webakery.ir" target="_blank" rel="noopener">webakery.ir</a>';
		$links[] = 'محمد حاجی مهدیخانی';
		return $links;
	}
}
