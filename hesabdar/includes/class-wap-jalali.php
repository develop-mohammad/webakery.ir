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

    /** ارقام فارسی/عربی → لاتین و trim. */
    public static function normalize_digits( $str ): string {
        $str = trim( (string) $str );
        return strtr( $str, array(
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ) );
    }

    // تبدیل رشته تاریخ (شمسی ۱۴۰۳/۰۱/۰۱ یا میلادی) به timestamp
    public static function str_to_timestamp( $date_str, $end_of_day = false ) {
        $date_str = self::normalize_digits( $date_str );
        if ( $date_str === '' ) return 0;
        if ( preg_match( '/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $date_str, $m ) && (int) $m[1] > 1200 ) {
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

    /** نام ماه‌های شمسی (۱..۱۲). */
    public static function month_names(): array {
        return array( 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' );
    }

    public static function format( $jy, $jm, $jd ): string {
        return sprintf( '%d/%02d/%02d', (int) $jy, (int) $jm, (int) $jd );
    }

    /** پارس تاریخ شمسی YYYY/MM/DD — null اگر نامعتبر. */
    public static function parse( $date_str ): ?array {
        $normalized = self::normalize_digits( $date_str );
        if ( $normalized === '' ) {
            return null;
        }
        if ( ! preg_match( '/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $normalized, $m ) ) {
            return null;
        }
        $y = (int) $m[1];
        $mo = (int) $m[2];
        $d = (int) $m[3];
        if ( $y < 1200 || $mo < 1 || $mo > 12 || $d < 1 || $d > self::month_length( $y, $mo ) ) {
            return null;
        }
        return array( 'y' => $y, 'm' => $mo, 'd' => $d );
    }

    /** ابتدا و انتهای یک ماه شمسی. */
    public static function month_bounds( int $jy, int $jm ): array {
        return array(
            'from' => self::format( $jy, $jm, 1 ),
            'to'   => self::format( $jy, $jm, self::month_length( $jy, $jm ) ),
        );
    }

    /**
     * نرمال‌سازی بازه: اگر از>تا جابه‌جا می‌شود؛ پیام‌های قابل‌نمایش برمی‌گرداند.
     *
     * @return array{from:string,to:string,swapped:bool,invalid:bool,messages:string[]}
     */
    public static function normalize_range( string $from, string $to ): array {
        $out = array(
            'from'     => $from,
            'to'       => $to,
            'swapped'  => false,
            'invalid'  => false,
            'messages' => array(),
        );
        if ( $from === '' && $to === '' ) {
            return $out;
        }
        $pf = $from !== '' ? self::parse( $from ) : null;
        $pt = $to !== '' ? self::parse( $to ) : null;
        if ( $from !== '' && ! $pf ) {
            $out['invalid']    = true;
            $out['messages'][] = 'تاریخ شروع نامعتبر است. قالب درست: ۱۴۰۴/۰۱/۰۱';
        }
        if ( $to !== '' && ! $pt ) {
            $out['invalid']    = true;
            $out['messages'][] = 'تاریخ پایان نامعتبر است. قالب درست: ۱۴۰۴/۰۱/۳۱';
        }
        if ( $out['invalid'] ) {
            return $out;
        }
        if ( $pf && $pt ) {
            $ts_f = self::str_to_timestamp( $from, false );
            $ts_t = self::str_to_timestamp( $to, true );
            if ( $ts_f && $ts_t && $ts_f > $ts_t ) {
                $out['from']       = $to;
                $out['to']         = $from;
                $out['swapped']    = true;
                $out['messages'][] = 'بازه تاریخ برعکس بود (پایان قبل از شروع). خودکار اصلاح شد: «' . $out['from'] . '» تا «' . $out['to'] . '».';
            }
        }
        return $out;
    }

    /** شیفت سال روی یک تاریخ شمسی (مثلاً ماه مشابه پارسال). */
    public static function shift_year( string $date_str, int $years ): string {
        $p = self::parse( $date_str );
        if ( ! $p ) {
            return '';
        }
        $y = $p['y'] + $years;
        $d = min( $p['d'], self::month_length( $y, $p['m'] ) );
        return self::format( $y, $p['m'], $d );
    }

    /** شیفت ماه شمسی (مثلاً ماه قبل). روز به طول ماه مقصد محدود می‌شود. */
    public static function shift_month( string $date_str, int $months ): string {
        $p = self::parse( $date_str );
        if ( ! $p ) {
            return '';
        }
        $y = $p['y'];
        $m = $p['m'] + $months;
        while ( $m < 1 ) {
            $m += 12;
            $y--;
        }
        while ( $m > 12 ) {
            $m -= 12;
            $y++;
        }
        $d = min( $p['d'], self::month_length( $y, $m ) );
        return self::format( $y, $m, $d );
    }

    /**
     * بازهٔ ماه قبل نسبت به بازهٔ اصلی.
     * اگر بازهٔ اصلی یک ماه کامل باشد، کل ماه قبل برمی‌گردد؛ وگرنه هر دو سر بازه یک ماه جابه‌جا می‌شوند.
     *
     * @return array{from:string,to:string}|null
     */
    public static function previous_month_range( string $from, string $to ): ?array {
        $pf = self::parse( $from );
        $pt = self::parse( $to );
        if ( ! $pf || ! $pt ) {
            return null;
        }
        $full_month = ( $pf['y'] === $pt['y'] && $pf['m'] === $pt['m']
            && $pf['d'] === 1 && $pt['d'] === self::month_length( $pt['y'], $pt['m'] ) );
        if ( $full_month ) {
            $y = $pf['y'];
            $m = $pf['m'] - 1;
            if ( $m < 1 ) {
                $m = 12;
                $y--;
            }
            return self::month_bounds( $y, $m );
        }
        $prev_from = self::shift_month( $from, -1 );
        $prev_to   = self::shift_month( $to, -1 );
        if ( $prev_from === '' || $prev_to === '' ) {
            return null;
        }
        return array( 'from' => $prev_from, 'to' => $prev_to );
    }

    /**
     * لیست ماه‌های اخیر برای انتخاب سریع.
     *
     * @return array<int,array{label:string,from:string,to:string,y:int,m:int}>
     */
    public static function recent_months( int $count = 14 ): array {
        $today = self::today();
        $y     = $today['y'];
        $m     = $today['m'];
        $names = self::month_names();
        $out   = array();
        for ( $i = 0; $i < $count; $i++ ) {
            $bounds = self::month_bounds( $y, $m );
            $out[]  = array(
                'label' => $names[ $m - 1 ] . ' ' . $y,
                'from'  => $bounds['from'],
                'to'    => $bounds['to'],
                'y'     => $y,
                'm'     => $m,
            );
            $m--;
            if ( $m < 1 ) {
                $m = 12;
                $y--;
            }
        }
        return $out;
    }
}
