<?php
/**
 * پاک‌سازی گزینه‌ها هنگام حذف افزونه.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wbl_settings' );
delete_option( 'wb_license_webakery-login_key' );
delete_option( 'wb_license_webakery-login_status' );
delete_option( 'wb_license_webakery-login_install_time' );
delete_transient( 'wbl_otp_cleanup' );

global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wbl_otp_%' OR option_name LIKE '_transient_timeout_wbl_otp_%'" ); // phpcs:ignore
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wbl_rate_%' OR option_name LIKE '_transient_timeout_wbl_rate_%'" ); // phpcs:ignore
