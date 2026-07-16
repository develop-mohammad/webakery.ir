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
delete_option( 'webakery_invoice_counter' );

$post_types = array( 'wbk_order', 'wbk_invoice' );

foreach ( $post_types as $post_type ) {
	$ids = get_posts(
		array(
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	foreach ( $ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}
}

remove_role( 'wbk_manager' );
remove_role( 'wbk_accountant' );

$admin = get_role( 'administrator' );
if ( $admin ) {
	$admin->remove_cap( 'wbk_manage_panel' );
}
