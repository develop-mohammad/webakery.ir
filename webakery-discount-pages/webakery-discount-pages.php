<?php
/**
 * Plugin Name: صفحه‌های تخفیف هوشمند | Webakery Discount Pages
 * Description: برای هر بازه تخفیف (درصدی یا مبلغ ثابت) یک صفحه با URL اختصاصی بساز؛ محصولات ووکامرس بر اساس تخفیف فعلی‌شان خودکار در همان صفحه نمایش داده می‌شوند و با تغییر تخفیف محصول، خودکار به صفحه درست منتقل می‌شوند.
 * Version:     1.2.0
 * Plugin URI:  https://webakery.ir
 * Author:      webakery.ir
 * Author URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to: 6.7
 * WC requires at least: 6.0
 * Text Domain: webakery-discount-pages
 * License:     GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'WDP_LOADED' ) ) {
	return;
}
define( 'WDP_LOADED', true );
define( 'WDP_VERSION', '1.2.0' );
define( 'WDP_FILE', __FILE__ );
define( 'WDP_PATH', plugin_dir_path( __FILE__ ) );
define( 'WDP_URL', plugin_dir_url( __FILE__ ) );
define( 'WDP_PRODUCT', 'webakery-discount-pages' );
define( 'WDP_MENU', 'webakery-discount-pages' );

require_once WDP_PATH . 'includes/class-wdp-plugin.php';

register_activation_hook( __FILE__, array( 'WDP_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WDP_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'WDP_Plugin', 'instance' ), 15 );
