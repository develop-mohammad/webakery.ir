<?php
/**
 * Plugin Name: قیمت‌گذاری درگاه | Gateway Pricing
 * Description: کارمزد برای درگاه‌های قسطی و تخفیف برای درگاه‌های نقدی (زیبال / زرین‌پال) — درصدی یا مبلغ ثابت.
 * Version:     1.0.0
 * Plugin URI:  https://webakery.ir
 * Author:      webakery.ir
 * Author URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * Text Domain: webakery-gateway-pricing
 * License:     GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'WBGP_LOADED' ) ) {
	return;
}
define( 'WBGP_LOADED', true );
define( 'WBGP_VERSION', '1.0.0' );
define( 'WBGP_FILE', __FILE__ );
define( 'WBGP_PATH', plugin_dir_path( __FILE__ ) );
define( 'WBGP_URL', plugin_dir_url( __FILE__ ) );

require_once WBGP_PATH . 'includes/class-wbgp-settings.php';
require_once WBGP_PATH . 'includes/class-wbgp-fees.php';
require_once WBGP_PATH . 'includes/class-wbgp-plugin.php';

register_activation_hook( __FILE__, array( 'WBGP_Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( 'WBGP_Plugin', 'instance' ), 20 );
