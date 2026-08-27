<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * تنظیمات سراسری افزونه.
 */
class WBE_Settings {

	const OPTION = 'wbe_settings';

	public static function defaults() {
		return array(
			'calendar'          => 'jalali',
			'near_expiry_days'  => 30,
			'show_near_price'   => 1,
			'show_in_description' => 1,
		);
	}

	public static function get() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	public static function calendar() {
		$s = self::get();
		return ( 'gregorian' === $s['calendar'] ) ? 'gregorian' : 'jalali';
	}

	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		return array(
			'calendar'            => ( isset( $input['calendar'] ) && 'gregorian' === $input['calendar'] ) ? 'gregorian' : 'jalali',
			'near_expiry_days'    => max( 1, min( 3650, (int) ( $input['near_expiry_days'] ?? 30 ) ) ),
			'show_near_price'     => empty( $input['show_near_price'] ) ? 0 : 1,
			'show_in_description' => empty( $input['show_in_description'] ) ? 0 : 1,
		);
	}
}
