<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'wbe_settings' );
delete_option( 'wbe_last_notify_date' );
delete_transient( 'wbe_sales_map' );
delete_transient( 'wbe_alerts_cache' );
wp_clear_scheduled_hook( 'wbe_daily_sync' );
delete_option( 'wbl_webakery-expiry_key' );
delete_option( 'wbl_webakery-expiry_status' );
delete_option( 'wbl_webakery-expiry_info' );
delete_option( 'wbl_webakery-expiry_last_check' );
delete_option( 'wbl_webakery-expiry_install_time' );
delete_option( 'wbl_webakery-expiry_ver' );
