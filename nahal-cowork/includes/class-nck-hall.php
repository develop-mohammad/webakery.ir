<?php
defined( 'ABSPATH' ) || exit;

/**
 * قرارداد اجاره سالن — موسسه فرهنگی آموزشی نهال.
 */
class NCK_Hall {

	public static function title_label( $title ) {
		if ( 'ms' === $title ) {
			return 'سرکارخانم';
		}
		if ( 'mr' === $title ) {
			return 'آقای';
		}
		return 'آقای/سرکارخانم';
	}

	public static function default_org() {
		return 'موسسه فرهنگی آموزشی نهال';
	}

	public static function default_signer() {
		return 'هادی فروغی';
	}

	public static function chairs_min() {
		return 20;
	}

	public static function chairs_max() {
		return 25;
	}

	public static function slot_minutes() {
		return 90;
	}

	/**
	 * سه فضای قابل اجاره.
	 *
	 * @return array<string,array{id:string,name:string,price_key:string,default_price:int,desc:string}>
	 */
	public static function space_defs() {
		return array(
			'library'  => array(
				'id'            => 'library',
				'name'          => 'کتابخانه',
				'price_key'     => 'hall_space_library',
				'default_price' => 1500000,
				'desc'          => 'فضای مطالعه و جلسات آرام',
			),
			'cafe'     => array(
				'id'            => 'cafe',
				'name'          => 'کافی‌شاپ',
				'price_key'     => 'hall_space_cafe',
				'default_price' => 2500000,
				'desc'          => 'فضای پذیرایی و دورهمی',
			),
			'woodshop' => array(
				'id'            => 'woodshop',
				'name'          => 'کارگاه نجاری',
				'price_key'     => 'hall_space_woodshop',
				'default_price' => 1500000,
				'desc'          => 'فضای کار عملی و کارگاه',
			),
		);
	}

	/**
	 * @return array<string,array{id:string,name:string,price:int,desc:string}>
	 */
	public static function spaces() {
		$s   = class_exists( 'NCK_Settings' ) ? NCK_Settings::all() : array();
		$out = array();
		foreach ( self::space_defs() as $id => $def ) {
			$price = isset( $s[ $def['price_key'] ] ) ? (int) $s[ $def['price_key'] ] : (int) $def['default_price'];
			$out[ $id ] = array(
				'id'    => $id,
				'name'  => $def['name'],
				'price' => max( 0, $price ),
				'desc'  => $def['desc'],
			);
		}
		return $out;
	}

	public static function space_names() {
		$names = array();
		foreach ( self::space_defs() as $def ) {
			$names[] = $def['name'];
		}
		return $names;
	}

	public static function space_of( $raw ) {
		$raw = trim( (string) $raw );
		if ( $raw === '' ) {
			return null;
		}
		$spaces = self::spaces();
		if ( isset( $spaces[ $raw ] ) ) {
			return $spaces[ $raw ];
		}
		$key = self::hall_key( $raw );
		foreach ( $spaces as $sp ) {
			if ( self::hall_key( $sp['name'] ) === $key ) {
				return $sp;
			}
		}
		return null;
	}

	public static function default_halls() {
		return self::space_names();
	}

	public static function parse_halls( $raw ) {
		$out = array();
		$raw = str_replace( array( "\r\n", "\r" ), "\n", (string) $raw );
		foreach ( explode( "\n", $raw ) as $line ) {
			$line = trim( $line );
			if ( $line !== '' ) {
				$out[] = $line;
			}
		}
		return $out ? array_values( array_unique( $out ) ) : self::default_halls();
	}

	public static function normalize_nid( $raw ) {
		$raw = NCK_Phone::latin_digits( trim( (string) $raw ) );
		$raw = preg_replace( '/\D+/', '', $raw );
		if ( ! preg_match( '/^\d{10}$/', $raw ) ) {
			return null;
		}
		if ( preg_match( '/^(\d)\1{9}$/', $raw ) ) {
			return null;
		}
		$sum = 0;
		for ( $i = 0; $i < 9; $i++ ) {
			$sum += (int) $raw[ $i ] * ( 10 - $i );
		}
		$r = $sum % 11;
		$c = (int) $raw[9];
		$ok = ( $r < 2 && $c === $r ) || ( $r >= 2 && $c === ( 11 - $r ) );
		return $ok ? $raw : null;
	}

