<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'svac_settings' );

if ( defined( 'SVAC_REMOVE_DATA' ) && SVAC_REMOVE_DATA ) {
	global $wpdb;
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}video_access_logs" );
	$video_ids = get_posts(
		array(
			'post_type'      => 'svac_video',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	foreach ( $video_ids as $video_id ) {
		wp_delete_post( $video_id, true );
	}
}
