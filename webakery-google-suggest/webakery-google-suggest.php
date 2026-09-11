<?php
/**
 * Plugin Name: سجست‌یاب گوگل | Webakery Google Suggest
 * Description: ایستگاه کار سئو با Suggest واقعی گوگل: لانگ‌تیل، اینتنت، بریف، رقابت نسبی، تاریخچه و مقایسه — میزان سرچ فقط از Keyword Planner.
 * Version:     1.3.4
 * Plugin URI:  https://webakery.ir
 * Author:      webakery.ir
 * Author URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to: 6.7
 * Text Domain: webakery-google-suggest
 * License:     GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'WBGS_LOADED' ) ) {
	return;
}
define( 'WBGS_LOADED', true );
define( 'WBGS_VERSION', '1.3.4' );
define( 'WBGS_FILE', __FILE__ );
define( 'WBGS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WBGS_URL', plugin_dir_url( __FILE__ ) );
define( 'WBGS_PRODUCT', 'webakery-google-suggest' );
define( 'WBGS_MENU', 'webakery-google-suggest' );

require_once WBGS_PATH . 'includes/class-wbgs-plugin.php';

register_activation_hook( __FILE__, array( 'WBGS_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WBGS_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'WBGS_Plugin', 'instance' ), 15 );
