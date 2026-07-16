<?php
defined( 'ABSPATH' ) || exit;

/**
 * توابع تبدیل تقویم شمسی/میلادی — مستقل از سایر افزونه‌ها.
 */
class WAP_Jalali {

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
        $days_in_month = array( 0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
        for ( $i = 1; $gd > $days_in_month[ $i ]; $i++ ) { $gd -= $days_in_month[ $i ]; }
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

    // تاریخ شمسی دقیق «امروز» — بر اساس ساعت واقعی سرور وردپرس (منطقه زمانی سایت)
    public static function today() {
        $now = current_time( 'timestamp' );
        list( $jy, $jm, $jd ) = self::to_jalali( (int) date( 'Y', $now ), (int) date( 'n', $now ), (int) date( 'j', $now ) );
        return array( 'y' => $jy, 'm' => $jm, 'd' => $jd );
    }

    // تبدیل رشته تاریخ (شمسی ۱۴۰۳/۰۱/۰۱ یا میلادی) به timestamp
    public static function str_to_timestamp( $date_str, $end_of_day = false ) {
        $date_str = trim( $date_str );
        if ( empty( $date_str ) ) return 0;
        $normalized = strtr( $date_str, array(
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ) );
        if ( preg_match( '/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $normalized, $m ) && (int) $m[1] > 1200 ) {
            $greg = self::to_gregorian( $m[1], $m[2], $m[3] );
            $h = $end_of_day ? 23 : 0; $i = $end_of_day ? 59 : 0; $s = $end_of_day ? 59 : 0;
            return mktime( $h, $i, $s, $greg[1], $greg[2], $greg[0] );
        }
        $ts = strtotime( $date_str );
        if ( $end_of_day && $ts ) {
            $ts = mktime( 23, 59, 59, (int) date( 'n', $ts ), (int) date( 'j', $ts ), (int) date( 'Y', $ts ) );
        }
        return $ts ?: 0;
    }

    public static function period_key( $jy, $jm, $jd, $period ) {
        $season_names = array( 'بهار', 'تابستان', 'پاییز', 'زمستان' );
        $month_names  = array( 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' );
        switch ( $period ) {
            case 'day':
                return array( sprintf( '%04d%02d%02d', $jy, $jm, $jd ), sprintf( '%d/%02d/%02d', $jy, $jm, $jd ) );
            case 'week':
                $month_days = array( 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 30 );
                $doy = $jd;
                for ( $i = 0; $i < $jm - 1; $i++ ) { $doy += $month_days[ $i ]; }
                $week_no = (int) ceil( $doy / 7 );
                return array( sprintf( '%04d-W%02d', $jy, $week_no ), 'هفته ' . $week_no . ' سال ' . $jy );
            case 'quarter':
                $q = intdiv( $jm - 1, 3 );
                return array( sprintf( '%04d-Q%d', $jy, $q + 1 ), $season_names[ $q ] . ' ' . $jy );
            case 'year':
                return array( (string) $jy, 'سال ' . $jy );
            case 'month':
            default:
                return array( sprintf( '%04d%02d', $jy, $jm ), $month_names[ $jm - 1 ] . ' ' . $jy );
        }
    }
}
