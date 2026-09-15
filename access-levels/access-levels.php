<?php
/**
 * Plugin Name: Barbari | مدیریت دسترسی
 * Description: کنترل دسترسی کاربران به افزونه‌ها و بخش‌های پیشخوان وردپرس.
 * Version:     1.5.8
 * Plugin URI:  https://webakery.ir
 * Author:      webakery.ir
 * Author URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to: 6.7
 * Text Domain: access-levels
 * License:     GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'AL_LOADED' ) ) {
	return;
}
define( 'AL_LOADED', true );
define( 'AL_VERSION', '1.5.8' );
define( 'AL_FILE', __FILE__ );
define( 'AL_PATH', plugin_dir_path( __FILE__ ) );
define( 'AL_URL', plugin_dir_url( __FILE__ ) );
define( 'AL_PRODUCT', 'access-levels' );

/** نسخه دمو مارکت‌پلیس — با tools/build-demo-zips.sh روی true ست می‌شود */
if ( ! defined( 'AL_DEMO' ) ) {
	define( 'AL_DEMO', false );
}

require_once AL_PATH . 'includes/class-al-plugin.php';

register_activation_hook( __FILE__, array( 'AL_Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( 'AL_Plugin', 'instance' ), 5 );
