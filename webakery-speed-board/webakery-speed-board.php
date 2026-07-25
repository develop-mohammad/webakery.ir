<?php
/**
 * Plugin Name: پنل سرعت | Speed Board
 * Description: پنل سریع بهینه‌سازی بر اساس اولویت: سرچ‌کنسول → Render-blocking → تصاویر → فونت → LCP → Forced reflow → Network tree
 * Version:     1.0.0
 * Plugin URI:  https://webakery.ir
 * Author:      webakery.ir
 * Author URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: webakery-speed-board
 * License:     GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'WBSB_LOADED' ) ) {
	return;
}
define( 'WBSB_LOADED', true );
define( 'WBSB_VERSION', '1.0.0' );
define( 'WBSB_FILE', __FILE__ );
define( 'WBSB_PATH', plugin_dir_path( __FILE__ ) );
define( 'WBSB_URL', plugin_dir_url( __FILE__ ) );

require_once WBSB_PATH . 'includes/class-wbsb-scanner.php';
require_once WBSB_PATH . 'includes/class-wbsb-plugin.php';

register_activation_hook( __FILE__, array( 'WBSB_Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( 'WBSB_Plugin', 'instance' ), 5 );
