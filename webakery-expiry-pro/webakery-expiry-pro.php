<?php
/**
 * Plugin Name: انقضای کالا پرو | Webakery Expiry Pro
 * Description: بچ قیمت، موجودی و تاریخ انقضای ووکامرس با سوییچ خودکار رزرو — نسخه لایسنس‌دار (۸۰۰٬۰۰۰ تومان).
 * Version:     1.2.0
 * Plugin URI:  https://webakery.ir
 * Author:      webakery.ir — محمد حاجی مهدیخانی
 * Author URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to: 6.7
 * WC requires at least: 6.0
 * Text Domain: webakery-expiry-pro
 * License:     GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'WBE_LOADED' ) ) {
	return;
}
define( 'WBE_LOADED', true );
define( 'WBE_VERSION', '1.2.0' );
define( 'WBE_FILE', __FILE__ );
define( 'WBE_PATH', plugin_dir_path( __FILE__ ) );
define( 'WBE_URL', plugin_dir_url( __FILE__ ) );
define( 'WBE_PRODUCT', 'webakery-expiry' );
define( 'WBE_EDITION', 'pro' );
define( 'WBE_AUTHOR', 'webakery.ir — محمد حاجی مهدیخانی' );

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p><strong>انقضای کالا پرو:</strong> نیاز به PHP 7.4 یا بالاتر دارد.</p></div>';
		}
	);
	return;
}

require_once WBE_PATH . 'includes/class-wbe-plugin.php';
WBE_Plugin::includes();

register_activation_hook( __FILE__, array( 'WBE_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WBE_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'WBE_Plugin', 'instance' ), 15 );
