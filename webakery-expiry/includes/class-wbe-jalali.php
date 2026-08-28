<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * تبدیل تقویم شمسی/میلادی — سبک و مستقل از سایر افزونه‌ها.
 */
class WBE_Jalali {

	public static function fa_to_en( $value ) {
		$map = array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
			'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
			'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		);
		return strtr( (string) $value, $map );
	}

	public static function en_to_fa( $value ) {
		$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		return str_replace( $en, $fa, (string) $value );
	}

	public static function to_gregorian( $jy, $jm, $jd ) {
		$jy  = (int) $jy;
		$jm  = (int) $jm;
		$jd  = (int) $jd;
		$jy += 1595;
		$days = -355668 + ( 365 * $jy ) + ( (int) floor( $jy / 33 ) * 8 ) + (int) floor( ( ( $jy % 33 ) + 3 ) / 4 ) + $jd
			+ ( $jm < 7 ? ( $jm - 1 ) * 31 : ( ( $jm - 7 ) * 30 ) + 186 );
		$gy   = 400 * (int) floor( $days / 146097 );
		$days = $days % 146097;
		if ( $days > 36524 ) {
			$gy  += 100 * (int) floor( --$days / 36524 );
			$days = $days % 36524;
			if ( $days >= 365 ) {
				$days++;
			}
		}
		$gy  += 4 * (int) floor( $days / 1461 );
		$days = $days % 1461;
		if ( $days > 364 ) {
			$gy  += (int) floor( ( $days - 1 ) / 365 );
			$days = ( $days - 1 ) % 365;
		}
		$gd   = $days + 1;
		$leap = ( $gy % 4 === 0 && ( $gy % 100 !== 0 || $gy % 400 === 0 ) );
		$dim  = array( 0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
		for ( $i = 1; $gd > $dim[ $i ]; $i++ ) {
			$gd -= $dim[ $i ];
		}
		return array( $gy, $i, $gd );
	}

	public static function to_jalali( $gy, $gm, $gd ) {
		$gy    = (int) $gy;
		$gm    = (int) $gm;
		$gd    = (int) $gd;
		$g_y_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$jy    = ( $gy <= 1600 ) ? 0 : 979;
		$gy   -= ( $gy <= 1600 ) ? 621 : 1600;
		$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days  = ( 365 * $gy ) + (int) ( ( $gy2 + 3 ) / 4 ) - (int) ( ( $gy2 + 99 ) / 100 )
			+ (int) ( ( $gy2 + 399 ) / 400 ) - 80 + $gd + $g_y_m[ $gm - 1 ];
		$jy   += 33 * (int) ( $days / 12053 );
		$days  = $days % 12053;
		$jy   += 4 * (int) ( $days / 1461 );
		$days  = $days % 1461;
		if ( $days > 365 ) {
			$jy  += (int) ( ( $days - 1 ) / 365 );
			$days = ( $days - 1 ) % 365;
		}
		$jm_days = array( 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29 );
		for ( $i = 0; $i < 11 && $days >= $jm_days[ $i ]; $i++ ) {
			$days -= $jm_days[ $i ];
		}
		return array( $jy, $i + 1, $days + 1 );
	}

	public static function today_ymd() {
		if ( function_exists( 'current_time' ) ) {
			return current_time( 'Y-m-d' );
		}
		return gmdate( 'Y-m-d' );
	}

	/**
	 * ورودی آزاد (شمسی یا میلادی، ارقام فارسی/عربی) → Y-m-d میلادی.
	 *
	 * @param string $raw
	 * @param string $calendar jalali|gregorian
	 * @return string
	 */
	public static function parse_to_ymd( $raw, $calendar = 'gregorian' ) {
		$raw = trim( self::fa_to_en( $raw ) );
		if ( $raw === '' ) {
			return '';
		}
		$raw = str_replace( array( '.', ' ', '\\' ), '/', $raw );
		$raw = str_replace( '-', '/', $raw );
		if ( ! preg_match( '/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $raw, $m ) ) {
			return '';
		}
		$y = (int) $m[1];
		$mo = (int) $m[2];
		$d = (int) $m[3];
		if ( $mo < 1 || $mo > 12 || $d < 1 || $d > 31 ) {
			return '';
		}
		$use_jalali = ( $y > 1200 && $y < 1700 );
		if ( ! $use_jalali && $y < 1700 ) {
			$use_jalali = ( 'jalali' === $calendar );
		}
		if ( $use_jalali ) {
			$g = self::to_gregorian( $y, $mo, $d );
			$y  = $g[0];
			$mo = $g[1];
			$d  = $g[2];
		}
		if ( ! checkdate( $mo, $d, $y ) ) {
			return '';
		}
		return sprintf( '%04d-%02d-%02d', $y, $mo, $d );
	}

	/**
	 * @param string $ymd Y-m-d میلادی
	 * @param string $calendar jalali|gregorian
	 * @param bool   $fa_digits
	 * @return string
	 */
	public static function format_ymd( $ymd, $calendar = 'jalali', $fa_digits = true ) {
		$ymd = trim( (string) $ymd );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m ) ) {
			return '';
		}
		$y  = (int) $m[1];
		$mo = (int) $m[2];
		$d  = (int) $m[3];
		if ( 'jalali' === $calendar ) {
			$j   = self::to_jalali( $y, $mo, $d );
			$out = sprintf( '%04d/%02d/%02d', $j[0], $j[1], $j[2] );
		} else {
			$out = sprintf( '%04d/%02d/%02d', $y, $mo, $d );
		}
		return $fa_digits ? self::en_to_fa( $out ) : $out;
	}

	/**
	 * WC_DateTime / timestamp / رشته → Y-m-d در منطقه زمانی سایت.
	 *
	 * @param mixed $value
	 * @return string
	 */
	public static function datetime_to_ymd( $value ) {
		if ( empty( $value ) && ! is_numeric( $value ) ) {
			return '';
		}
		if ( is_object( $value ) && method_exists( $value, 'date' ) ) {
			$ymd = $value->date( 'Y-m-d' );
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $ymd ) ? (string) $ymd : '';
		}
		if ( is_numeric( $value ) ) {
			$ts = (int) $value;
			if ( $ts <= 0 ) {
				return '';
			}
			if ( function_exists( 'wp_date' ) ) {
				return wp_date( 'Y-m-d', $ts );
			}
			return gmdate( 'Y-m-d', $ts );
		}
		$raw = trim( (string) $value );
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})/', $raw, $m ) ) {
			return $m[1];
		}
		return '';
	}

	public static function number( $value ) {
		$value = self::fa_to_en( $value );
		$value = str_replace( array( ',', '٬', '،', ' ' ), '', $value );
		if ( $value === '' || ! is_numeric( $value ) ) {
			return 0.0;
		}
		return (float) $value;
	}
}
