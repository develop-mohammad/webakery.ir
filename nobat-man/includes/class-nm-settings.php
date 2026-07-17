<?php
defined( 'ABSPATH' ) || exit;

class NM_Settings {

	const OPTION = 'nm_settings';

	public static function defaults() {
		return array(
			'business_name'         => 'مشاوره آنلاین',
			'currency_label'        => 'تومان',
			'default_price'         => 500000,
			'default_duration'      => 60,
			'min_duration'          => 5,
			'max_duration'          => 300,
			'buffer_minutes'        => 10,
			'slot_step'             => 15,
			'closed_weekdays'       => array( 6 ),
			'block_holidays'        => 1,
			'custom_holidays'       => array(),
			'booking_from'          => '',
			'booking_until'         => '',
			'booking_months_ahead'  => 3,
			'active_months'         => array( 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12 ),
			'thank_you_text'        => "از رزرو شما سپاسگزاریم.\nنوبت شما در <strong>سایت مشاوره آنلاین</strong> ثبت شد.\nکد پیگیری: {booking_code}\nتاریخ: {jalali_date} ساعت {start_time}\n<a href=\"{site_url}\">بازگشت به سایت</a>",
			'thank_you_page'        => 0,
			'primary_color'         => '#6d28d9',
			'accent_color'          => '#06b6d4',
			'enable_voice'          => 1,
			'enable_photo'          => 1,
			'max_upload_mb'         => 8,
			'require_email'         => 1,
			'require_city'          => 1,
			'require_gender'        => 1,
			'pending_ttl_hours'     => 24,
			'wc_product_id'         => 0,
			'payment_gateway'       => 'auto',
			'zibal_merchant'        => '',
			'notify_email'          => 1,
			'notify_sms'            => 0,
			'sms_provider'          => 'ippanel',
			'sms_api_key'           => '',
			'sms_sender'            => '',
			'sms_pattern'           => '',
			'admin_email'           => '',
			'google_client_id'      => '',
			'google_client_secret'  => '',
			'google_refresh_token'  => '',
			'enable_installments'   => 0,
			'installment_count'     => 2,
			'appearance'            => array(
				'font'        => 'vazirmatn',
				'radius'      => '16',
				'card_shadow' => '1',
			),
		);
	}

	public static function all() {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	public static function get( $key, $default = null ) {
		$all = self::all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}
		return $default;
	}

	public static function update( array $data ) {
		$current = self::all();
		$merged  = array_merge( $current, $data );
		update_option( self::OPTION, $merged, false );
		return $merged;
	}

	/**
	 * اصلاح تنظیمات برعکس‌شده: بعضی کاربران روزهای کاری را به‌جای روزهای بسته تیک زده‌اند.
	 * الگو: ۵ روز یا بیشتر «بسته» و جمعه باز → یعنی شنبه تا پنج‌شنبه را کاری فرض کرده‌اند.
	 */
	public static function normalize_closed_weekdays( $closed ) {
		$closed = array_values( array_unique( array_map( 'intval', (array) $closed ) ) );
		$closed = array_values( array_filter( $closed, function ( $d ) {
			return $d >= 0 && $d <= 6;
		} ) );

		if ( count( $closed ) >= 5 && ! in_array( 6, $closed, true ) ) {
			$closed = array_values( array_diff( range( 0, 6 ), $closed ) );
		}
		if ( count( $closed ) >= 7 ) {
			$closed = array( 6 );
		}
		return $closed;
	}

	/** روزهای بسته هفتگی (پس از نرمال‌سازی) */
	public static function closed_weekdays() {
		return self::normalize_closed_weekdays( self::get( 'closed_weekdays', array( 6 ) ) );
	}

	/** روزهای کاری هفته */
	public static function working_weekdays() {
		return array_values( array_diff( range( 0, 6 ), self::closed_weekdays() ) );
	}

