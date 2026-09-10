<?php
/**
 * پاک‌سازی هنگام حذف افزونه.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
$tables = array( 'nck_members', 'nck_contracts', 'nck_subscriptions', 'nck_attendances' );
foreach ( $tables as $t ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . $t ); // phpcs:ignore
}

delete_option( 'nck_settings' );
delete_option( 'nck_db_version' );
delete_option( 'wbl_nahal-cowork_key' );
delete_option( 'wbl_nahal-cowork_status' );
delete_option( 'wbl_nahal-cowork_info' );
delete_option( 'wbl_nahal-cowork_last_check' );
delete_option( 'wbl_nahal-cowork_install_time' );
delete_option( 'wbl_nahal-cowork_ver' );
