<?php
/**
 * Plugin Name: Baget | ادیت فیلدهای پرداخت
 * Description: مدیریت فیلدهای صفحه پرداخت و محصولات آنلاین — جابه‌جایی و ذخیره فیلدهای پیش‌فرض و سفارشی.
 * Version:     1.3.6
 * Plugin URI:  https://webakery.ir
 * Author:      webakery.ir
 * Author URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: wccp
 */

defined( 'ABSPATH' ) || exit;

define( 'WCCP_VERSION', '1.3.6' );
define( 'WCCP_FILE', __FILE__ );
define( 'WCCP_PATH', plugin_dir_path( __FILE__ ) );
define( 'WCCP_URL', plugin_dir_url( __FILE__ ) );
define( 'WCCP_PRODUCT', 'wccp' );

require_once WCCP_PATH . 'includes/Autoload.php';
\WCCP\Autoload::register();

add_action( 'plugins_loaded', static function () {
	\WCCP\Plugin::instance();
}, 5 );

register_activation_hook( __FILE__, array( '\WCCP\Plugin', 'activate' ) );
