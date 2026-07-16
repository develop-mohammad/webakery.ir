<?php
/**
 * Plugin Name:       Webakery
 * Plugin URI:        https://webakery.ir
 * Description:       کاتالوگ محصولات، ساعات کاری و فرم سفارش برای سایت نانوایی و شیرینی‌فروشی Webakery.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Webakery
 * Author URI:        https://webakery.ir
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       webakery
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WEBAKERY_VERSION', '1.0.0' );
define( 'WEBAKERY_FILE', __FILE__ );
define( 'WEBAKERY_PATH', plugin_dir_path( __FILE__ ) );
define( 'WEBAKERY_URL', plugin_dir_url( __FILE__ ) );
define( 'WEBAKERY_BASENAME', plugin_basename( __FILE__ ) );

require_once WEBAKERY_PATH . 'includes/class-webakery.php';
require_once WEBAKERY_PATH . 'includes/class-webakery-cpt.php';
require_once WEBAKERY_PATH . 'includes/class-webakery-meta.php';
require_once WEBAKERY_PATH . 'includes/class-webakery-settings.php';
require_once WEBAKERY_PATH . 'includes/class-webakery-shortcodes.php';
require_once WEBAKERY_PATH . 'includes/class-webakery-orders.php';
require_once WEBAKERY_PATH . 'admin/class-webakery-admin.php';

/**
 * Bootstrap the plugin.
 */
function webakery() {
	return Webakery::instance();
}

webakery();

register_activation_hook( __FILE__, array( 'Webakery', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Webakery', 'deactivate' ) );
