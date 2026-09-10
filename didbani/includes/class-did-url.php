<?php
defined( 'ABSPATH' ) || exit;

/**
 * نرمال‌سازی دامنه و URL.
 */
class DID_Url {

	/**
	 * @param string $value دامنه یا URL
	 * @return string میزبان بدون www
	 */
	public static function host( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( ! preg_match( '#^https?://#i', $value ) ) {
			$value = 'https://' . $value;
		}
		$host = (string) parse_url( $value, PHP_URL_HOST );
		$host = strtolower( $host );
		if ( 0 === strpos( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}
		return $host;
	}

	/**
	 * @param string $url
	 * @return string
	 */
	public static function origin( $url ) {
		$parts = parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}
		$scheme = ! empty( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : 'https';
		if ( 'http' !== $scheme && 'https' !== $scheme ) {
			return '';
		}
		return $scheme . '://' . strtolower( $parts['host'] );
	}

	/**
	 * @param string $url
	 * @param string $base
	 * @return string
	 */
	public static function absolute( $url, $base ) {
		$url = trim( (string) $url );
		if ( '' === $url || 0 === strpos( $url, '#' ) || 0 === stripos( $url, 'javascript:' ) || 0 === stripos( $url, 'mailto:' ) || 0 === stripos( $url, 'tel:' ) ) {
			return '';
		}
		if ( preg_match( '#^https?://#i', $url ) ) {
			return self::canonical( $url );
		}
		if ( 0 === strpos( $url, '//' ) ) {
			$scheme = (string) parse_url( $base, PHP_URL_SCHEME );
			if ( '' === $scheme ) {
				$scheme = 'https';
			}
			return self::canonical( $scheme . ':' . $url );
		}
		$origin = self::origin( $base );
		if ( '' === $origin ) {
			return '';
		}
		if ( 0 === strpos( $url, '/' ) ) {
			return self::canonical( $origin . $url );
		}
		$path = (string) parse_url( $base, PHP_URL_PATH );
		$dir  = preg_replace( '#/[^/]*$#', '/', $path );
		if ( ! is_string( $dir ) || '' === $dir ) {
			$dir = '/';
		}
		return self::canonical( $origin . $dir . $url );
	}

	/**
	 * @param string $url
	 * @return string
	 */
	public static function canonical( $url ) {
		$parts = parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}
		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : 'https';
		if ( 'http' !== $scheme && 'https' !== $scheme ) {
			return '';
		}
		$host = strtolower( $parts['host'] );
		$path = isset( $parts['path'] ) ? $parts['path'] : '/';
		$path = preg_replace( '#/\./#', '/', $path );
		$path = preg_replace( '#/+#', '/', (string) $path );
		if ( '' === $path ) {
			$path = '/';
		}
		$query = isset( $parts['query'] ) && '' !== $parts['query'] ? '?' . $parts['query'] : '';
		return $scheme . '://' . $host . $path . $query;
	}

	/**
	 * آیا میزبان نتیجه متعلق به دامنهٔ رصدشده است؟
	 *
	 * @param string $result_host
	 * @param string $tracked_host
	 * @return bool
	 */
	public static function host_matches( $result_host, $tracked_host ) {
		$a = self::host( $result_host );
		$b = self::host( $tracked_host );
		if ( '' === $a || '' === $b ) {
			return false;
		}
		if ( $a === $b ) {
			return true;
		}
		$suffix = '.' . $b;
		$len    = strlen( $suffix );
		return strlen( $a ) > $len && substr( $a, -$len ) === $suffix;
	}

	/**
	 * @param string $url
	 * @return bool
	 */
	public static function is_htmlish( $url ) {
		$path = strtolower( (string) parse_url( $url, PHP_URL_PATH ) );
		if ( '' === $path || '/' === $path ) {
			return true;
		}
		$skip = array( '.jpg', '.jpeg', '.png', '.gif', '.webp', '.svg', '.pdf', '.zip', '.rar', '.7z', '.mp4', '.mp3', '.css', '.js', '.ico', '.woff', '.woff2', '.ttf', '.eot', '.xml', '.json' );
		foreach ( $skip as $ext ) {
			if ( substr( $path, -strlen( $ext ) ) === $ext ) {
				return false;
			}
		}
		return true;
	}
}
