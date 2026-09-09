<?php
/**
 * Plugin Name: کانال‌یار | Webakery Channel
 * Description: ربات تلگرام برای کانال — ارسال مطلب، ایده رشد و محتوای ارزشمند از نوشته‌ها و محصولات سایت.
 * Version:     1.0.0
 * Plugin URI:  https://webakery.ir
 * Author:      webakery.ir
 * Author URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to: 6.7
 * Text Domain: webakery-channel
 * License:     GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'WBCN_LOADED' ) ) {
	return;
}
define( 'WBCN_LOADED', true );
define( 'WBCN_VERSION', '1.0.0' );
define( 'WBCN_FILE', __FILE__ );
define( 'WBCN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WBCN_URL', plugin_dir_url( __FILE__ ) );
define( 'WBCN_PRODUCT', 'webakery-channel' );
define( 'WBCN_MENU', 'webakery-channel' );

require_once WBCN_PATH . 'includes/class-wbcn-plugin.php';

register_activation_hook( __FILE__, array( 'WBCN_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WBCN_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'WBCN_Plugin', 'instance' ), 12 );
