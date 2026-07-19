<?php
/**
 * Plugin Name: Baget | ادیت فیلدهای پرداخت
 * Description: مدیریت فیلدهای صفحه پرداخت و محصولات آنلاین — جابه‌جایی و ذخیره فیلدهای پیش‌فرض و سفارشی.
 * Version:     1.5.9
 * Plugin URI:  https://webakery.ir
 * Author:      webakery.ir
 * Author URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: wccp
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'WCCP_LOADED' ) ) {
	return;
}
define( 'WCCP_LOADED', true );

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-error"><p><strong>Baget:</strong> PHP 7.4+ لازم است. نسخه فعلی: '
					. esc_html( PHP_VERSION ) . '</p></div>';
			}
		}
	);
	return;
}

if ( ! defined( 'WCCP_VERSION' ) ) {
	define( 'WCCP_VERSION', '1.5.9' );
}
if ( ! defined( 'WCCP_FILE' ) ) {
	define( 'WCCP_FILE', __FILE__ );
}
if ( ! defined( 'WCCP_PATH' ) ) {
	define( 'WCCP_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'WCCP_URL' ) ) {
	define( 'WCCP_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'WCCP_PRODUCT' ) ) {
	define( 'WCCP_PRODUCT', 'wccp' );
}

$wccp_autoload = WCCP_PATH . 'includes/Autoload.php';
if ( ! is_readable( $wccp_autoload ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p><strong>Baget:</strong> فایل‌های افزونه ناقص است. '
				. 'پوشه <code>baget</code> را حذف کنید و ZIP کامل v1.4.1 را دوباره نصب کنید.</p></div>';
		}
	);
	return;
}

require_once $wccp_autoload;

if ( ! class_exists( '\\WCCP\\Autoload' ) ) {
	return;
}
\WCCP\Autoload::register();

/**
 * راه‌اندازی امن — خطای داخلی نباید کل سایت را بخواباند.
 */
function wccp_safe_boot() {
	try {
		if ( ! class_exists( '\\WCCP\\Plugin' ) ) {
			return;
		}
		\WCCP\Plugin::instance();
	} catch ( Exception $e ) {
		wccp_boot_error( $e->getMessage() );
	} catch ( Throwable $e ) {
		wccp_boot_error( $e->getMessage() );
	}
}

/**
 * @param string $message
 */
function wccp_boot_error( $message ) {
	if ( ! is_admin() ) {
		return;
	}
	add_action(
		'admin_notices',
		static function () use ( $message ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p><strong>Baget:</strong> '
				. esc_html( $message ) . '</p></div>';
		}
	);
}

add_action( 'plugins_loaded', 'wccp_safe_boot', 30 );

register_activation_hook(
	__FILE__,
	static function () {
		try {
			if ( class_exists( '\\WCCP\\Plugin' ) ) {
				\WCCP\Plugin::activate();
			}
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}
	}
);
