<?php
/**
 * Plugin Name: تنظیمات Perfmatters | گین استور
 * Description: با فعال‌سازی، تنظیمات بهینه‌سازی Perfmatters مخصوص gainstore.ir (بر اساس PageSpeed موبایل) را ایمپورت می‌کند. بعد از اعمال می‌توانید این افزونه را حذف کنید.
 * Version:     1.0.0
 * Plugin URI:  https://webakery.ir
 * Author:      webakery.ir
 * Author URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: gainstore-perfmatters-setup
 * License:     GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

define( 'GSPS_VERSION', '1.0.0' );
define( 'GSPS_FILE', __FILE__ );
define( 'GSPS_PATH', plugin_dir_path( __FILE__ ) );

register_activation_hook( __FILE__, 'gsps_activate' );

function gsps_activate() {
	$file = GSPS_PATH . 'settings.json';

	if ( ! file_exists( $file ) ) {
		update_option( 'gsps_import_result', array(
			'ok'      => false,
			'message' => 'فایل settings.json پیدا نشد.',
		), false );
		return;
	}

	$data = json_decode( file_get_contents( $file ), true );
	if ( ! is_array( $data ) || empty( $data['perfmatters_options'] ) ) {
		update_option( 'gsps_import_result', array(
			'ok'      => false,
			'message' => 'فایل تنظیمات نامعتبر است.',
		), false );
		return;
	}

	if ( ! defined( 'PERFMATTERS_VERSION' ) && ! function_exists( 'perfmatters_network_admin_actions' ) && ! class_exists( '\\Perfmatters\\Config' ) ) {
		// Soft warning only; still write options so they apply once Perfmatters is active.
		$warn = ' توجه: به نظر می‌رسد Perfmatters هنوز فعال نیست. اول Perfmatters را نصب/فعال کنید.';
	} else {
		$warn = '';
	}

	$current_options = get_option( 'perfmatters_options', null );
	$current_tools   = get_option( 'perfmatters_tools', null );
	if ( false === get_option( 'gsps_backup_options', false ) && null !== $current_options ) {
		update_option( 'gsps_backup_options', $current_options, false );
	}
	if ( false === get_option( 'gsps_backup_tools', false ) && null !== $current_tools ) {
		update_option( 'gsps_backup_tools', $current_tools, false );
	}

	update_option( 'perfmatters_options', $data['perfmatters_options'] );
	if ( isset( $data['perfmatters_tools'] ) && is_array( $data['perfmatters_tools'] ) ) {
		update_option( 'perfmatters_tools', $data['perfmatters_tools'] );
	}

	if ( class_exists( '\\Perfmatters\\CSS' ) && method_exists( '\\Perfmatters\\CSS', 'clear_used_css' ) ) {
		\Perfmatters\CSS::clear_used_css();
	}

	update_option( 'gsps_import_result', array(
		'ok'      => true,
		'message' => 'تنظیمات Perfmatters برای gainstore.ir اعمال شد. کش را پاک کنید و صفحه اصلی را یک‌بار باز کنید.' . $warn,
		'time'    => time(),
	), false );
}

add_action( 'admin_notices', 'gsps_admin_notice' );
add_action( 'admin_post_gsps_restore_backup', 'gsps_restore_backup' );

function gsps_admin_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$result = get_option( 'gsps_import_result' );
	if ( empty( $result ) || ! is_array( $result ) ) {
		return;
	}
	$class = ! empty( $result['ok'] ) ? 'notice-success' : 'notice-error';
	echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p><strong>تنظیمات Perfmatters | گین استور:</strong> ' . esc_html( $result['message'] ?? '' ) . '</p>';
	if ( ! empty( $result['ok'] ) ) {
		echo '<p>بعد از تست می‌توانید این افزونه کمکی را حذف کنید؛ تنظیمات Perfmatters باقی می‌ماند.</p>';
		echo '<p><strong>خارج از Perfmatters:</strong> تصاویر PNG خیلی سنگین‌اند (حدود ۳MB قابل صرفه‌جویی). آن‌ها را WebP کنید تا LCP بهتر شود.</p>';
		if ( get_option( 'gsps_backup_options', false ) ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=gsps_restore_backup' ), 'gsps_restore_backup' );
			echo '<p><a class="button" href="' . esc_url( $url ) . '">بازگردانی تنظیمات قبلی Perfmatters</a></p>';
		}
	}
	echo '</div>';
}

function gsps_restore_backup() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		wp_die( 'Forbidden' );
	}
	check_admin_referer( 'gsps_restore_backup' );
	$backup_options = get_option( 'gsps_backup_options', null );
	$backup_tools   = get_option( 'gsps_backup_tools', null );
	if ( null !== $backup_options ) {
		update_option( 'perfmatters_options', $backup_options );
	}
	if ( null !== $backup_tools ) {
		update_option( 'perfmatters_tools', $backup_tools );
	}
	update_option( 'gsps_import_result', array(
		'ok'      => true,
		'message' => 'تنظیمات قبلی Perfmatters بازگردانی شد.',
		'time'    => time(),
	), false );
	wp_safe_redirect( admin_url( 'plugins.php' ) );
	exit;
}
