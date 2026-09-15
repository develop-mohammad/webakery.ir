<?php
/**
 * Plugin Name: سئو استودیو | Webakery SEO Studio
 * Description: گزارش‌سازی مصور سئو مثل گوگل استودیو — رتبه، کیورد، محتوا، تکنیکال، بک‌لینک و رپورتاژ، همه به‌صورت لوکال.
 * Version:     1.0.0
 * Plugin URI:  https://webakery.ir
 * Author:      webakery.ir
 * Author URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to: 6.7
 * Text Domain: webakery-seo-studio
 * License:     GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'WBSS_LOADED' ) ) {
	return;
}
define( 'WBSS_LOADED', true );
define( 'WBSS_VERSION', '1.0.0' );
define( 'WBSS_FILE', __FILE__ );
define( 'WBSS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WBSS_URL', plugin_dir_url( __FILE__ ) );
define( 'WBSS_PRODUCT', 'webakery-seo-studio' );

require_once WBSS_PATH . 'includes/class-wbss-jalali.php';
require_once WBSS_PATH . 'includes/class-wbss-install.php';
require_once WBSS_PATH . 'includes/class-wbss-db.php';
require_once WBSS_PATH . 'includes/class-wbss-seed.php';
require_once WBSS_PATH . 'includes/class-wbss-ajax.php';
require_once WBSS_PATH . 'includes/class-wbss-admin.php';
require_once WBSS_PATH . 'includes/class-wbss-plugin.php';

register_activation_hook( __FILE__, array( 'WBSS_Install', 'activate' ) );

add_action( 'plugins_loaded', array( 'WBSS_Plugin', 'instance' ), 5 );
