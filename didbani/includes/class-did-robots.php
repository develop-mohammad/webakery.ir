<?php
defined( 'ABSPATH' ) || exit;

/**
 * پارس robots.txt ساده (User-agent: *) و استخراج Sitemap.
 */
class DID_Robots {

	/**
	 * @param string $txt
	 * @return array{disallow:string[],allow:string[],sitemaps:string[]}
	 */
	public static function parse( $txt ) {
		$out = array(
			'disallow' => array(),
			'allow'    => array(),
			'sitemaps' => array(),
		);
		$lines     = preg_split( '/\R/u', (string) $txt );
		$in_star   = true;
		$seen_ua   = false;
		if ( ! is_array( $lines ) ) {
			return $out;
		}
		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line || 0 === strpos( $line, '#' ) ) {
				continue;
			}
			if ( stripos( $line, 'sitemap:' ) === 0 ) {
				$url = trim( substr( $line, 8 ) );
				if ( $url ) {
					$out['sitemaps'][] = $url;
				}
				continue;
			}
			if ( stripos( $line, 'user-agent:' ) === 0 ) {
				$ua      = strtolower( trim( substr( $line, 11 ) ) );
				$seen_ua = true;
				$in_star = ( '*' === $ua || false !== strpos( $ua, 'didbani' ) );
				continue;
			}
			if ( ! $in_star ) {
				continue;
			}
			if ( stripos( $line, 'disallow:' ) === 0 ) {
				$path = trim( substr( $line, 9 ) );
				if ( '' !== $path ) {
					$out['disallow'][] = $path;
				}
				continue;
			}
			if ( stripos( $line, 'allow:' ) === 0 ) {
				$path = trim( substr( $line, 6 ) );
				if ( '' !== $path ) {
					$out['allow'][] = $path;
				}
			}
		}
		unset( $seen_ua );
		return $out;
	}

	/**
	 * @param string $url
	 * @param array  $rules
	 * @return bool
	 */
	public static function allowed( $url, array $rules ) {
		$path = (string) parse_url( $url, PHP_URL_PATH );
		if ( '' === $path ) {
			$path = '/';
		}
		$query = (string) parse_url( $url, PHP_URL_QUERY );
		$full  = $path . ( '' !== $query ? '?' . $query : '' );

		$allow_len    = 0;
		$disallow_len = 0;
		foreach ( (array) ( $rules['allow'] ?? array() ) as $rule ) {
			if ( self::path_match( $full, $rule ) ) {
				$allow_len = max( $allow_len, strlen( $rule ) );
			}
		}
		foreach ( (array) ( $rules['disallow'] ?? array() ) as $rule ) {
			if ( self::path_match( $full, $rule ) ) {
				$disallow_len = max( $disallow_len, strlen( $rule ) );
			}
		}
		if ( $disallow_len > 0 && $disallow_len >= $allow_len ) {
			return false;
		}
		return true;
	}

	/**
	 * @param string $path
	 * @param string $rule
	 * @return bool
	 */
	private static function path_match( $path, $rule ) {
		$rule = (string) $rule;
		if ( '' === $rule ) {
			return false;
		}
		$rule = str_replace( '*', '', $rule );
		return 0 === strpos( $path, $rule );
	}

	/**
	 * استخراج <loc> از sitemap یا sitemapindex.
	 *
	 * @param string $xml
	 * @return string[]
	 */
	public static function sitemap_locs( $xml ) {
		$xml = (string) $xml;
		if ( '' === trim( $xml ) ) {
			return array();
		}
		$previous = libxml_use_internal_errors( true );
		$sx       = simplexml_load_string( $xml );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( false === $sx ) {
			preg_match_all( '#<loc>\s*([^<]+)\s*</loc>#i', $xml, $m );
			$locs = isset( $m[1] ) ? $m[1] : array();
			return array_values( array_filter( array_map( 'trim', $locs ) ) );
		}
		$sx->registerXPathNamespace( 'sm', 'http://www.sitemaps.org/schemas/sitemap/0.9' );
		$nodes = $sx->xpath( '//sm:loc' );
		if ( ! $nodes ) {
			$nodes = $sx->xpath( '//loc' );
		}
		$out = array();
		if ( is_array( $nodes ) ) {
			foreach ( $nodes as $n ) {
				$v = trim( (string) $n );
				if ( '' !== $v ) {
					$out[] = $v;
				}
			}
		}
		return $out;
	}
}
