<?php
/**
 * پاک‌سازی گزینه‌ها هنگام حذف افزونه.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wbl_settings' );
delete_option( 'wbl_webakery-login_key' );
delete_option( 'wbl_webakery-login_status' );
delete_option( 'wbl_webakery-login_info' );
delete_option( 'wbl_webakery-login_last_check' );
delete_option( 'wbl_webakery-login_install_time' );
delete_option( 'wbl_webakery-login_ver' );
delete_transient( 'wbl_upd_webakery-login' );

global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wbl_otp_%' OR option_name LIKE '_transient_timeout_wbl_otp_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wbl_rate_%' OR option_name LIKE '_transient_timeout_wbl_rate_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wbl_ip_%' OR option_name LIKE '_transient_timeout_wbl_ip_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wbl_gstate_%' OR option_name LIKE '_transient_timeout_wbl_gstate_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
