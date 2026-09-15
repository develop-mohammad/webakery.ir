<?php
defined( 'ABSPATH' ) || exit;

/**
 * نرمال‌سازی شماره موبایل ایران به 09xxxxxxxxx.
 */
class NCK_Phone {

	public static function latin_digits( $raw ) {
		$map = array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
			'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
			'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		);
		return strtr( (string) $raw, $map );
	}

	/**
	 * @return string|null شماره نرمال یا null اگر نامعتبر باشد.
	 */
	public static function normalize( $raw ) {
		$raw = trim( (string) $raw );
		$raw = str_replace( array( ' ', '-', '_', '(', ')', '.' ), '', $raw );
		$raw = self::latin_digits( $raw );
		$raw = preg_replace( '/\D+/', '', $raw );

		if ( 0 === strpos( $raw, '0098' ) ) {
			$raw = '0' . substr( $raw, 4 );
		} elseif ( 0 === strpos( $raw, '98' ) && 12 === strlen( $raw ) ) {
			$raw = '0' . substr( $raw, 2 );
		} elseif ( 10 === strlen( $raw ) && '9' === $raw[0] ) {
			$raw = '0' . $raw;
		}

		if ( ! preg_match( '/^09\d{9}$/', $raw ) ) {
			return null;
		}
		return $raw;
	}
}
