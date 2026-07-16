<?php
/**
 * Uninstall cleanup.
 *
 * @package Webakery
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'webakery_settings' );

$order_ids = get_posts(
	array(
		'post_type'      => 'wbk_order',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

foreach ( $order_ids as $order_id ) {
	wp_delete_post( $order_id, true );
}
