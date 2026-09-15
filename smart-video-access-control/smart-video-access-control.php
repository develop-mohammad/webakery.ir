<?php
/**
 * Plugin Name: Smart Video Access Control
 * Description: کنترل دسترسی امن و زمان‌بندی‌شده برای ویدیوها بر اساس کاربر، نقش و دسته‌بندی.
 * Version: 1.0.0
 * Plugin URI: https://webakery.ir
 * Author: webakery.ir
 * Author URI: https://webakery.ir
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Text Domain: smart-video-access-control
 * Domain Path: /languages
 * License: GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

define( 'SVAC_VERSION', '1.0.0' );
define( 'SVAC_FILE', __FILE__ );
define( 'SVAC_PATH', plugin_dir_path( __FILE__ ) );
define( 'SVAC_URL', plugin_dir_url( __FILE__ ) );

require_once SVAC_PATH . 'includes/class-access-logs.php';
require_once SVAC_PATH . 'includes/class-video-post-type.php';
require_once SVAC_PATH . 'includes/class-access-control.php';
require_once SVAC_PATH . 'includes/class-video-shortcode.php';
require_once SVAC_PATH . 'includes/class-rest-api.php';
require_once SVAC_PATH . 'includes/class-admin-settings.php';

register_activation_hook( __FILE__, array( 'SVAC_Access_Logs', 'activate' ) );
register_activation_hook( __FILE__, array( 'SVAC_Video_Post_Type', 'register' ) );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'smart-video-access-control', false, dirname( plugin_basename( SVAC_FILE ) ) . '/languages' );
		SVAC_Video_Post_Type::init();
		SVAC_Access_Control::init();
		SVAC_Video_Shortcode::init();
		SVAC_REST_API::init();
		SVAC_Admin_Settings::init();
	}
);
