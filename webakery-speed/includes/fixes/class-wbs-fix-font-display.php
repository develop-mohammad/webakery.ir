<?php
/**
 * Font display swap.
 *
 * @package WebakerySpeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WBS_Fix_Font_Display {

	public static function boot() {
		add_filter( 'style_loader_tag', array( __CLASS__, 'google_swap' ), 25, 4 );
		add_action( 'wp_head', array( __CLASS__, 'inline_swap' ), 1 );
	}

	public static function google_swap( $html, $handle, $href, $media ) {
		if ( is_admin() || empty( $href ) ) {
			return $html;
		}

		if ( false !== strpos( $href, 'fonts.googleapis.com' ) && false === strpos( $href, 'display=' ) ) {
			$href  = add_query_arg( 'display', 'swap', $href );
			$html  = preg_replace( '/href=(["\']).*?\1/', 'href="' . esc_url( $href ) . '"', $html, 1 );
		}

		return $html;
	}

	public static function inline_swap() {
		if ( is_admin() ) {
			return;
		}
		echo "<style>@font-face{font-display:swap !important;}</style>\n";
	}
}
