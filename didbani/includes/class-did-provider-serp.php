<?php
defined( 'ABSPATH' ) || exit;

/**
 * گوگل از SerpAPI (رتبهٔ ارگانیک).
 */
class DID_Provider_Serp {

	const ENDPOINT = 'https://serpapi.com/search.json';

	/**
	 * @param string $keyword
	 * @param int    $depth
	 * @param string $api_key
	 * @param string $hl
	 * @param string $gl
	 * @return array{ok:bool,error:string,results:array,raw:mixed}
	 */
	public static function search( $keyword, $depth, $api_key, $hl = 'fa', $gl = 'ir', $opts = array() ) {
		$keyword = trim( (string) $keyword );
		$api_key = trim( (string) $api_key );
		if ( '' === $api_key ) {
			return self::fail( 'کلید SerpAPI تنظیم نشده.' );
		}
		if ( '' === $keyword ) {
			return self::fail( 'کلیدواژه خالی است.' );
		}
		$num = min( 20, max( 10, (int) $depth ) );
		$url = self::ENDPOINT . '?' . http_build_query( self::query_params( $keyword, $num, $api_key, $hl, $gl, $opts ) );
		$res = DID_Http::get( $url, array( 'timeout' => 25, 'headers' => array( 'Accept' => 'application/json' ) ) );
		if ( ! $res['ok'] ) {
			return self::fail( $res['error'] ? $res['error'] : 'پاسخ SerpAPI نامعتبر بود.' );
		}
		$data = json_decode( $res['body'], true );
		if ( ! is_array( $data ) ) {
			return self::fail( 'JSON SerpAPI خوانده نشد.' );
		}
		if ( ! empty( $data['error'] ) ) {
			return self::fail( (string) $data['error'] );
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
		if ( isset( $data['organic_results'] ) && is_array( $data['organic_results'] ) ) {
			$items = $data['organic_results'];
		}
		$out = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['link'] ) ) {
				continue;
			}
			$pos = isset( $item['position'] ) ? (int) $item['position'] : ( count( $out ) + 1 );
			$out[] = array(
				'position' => $pos,
				'url'      => (string) $item['link'],
				'title'    => isset( $item['title'] ) ? (string) $item['title'] : '',
			);
		}
		return $out;
	}

	/**
	 * @param array $opts
	 * @return array
	 */
	public static function query_params( $keyword, $num, $api_key, $hl, $gl, $opts = array() ) {
		$q = array(
			'engine'  => 'google',
			'q'       => $keyword,
			'hl'      => $hl ? $hl : 'fa',
			'gl'      => $gl ? $gl : 'ir',
			'num'     => (int) $num,
			'api_key' => $api_key,
			'device'  => ! empty( $opts['device'] ) ? $opts['device'] : 'mobile',
		);
		if ( ! empty( $opts['location'] ) ) {
			$q['location'] = (string) $opts['location'];
		}
		return $q;
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

/**
 * گوگل از DataForSEO SERP Live.
 */
class DID_Provider_DataForSeo {

	const ENDPOINT = 'https://api.dataforseo.com/v3/serp/google/organic/live/regular';

	public static function search( $keyword, $depth, $login, $password, $opts = array() ) {
		$keyword  = trim( (string) $keyword );
		$login    = trim( (string) $login );
		$password = (string) $password;
		if ( '' === $login || '' === $password ) {
			return self::fail( 'ورود DataForSEO تنظیم نشده.' );
		}
		if ( '' === $keyword ) {
			return self::fail( 'کلیدواژه خالی است.' );
		}
		$depth = min( 50, max( 10, (int) $depth ) );
		$loc   = ! empty( $opts['location_name'] ) ? (string) $opts['location_name'] : 'Iran';
		$dev   = ! empty( $opts['device'] ) ? $opts['device'] : 'mobile';
		$body  = wp_json_encode(
			array(
				array(
					'keyword'       => $keyword,
					'location_name' => $loc,
					'language_code' => 'fa',
					'device'        => 'desktop' === $dev ? 'desktop' : 'mobile',
					'os'            => 'desktop' === $dev ? 'windows' : 'android',
					'depth'         => $depth,
				),
			)
		);

		$args = array(
			'timeout' => 40,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $login . ':' . $password ),
				'Content-Type'  => 'application/json',
			),
			'body'    => $body,
			'method'  => 'POST',
		);

		if ( is_callable( DID_Http::$transport ) ) {
			$res = call_user_func( DID_Http::$transport, self::ENDPOINT, $args );
			if ( is_array( $res ) && isset( $res['did_http'] ) ) {
				$http = $res;
			} else {
				$http = array(
					'ok'     => false,
					'body'   => '',
					'error'  => 'پاسخ DataForSEO نامعتبر بود.',
					'status' => 0,
				);
			}
		} else {
			$post = wp_remote_post( self::ENDPOINT, $args );
			if ( is_wp_error( $post ) ) {
				return self::fail( $post->get_error_message() );
			}
			$code = (int) wp_remote_retrieve_response_code( $post );
			$http = array(
				'ok'     => $code >= 200 && $code < 300,
				'body'   => (string) wp_remote_retrieve_body( $post ),
				'error'  => $code >= 200 && $code < 300 ? '' : 'کد پاسخ HTTP: ' . $code,
				'status' => $code,
			);
		}

		if ( empty( $http['ok'] ) ) {
			return self::fail( ! empty( $http['error'] ) ? $http['error'] : 'پاسخ DataForSEO نامعتبر بود.' );
		}
		$data = json_decode( $http['body'], true );
		if ( ! is_array( $data ) ) {
			return self::fail( 'JSON DataForSEO خوانده نشد.' );
		}
		if ( isset( $data['status_code'] ) && (int) $data['status_code'] !== 20000 ) {
			$msg = isset( $data['status_message'] ) ? (string) $data['status_message'] : 'خطای DataForSEO';
			return self::fail( $msg );
		}
		return array(
			'ok'      => true,
			'error'   => '',
			'results' => self::parse( $data ),
			'raw'     => $data,
		);
	}

	public static function parse( array $data ) {
		$items = array();
		if ( isset( $data['tasks'][0]['result'][0]['items'] ) && is_array( $data['tasks'][0]['result'][0]['items'] ) ) {
			$items = $data['tasks'][0]['result'][0]['items'];
		}
		$out = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$type = isset( $item['type'] ) ? $item['type'] : '';
			if ( $type && 'organic' !== $type ) {
				continue;
			}
			$url = isset( $item['url'] ) ? (string) $item['url'] : '';
			if ( '' === $url ) {
				continue;
			}
			$out[] = array(
				'position' => isset( $item['rank_group'] ) ? (int) $item['rank_group'] : ( count( $out ) + 1 ),
				'url'      => $url,
				'title'    => isset( $item['title'] ) ? (string) $item['title'] : '',
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

/**
 * Google Programmable Search — تقریبی، رتبهٔ واقعی ارگانیک نیست.
 */
class DID_Provider_Cse {

	const ENDPOINT = 'https://www.googleapis.com/customsearch/v1';

	public static function search( $keyword, $depth, $api_key, $cx, $hl = 'fa', $gl = 'ir' ) {
		$keyword = trim( (string) $keyword );
		$api_key = trim( (string) $api_key );
		$cx      = trim( (string) $cx );
		if ( '' === $api_key || '' === $cx ) {
			return self::fail( 'کلید یا شناسهٔ CSE تنظیم نشده.' );
		}
		if ( '' === $keyword ) {
			return self::fail( 'کلیدواژه خالی است.' );
		}
		$want    = min( 20, max( 10, (int) $depth ) );
		$results = array();
		$start   = 1;
		while ( count( $results ) < $want && $start <= 11 ) {
			$num = min( 10, $want - count( $results ) );
			$url = self::ENDPOINT . '?' . http_build_query(
				array(
					'key' => $api_key,
					'cx'  => $cx,
					'q'   => $keyword,
					'hl'  => $hl ? $hl : 'fa',
					'gl'  => $gl ? $gl : 'ir',
					'num' => $num,
					'start' => $start,
				)
			);
			$res = DID_Http::get( $url, array( 'timeout' => 20, 'headers' => array( 'Accept' => 'application/json' ) ) );
			if ( ! $res['ok'] ) {
				if ( $results ) {
					break;
				}
				return self::fail( $res['error'] ? $res['error'] : 'پاسخ CSE نامعتبر بود.' );
			}
			$data = json_decode( $res['body'], true );
			if ( ! is_array( $data ) ) {
				return self::fail( 'JSON CSE خوانده نشد.' );
			}
			if ( ! empty( $data['error']['message'] ) ) {
				return self::fail( (string) $data['error']['message'] );
			}
			$chunk = self::parse( $data, count( $results ) );
			if ( ! $chunk ) {
				break;
			}
			$results = array_merge( $results, $chunk );
			$start  += 10;
		}
		return array(
			'ok'      => true,
			'error'   => '',
			'results' => $results,
			'raw'     => null,
		);
	}

	public static function parse( array $data, $offset = 0 ) {
		$items = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();
		$out   = array();
		$i     = $offset;
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['link'] ) ) {
				continue;
			}
			$i++;
			$out[] = array(
				'position' => $i,
				'url'      => (string) $item['link'],
				'title'    => isset( $item['title'] ) ? (string) $item['title'] : '',
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
