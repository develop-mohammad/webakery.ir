<?php
/**
 * Plugin Name: فونت سوییپ | Font Swap
 * Description: تشخیص فونت‌های سایت، Preload و فعال‌سازی font-display:swap با یک تیک — سبک و زیبا.
 * Version:     1.0.0
 * Plugin URI:  https://webakery.ir
 * Author:      webakery.ir
 * Author URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to: 6.7
 * Text Domain: webakery-font-swap
 * License:     GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'WBFS_LOADED' ) ) {
	return;
}
define( 'WBFS_LOADED', true );
define( 'WBFS_VERSION', '1.0.0' );
define( 'WBFS_FILE', __FILE__ );
define( 'WBFS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WBFS_URL', plugin_dir_url( __FILE__ ) );

require_once WBFS_PATH . 'includes/class-wbfs-plugin.php';

register_activation_hook( __FILE__, array( 'WBFS_Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( 'WBFS_Plugin', 'instance' ), 5 );
