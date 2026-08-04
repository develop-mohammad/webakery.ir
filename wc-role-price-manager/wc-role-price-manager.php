<?php
/**
 * Plugin Name:       نقش‌قیمت | مدیریت نقش و قیمت ووکامرس
 * Plugin URI:        https://webakery.ir
 * Description:       مدیریت نقش‌های کاربری و قیمت‌گذاری بر اساس نقش در ووکامرس.
 * Version:           1.0.0
 * Author:            webakery.ir
 * Author URI:        https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Tested up to:      6.7
 * WC requires at least: 7.0
 * WC tested up to:   9.0
 * Text Domain:       wc-role-price-manager
 * Domain Path:       /languages
 * License:           GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'WRPM_LOADED' ) ) {
	return;
}
define( 'WRPM_LOADED', true );
define( 'WRPM_VERSION', '1.0.0' );
define( 'WRPM_FILE', __FILE__ );
define( 'WRPM_PATH', plugin_dir_path( WRPM_FILE ) );
define( 'WRPM_URL', plugin_dir_url( WRPM_FILE ) );
define( 'WRPM_PRODUCT', 'wc-role-price-manager' );

/**
 * کلاس اصلی پلاگین
 */
final class WC_Role_Price_Manager {

	/** @var WC_Role_Price_Manager|null */
	private static $instance = null;

	/**
	 * @return WC_Role_Price_Manager
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
	}

	public function declare_hpos_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				WRPM_FILE,
				true
			);
		}
	}

	public function init_plugin() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		$this->boot_license();
		$this->includes();
		$this->init_modules();
	}

	private function boot_license() {
		require_once WRPM_PATH . 'includes/class-wb-license.php';
		WB_License::init(
			array(
				'product'    => WRPM_PRODUCT,
				'name'       => 'نقش‌قیمت — مدیریت نقش و قیمت ووکامرس',
				'price'      => '۲۴۹,۰۰۰ تومان',
				'file'       => WRPM_FILE,
				'version'    => WRPM_VERSION,
				'trial_days' => 7,
				'page'       => 'admin.php?page=wc-role-price-manager&tab=license',
				'features'   => array(
					'نقش‌های سفارشی ووکامرس',
					'قیمت جداگانه برای هر نقش روی محصول',
					'تخفیف درصدی سراسری بر اساس نقش',
					'مخفی‌کردن قیمت برای مهمان/نقش خاص',
					'به‌روزرسانی خودکار از webakery.ir',
				),
			)
		);
	}

	private function includes() {
		require_once WRPM_PATH . 'includes/class-wrpm-roles.php';
		require_once WRPM_PATH . 'includes/class-wrpm-pricing.php';
		require_once WRPM_PATH . 'includes/class-wrpm-admin.php';
	}

	private function init_modules() {
		if ( class_exists( 'WRPM_Roles' ) ) {
			WRPM_Roles::get_instance();
		}
		if ( class_exists( 'WRPM_Pricing' ) ) {
			WRPM_Pricing::get_instance();
		}
		if ( is_admin() && class_exists( 'WRPM_Admin' ) ) {
			WRPM_Admin::get_instance();
		}
	}

	public static function licensed() {
		return class_exists( 'WB_License' ) && WB_License::is_active( WRPM_PRODUCT );
	}

	public function woocommerce_missing_notice() {
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( 'افزونه «نقش‌قیمت» برای اجرا به ووکامرس نیاز دارد. لطفاً ووکامرس را نصب و فعال کنید.', 'wc-role-price-manager' ); ?></p>
		</div>
		<?php
	}

	public static function activate() {
		if ( ! get_option( 'wrpm_custom_roles' ) ) {
			update_option( 'wrpm_custom_roles', array(), false );
		}
		if ( ! get_option( 'wrpm_settings' ) ) {
			update_option(
				'wrpm_settings',
				array(
					'global_discounts'   => array(),
					'hide_price_roles'   => array(),
					'hide_price_guests'  => 0,
					'hide_price_message' => 'برای مشاهده قیمت وارد شوید.',
				),
				false
			);
		}
		if ( ! get_option( 'wbl_wc-role-price-manager_install_time' ) ) {
			add_option( 'wbl_wc-role-price-manager_install_time', time() );
		}
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}
}

register_activation_hook( WRPM_FILE, array( 'WC_Role_Price_Manager', 'activate' ) );
register_deactivation_hook( WRPM_FILE, array( 'WC_Role_Price_Manager', 'deactivate' ) );

/**
 * @return WC_Role_Price_Manager
 */
function wrpm_run() {
	return WC_Role_Price_Manager::get_instance();
}

wrpm_run();
