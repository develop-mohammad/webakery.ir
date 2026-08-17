<?php
defined( 'ABSPATH' ) || exit;

/**
 * نمایش تاریخ شمسی — مستقل و سبک (فقط تبدیل میلادی → شمسی).
 */
class WBCC_Date {

	public static function month_names() {
		return array( 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' );
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

	/** تاریخ شمسی از تایم‌استمپ در منطقهٔ زمانی سایت — مثال: 1404/06/12 */
	public static function format( $timestamp ) {
		$timestamp = (int) $timestamp;
		if ( $timestamp <= 0 ) {
			return '—';
		}
		$y = (int) wp_date( 'Y', $timestamp );
		$m = (int) wp_date( 'n', $timestamp );
		$d = (int) wp_date( 'j', $timestamp );
		list( $jy, $jm, $jd ) = self::to_jalali( $y, $m, $d );
		return sprintf( '%04d/%02d/%02d', $jy, $jm, $jd );
	}

	/** مثال: ۱۲ شهریور ۱۴۰۴ */
	public static function format_long( $timestamp ) {
		$timestamp = (int) $timestamp;
		if ( $timestamp <= 0 ) {
			return '—';
		}
		$y = (int) wp_date( 'Y', $timestamp );
		$m = (int) wp_date( 'n', $timestamp );
		$d = (int) wp_date( 'j', $timestamp );
		list( $jy, $jm, $jd ) = self::to_jalali( $y, $m, $d );
		$names = self::month_names();
		return self::fa_digits( $jd . ' ' . $names[ $jm - 1 ] . ' ' . $jy );
	}

	public static function fa_digits( $value ) {
		$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		return str_replace( $en, $fa, (string) $value );
	}
}
