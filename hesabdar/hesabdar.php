<?php
/**
 * Plugin Name:       Hesabdar
 * Plugin URI:        https://webakery.ir
 * Description:       مدیریت محصولات، سفارش‌ها و فاکتور — افزونه حسابدار (Hesabdar).
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Webakery
 * Author URI:        https://webakery.ir
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hesabdar
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HESABDAR_VERSION', '1.1.0' );
define( 'HESABDAR_FILE', __FILE__ );
define( 'HESABDAR_PATH', plugin_dir_path( __FILE__ ) );
define( 'HESABDAR_URL', plugin_dir_url( __FILE__ ) );
define( 'HESABDAR_BASENAME', plugin_basename( __FILE__ ) );

require_once HESABDAR_PATH . 'includes/class-hesabdar.php';
require_once HESABDAR_PATH . 'includes/class-hesabdar-cpt.php';
require_once HESABDAR_PATH . 'includes/class-hesabdar-meta.php';
require_once HESABDAR_PATH . 'includes/class-hesabdar-settings.php';
require_once HESABDAR_PATH . 'includes/class-hesabdar-shortcodes.php';
require_once HESABDAR_PATH . 'includes/class-hesabdar-orders.php';
require_once HESABDAR_PATH . 'includes/class-hesabdar-invoice.php';
require_once HESABDAR_PATH . 'admin/class-hesabdar-admin.php';

/**
 * Bootstrap the plugin.
 *
 * @return Hesabdar
 */
function hesabdar() {
	return Hesabdar::instance();
}

hesabdar();

register_activation_hook( __FILE__, array( 'Hesabdar', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Hesabdar', 'deactivate' ) );
