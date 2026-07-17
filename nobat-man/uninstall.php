<?php
/**
 * Uninstall نوبت من
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
$tables = array(
	'nm_specialists',
	'nm_businesses',
	'nm_bookings',
	'nm_schedules',
	'nm_exceptions',
	'nm_questions',
	'nm_tickets',
	'nm_ticket_replies',
	'nm_pricing_rules',
	'nm_subscriptions',
);
foreach ( $tables as $t ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . $t );
}

delete_option( 'nm_settings' );
delete_option( 'nm_db_version' );

// لایسنس گزینه‌ها را نگه نمی‌داریم مگر اینکه کاربر بخواهد — پاک می‌کنیم
delete_option( 'wbl_nobat-man_key' );
delete_option( 'wbl_nobat-man_status' );
delete_option( 'wbl_nobat-man_info' );
delete_option( 'wbl_nobat-man_last_check' );
delete_option( 'wbl_nobat-man_install_time' );
delete_option( 'wbl_nobat-man_ver' );

wp_clear_scheduled_hook( 'nm_daily_maintenance' );
