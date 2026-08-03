<?php
/**
 * Plugin Name: روزم | برنامه‌ریز روزانه
 * Description: وب‌اپلیکیشن برنامه‌ریزی روزانه با زمان‌بندی هوشمند، عادت‌های تکرارشونده و تقویم شمسی.
 * Version:     1.0.0
 * Plugin URI:  https://webakery.ir
 * Author:      webakery.ir
 * Author URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to: 6.7
 * Text Domain: roozam
 * License:     GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'RZM_LOADED' ) ) {
	return;
}
define( 'RZM_LOADED', true );
define( 'RZM_VERSION', '1.0.0' );
define( 'RZM_FILE', __FILE__ );
define( 'RZM_PATH', plugin_dir_path( __FILE__ ) );
define( 'RZM_URL', plugin_dir_url( __FILE__ ) );
define( 'RZM_PRODUCT', 'roozam' );

require_once RZM_PATH . 'includes/class-rzm-settings.php';
require_once RZM_PATH . 'includes/class-rzm-planner.php';
require_once RZM_PATH . 'includes/class-rzm-ajax.php';
require_once RZM_PATH . 'includes/class-rzm-frontend.php';
require_once RZM_PATH . 'includes/class-rzm-admin.php';
require_once RZM_PATH . 'includes/class-rzm-plugin.php';

register_activation_hook( __FILE__, array( 'RZM_Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( 'RZM_Plugin', 'instance' ), 5 );