	public static function nid_check_digit( $nine ) {
		$nine = preg_replace( '/\D+/', '', NCK_Phone::latin_digits( (string) $nine ) );
		if ( 9 !== strlen( $nine ) ) {
			return null;
		}
		$sum = 0;
		for ( $i = 0; $i < 9; $i++ ) {
			$sum += (int) $nine[ $i ] * ( 10 - $i );
		}
		$r = $sum % 11;
		return $r < 2 ? $r : ( 11 - $r );
	}

	public static function parse_amount( $raw ) {
		$raw = NCK_Phone::latin_digits( (string) $raw );
		$raw = str_replace( array( ',', '،', '٬', ' ', 'تومان', 'ریال' ), '', $raw );
		$raw = preg_replace( '/\D+/', '', $raw );
		if ( $raw === '' ) {
			return 0;
		}
		return (int) $raw;
	}

	public static function format_money( $amount ) {
		$n = max( 0, (int) $amount );
		$en = number_format( $n, 0, '', ',' );
		return NCK_Jalali::fa_digits( $en ) . ' تومان';
	}

	public static function projector_note( $price = 500000, $minutes = 90 ) {
		$minutes = max( 1, (int) $minutes );
		return 'توضیح: هزینه استفاده از ویدئو پروژکتور به ازای ' . NCK_Jalali::fa_digits( $minutes ) . ' دقیقه، ' . self::format_money( $price ) . ' می‌باشد.';
	}

	/**
	 * بازه‌های مجاز اجاره سالن: ۹ تا ۱۳ و ۱۶ تا ۲۲.
	 *
	 * @return array<int,array{id:string,start:int,end:int}>
	 */
	public static function time_windows() {
		return array(
			array(
				'id'    => 'morning',
				'start' => 9 * 60,
				'end'   => 13 * 60,
			),
			array(
				'id'    => 'evening',
				'start' => 16 * 60,
				'end'   => 22 * 60,
			),
		);
	}

	public static function format_hour( $minutes ) {
		$minutes = (int) $minutes;
		$h       = (int) floor( $minutes / 60 );
		$i       = $minutes % 60;
		return sprintf( '%02d:%02d', $h, $i );
	}

	/**
	 * @return string[]
	 */
	public static function time_slots( $step = 30 ) {
		$step = max( 1, (int) $step );
		$out  = array();
		foreach ( self::time_windows() as $w ) {
			for ( $m = (int) $w['start']; $m <= (int) $w['end']; $m += $step ) {
				$out[] = self::format_hour( $m );
			}
		}
		return $out;
	}

	public static function window_of( $minutes ) {
		$minutes = (int) $minutes;
		foreach ( self::time_windows() as $w ) {
			if ( $minutes >= (int) $w['start'] && $minutes <= (int) $w['end'] ) {
				return $w['id'];
			}
		}
		return '';
	}

	public static function is_slot( $minutes, $step = 30 ) {
		$minutes = (int) $minutes;
		$step    = max( 1, (int) $step );
		if ( $minutes % $step !== 0 ) {
			return false;
		}
		return self::window_of( $minutes ) !== '';
	}

	/**
	 * نوبت‌های ۹۰ دقیقه‌ای داخل بازه‌های مجاز، با گام ۳۰ دقیقه.
	 *
	 * @return array<int,array{start:string,end:string,morning:bool,window:string}>
	 */
	public static function bookable_slots( $step = 30 ) {
		$step = max( 1, (int) $step );
		$len  = self::slot_minutes();
		$out  = array();
		foreach ( self::time_windows() as $w ) {
			$start = (int) $w['start'];
			$end   = (int) $w['end'];
			for ( $m = $start; ( $m + $len ) <= $end; $m += $step ) {
				$out[] = array(
					'start'   => self::format_hour( $m ),
					'end'     => self::format_hour( $m + $len ),
					'morning' => ( 'morning' === $w['id'] ),
					'window'  => $w['id'],
				);
			}
		}
		return $out;
	}

