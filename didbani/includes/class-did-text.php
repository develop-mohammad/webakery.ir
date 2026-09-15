<?php
defined( 'ABSPATH' ) || exit;

/**
 * نرمال‌سازی متن و کلیدواژه (ارقام فارسی/عربی، ی/ک، فاصله).
 */
class DID_Text {

	/**
	 * @param mixed $value
	 * @return string
	 */
	public static function normalize( $value ) {
		$s = (string) $value;
		$s = self::digits_to_latin( $s );
		$s = str_replace( array( 'ي', 'ك', 'ة', '‌' ), array( 'ی', 'ک', 'ه', ' ' ), $s );
		$s = function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
		$s = preg_replace( '/\s+/u', ' ', $s );
		return trim( (string) $s );
	}

	/**
	 * @param string $s
	 * @return string
	 */
	public static function digits_to_latin( $s ) {
		$map = array(
			'۰' => '0',
			'۱' => '1',
			'۲' => '2',
			'۳' => '3',
			'۴' => '4',
			'۵' => '5',
			'۶' => '6',
			'۷' => '7',
			'۸' => '8',
			'۹' => '9',
			'٠' => '0',
			'١' => '1',
			'٢' => '2',
			'٣' => '3',
			'٤' => '4',
			'٥' => '5',
			'٦' => '6',
			'٧' => '7',
			'٨' => '8',
			'٩' => '9',
		);
		return strtr( $s, $map );
	}

	/**
	 * @param string $haystack
	 * @param string $needle
	 * @return bool
	 */
	public static function contains( $haystack, $needle ) {
		$h = self::normalize( $haystack );
		$n = self::normalize( $needle );
		if ( '' === $n || '' === $h ) {
			return false;
		}
		if ( function_exists( 'mb_strpos' ) ) {
			return false !== mb_strpos( $h, $n, 0, 'UTF-8' );
		}
		return false !== strpos( $h, $n );
	}

	/**
	 * @param string $haystack
	 * @param string $needle
	 * @return int
	 */
	public static function count( $haystack, $needle ) {
		$h = self::normalize( $haystack );
		$n = self::normalize( $needle );
		if ( '' === $n || '' === $h ) {
			return 0;
		}
		return substr_count( $h, $n );
	}

	/**
	 * @param string $keyword
	 * @param string $title
	 * @param string $h1
	 * @param string $body
	 * @return array{in_title:int,in_h1:int,in_body:int,count:int}
	 */
	public static function keyword_hits( $keyword, $title, $h1, $body ) {
		$count = self::count( $title, $keyword ) + self::count( $h1, $keyword ) + self::count( $body, $keyword );
		return array(
			'in_title' => self::contains( $title, $keyword ) ? 1 : 0,
			'in_h1'    => self::contains( $h1, $keyword ) ? 1 : 0,
			'in_body'  => self::contains( $body, $keyword ) ? 1 : 0,
			'count'    => $count,
		);
	}

	/**
	 * @param string $raw
	 * @return string[]
	 */
	public static function lines( $raw ) {
		$raw = str_replace( array( "\r\n", "\r" ), "\n", (string) $raw );
		$out = array();
		foreach ( explode( "\n", $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$out[] = $line;
		}
		return $out;
	}
}
