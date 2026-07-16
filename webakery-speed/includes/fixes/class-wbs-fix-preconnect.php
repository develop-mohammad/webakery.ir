<?php
/**
 * Preconnect hints.
 *
 * @package WebakerySpeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WBS_Fix_Preconnect {

	public static function boot() {
		add_action( 'wp_head', array( __CLASS__, 'hints' ), 1 );
	}

	public static function hints() {
		if ( is_admin() ) {
			return;
		}

		$origins = array(
			'https://fonts.googleapis.com',
			'https://fonts.gstatic.com',
			'https://www.google-analytics.com',
			'https://www.googletagmanager.com',
		);

		$origins = apply_filters( 'webakery_speed_preconnect_origins', $origins );

		foreach ( array_unique( $origins ) as $origin ) {
			echo '<link rel="preconnect" href="' . esc_url( $origin ) . '" crossorigin />' . "\n";
			echo '<link rel="dns-prefetch" href="' . esc_url( $origin ) . '" />' . "\n";
		}
	}
}
