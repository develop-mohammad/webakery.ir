<?php
/**
 * Plugin Name: نوبت من | Nobat Man
 * Description: رزرو نوبت مشاوره (روانشناسی مثبت و خدمات تخصصی) با تقویم شمسی ایرانی، پرداخت ووکامرس، پنل مدرن و نسخه پرو.
 * Version:     1.0.12
 * Plugin URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to: 6.7
 * WC requires at least: 6.0
 * Author:      webakery.ir
 * Author URI:  https://webakery.ir
 * Text Domain: nobat-man
 * Domain Path: /languages
 * License:     GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'NOBAT_MAN_LOADED' ) ) {
	return;
}
define( 'NOBAT_MAN_LOADED', true );

define( 'NM_VERSION', '1.0.12' );
define( 'NM_FILE', __FILE__ );
define( 'NM_PATH', plugin_dir_path( __FILE__ ) );
define( 'NM_URL', plugin_dir_url( __FILE__ ) );
define( 'NM_PRODUCT', 'nobat-man' );
define( 'NM_DB_VERSION', '1.0.12' );
define( 'NM_AUTHOR', 'webakery.ir' );
define( 'NM_AUTHOR_URI', 'https://webakery.ir' );

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action( 'admin_notices', function () {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>نوبت من:</strong> نیاز به PHP 7.4 یا بالاتر دارد. نسخه فعلی: '
			. esc_html( PHP_VERSION ) . '</p></div>';
	} );
	return;
}

require_once NM_PATH . 'includes/class-nm-autoload.php';
NM_Autoload::register();

register_activation_hook( __FILE__, array( 'NM_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'NM_Activator', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'NM_Plugin', 'instance' ), 5 );
