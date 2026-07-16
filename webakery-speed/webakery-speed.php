<?php
/**
 * Plugin Name:       Webakery Speed
 * Plugin URI:        https://webakery.ir
 * Description:       دریافت خطاهای Google PageSpeed و اعمال اصلاحات امن بدون خراب کردن سایت.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Webakery
 * Author URI:        https://webakery.ir
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       webakery-speed
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WBS_VERSION', '1.0.1' );
define( 'WBS_FILE', __FILE__ );
define( 'WBS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WBS_URL', plugin_dir_url( __FILE__ ) );
define( 'WBS_BASENAME', plugin_basename( __FILE__ ) );

require_once WBS_PATH . 'includes/class-wbs.php';
require_once WBS_PATH . 'includes/class-wbs-settings.php';
require_once WBS_PATH . 'includes/class-wbs-fix-registry.php';
require_once WBS_PATH . 'includes/class-wbs-fix-manager.php';
require_once WBS_PATH . 'includes/class-wbs-pagespeed-api.php';
require_once WBS_PATH . 'includes/class-wbs-scanner.php';
require_once WBS_PATH . 'admin/class-wbs-admin.php';

/**
 * Bootstrap plugin.
 *
 * @return WBS
 */
function webakery_speed() {
	return WBS::instance();
}

webakery_speed();

register_activation_hook( __FILE__, array( 'WBS', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WBS', 'deactivate' ) );
