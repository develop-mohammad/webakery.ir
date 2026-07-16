<?php
/**
 * Preload LCP image candidate.
 *
 * @package WebakerySpeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WBS_Fix_Preload_LCP {

	public static function boot() {
		add_action( 'wp_head', array( __CLASS__, 'preload' ), 2 );
	}

	public static function preload() {
		if ( is_admin() || ! is_singular() ) {
			return;
		}

		$url = '';
		if ( has_post_thumbnail() ) {
			$url = get_the_post_thumbnail_url( get_queried_object_id(), 'full' );
		}

		$url = apply_filters( 'webakery_speed_lcp_image_url', $url );
		if ( empty( $url ) ) {
			return;
		}

		echo '<link rel="preload" as="image" href="' . esc_url( $url ) . '" fetchpriority="high" />' . "\n";
	}
}
