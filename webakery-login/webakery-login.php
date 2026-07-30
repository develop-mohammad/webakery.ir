<?php
/**
 * Plugin Name: ورود آسان | لاگین پیامکی و جیمیل
 * Description: ورود و ثبت‌نام با شماره موبایل (OTP) و جیمیل — اتصال به ملی‌پیامک، کاوه‌نگار، IPPanel و سایر پنل‌های پیامکی.
 * Version:     1.0.2
 * Plugin URI:  https://webakery.ir
 * Author:      webakery.ir
 * Author URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to: 6.7
 * Text Domain: webakery-login
 * License:     GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'WBL_LOADED' ) ) {
	return;
}
define( 'WBL_LOADED', true );
define( 'WBL_VERSION', '1.0.2' );
define( 'WBL_FILE', __FILE__ );
define( 'WBL_PATH', plugin_dir_path( __FILE__ ) );
define( 'WBL_URL', plugin_dir_url( __FILE__ ) );
define( 'WBL_PRODUCT', 'webakery-login' );

require_once WBL_PATH . 'includes/class-wbl-settings.php';
require_once WBL_PATH . 'includes/class-wbl-sms.php';
require_once WBL_PATH . 'includes/class-wbl-otp.php';
require_once WBL_PATH . 'includes/class-wbl-auth.php';
require_once WBL_PATH . 'includes/class-wbl-google.php';
require_once WBL_PATH . 'includes/class-wbl-ajax.php';
require_once WBL_PATH . 'includes/class-wbl-frontend.php';
require_once WBL_PATH . 'includes/class-wbl-admin.php';
require_once WBL_PATH . 'includes/class-wbl-plugin.php';

register_activation_hook( __FILE__, array( 'WBL_Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( 'WBL_Plugin', 'instance' ), 5 );