	public static function is_bookable_slot( $start_raw, $end_raw ) {
		$s = NCK_Shifts::parse_hhmm( $start_raw );
		$e = NCK_Shifts::parse_hhmm( $end_raw );
		if ( null === $s || null === $e ) {
			return false;
		}
		$want_s = self::format_hour( $s );
		$want_e = self::format_hour( $e );
		foreach ( self::bookable_slots() as $slot ) {
			if ( $slot['start'] === $want_s && $slot['end'] === $want_e ) {
				return true;
			}
		}
		return false;
	}

	public static function is_morning_slot( $start_raw, $end_raw = '' ) {
		$start = NCK_Shifts::parse_hhmm( $start_raw );
		if ( null === $start ) {
			return false;
		}
		if ( 'morning' !== self::window_of( $start ) ) {
			return false;
		}
		if ( $end_raw === '' ) {
			return true;
		}
		$end = NCK_Shifts::parse_hhmm( $end_raw );
		if ( null === $end ) {
			return false;
		}
		return 'morning' === self::window_of( $end );
	}

	/**
	 * بازه داخل ساعات مجاز سالن (بدون محدودیت مدت) — برای تقویم رزروهای قبلی.
	 *
	 * @return array{ok:bool,message?:string,start?:string,end?:string}
	 */
	public static function check_window_hours( $start_raw, $end_raw ) {
		$start = NCK_Shifts::parse_hhmm( $start_raw );
		$end   = NCK_Shifts::parse_hhmm( $end_raw );
		if ( null === $start || null === $end ) {
			return array( 'ok' => false, 'message' => 'ساعت شروع و پایان را انتخاب کنید.' );
		}
		if ( ! self::is_slot( $start ) || ! self::is_slot( $end ) ) {
			return array( 'ok' => false, 'message' => 'ساعت اجاره فقط از ۹ تا ۱۳ یا از ۱۶ تا ۲۲ مجاز است.' );
		}
		$sw = self::window_of( $start );
		$ew = self::window_of( $end );
		if ( $sw === '' || $ew === '' || $sw !== $ew ) {
			return array( 'ok' => false, 'message' => 'شروع و پایان باید در یک بازه باشد؛ بین ۱۳ تا ۱۶ سالن اجاره داده نمی‌شود.' );
		}
		if ( $end <= $start ) {
			return array( 'ok' => false, 'message' => 'ساعت پایان باید بعد از ساعت شروع باشد.' );
		}
		return array(
			'ok'    => true,
			'start' => self::format_hour( $start ),
			'end'   => self::format_hour( $end ),
		);
	}

	/**
	 * رزرو جدید: نوبت دقیق ۹۰ دقیقه‌ای داخل ساعات مجاز.
	 *
	 * @return array{ok:bool,message?:string,start?:string,end?:string}
	 */
	public static function check_hours( $start_raw, $end_raw ) {
		$hours = self::check_window_hours( $start_raw, $end_raw );
		if ( empty( $hours['ok'] ) ) {
			return $hours;
		}
		if ( ! self::is_bookable_slot( $hours['start'], $hours['end'] ) ) {
			return array( 'ok' => false, 'message' => 'هر رزرو سالن ۹۰ دقیقه است. یک نوبت ۹۰ دقیقه‌ای انتخاب کنید.' );
		}
		return $hours;
	}

