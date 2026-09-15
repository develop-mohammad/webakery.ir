<?php
defined( 'ABSPATH' ) || exit;

/**
 * میزان سرچ ماهانهٔ واقعی از Google Ads Keyword Planner.
 * بدون اتصال ادز هیچ عددی ساخته نمی‌شود.
 */
class WBGS_Ads {

	const API = 'https://googleads.googleapis.com/v18';

	public static function configured() {
		$s = WBGS_Plugin::settings();
		return ( $s['ads_developer_token'] && $s['ads_client_id'] && $s['ads_client_secret'] && $s['ads_refresh_token'] && $s['ads_customer_id'] );
	}

	/**
	 * @param string[] $keywords
	 * @return array{ok:bool,configured:bool,message:string,volumes:array<string,array>}
	 */
	public static function volumes( $keywords, $hl = 'fa', $gl = 'ir' ) {
		$keywords = array_values( array_unique( array_filter( array_map( array( 'WBGS_Suggest', 'normalize_seed' ), (array) $keywords ) ) ) );
		if ( ! $keywords ) {
			return array(
				'ok'          => true,
				'configured'  => self::configured(),
				'message'     => '',
				'volumes'     => array(),
			);
		}
		if ( ! self::configured() ) {
			return array(
				'ok'         => false,
				'configured' => false,
				'message'    => 'برای عدد ماهانهٔ دقیق، گوگل ادز (Keyword Planner) را در تنظیمات وصل کنید. بدون آن عددی ساخته نمی‌شود.',
				'volumes'    => array(),
			);
		}

		$cached  = array();
		$missing = array();
		foreach ( $keywords as $kw ) {
			$hit = self::cache_get( $kw, $hl, $gl );
			if ( is_array( $hit ) ) {
				$cached[ $kw ] = $hit;
			} else {
				$missing[] = $kw;
			}
		}

		foreach ( array_chunk( $missing, 40 ) as $chunk ) {
			$part = self::fetch_chunk( $chunk, $hl, $gl );
			if ( ! $part['ok'] ) {
				return array(
					'ok'         => false,
					'configured' => true,
					'message'    => $part['message'],
					'volumes'    => $cached,
				);
			}
			foreach ( $part['volumes'] as $kw => $row ) {
				$cached[ $kw ] = $row;
				self::cache_set( $kw, $hl, $gl, $row );
			}
		}

		return array(
			'ok'         => true,
			'configured' => true,
			'message'    => '',
			'volumes'    => $cached,
		);
	}

	/**
	 * @param string[] $keywords
	 * @return array{ok:bool,message:string,volumes:array}
	 */
	private static function fetch_chunk( $keywords, $hl, $gl ) {
		$token = self::access_token();
		if ( is_wp_error( $token ) ) {
			return array( 'ok' => false, 'message' => $token->get_error_message(), 'volumes' => array() );
		}

		$s          = WBGS_Plugin::settings();
		$customer   = preg_replace( '/\D/', '', (string) $s['ads_customer_id'] );
		$login      = preg_replace( '/\D/', '', (string) $s['ads_login_customer_id'] );
		$geo        = self::geo_id( $gl );
		$lang       = self::lang_id( $hl );
		$url        = self::API . '/customers/' . $customer . ':generateKeywordHistoricalMetrics';
		$headers    = array(
			'Authorization'  => 'Bearer ' . $token,
			'developer-token'=> (string) $s['ads_developer_token'],
			'Content-Type'   => 'application/json',
		);
		if ( $login ) {
			$headers['login-customer-id'] = $login;
		}

		$body = array(
			'keywords'           => array_values( $keywords ),
			'geoTargetConstants' => array( 'geoTargetConstants/' . $geo ),
			'language'           => 'languageConstants/' . $lang,
			'keywordPlanNetwork' => 'GOOGLE_SEARCH',
		);

		$res = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'message' => $res->get_error_message(), 'volumes' => array() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$raw  = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $code < 200 || $code >= 300 ) {
			$msg = isset( $raw['error']['message'] ) ? (string) $raw['error']['message'] : ( 'خطای گوگل ادز HTTP ' . $code );
			return array( 'ok' => false, 'message' => $msg, 'volumes' => array() );
		}

		$volumes = array();
		$results = isset( $raw['results'] ) && is_array( $raw['results'] ) ? $raw['results'] : array();
		$lookup  = array();
		foreach ( $keywords as $kw ) {
			$lookup[ mb_strtolower( $kw ) ] = $kw;
		}
		foreach ( $results as $row ) {
			$text = isset( $row['text'] ) ? WBGS_Suggest::normalize_seed( $row['text'] ) : '';
			if ( $text === '' ) {
				continue;
			}
			$m   = isset( $row['keywordMetrics'] ) && is_array( $row['keywordMetrics'] ) ? $row['keywordMetrics'] : array();
			$data = array(
				'searches'    => isset( $m['avgMonthlySearches'] ) ? (int) $m['avgMonthlySearches'] : 0,
				'competition' => isset( $m['competition'] ) ? (string) $m['competition'] : '',
			);
			$volumes[ $text ] = $data;
			$low              = mb_strtolower( $text );
			if ( isset( $lookup[ $low ] ) ) {
				$volumes[ $lookup[ $low ] ] = $data;
			}
		}

		return array( 'ok' => true, 'message' => '', 'volumes' => $volumes );
	}

	/**
	 * @return string|WP_Error
	 */
	private static function access_token() {
		$cached = get_transient( 'wbgs_ads_token' );
		if ( is_string( $cached ) && $cached !== '' ) {
			return $cached;
		}
		$s    = WBGS_Plugin::settings();
		$res  = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 20,
				'body'    => array(
					'client_id'     => $s['ads_client_id'],
					'client_secret' => $s['ads_client_secret'],
					'refresh_token' => $s['ads_refresh_token'],
					'grant_type'    => 'refresh_token',
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( empty( $data['access_token'] ) ) {
			$msg = isset( $data['error_description'] ) ? (string) $data['error_description'] : 'توکن گوگل ادز گرفته نشد.';
			return new WP_Error( 'wbgs_ads_token', $msg );
		}
		$ttl = isset( $data['expires_in'] ) ? max( 60, (int) $data['expires_in'] - 60 ) : 3000;
		set_transient( 'wbgs_ads_token', (string) $data['access_token'], $ttl );
		return (string) $data['access_token'];
	}

	private static function cache_key( $kw, $hl, $gl ) {
		return 'wbgs_vol_' . md5( $kw . '|' . $hl . '|' . $gl );
	}

	private static function cache_get( $kw, $hl, $gl ) {
		$hit = get_transient( self::cache_key( $kw, $hl, $gl ) );
		return is_array( $hit ) ? $hit : null;
	}

	private static function cache_set( $kw, $hl, $gl, $row ) {
		set_transient( self::cache_key( $kw, $hl, $gl ), $row, 12 * HOUR_IN_SECONDS );
	}

	public static function geo_id( $gl ) {
		$map = array(
			'ir' => '2303',
			'us' => '2840',
			'gb' => '2826',
			'de' => '2276',
			'ae' => '2784',
			'tr' => '2792',
		);
		$gl = strtolower( (string) $gl );
		return isset( $map[ $gl ] ) ? $map[ $gl ] : '2303';
	}

	public static function lang_id( $hl ) {
		$map = array(
			'fa' => '1056',
			'en' => '1000',
			'ar' => '1013',
			'de' => '1001',
			'tr' => '1037',
		);
		$hl = strtolower( (string) $hl );
		return isset( $map[ $hl ] ) ? $map[ $hl ] : '1056';
	}
}
