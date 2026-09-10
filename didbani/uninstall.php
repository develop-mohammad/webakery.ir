<?php
/**
 * پاک‌سازی هنگام حذف افزونه.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'did_settings' );
delete_option( 'wb_license_didbani_key' );
delete_option( 'wb_license_didbani_status' );
delete_option( 'wb_license_didbani_install_time' );

global $wpdb;
$tables = array(
	$wpdb->prefix . 'did_projects',
	$wpdb->prefix . 'did_domains',
	$wpdb->prefix . 'did_keywords',
	$wpdb->prefix . 'did_pages',
	$wpdb->prefix . 'did_ranks',
	$wpdb->prefix . 'did_jobs',
);
foreach ( $tables as $t ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$t}" ); // phpcs:ignore
}