	/**
	 * مبلغ اجاره: صبح ۵۰٪ تخفیف فضا؛ پروژکتور جدا و بدون تخفیف.
	 *
	 * @return array{ok:bool,message?:string,space_id?:string,hall_name?:string,start?:string,end?:string,morning?:int,rent?:int,projector?:int,total?:int}
	 */
	public static function quote( $space_key, $start, $end, $projector = false ) {
		$space = self::space_of( $space_key );
		if ( ! $space ) {
			return array( 'ok' => false, 'message' => 'یکی از فضاهای کتابخانه، کافی‌شاپ یا کارگاه نجاری را انتخاب کنید.' );
		}
		$hours = self::check_hours( $start, $end );
		if ( empty( $hours['ok'] ) ) {
			return $hours;
		}
		$morning = self::is_morning_slot( $hours['start'], $hours['end'] );
		$rent    = (int) $space['price'];
		if ( $morning ) {
			$rent = (int) round( $rent / 2 );
		}
		$proj = 0;
		if ( $projector ) {
			$proj = 500000;
			if ( class_exists( 'NCK_Settings' ) ) {
				$proj = max( 0, (int) NCK_Settings::get( 'projector_price', 500000 ) );
			}
		}
		return array(
			'ok'        => true,
			'space_id'  => $space['id'],
			'hall_name' => $space['name'],
			'start'     => $hours['start'],
			'end'       => $hours['end'],
			'morning'   => $morning ? 1 : 0,
			'rent'      => $rent,
			'projector' => $proj,
			'total'     => $rent + $proj,
		);
	}

	public static function hall_key( $name ) {
		$name = trim( (string) $name );
		$name = preg_replace( '/\s+/u', ' ', $name );
		return $name;
	}

	/**
	 * بازه نیمه‌باز: ۱۶:۰۰–۲۰:۰۰ با ۲۰:۰۰–۲۲:۰۰ تداخل ندارد.
	 */
	public static function ranges_overlap( $a_start, $a_end, $b_start, $b_end ) {
		$a0 = NCK_Shifts::parse_hhmm( $a_start );
		$a1 = NCK_Shifts::parse_hhmm( $a_end );
		$b0 = NCK_Shifts::parse_hhmm( $b_start );
		$b1 = NCK_Shifts::parse_hhmm( $b_end );
		if ( null === $a0 || null === $a1 || null === $b0 || null === $b1 ) {
			return false;
		}
		return $a0 < $b1 && $b0 < $a1;
	}

	/**
	 * رزروهای امضاشدهٔ یک ماه، گروه‌بندی‌شده با کلید تاریخ شمسی.
	 *
	 * @param array<int,array> $payloads
	 * @return array<string,array<int,array{start:string,end:string,hall:string}>>
	 */
	public static function group_month_payloads( array $payloads, $jy, $jm, $hall = '' ) {
		$jy   = (int) $jy;
		$jm   = (int) $jm;
		$want = self::hall_key( $hall );
		$days = array();
		foreach ( $payloads as $p ) {
			if ( ! is_array( $p ) ) {
				continue;
			}
			$date = NCK_Jalali::parse( isset( $p['event_date'] ) ? $p['event_date'] : '' );
			if ( ! $date || (int) $date['y'] !== $jy || (int) $date['m'] !== $jm ) {
				continue;
			}
			$room = self::hall_key( isset( $p['hall_name'] ) ? $p['hall_name'] : '' );
			if ( $want !== '' && $room !== $want ) {
				continue;
			}
			$hours = self::check_window_hours(
				isset( $p['start_hour'] ) ? $p['start_hour'] : '',
				isset( $p['end_hour'] ) ? $p['end_hour'] : ''
			);
			if ( empty( $hours['ok'] ) ) {
				continue;
			}
			$key = NCK_Jalali::format( $date['y'], $date['m'], $date['d'] );
			if ( ! isset( $days[ $key ] ) ) {
				$days[ $key ] = array();
			}
			$days[ $key ][] = array(
				'start' => $hours['start'],
				'end'   => $hours['end'],
				'hall'  => $room,
			);
		}
		return $days;
	}

	/**
	 * @return array<string,array<int,array{start:string,end:string,hall:string}>>
	 */
	public static function month_bookings( $jy, $jm, $hall = '' ) {
		return self::group_month_payloads( self::month_payloads( $jy, $jm ), $jy, $jm, $hall );
	}

