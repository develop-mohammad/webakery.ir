<?php
defined( 'ABSPATH' ) || exit;

/**
 * ترند گوگل برای ایران از فید رسمی RSS — نه عدد ساختگی و نه API غیررسمی Explore.
 */
class WBGS_Trends {

	const RSS      = 'https://trends.google.com/trending/rss';
	const CACHE    = 'wbgs_trends_';
	const TTL      = 1800;
	const NS       = 'https://trends.google.com/trending/rss';

	/**
	 * @param string $gl
	 * @return string
	 */
	public static function geo( $gl = 'IR' ) {
		$gl = strtoupper( preg_replace( '/[^a-zA-Z]/', '', (string) $gl ) );
		return $gl !== '' ? $gl : 'IR';
	}

	/**
	 * @param string $gl
	 * @return string
	 */
	public static function geo_label( $gl = 'IR' ) {
		return 'IR' === self::geo( $gl ) ? 'ایران' : self::geo( $gl );
	}

	/**
	 * @param string $gl
	 * @return string
	 */
	public static function rss_url( $gl = 'IR' ) {
		return self::RSS . '?geo=' . rawurlencode( self::geo( $gl ) );
	}

	/**
	 * @param string $keyword
	 * @param string $gl
	 * @param string $hl
	 * @return string
	 */
	public static function explore_url( $keyword, $gl = 'IR', $hl = 'fa' ) {
		$keyword = WBGS_Suggest::normalize_seed( $keyword );
		$hl      = preg_replace( '/[^a-zA-Z\-]/', '', (string) $hl );
		if ( $hl === '' ) {
			$hl = 'fa';
		}
		$args = array(
			'geo' => self::geo( $gl ),
			'hl'  => $hl,
		);
		if ( $keyword !== '' ) {
			$args['q'] = $keyword;
		}
		return 'https://trends.google.com/trends/explore?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * صفحهٔ ترندهای زندهٔ کشور.
	 *
	 * @param string $gl
	 * @param string $hl
	 * @return string
	 */
	public static function trending_url( $gl = 'IR', $hl = 'fa' ) {
		$hl = preg_replace( '/[^a-zA-Z\-]/', '', (string) $hl );
		if ( $hl === '' ) {
			$hl = 'fa';
		}
		return 'https://trends.google.com/trending?' . http_build_query(
			array(
				'geo' => self::geo( $gl ),
				'hl'  => $hl,
			),
			'',
			'&',
			PHP_QUERY_RFC3986
		);
	}

	/**
	 * @param string $xml
	 * @return array<int,array<string,mixed>>
	 */
	public static function parse_rss( $xml ) {
		$xml = is_string( $xml ) ? $xml : '';
		if ( $xml === '' || ! function_exists( 'simplexml_load_string' ) ) {
			return array();
		}
		$prev = libxml_use_internal_errors( true );
		$feed = simplexml_load_string( $xml );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		if ( ! $feed || ! isset( $feed->channel ) ) {
			return array();
		}

		$out = array();
		$seen = array();
		foreach ( $feed->channel->item as $item ) {
			$title = WBGS_Suggest::normalize_seed( (string) $item->title );
			if ( $title === '' || isset( $seen[ $title ] ) ) {
				continue;
			}
			$seen[ $title ] = true;
			$ht             = $item->children( self::NS );
			$traffic        = isset( $ht->approx_traffic ) ? trim( (string) $ht->approx_traffic ) : '';
			$picture        = isset( $ht->picture ) ? trim( (string) $ht->picture ) : '';
			$news           = array();
			if ( isset( $ht->news_item ) ) {
				foreach ( $ht->news_item as $row ) {
					$n = $row->children( self::NS );
					$nt = isset( $n->news_item_title ) ? html_entity_decode( trim( (string) $n->news_item_title ), ENT_QUOTES, 'UTF-8' ) : '';
					$nu = isset( $n->news_item_url ) ? trim( (string) $n->news_item_url ) : '';
					if ( $nt === '' && $nu === '' ) {
						continue;
					}
					$news[] = array(
						'title'  => $nt,
						'url'    => $nu,
						'source' => isset( $n->news_item_source ) ? trim( (string) $n->news_item_source ) : '',
					);
					if ( count( $news ) >= 3 ) {
						break;
					}
				}
			}
			$out[] = array(
				'title'     => $title,
				'traffic'   => $traffic,
				'published' => isset( $item->pubDate ) ? trim( (string) $item->pubDate ) : '',
				'picture'   => $picture,
				'news'      => $news,
			);
		}
		return $out;
	}

	/**
	 * @param string $gl
	 * @return array{ok:bool,error:string,items:array,status:int,cached:bool}
	 */
	public static function fetch_daily( $gl = 'IR' ) {
		$gl    = self::geo( $gl );
		$key   = self::CACHE . strtolower( $gl );
		$cached = self::cache_get( $key );
		if ( is_array( $cached ) && isset( $cached['items'] ) ) {
			return array(
				'ok'     => true,
				'error'  => '',
				'items'  => $cached['items'],
				'status' => 200,
				'cached' => true,
			);
		}

		$url  = self::rss_url( $gl );
		$args = array(
			'timeout'     => 12,
			'redirection' => 2,
			'headers'     => array(
				'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
				'Accept'          => 'application/rss+xml,application/xml,text/xml,*/*;q=0.8',
				'Accept-Language' => 'fa-IR,fa;q=0.9,en;q=0.8',
			),
		);

		if ( function_exists( 'wp_remote_get' ) ) {
			$response = wp_remote_get( $url, $args );
			if ( is_wp_error( $response ) ) {
				return array(
					'ok'     => false,
					'error'  => $response->get_error_message(),
					'items'  => array(),
					'status' => 0,
					'cached' => false,
				);
			}
			$status = (int) wp_remote_retrieve_response_code( $response );
			$body   = (string) wp_remote_retrieve_body( $response );
		} else {
			$ctx    = stream_context_create(
				array(
					'http' => array(
						'timeout' => 12,
						'header'  => "User-Agent: Mozilla/5.0\r\nAccept-Language: fa-IR,fa;q=0.9\r\n",
					),
				)
			);
			$body   = @file_get_contents( $url, false, $ctx );
			$status = is_string( $body ) ? 200 : 0;
			$body   = is_string( $body ) ? $body : '';
		}

		if ( $status < 200 || $status >= 300 || $body === '' ) {
			return array(
				'ok'     => false,
				'error'  => $status ? ( 'http_' . $status ) : 'network',
				'items'  => array(),
				'status' => $status,
				'cached' => false,
			);
		}

		$items = self::parse_rss( $body );
		if ( ! $items ) {
			return array(
				'ok'     => false,
				'error'  => 'empty',
				'items'  => array(),
				'status' => $status,
				'cached' => false,
			);
		}

		self::cache_set( $key, array( 'items' => $items ) );
		return array(
			'ok'     => true,
			'error'  => '',
			'items'  => $items,
			'status' => $status,
			'cached' => false,
		);
	}

	/**
	 * @param string $text
	 * @param array  $trend
	 * @return bool
	 */
	public static function phrase_matches( $text, $trend ) {
		$text  = WBGS_Suggest::normalize_seed( $text );
		$title = isset( $trend['title'] ) ? WBGS_Suggest::normalize_seed( $trend['title'] ) : '';
		if ( $text === '' || $title === '' ) {
			return false;
		}
		if ( mb_strtolower( $text ) === mb_strtolower( $title ) ) {
			return true;
		}
		if ( mb_strlen( $title ) >= 2 && false !== mb_stripos( $text, $title ) ) {
			return true;
		}
		if ( mb_strlen( $text ) >= 3 && false !== mb_stripos( $title, $text ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param string               $text
	 * @param array<int,array>     $trends
	 * @return array<string,mixed>|null
	 */
	public static function match_one( $text, $trends ) {
		foreach ( (array) $trends as $trend ) {
			if ( self::phrase_matches( $text, $trend ) ) {
				return $trend;
			}
		}
		return null;
	}

	/**
	 * @param string $gl
	 * @param string $hl
	 * @param string $seed
	 * @return array<string,mixed>
	 */
	public static function payload( $gl = 'IR', $hl = 'fa', $seed = '' ) {
		$gl   = self::geo( $gl );
		$got  = self::fetch_daily( $gl );
		$seed = WBGS_Suggest::normalize_seed( $seed );
		$items = array();
		foreach ( $got['items'] as $row ) {
			$row['explore'] = self::explore_url( $row['title'], $gl, $hl );
			$items[]        = $row;
		}
		$hit = $seed !== '' ? self::match_one( $seed, $items ) : null;
		return array(
			'ok'         => $got['ok'],
			'error'      => $got['error'],
			'geo'        => $gl,
			'geo_fa'     => self::geo_label( $gl ),
			'cached'     => $got['cached'],
			'items'      => $items,
			'seed'       => $seed,
			'seed_hit'   => $hit,
			'explore'    => self::explore_url( $seed, $gl, $hl ),
			'trending'   => self::trending_url( $gl, $hl ),
			'note'       => 'ترند روزانهٔ گوگل برای ' . self::geo_label( $gl ) . '. عدد تقریبی ترند است، حجم ماهانه نیست.',
		);
	}

	/**
	 * @param string $key
	 * @return array|null
	 */
	private static function cache_get( $key ) {
		if ( function_exists( 'get_transient' ) ) {
			$hit = get_transient( $key );
			return is_array( $hit ) ? $hit : null;
		}
		return isset( $GLOBALS['wbgs_test_transients'][ $key ] ) && is_array( $GLOBALS['wbgs_test_transients'][ $key ] )
			? $GLOBALS['wbgs_test_transients'][ $key ]
			: null;
	}

	/**
	 * @param string $key
	 * @param array  $value
	 */
	private static function cache_set( $key, $value ) {
		if ( function_exists( 'set_transient' ) ) {
			set_transient( $key, $value, self::TTL );
			return;
		}
		$GLOBALS['wbgs_test_transients'][ $key ] = $value;
	}
}
