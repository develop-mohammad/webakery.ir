<?php
/**
 * Plugin Name: کد تخفیف دسته‌بندی | Webakery Category Coupons
 * Description: ساخت خودکار کد تخفیف ووکامرس برای دسته‌بندی‌های محصولات با بازه درصدی دلخواه (مثلاً ۴۰ تا ۵۰ درصد).
 * Version:     1.0.0
 * Plugin URI:  https://webakery.ir
 * Author:      webakery.ir
 * Author URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to: 6.7
 * WC requires at least: 6.0
 * Text Domain: webakery-category-coupons
 * License:     GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'WBCC_LOADED' ) ) {
	return;
}
define( 'WBCC_LOADED', true );
define( 'WBCC_VERSION', '1.0.0' );
define( 'WBCC_FILE', __FILE__ );
define( 'WBCC_PATH', plugin_dir_path( __FILE__ ) );
define( 'WBCC_URL', plugin_dir_url( __FILE__ ) );
define( 'WBCC_PRODUCT', 'webakery-category-coupons' );
define( 'WBCC_MENU', 'webakery-category-coupons' );

require_once WBCC_PATH . 'includes/class-wbcc-plugin.php';

register_activation_hook( __FILE__, array( 'WBCC_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WBCC_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'WBCC_Plugin', 'instance' ), 15 );