	/**
	 * @return array<int,array>
	 */
	public static function month_payloads( $jy, $jm ) {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) ) {
			return array();
		}
		$jy    = (int) $jy;
		$jm    = (int) $jm;
		if ( $jy < 1390 || $jy > 1500 || $jm < 1 || $jm > 12 ) {
			return array();
		}
		$table = ( isset( $wpdb->prefix ) ? $wpdb->prefix : '' ) . 'nck_contracts';
		$plain = sprintf( '%04d/%02d', $jy, $jm );
		$esc   = str_replace( '/', '\\/', $plain );
		$like1 = '%' . $wpdb->esc_like( $plain ) . '%';
		$like2 = '%' . $wpdb->esc_like( $esc ) . '%';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT payload FROM {$table} WHERE kind = %s AND status = %s AND (payload LIKE %s OR payload LIKE %s)",
				'hall',
				'signed',
				$like1,
				$like2
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $row ) {
			$raw = isset( $row['payload'] ) ? $row['payload'] : '';
			$p   = json_decode( (string) $raw, true );
			if ( is_array( $p ) ) {
				$out[] = $p;
			}
		}
		return $out;
	}

	/**
	 * @return array<int,array{start:string,end:string,hall:string}>
	 */
	public static function day_bookings( $date, $hall = '' ) {
		$parsed = NCK_Jalali::parse( $date );
		if ( ! $parsed ) {
			return array();
		}
		$key  = NCK_Jalali::format( $parsed['y'], $parsed['m'], $parsed['d'] );
		$days = self::month_bookings( $parsed['y'], $parsed['m'], $hall );
		return isset( $days[ $key ] ) ? $days[ $key ] : array();
	}

	/**
	 * @return array{ok:bool,message?:string,busy?:array}
	 */
	public static function clash( $date, $start, $end, $hall ) {
		$busy = self::day_bookings( $date, $hall );
		foreach ( $busy as $row ) {
			if ( self::ranges_overlap( $start, $end, $row['start'], $row['end'] ) ) {
				$from = NCK_Jalali::fa_digits( $row['start'] );
				$to   = NCK_Jalali::fa_digits( $row['end'] );
				return array(
					'ok'      => false,
					'busy'    => $row,
					'message' => 'این بازه در «' . $hall . '» پر است (' . $from . ' تا ' . $to . '). ساعت دیگری انتخاب کنید.',
				);
			}
		}
		return array( 'ok' => true );
	}

	/**
	 * @return array{ok:bool,message:string,payload?:array}
	 */
	public static function validate( array $in ) {
		$name = trim( isset( $in['name'] ) ? (string) $in['name'] : '' );
		if ( $name === '' || ( function_exists( 'mb_strlen' ) ? mb_strlen( $name ) : strlen( $name ) ) < 3 ) {
			return array( 'ok' => false, 'message' => 'نام برگزارکننده را کامل وارد کنید.' );
		}

		$nid = self::normalize_nid( isset( $in['national_id'] ) ? $in['national_id'] : '' );
		if ( ! $nid ) {
			return array( 'ok' => false, 'message' => 'شماره ملی معتبر نیست. باید ۱۰ رقم و مطابق الگوریتم ملی باشد.' );
		}

		$phone = NCK_Phone::normalize( isset( $in['phone'] ) ? $in['phone'] : '' );
		if ( ! $phone ) {
			return array( 'ok' => false, 'message' => 'شماره موبایل معتبر نیست. مثال: ۰۹۱۲۳۴۵۶۷۸۹' );
		}

		$space_raw = isset( $in['space'] ) ? (string) $in['space'] : '';
		if ( $space_raw === '' && isset( $in['hall_name'] ) ) {
			$space_raw = (string) $in['hall_name'];
		}
		if ( function_exists( 'sanitize_text_field' ) ) {
			$name      = sanitize_text_field( $name );
			$space_raw = sanitize_text_field( $space_raw );
		}
		$space = self::space_of( $space_raw );
		if ( ! $space ) {
			return array( 'ok' => false, 'message' => 'یکی از فضاهای کتابخانه، کافی‌شاپ یا کارگاه نجاری را انتخاب کنید.' );
		}
		$hall = $space['name'];

		$date = NCK_Jalali::parse( isset( $in['event_date'] ) ? $in['event_date'] : '' );
		if ( ! $date ) {
			return array( 'ok' => false, 'message' => 'تاریخ برگزاری را به صورت ۱۴۰۴/۰۶/۲۰ وارد کنید.' );
		}

		$hours = self::check_hours(
			isset( $in['start_hour'] ) ? $in['start_hour'] : '',
			isset( $in['end_hour'] ) ? $in['end_hour'] : ''
		);
		if ( empty( $hours['ok'] ) ) {
			return $hours;
		}

		$chairs = isset( $in['chairs'] ) ? (int) NCK_Phone::latin_digits( $in['chairs'] ) : 0;
		if ( $chairs < self::chairs_min() || $chairs > self::chairs_max() ) {
			return array( 'ok' => false, 'message' => 'تعداد صندلی باید بین ۲۰ تا ۲۵ باشد.' );
		}

		$want_proj = ! empty( $in['projector'] );
		$quote     = self::quote( $space['id'], $hours['start'], $hours['end'], $want_proj );
		if ( empty( $quote['ok'] ) ) {
			return $quote;
		}
		$amount = (int) $quote['total'];

		$honorific = isset( $in['honorific'] ) ? (string) $in['honorific'] : 'mr';
		$honorific = in_array( $honorific, array( 'mr', 'ms' ), true ) ? $honorific : 'mr';

		$start_s = $hours['start'];
		$end_s   = $hours['end'];
		$date_s  = NCK_Jalali::format( $date['y'], $date['m'], $date['d'] );

		$clash = self::clash( $date_s, $start_s, $end_s, $hall );
		if ( empty( $clash['ok'] ) ) {
			return $clash;
		}

		$pay = class_exists( 'NCK_Pay' ) ? NCK_Pay::parse_front_payment( $in, $amount ) : array( 'ok' => true, 'payload' => array() );
		if ( empty( $pay['ok'] ) ) {
			return $pay;
		}
		$pay_p = isset( $pay['payload'] ) && is_array( $pay['payload'] ) ? $pay['payload'] : array();
		$pay_p['pay_amount'] = $amount;

		return array(
			'ok'      => true,
			'message' => '',
			'payload' => array_merge(
				array(
					'kind'              => 'hall',
					'honorific'         => $honorific,
					'name'              => $name,
					'national_id'       => $nid,
					'phone'             => $phone,
					'space_id'          => $space['id'],
					'hall_name'         => $hall,
					'amount'            => $amount,
					'rent'              => (int) $quote['rent'],
					'projector_amount'  => (int) $quote['projector'],
					'morning'           => ! empty( $quote['morning'] ) ? 1 : 0,
					'event_date'        => $date_s,
					'start_hour'        => $start_s,
					'end_hour'          => $end_s,
					'chairs'            => $chairs,
					'projector'         => $want_proj ? 1 : 0,
				),
				$pay_p
			),
		);
	}

	public static function clauses() {
		return array(
			array(
				'num'   => '۱',
				'title' => 'موضوع قرارداد',
				'body'  => 'این قرارداد جهت اجاره سالن {{hall}} با رعایت مقررات داخلی اتاق به مبلغ {{amount}} منعقد گردید.',
			),
			array(
				'num'   => '۲',
				'title' => 'مدت قرارداد',
				'body'  => 'مدت قرارداد از تاریخ {{date}} ساعت {{start}} الی ساعت {{end}} می‌باشد.',
			),
			array(
				'num'   => '۴',
				'title' => 'سایر موارد',
				'items' => array(
					'۴-۱ امکانات سالن به تعداد {{chairs}} عدد صندلی باشد.',
					'۴-۲ تهیه مواد مصرفی، پذیرایی به عهده برگزار کننده مراسم می‌باشد که در صورت نیاز با هماهنگی قبلی توسط کافه شاپ مجموعه قابل پیش‌بینی است. بدیهی است خدمات فوق بصورت جداگانه محاسبه و دریافت می‌گردد.',
					'۴-۳ پس از اخذ مبلغ ورودی تا ۳ روز قبل برگزاری، چنانچه برنامه کنسل گردد، ۱۳ درصد به عنوان خسارت و مالیات کسر خواهد شد، لذا کمتر از ۳ روز مانده به برگزاری مراسم، برنامه کنسل شود به هیچ عنوان وجهی مسترد نخواهد شد.',
					'۴-۴ برآورد و اعلام خسارات احتمالی و هرگونه ضرر و زیان وارده به سالن و امکانات آن به عهده طرف اول قرارداد بوده و طرف دوم تعهد می‌نماید در صورت ایجاد خسارت، ضرر و زیان آن را طبق برآورد طرف اول پرداخت نماید.',
					'۴-۵ اخذ کلیه مجوزهای قانونی جهت برگزاری مراسم (اماکن و …) بر عهده طرف دوم می‌باشد و در صورت لغو یا تعطیلی به هر دلیل طرف اول هیچ مسئولیتی در قبال استرداد وجه نخواهد داشت.',
					'۴-۶ برگزار کننده (طرف دوم) بایستی کلیه شئونات را طبق قوانین جمهوری اسلامی در حین برگزاری مراسم حفظ و از هرگونه بحث‌های سیاسی و مذهبی اجتناب نماید.',
					'۴-۷ امضاء کننده ذیل این قرارداد برگزار کننده مراسم بوده و حق واگذاری سالن را به شخص یا گروه و نهادی و اداره دیگری ندارد.',
					'۴-۸ توصیه می‌شود برگزارکننده مراسم قبل از عقد قرارداد حتماً از سالن و جزئیات مورد نظر بازدید بعمل آورده و پیش‌بینی‌های لازم را در کلیه موارد بنماید.',
				),
			),
		);
	}

	public static function fill_vars( array $payload, array $s = array() ) {
		$blank = NCK_Contract::blank( 12 );
		$org   = isset( $s['hall_org'] ) && $s['hall_org'] !== '' ? $s['hall_org'] : self::default_org();
		$date  = isset( $payload['event_date'] ) ? NCK_Jalali::parse( $payload['event_date'] ) : null;
		$date_s = $date ? NCK_Jalali::fa_digits( NCK_Jalali::format( $date['y'], $date['m'], $date['d'] ) ) : '…. / …. / ….';

		return array(
			'org'    => $org,
			'title'  => self::title_label( isset( $payload['honorific'] ) ? $payload['honorific'] : '' ),
			'name'   => ! empty( $payload['name'] ) ? $payload['name'] : $blank,
			'nid'    => ! empty( $payload['national_id'] ) ? NCK_Jalali::fa_digits( $payload['national_id'] ) : $blank,
			'phone'  => ! empty( $payload['phone'] ) ? NCK_Jalali::fa_digits( $payload['phone'] ) : $blank,
			'hall'   => ! empty( $payload['hall_name'] ) ? $payload['hall_name'] : $blank,
			'amount' => ! empty( $payload['amount'] ) ? self::format_money( $payload['amount'] ) : $blank,
			'date'   => $date_s,
			'start'  => ! empty( $payload['start_hour'] ) ? NCK_Jalali::fa_digits( $payload['start_hour'] ) : '….',
			'end'    => ! empty( $payload['end_hour'] ) ? NCK_Jalali::fa_digits( $payload['end_hour'] ) : '….',
			'chairs' => ! empty( $payload['chairs'] ) ? NCK_Jalali::fa_digits( $payload['chairs'] ) : '….',
		);
	}

	public static function filled_clauses( array $vars ) {
		$out = array();
		foreach ( self::clauses() as $block ) {
			$row = array(
				'num'   => $block['num'],
				'title' => $block['title'],
			);
			if ( isset( $block['body'] ) ) {
				$row['body'] = NCK_Contract::fill_template( $block['body'], $vars );
			}
			if ( isset( $block['items'] ) ) {
				$row['items'] = array();
				foreach ( $block['items'] as $item ) {
					$row['items'][] = NCK_Contract::fill_template( $item, $vars );
				}
			}
			$out[] = $row;
		}
		return $out;
	}
}
