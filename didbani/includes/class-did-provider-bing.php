<?php
defined( 'ABSPATH' ) || exit;

/**
 * بینگ از Azure Bing Web Search API v7.
 */
class DID_Provider_Bing {

	const ENDPOINT = 'https://api.bing.microsoft.com/v7.0/search';

	/**
	 * @param string $keyword
	 * @param int    $depth
	 * @param string $api_key
	 * @param string $mkt
	 * @return array{ok:bool,error:string,results:array,raw:mixed}
	 */
	public static function search( $keyword, $depth, $api_key, $mkt = 'fa-IR', $opts = array() ) {
		$keyword = trim( (string) $keyword );
		$api_key = trim( (string) $api_key );
		if ( '' === $api_key ) {
			return self::fail( 'کلید Bing (Azure) تنظیم نشده.' );
		}
		if ( '' === $keyword ) {
			return self::fail( 'کلیدواژه خالی است.' );
		}
		$depth = min( 50, max( 10, (int) $depth ) );
		$count = min( 50, $depth );
		$url   = self::ENDPOINT . '?' . http_build_query(
			array(
				'q'     => $keyword,
				'mkt'   => $mkt ? $mkt : 'fa-IR',
				'count' => $count,
				'safeSearch' => 'Off',
				'textDecorations' => 'false',
			)
		);
		$headers = array(
			'Ocp-Apim-Subscription-Key' => $api_key,
			'Accept'                    => 'application/json',
		);
		$ua = DID_UA;
		if ( ! empty( $opts['device'] ) && 'mobile' === $opts['device'] ) {
			$ua = DID_Geo::mobile_ua();
		}
		if ( ! empty( $opts['lat'] ) && ! empty( $opts['lng'] ) ) {
			$headers['X-Search-Location'] = sprintf(
				'lat:%.4f;long:%.4f;re:20000',
				(float) $opts['lat'],
				(float) $opts['lng']
			);
		}
		$res = DID_Http::get(
			$url,
			array(
				'timeout'     => 20,
				'headers'     => $headers,
				'user-agent'  => $ua,
			)
		);
		if ( ! $res['ok'] ) {
			return self::fail( $res['error'] ? $res['error'] : 'پاسخ بینگ نامعتبر بود.' );
		}
		$data = json_decode( $res['body'], true );
		if ( ! is_array( $data ) ) {
			return self::fail( 'JSON بینگ خوانده نشد.' );
		}
		if ( ! empty( $data['error']['message'] ) ) {
			return self::fail( (string) $data['error']['message'] );
		}
		return array(
			'ok'      => true,
			'error'   => '',
			'results' => self::parse( $data ),
			'raw'     => $data,
		);
	}

	/**
	 * @param array $data
	 * @return array<int,array{position:int,url:string,title:string}>
	 */
	public static function parse( array $data ) {
		$items = array();
		if ( isset( $data['webPages']['value'] ) && is_array( $data['webPages']['value'] ) ) {
			$items = $data['webPages']['value'];
		}
		$out = array();
		$i   = 0;
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['url'] ) ) {
				continue;
			}
			$i++;
			$out[] = array(
				'position' => $i,
				'url'      => (string) $item['url'],
				'title'    => isset( $item['name'] ) ? (string) $item['name'] : '',
			);
		}
		return $out;
	}

	private static function fail( $message ) {
		return array(
			'ok'      => false,
			'error'   => $message,
			'results' => array(),
			'raw'     => null,
		);
	}
}
