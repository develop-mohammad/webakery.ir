<?php
defined( 'ABSPATH' ) || exit;

class DID_Settings {

	const OPTION = 'did_settings';

	public static function defaults() {
		return array(
			'bing_api_key'         => '',
			'google_serp_provider' => 'none',
			'serpapi_key'          => '',
			'dataforseo_login'     => '',
			'dataforseo_password'  => '',
			'google_cse_key'       => '',
			'google_cse_cx'        => '',
			'max_pages'            => 50,
			'crawl_delay_ms'       => 800,
			'rank_depth'           => 20,
			'market'               => 'fa-IR',
			'country'              => 'ir',
			'schedule'             => 'off',
			'active_project'       => 0,
		);
	}

	public static function all() {
		$raw = array();
		if ( function_exists( 'get_option' ) ) {
			$raw = (array) get_option( self::OPTION, array() );
		}
		return array_merge( self::defaults(), $raw );
	}

	public static function get( $key, $default = null ) {
		$all = self::all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}
		return $default;
	}

	public static function save( array $input ) {
		$out = self::all();

		$text = array(
			'bing_api_key',
			'google_serp_provider',
			'serpapi_key',
			'dataforseo_login',
			'dataforseo_password',
			'google_cse_key',
			'google_cse_cx',
			'market',
			'country',
			'schedule',
		);
		foreach ( $text as $k ) {
			if ( array_key_exists( $k, $input ) ) {
				$val = $input[ $k ];
				$out[ $k ] = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( (string) $val ) : trim( (string) $val );
			}
		}

		$ints = array( 'max_pages', 'crawl_delay_ms', 'rank_depth', 'active_project' );
		foreach ( $ints as $k ) {
			if ( array_key_exists( $k, $input ) ) {
				$out[ $k ] = max( 0, (int) $input[ $k ] );
			}
		}

		$out['max_pages']      = min( 100, max( 5, (int) $out['max_pages'] ) );
		$out['crawl_delay_ms'] = min( 5000, max( 200, (int) $out['crawl_delay_ms'] ) );
		$out['rank_depth']     = min( 50, max( 10, (int) $out['rank_depth'] ) );

		$allowed_provider = array( 'none', 'serpapi', 'dataforseo', 'cse' );
		if ( ! in_array( $out['google_serp_provider'], $allowed_provider, true ) ) {
			$out['google_serp_provider'] = 'none';
		}
		$allowed_schedule = array( 'off', 'daily' );
		if ( ! in_array( $out['schedule'], $allowed_schedule, true ) ) {
			$out['schedule'] = 'off';
		}
		if ( ! preg_match( '/^[a-z]{2}(-[A-Z]{2})?$/', (string) $out['market'] ) ) {
			$out['market'] = 'fa-IR';
		}
		$out['country'] = strtolower( preg_replace( '/[^a-z]/', '', (string) $out['country'] ) );
		if ( strlen( $out['country'] ) !== 2 ) {
			$out['country'] = 'ir';
		}

		update_option( self::OPTION, $out, false );
		return $out;
	}
}
