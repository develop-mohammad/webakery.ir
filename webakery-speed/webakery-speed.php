<?php
/**
 * Plugin Name: پنل سرعت | WebAkery Speed
 * Description: پنل یکپارچه سرعت: اولویت‌های Core Web Vitals + بهینه‌سازی اجباری فونت (Font Swap).
 * Version:     1.0.0
 * Plugin URI:  https://webakery.ir
 * Author:      webakery.ir
 * Author URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to: 6.7
 * Text Domain: webakery-speed
 * License:     GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'WBS_LOADED' ) ) {
	return;
}
define( 'WBS_LOADED', true );
define( 'WBS_VERSION', '1.0.0' );
define( 'WBS_FILE', __FILE__ );
define( 'WBS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WBS_URL', plugin_dir_url( __FILE__ ) );

require_once WBS_PATH . 'includes/class-wbs-scanner.php';
require_once WBS_PATH . 'includes/class-wbs-board.php';
require_once WBS_PATH . 'includes/class-wbs-fonts.php';

/**
 * Bootstrap + conflict notices for legacy split plugins.
 */
final class WBS_Plugin {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function activate() {
		WBS_Board::activate();
		WBS_Fonts::activate();
	}

	private function __construct() {
		add_action( 'admin_notices', array( $this, 'legacy_conflict_notice' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WBS_FILE ), array( $this, 'action_links' ) );

		// Load modules (each registers its own hooks/menus).
		WBS_Board::instance();
		WBS_Fonts::instance();
	}

	public function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=webakery-speed-fonts' ) ) . '">فونت</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=webakery-speed' ) ) . '"><strong>باز کردن پنل</strong></a>'
		);
		return $links;
	}

	public function legacy_conflict_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$active = array();
		if ( is_plugin_active( 'webakery-font-swap/webakery-font-swap.php' ) ) {
			$active[] = 'فونت سوییپ (Font Swap)';
		}
		if ( is_plugin_active( 'webakery-speed-board/webakery-speed-board.php' ) ) {
			$active[] = 'پنل سرعت (Speed Board)';
		}
		if ( empty( $active ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo '<strong>پنل سرعت یکپارچه:</strong> این افزونه‌های قدیمی هنوز فعال‌اند و ممکن است تداخل بسازند: ';
		echo esc_html( implode( '، ', $active ) );
		echo '. لطفاً از صفحه افزونه‌ها آن‌ها را <strong>غیرفعال و حذف</strong> کنید؛ همه قابلیت‌ها داخل همین افزونه است.';
		echo '</p></div>';
	}
}

register_activation_hook( __FILE__, array( 'WBS_Plugin', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		WBS_Plugin::instance();
	},
	5
);
