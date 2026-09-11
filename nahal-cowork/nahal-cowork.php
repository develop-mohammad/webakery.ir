<?php
/**
 * Plugin Name: قرارداد نهال | فضای کار، سالن و پذیرش
 * Description: قرارداد دیجیتال فضای کار اشتراکی و اجاره سالن، فرم پذیرش فراگیر، فرم‌ساز سفارشی، ثبت پرداخت در ووکامرس و حسابدار.
 * Version:     1.2.4
 * Plugin URI:  https://webakery.ir
 * Author:      webakery.ir
 * Author URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to: 6.7
 * Text Domain: nahal-cowork
 * License:     GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'NCK_LOADED' ) ) {
	return;
}
define( 'NCK_LOADED', true );
define( 'NCK_VERSION', '1.2.4' );
define( 'NCK_FILE', __FILE__ );
define( 'NCK_PATH', plugin_dir_path( __FILE__ ) );
define( 'NCK_URL', plugin_dir_url( __FILE__ ) );
define( 'NCK_PRODUCT', 'nahal-cowork' );
define( 'NCK_MENU', 'nahal-cowork' );

require_once NCK_PATH . 'includes/class-nck-jalali.php';
require_once NCK_PATH . 'includes/class-nck-phone.php';
require_once NCK_PATH . 'includes/class-nck-holidays.php';
require_once NCK_PATH . 'includes/class-nck-shifts.php';
require_once NCK_PATH . 'includes/class-nck-contract.php';
require_once NCK_PATH . 'includes/class-nck-hall.php';
require_once NCK_PATH . 'includes/class-nck-learner.php';
require_once NCK_PATH . 'includes/class-nck-forms.php';
require_once NCK_PATH . 'includes/class-nck-pay.php';
require_once NCK_PATH . 'includes/class-nck-contracts.php';
require_once NCK_PATH . 'includes/class-nck-settings.php';
require_once NCK_PATH . 'includes/class-nck-install.php';
require_once NCK_PATH . 'includes/class-nck-members.php';
require_once NCK_PATH . 'includes/class-nck-subscriptions.php';
require_once NCK_PATH . 'includes/class-nck-attendance.php';
require_once NCK_PATH . 'includes/class-nck-ajax.php';
require_once NCK_PATH . 'includes/class-nck-print.php';
require_once NCK_PATH . 'includes/class-nck-frontend.php';
require_once NCK_PATH . 'includes/class-nck-admin.php';
require_once NCK_PATH . 'includes/class-nck-plugin.php';

register_activation_hook( __FILE__, array( 'NCK_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'NCK_Install', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'NCK_Plugin', 'instance' ), 5 );
