<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$settings = get_option( 'wdp_settings', array() );
if ( empty( $settings['delete_data'] ) ) {
	return;
}

if ( ! taxonomy_exists( 'wdp_discount_page' ) ) {
	register_taxonomy( 'wdp_discount_page', array( 'product' ) );
}

$terms = get_terms(
	array(
		'taxonomy'   => 'wdp_discount_page',
		'hide_empty' => false,
		'fields'     => 'ids',
	)
);
if ( ! is_wp_error( $terms ) ) {
	foreach ( $terms as $term_id ) {
		wp_delete_term( $term_id, 'wdp_discount_page' );
	}
}

global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_wdp_discount_percent','_wdp_discount_fixed')" );

delete_option( 'wdp_settings' );
delete_option( 'wdp_log' );
delete_option( 'wdp_recalc_queue' );
delete_transient( 'wdp_product_cat_tree' );

foreach ( array( 'key', 'status', 'info', 'install_time', 'last_check', 'ver' ) as $k ) {
	delete_option( 'wbl_webakery-discount-pages_' . $k );
}

$timestamp = wp_next_scheduled( 'wdp_recalculate_all' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'wdp_recalculate_all' );
}
$batch = wp_next_scheduled( 'wdp_recalculate_batch' );
if ( $batch ) {
	wp_unschedule_event( $batch, 'wdp_recalculate_batch' );
}
