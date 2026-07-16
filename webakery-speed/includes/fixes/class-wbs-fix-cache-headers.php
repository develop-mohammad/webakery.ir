<?php
/**
 * Cache headers for static assets.
 *
 * @package WebakerySpeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WBS_Fix_Cache_Headers {

	public static function boot() {
		add_action( 'send_headers', array( __CLASS__, 'headers' ) );
	}

	public static function headers() {
		if ( is_admin() ) {
			return;
		}

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( ! preg_match( '/\.(css|js|woff2?|ttf|otf|svg|jpg|jpeg|png|gif|webp|avif)(\?|$)/i', $uri ) ) {
			return;
		}

		if ( ! headers_sent() ) {
			header( 'Cache-Control: public, max-age=31536000, immutable' );
		}
	}
}
