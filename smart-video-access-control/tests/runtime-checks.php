<?php
/**
 * Runtime verification for Smart Video Access Control on the local test site.
 * Run with: bin/wp-test.sh eval-file /tmp/svac-check.php
 */

$failures = 0;
function check( string $label, bool $passed, string $detail = '' ) {
	global $failures;
	if ( ! $passed ) {
		$failures++;
	}
	printf( "%s %s%s\n", $passed ? '[PASS]' : '[FAIL]', $label, '' !== $detail ? ' — ' . $detail : '' );
}

// --- fixtures -------------------------------------------------------------
add_role( 'premium', 'Premium Member', array( 'read' => true ) );

$premium_id = username_exists( 'premium1' ) ?: wp_insert_user(
	array( 'user_login' => 'premium1', 'user_pass' => 'pass1234', 'role' => 'premium' )
);
$sub_id = username_exists( 'sub1' ) ?: wp_insert_user(
	array( 'user_login' => 'sub1', 'user_pass' => 'pass1234', 'role' => 'subscriber' )
);
$blocked_id = username_exists( 'blocked1' ) ?: wp_insert_user(
	array( 'user_login' => 'blocked1', 'user_pass' => 'pass1234', 'role' => 'premium' )
);

$video_id = wp_insert_post(
	array( 'post_type' => 'svac_video', 'post_title' => 'Premium Course 1', 'post_status' => 'publish' )
);
update_post_meta( $video_id, '_svac_video_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' );
update_post_meta( $video_id, '_svac_allowed_roles', array( 'premium' ) );
update_post_meta( $video_id, '_svac_blocked_users', array( (int) $blocked_id ) );

echo "== custom post type & taxonomy ==\n";
check( 'CPT svac_video registered', post_type_exists( 'svac_video' ) );
check( 'taxonomy svac_video_category registered', taxonomy_exists( 'svac_video_category' ) );
check( 'log table created', (bool) $GLOBALS['wpdb']->get_var( "SHOW TABLES LIKE '{$GLOBALS['wpdb']->prefix}video_access_logs'" ) );

echo "\n== role based access ==\n";
wp_set_current_user( (int) $premium_id );
$out = do_shortcode( '[protected_video id="' . $video_id . '"]' );
check( 'premium role sees the player', str_contains( $out, 'youtube-nocookie.com/embed/dQw4w9WgXcQ' ) );

wp_set_current_user( (int) $sub_id );
$out = do_shortcode( '[protected_video id="' . $video_id . '"]' );
check( 'subscriber is denied', str_contains( $out, 'svac-access-message' ) );
check( 'denied output leaks no video URL', ! str_contains( $out, 'dQw4w9WgXcQ' ), 'source must stay clean' );

wp_set_current_user( (int) $blocked_id );
$out = do_shortcode( '[protected_video id="' . $video_id . '"]' );
check( 'blocked user is denied despite premium role', ! str_contains( $out, 'iframe' ) );

wp_set_current_user( 0 );
$out = do_shortcode( '[protected_video id="' . $video_id . '"]' );
check( 'guest gets the login message', str_contains( $out, 'وارد حساب کاربری' ) );

echo "\n== time based access ==\n";
$tz  = wp_timezone();
$now = new DateTimeImmutable( 'now', $tz );
update_post_meta( $video_id, '_svac_end_at', $now->modify( '-1 day' )->format( 'Y-m-d\TH:i' ) );
wp_set_current_user( (int) $premium_id );
$out = do_shortcode( '[protected_video id="' . $video_id . '"]' );
check( 'expired window denies access', str_contains( $out, 'به پایان رسیده' ) );

update_post_meta( $video_id, '_svac_start_at', $now->modify( '+2 days' )->format( 'Y-m-d\TH:i' ) );
update_post_meta( $video_id, '_svac_end_at', $now->modify( '+3 days' )->format( 'Y-m-d\TH:i' ) );
$out = do_shortcode( '[protected_video id="' . $video_id . '"]' );
check( 'future window denies access', str_contains( $out, 'هنوز در دسترس نیست' ) );

update_post_meta( $video_id, '_svac_start_at', $now->modify( '-1 hour' )->format( 'Y-m-d\TH:i' ) );
update_post_meta( $video_id, '_svac_end_at', $now->modify( '+1 hour' )->format( 'Y-m-d\TH:i' ) );
$out = do_shortcode( '[protected_video id="' . $video_id . '"]' );
check( 'active window allows access', str_contains( $out, '<iframe' ) );

echo "\n== category rule fallback ==\n";
$term = wp_insert_term( 'Premium Videos ' . wp_rand( 100, 999 ), 'svac_video_category' );
$cat_video = wp_insert_post(
	array( 'post_type' => 'svac_video', 'post_title' => 'Category Ruled', 'post_status' => 'publish' )
);
update_post_meta( $cat_video, '_svac_video_url', 'https://example.com/lesson.mp4' );
wp_set_object_terms( $cat_video, array( (int) $term['term_id'] ), 'svac_video_category' );
update_term_meta( $term['term_id'], '_svac_allowed_roles', array( 'premium' ) );

wp_set_current_user( (int) $sub_id );
check( 'category rule blocks subscriber', ! SVAC_Access_Control::can_access( $cat_video )['allowed'] );
wp_set_current_user( (int) $premium_id );
check( 'category rule allows premium', SVAC_Access_Control::can_access( $cat_video )['allowed'] );
check( 'mp4 renders a video element', str_contains( do_shortcode( '[protected_video id="' . $cat_video . '"]' ), '<video controls' ) );

echo "\n== REST API ==\n";
wp_set_current_user( 0 );
$response = rest_do_request( new WP_REST_Request( 'GET', '/svac/v1/videos/' . $video_id . '/access' ) );
check( 'anonymous GET is rejected', 401 === $response->get_status() || 403 === $response->get_status(), 'status ' . $response->get_status() );

wp_set_current_user( (int) $premium_id );
$response = rest_do_request( new WP_REST_Request( 'GET', '/svac/v1/videos/' . $video_id . '/access' ) );
$data     = $response->get_data();
check( 'premium GET reports allowed', 200 === $response->get_status() && true === $data['allowed'] );
check( 'REST never returns the video URL', ! str_contains( wp_json_encode( $data ), 'dQw4w9WgXcQ' ) );

wp_set_current_user( (int) $sub_id );
$response = rest_do_request( new WP_REST_Request( 'POST', '/svac/v1/videos/' . $video_id . '/check' ) );
$data     = $response->get_data();
check( 'subscriber POST reports denied', 200 === $response->get_status() && false === $data['allowed'], 'reason: ' . $data['reason'] );

echo "\n== access logs ==\n";
$rows = SVAC_Access_Logs::get_logs( 100 );
check( 'logs recorded', count( $rows ) > 0, count( $rows ) . ' rows' );
$statuses = array_unique( wp_list_pluck( $rows, 'status' ) );
sort( $statuses );
check( 'both allowed and denied statuses stored', array( 'allowed', 'denied' ) === $statuses, implode( ',', $statuses ) );
check( 'log rows carry a video id', 0 !== (int) $rows[0]['video_id'] );

printf( "\n%s\n", 0 === $failures ? 'ALL CHECKS PASSED' : $failures . ' CHECK(S) FAILED' );
