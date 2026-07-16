<?php
/**
 * Load non-critical CSS asynchronously.
 *
 * @package WebakerySpeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WBS_Fix_Async_CSS {

	public static function boot() {
		add_filter( 'style_loader_tag', array( __CLASS__, 'async_tag' ), 20, 4 );
	}

	public static function async_tag( $html, $handle, $href, $media ) {
		if ( is_admin() || empty( $href ) ) {
			return $html;
		}

		$exclude = WBS_Settings::parse_list( WBS_Settings::get_one( 'exclude_styles', '' ) );
		$critical = array_merge( $exclude, array( 'wp-block-library', 'global-styles', 'classic-theme-styles' ) );

		if ( in_array( $handle, $critical, true ) ) {
			return $html;
		}

		if ( false !== strpos( $html, "media='print'" ) || false !== strpos( $html, 'media="print"' ) ) {
			return $html;
		}

		$original = $html;
		$html     = str_replace( "media='all'", "media='print' onload=\"this.media='all'\"", $html );
		$html     = str_replace( 'media="all"', 'media="print" onload="this.media=\'all\'"', $html );
		$html    .= '<noscript>' . $original . '</noscript>';

		return $html;
	}
}
