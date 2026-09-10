<?php
/**
 * Plugin Name: دیدبانی | رصد رقبا و رتبهٔ کلیدواژه
 * Description: رتبهٔ کلیدواژه در گوگل و بینگ (حتی بدون رقیب) و رصد بک‌لینک رقبا از API رسمی — بدون اسکرپ HTML موتور جستجو.
 * Version:     1.2.0
 * Plugin URI:  https://webakery.ir
 * Author:      webakery.ir
 * Author URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to: 6.7
 * Text Domain: didbani
 * License:     GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'DID_LOADED' ) ) {
	return;
}
define( 'DID_LOADED', true );
define( 'DID_VERSION', '1.2.0' );
define( 'DID_FILE', __FILE__ );
define( 'DID_PATH', plugin_dir_path( __FILE__ ) );
define( 'DID_URL', plugin_dir_url( __FILE__ ) );
define( 'DID_PRODUCT', 'didbani' );
define( 'DID_UA', 'Didbani/1.0 (+https://webakery.ir)' );

require_once DID_PATH . 'includes/class-did-text.php';
require_once DID_PATH . 'includes/class-did-url.php';
require_once DID_PATH . 'includes/class-did-html.php';
require_once DID_PATH . 'includes/class-did-robots.php';
require_once DID_PATH . 'includes/class-did-geo.php';
require_once DID_PATH . 'includes/class-did-settings.php';
require_once DID_PATH . 'includes/class-did-db.php';
require_once DID_PATH . 'includes/class-did-http.php';
require_once DID_PATH . 'includes/class-did-crawler.php';
require_once DID_PATH . 'includes/class-did-provider-bing.php';
require_once DID_PATH . 'includes/class-did-provider-serp.php';
require_once DID_PATH . 'includes/class-did-rank.php';
require_once DID_PATH . 'includes/class-did-provider-backlinks.php';
require_once DID_PATH . 'includes/class-did-backlinks.php';
require_once DID_PATH . 'includes/class-did-cron.php';
require_once DID_PATH . 'includes/class-did-ajax.php';
require_once DID_PATH . 'includes/class-did-admin.php';
require_once DID_PATH . 'includes/class-did-plugin.php';

register_activation_hook( __FILE__, array( 'DID_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'DID_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'DID_Plugin', 'instance' ), 5 );
