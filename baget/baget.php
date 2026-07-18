<?php
/**
 * Plugin Name: Baget | ادیت فیلدهای پرداخت
 * Description: مدیریت فیلدهای صفحه پرداخت و محصولات آنلاین — جابه‌جایی و ذخیره فیلدهای پیش‌فرض و سفارشی.
 * Version:     1.3.9
 * Plugin URI:  https://webakery.ir
 * Author:      webakery.ir
 * Author URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: wccp
 */

defined( 'ABSPATH' ) || exit;

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p><strong>Baget:</strong> '
				. 'این افزونه به PHP 7.4 یا بالاتر نیاز دارد. نسخهٔ فعلی سرور: '
				. esc_html( PHP_VERSION ) . '</p></div>';
		}
	);
	return;
}

define( 'WCCP_VERSION', '1.3.9' );
define( 'WCCP_FILE', __FILE__ );
define( 'WCCP_PATH', plugin_dir_path( __FILE__ ) );
define( 'WCCP_URL', plugin_dir_url( __FILE__ ) );
define( 'WCCP_PRODUCT', 'wccp' );

if ( ! is_readable( WCCP_PATH . 'includes/Autoload.php' ) ) {
	return;
}

require_once WCCP_PATH . 'includes/Autoload.php';
\WCCP\Autoload::register();

add_action(
	'plugins_loaded',
	static function () {
		try {
			\WCCP\Plugin::instance();
		} catch ( Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				add_action(
					'admin_notices',
					static function () use ( $e ) {
						if ( ! current_user_can( 'manage_options' ) ) {
							return;
						}
						echo '<div class="notice notice-error"><p><strong>Baget:</strong> '
							. esc_html( $e->getMessage() ) . '</p></div>';
					}
				);
			}
		}
	},
	20
);

register_activation_hook( __FILE__, array( '\WCCP\Plugin', 'activate' ) );
