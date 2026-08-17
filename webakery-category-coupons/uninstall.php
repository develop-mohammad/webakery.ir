<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$settings = get_option( 'wbcc_settings', array() );
if ( empty( $settings['delete_data'] ) ) {
	return;
}

delete_option( 'wbcc_campaigns' );
delete_option( 'wbcc_settings' );
delete_option( 'wbcc_log' );

$timestamp = wp_next_scheduled( 'wbcc_auto_run' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'wbcc_auto_run' );
}