	/**
	 * اگر تنظیمات برعکس ذخیره شده، یک‌بار اصلاح و ذخیره کن.
	 * @return bool آیا اصلاح انجام شد؟
	 */
	public static function heal_inverted_weekdays() {
		$raw  = array_map( 'intval', (array) self::get( 'closed_weekdays', array( 6 ) ) );
		$fix = self::normalize_closed_weekdays( $raw );
		sort( $raw );
		$cmp = $fix;
		sort( $cmp );
		if ( $raw === $cmp ) {
			return false;
		}
		self::update( array( 'closed_weekdays' => $fix ) );
		return true;
	}

	public static function format_price( $amount ) {
		$amount = (int) $amount;
		$label  = self::get( 'currency_label', 'تومان' );
		return number_format_i18n( $amount ) . ' ' . $label;
	}

	/** تبدیل تاریخ میلادی Y-m-d به timestamp نیمه‌شب/انتهای روز در TZ سایت */
	public static function day_ts( $g_date, $end_of_day = false ) {
		$g_date = preg_replace( '/[^0-9\-]/', '', (string) $g_date );
		$time   = $end_of_day ? '23:59:59' : '00:00:00';
		try {
			$tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'Asia/Tehran' );
			$dt = new DateTimeImmutable( $g_date . ' ' . $time, $tz );
			return $dt->getTimestamp();
		} catch ( Exception $e ) {
			$ts = strtotime( $g_date . ' ' . $time );
			return $ts ? $ts : 0;
		}
	}

	/** بازه مجاز رزرو به timestamp (شروع/پایان روز سایت) */
	public static function booking_window() {
		$today    = NM_Jalali::today();
		$today_ts = self::day_ts( NM_Jalali::to_g_date( $today['y'], $today['m'], $today['d'] ) );
		$from     = trim( (string) self::get( 'booking_from', '' ) );
		$until    = trim( (string) self::get( 'booking_until', '' ) );
		$ahead    = max( 1, min( 24, (int) self::get( 'booking_months_ahead', 3 ) ) );
		$repaired = false;

		if ( $from ) {
			$p       = NM_Jalali::parse( $from );
			$from_ts = $p ? self::day_ts( NM_Jalali::to_g_date( $p['y'], $p['m'], $p['d'] ) ) : 0;
		} else {
			$from_ts = $today_ts;
		}

		if ( $until ) {
			$p        = NM_Jalali::parse( $until );
			$until_ts = $p ? self::day_ts( NM_Jalali::to_g_date( $p['y'], $p['m'], $p['d'] ), true ) : 0;
		} else {
			// ماه‌های جلو: تقریبی ۳۰ روز × تعداد
			$until_ts = max( $from_ts, $today_ts ) + ( $ahead * 30 * DAY_IN_SECONDS );
		}

		if ( $from_ts <= 0 ) {
			$from_ts = $today_ts;
		}

		// بازه منقضی (مثلاً تا تاریخ ۱۴۰۴): دیگر همه روزها را «خارج از بازه» نکن
		if ( $until_ts < $today_ts ) {
			$from_ts  = $today_ts;
			$until_ts = $today_ts + ( $ahead * 30 * DAY_IN_SECONDS );
			$repaired = true;
		}

		if ( $until_ts < $from_ts ) {
			$until_ts = $from_ts + ( $ahead * 30 * DAY_IN_SECONDS );
			$repaired = true;
		}

		return array(
			'from_ts'  => $from_ts,
			'until_ts' => $until_ts,
			'from'     => $from,
			'until'    => $until,
			'ahead'    => $ahead,
			'repaired' => $repaired,
		);
	}

	public static function is_month_active( $jm ) {
		$active = array_map( 'intval', (array) self::get( 'active_months', range( 1, 12 ) ) );
		if ( empty( $active ) ) {
			return true; // اگر خالی باشد همه فعال
		}
		return in_array( (int) $jm, $active, true );
	}
}
