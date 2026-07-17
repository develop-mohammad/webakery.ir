<?php
defined( 'ABSPATH' ) || exit;

/**
 * تبدیل تقویم شمسی/میلادی — کاملاً مستقل.
 * weekday شمسی: شنبه=0 … جمعه=6
 */
class NM_Jalali {

	public static function month_names() {
		return array( 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' );
	}

	public static function weekday_names() {
		return array( 'شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه' );
	}

	/** تاریخ/زمان فعلی در منطقهٔ زمانی وردپرس */
	public static function now() {
		if ( function_exists( 'wp_timezone' ) ) {
			return new DateTimeImmutable( 'now', wp_timezone() );
		}
		$tz_string = function_exists( 'get_option' ) ? (string) get_option( 'timezone_string', '' ) : '';
		if ( $tz_string ) {
			try {
				return new DateTimeImmutable( 'now', new DateTimeZone( $tz_string ) );
			} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
			}
		}
		// تهران به‌عنوان پیش‌فرض معقول برای سایت‌های ایرانی
		try {
			return new DateTimeImmutable( 'now', new DateTimeZone( 'Asia/Tehran' ) );
		} catch ( Exception $e ) {
			return new DateTimeImmutable( 'now' );
		}
	}

	public static function to_gregorian( $jy, $jm, $jd ) {
		$jy  = (int) $jy; $jm = (int) $jm; $jd = (int) $jd;
		$jy += 1595;
		$days = -355668 + ( 365 * $jy ) + ( (int) floor( $jy / 33 ) * 8 ) + (int) floor( ( ( $jy % 33 ) + 3 ) / 4 ) + $jd
				+ ( $jm < 7 ? ( $jm - 1 ) * 31 : ( ( $jm - 7 ) * 30 ) + 186 );
		$gy   = 400 * (int) floor( $days / 146097 );
		$days = $days % 146097;
		if ( $days > 36524 ) {
			$gy  += 100 * (int) floor( --$days / 36524 );
			$days = $days % 36524;
			if ( $days >= 365 ) { $days++; }
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
		for ( $i = 1; $gd > $dim[ $i ]; $i++ ) { $gd -= $dim[ $i ]; }
		return array( $gy, $i, $gd );
	}

	public static function to_jalali( $gy, $gm, $gd ) {
		$gy = (int) $gy; $gm = (int) $gm; $gd = (int) $gd;
		$g_y_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$jy  = ( $gy <= 1600 ) ? 0 : 979;
		$gy -= ( $gy <= 1600 ) ? 621 : 1600;
		$gy2 = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days = ( 365 * $gy ) + (int) ( ( $gy2 + 3 ) / 4 ) - (int) ( ( $gy2 + 99 ) / 100 )
			  + (int) ( ( $gy2 + 399 ) / 400 ) - 80 + $gd + $g_y_m[ $gm - 1 ];
		$jy  += 33 * (int) ( $days / 12053 );
		$days = $days % 12053;
		$jy  += 4 * (int) ( $days / 1461 );
		$days = $days % 1461;
		if ( $days > 365 ) {
			$jy  += (int) ( ( $days - 1 ) / 365 );
			$days = ( $days - 1 ) % 365;
		}
		$jm_days = array( 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29 );
		for ( $i = 0; $i < 11 && $days >= $jm_days[ $i ]; $i++ ) { $days -= $jm_days[ $i ]; }
		return array( $jy, $i + 1, $days + 1 );
	}

	public static function month_length( $jy, $jm ) {
		if ( $jm <= 6 ) return 31;
		if ( $jm <= 11 ) return 30;
		$g1   = self::to_gregorian( $jy, 12, 30 );
		$back = self::to_jalali( $g1[0], $g1[1], $g1[2] );
		return ( $back[1] === 12 && $back[2] === 30 ) ? 30 : 29;
	}

	public static function today() {
		$dt = self::now();
		list( $jy, $jm, $jd ) = self::to_jalali(
			(int) $dt->format( 'Y' ),
			(int) $dt->format( 'n' ),
			(int) $dt->format( 'j' )
		);
		return array( 'y' => $jy, 'm' => $jm, 'd' => $jd );
	}

	/** شنبه=0 … جمعه=6 */
	public static function weekday( $jy, $jm, $jd ) {
		$g = self::to_gregorian( $jy, $jm, $jd );
		try {
			$tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
			$dt = new DateTimeImmutable(
				sprintf( '%04d-%02d-%02d 12:00:00', $g[0], $g[1], $g[2] ),
				$tz
			);
			// PHP: 0=یکشنبه … 6=شنبه → شمسی: شنبه=0
			return ( (int) $dt->format( 'w' ) + 1 ) % 7;
		} catch ( Exception $e ) {
			$ts = gmmktime( 12, 0, 0, $g[1], $g[2], $g[0] );
			return ( (int) gmdate( 'w', $ts ) + 1 ) % 7;
		}
	}

	public static function format( $jy, $jm, $jd ) {
		return sprintf( '%04d/%02d/%02d', (int) $jy, (int) $jm, (int) $jd );
	}

	public static function parse( $str ) {
		$str = trim( (string) $str );
		$str = strtr( $str, array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
			'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		) );
		if ( ! preg_match( '/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $str, $m ) ) {
			return null;
		}
		return array( 'y' => (int) $m[1], 'm' => (int) $m[2], 'd' => (int) $m[3] );
	}

	public static function to_g_date( $jy, $jm, $jd ) {
		$g = self::to_gregorian( $jy, $jm, $jd );
		return sprintf( '%04d-%02d-%02d', $g[0], $g[1], $g[2] );
	}

	public static function from_g_date( $g_date ) {
		if ( ! preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})/', $g_date, $m ) ) {
			return null;
		}
		list( $jy, $jm, $jd ) = self::to_jalali( (int) $m[1], (int) $m[2], (int) $m[3] );
		return array( 'y' => $jy, 'm' => $jm, 'd' => $jd );
	}

	public static function month_grid( $jy, $jm ) {
		$len   = self::month_length( $jy, $jm );
		$start = self::weekday( $jy, $jm, 1 );
		$days  = array();
		for ( $i = 0; $i < $start; $i++ ) {
			$days[] = null;
		}
		for ( $d = 1; $d <= $len; $d++ ) {
			$days[] = array(
				'd'       => $d,
				'jalali'  => self::format( $jy, $jm, $d ),
				'g_date'  => self::to_g_date( $jy, $jm, $d ),
				'weekday' => self::weekday( $jy, $jm, $d ),
			);
		}
		return $days;
	}
}
