<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$tables = array(
	'wbss_activity',
	'wbss_press',
	'wbss_backlinks',
	'wbss_technical',
	'wbss_content',
	'wbss_ranks',
	'wbss_keywords',
	'wbss_projects',
);

foreach ( $tables as $table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . $table ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

delete_option( 'wbss_db_version' );
delete_option( 'wbss_settings' );
delete_option( 'wbss_seeded' );
