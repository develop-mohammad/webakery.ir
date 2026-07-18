<?php
/**
 * Plugin Name: حذف نوتیف پیشخوان | Quiet Notices
 * Description: خاموش و مخفی کردن نوتیفیکیشن‌ها و اعلان‌های شلوغ افزونه‌ها در پیشخوان وردپرس.
 * Version:     1.0.1
 * Plugin URI:  https://webakery.ir
 * Author:      webakery.ir
 * Author URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to: 6.7
 * Text Domain: webakery-quiet-notices
 * License:     GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'WBQN_LOADED' ) ) {
	return;
}
define( 'WBQN_LOADED', true );
define( 'WBQN_VERSION', '1.0.1' );
define( 'WBQN_FILE', __FILE__ );
define( 'WBQN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WBQN_URL', plugin_dir_url( __FILE__ ) );
define( 'WBQN_AUTHOR', 'webakery.ir' );

require_once WBQN_PATH . 'includes/class-wbqn-plugin.php';

register_activation_hook( __FILE__, array( 'WBQN_Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( 'WBQN_Plugin', 'instance' ), 1 );
