<?php
/**
 * Defer non-critical scripts.
 *
 * @package WebakerySpeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WBS_Fix_Defer_JS {

	public static function boot() {
		add_filter( 'script_loader_tag', array( __CLASS__, 'defer_tag' ), 20, 3 );
	}

	public static function defer_tag( $tag, $handle, $src ) {
		if ( is_admin() || empty( $src ) ) {
			return $tag;
		}

		$exclude = WBS_Settings::parse_list( WBS_Settings::get_one( 'exclude_scripts', '' ) );
		$exclude = array_merge(
			$exclude,
			array( 'jquery', 'jquery-core', 'jquery-migrate', 'wp-polyfill', 'comment-reply' )
		);

		if ( in_array( $handle, $exclude, true ) ) {
			return $tag;
		}

		if ( false !== strpos( $tag, ' defer' ) || false !== strpos( $tag, ' async' ) ) {
			return $tag;
		}

		return str_replace( ' src', ' defer src', $tag );
	}
}
