<?php
/**
 * Plugin Name: تنظیمات Perfmatters | کیان استوک
 * Description: با فعال‌سازی، تنظیمات بهینه‌سازی Perfmatters مخصوص kianstock.ir را ایمپورت می‌کند. بعد از اعمال می‌توانید این افزونه را حذف کنید.
 * Version:     1.0.0
 * Plugin URI:  https://webakery.ir
 * Author:      webakery.ir
 * Author URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: kianstock-perfmatters-setup
 * License:     GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

define( 'KSPS_VERSION', '1.0.0' );
define( 'KSPS_FILE', __FILE__ );
define( 'KSPS_PATH', plugin_dir_path( __FILE__ ) );

register_activation_hook( __FILE__, 'ksps_activate' );
register_deactivation_hook( __FILE__, 'ksps_deactivate' );

/**
 * Import Perfmatters settings on activation.
 */
function ksps_activate() {
	$file = KSPS_PATH . 'settings.json';

	if ( ! file_exists( $file ) ) {
		update_option( 'ksps_import_result', array(
			'ok'      => false,
			'message' => 'فایل settings.json پیدا نشد.',
		), false );
		return;
	}

	$raw = file_get_contents( $file );
	$data = json_decode( $raw, true );

	if ( ! is_array( $data ) || empty( $data['perfmatters_options'] ) ) {
		update_option( 'ksps_import_result', array(
			'ok'      => false,
			'message' => 'فایل تنظیمات نامعتبر است.',
		), false );
		return;
	}

	// Backup current Perfmatters options once.
	$current_options = get_option( 'perfmatters_options', null );
	$current_tools   = get_option( 'perfmatters_tools', null );
	if ( false === get_option( 'ksps_backup_options', false ) && null !== $current_options ) {
		update_option( 'ksps_backup_options', $current_options, false );
	}
	if ( false === get_option( 'ksps_backup_tools', false ) && null !== $current_tools ) {
		update_option( 'ksps_backup_tools', $current_tools, false );
	}

	update_option( 'perfmatters_options', $data['perfmatters_options'] );

	if ( isset( $data['perfmatters_tools'] ) && is_array( $data['perfmatters_tools'] ) ) {
		update_option( 'perfmatters_tools', $data['perfmatters_tools'] );
	}

	// Clear Perfmatters used CSS if available.
	if ( class_exists( '\\Perfmatters\\CSS' ) && method_exists( '\\Perfmatters\\CSS', 'clear_used_css' ) ) {
		\Perfmatters\CSS::clear_used_css();
	}

	update_option( 'ksps_import_result', array(
		'ok'      => true,
		'message' => 'تنظیمات Perfmatters با موفقیت اعمال شد. کش سایت را پاک کنید و صفحه اصلی را یک‌بار باز کنید.',
		'time'    => time(),
	), false );
}

/**
 * Keep backup on deactivation; nothing destructive.
 */
function ksps_deactivate() {
	// Intentionally empty.
}

add_action( 'admin_notices', 'ksps_admin_notice' );
add_action( 'admin_post_ksps_restore_backup', 'ksps_restore_backup' );

/**
 * Show result notice in admin.
 */
function ksps_admin_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$result = get_option( 'ksps_import_result' );
	if ( empty( $result ) || ! is_array( $result ) ) {
		return;
	}

	$class = ! empty( $result['ok'] ) ? 'notice-success' : 'notice-error';
	$msg   = isset( $result['message'] ) ? $result['message'] : '';

	echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p><strong>تنظیمات Perfmatters | کیان استوک:</strong> ' . esc_html( $msg ) . '</p>';

	if ( ! empty( $result['ok'] ) ) {
		echo '<p>اگر WP Rocket دارید، Delay JS / Remove Unused CSS / LazyLoad را در Rocket خاموش کنید تا تداخل نداشته باشد.</p>';
		echo '<p>بعد از تست می‌توانید این افزونه را غیرفعال و حذف کنید؛ تنظیمات Perfmatters باقی می‌ماند.</p>';

		if ( get_option( 'ksps_backup_options', false ) ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=ksps_restore_backup' ), 'ksps_restore_backup' );
			echo '<p><a class="button" href="' . esc_url( $url ) . '">بازگردانی تنظیمات قبلی Perfmatters</a></p>';
		}
	}

	echo '</div>';
}

/**
 * Restore previous Perfmatters settings.
 */
function ksps_restore_backup() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		wp_die( 'Forbidden' );
	}
	check_admin_referer( 'ksps_restore_backup' );

	$backup_options = get_option( 'ksps_backup_options', null );
	$backup_tools   = get_option( 'ksps_backup_tools', null );

	if ( null !== $backup_options ) {
		update_option( 'perfmatters_options', $backup_options );
	}
	if ( null !== $backup_tools ) {
		update_option( 'perfmatters_tools', $backup_tools );
	}

	update_option( 'ksps_import_result', array(
		'ok'      => true,
		'message' => 'تنظیمات قبلی Perfmatters بازگردانی شد.',
		'time'    => time(),
	), false );

	wp_safe_redirect( admin_url( 'plugins.php' ) );
	exit;
}
