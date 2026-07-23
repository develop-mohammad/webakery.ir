<?php
/**
 * Plugin Name: چت باکس | Webakery Chat
 * Description: ویجت چت زیبا برای سایت — پیام بازدیدکننده، پاسخ از پیشخوان، اعلان ایمیل.
 * Version:     1.0.0
 * Plugin URI:  https://webakery.ir
 * Author:      webakery.ir
 * Author URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to: 6.7
 * Text Domain: webakery-chat-box
 * License:     GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'WBCB_LOADED' ) ) {
	return;
}
define( 'WBCB_LOADED', true );
define( 'WBCB_VERSION', '1.0.0' );
define( 'WBCB_FILE', __FILE__ );
define( 'WBCB_PATH', plugin_dir_path( __FILE__ ) );
define( 'WBCB_URL', plugin_dir_url( __FILE__ ) );
define( 'WBCB_PRODUCT', 'webakery-chat' );

require_once WBCB_PATH . 'includes/class-wbcb-install.php';
require_once WBCB_PATH . 'includes/class-wbcb-settings.php';
require_once WBCB_PATH . 'includes/class-wbcb-conversations.php';
require_once WBCB_PATH . 'includes/class-wbcb-messages.php';
require_once WBCB_PATH . 'includes/class-wbcb-ajax.php';
require_once WBCB_PATH . 'includes/class-wbcb-admin.php';
require_once WBCB_PATH . 'includes/class-wbcb-frontend.php';
require_once WBCB_PATH . 'includes/class-wbcb-plugin.php';

register_activation_hook( __FILE__, array( 'WBCB_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WBCB_Install', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'WBCB_Plugin', 'instance' ), 5 );
