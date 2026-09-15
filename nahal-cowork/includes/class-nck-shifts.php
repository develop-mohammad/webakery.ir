<?php
defined( 'ABSPATH' ) || exit;

/**
 * قوانین شیفت، سهمیه ماهانه و امکان حضور.
 */
class NCK_Shifts {

	const MORNING = 'morning';
	const EVENING = 'evening';

	public static function labels() {
		return array(
			self::MORNING => 'صبح',
			self::EVENING  => 'عصر',
		);
	}

	public static function plan_labels() {
		return array(
			'morning' => 'اشتراک شیفت صبح',
			'evening' => 'اشتراک شیفت عصر',
			'both'    => 'هر دو شیفت (دو اشتراک جدا)',
			'm1_am'   => 'اشتراک ۱ ماهه تک‌شیفت صبح',
			'm1_pm'   => 'اشتراک ۱ ماهه تک‌شیفت عصر',
			'm1_both' => 'اشتراک ۱ ماهه دو شیفت',
			'm2_am'   => 'اشتراک ۲ ماهه تک‌شیفت صبح',
			'm2_pm'   => 'اشتراک ۲ ماهه تک‌شیفت عصر',
			'm2_both' => 'اشتراک ۲ ماهه دو شیفت',
			'm3_am'   => 'اشتراک ۳ ماهه تک‌شیفت صبح',
			'm3_pm'   => 'اشتراک ۳ ماهه تک‌شیفت عصر',
		);
	}

	/**
	 * بسته‌های قابل فروش فضای کار (مدت + تک/دو شیفت).
	 *
	 * @return array<string,array{id:string,months:int,dual:bool,default_price:int,store?:string,store_prefix?:string,label:string,fee_key:string}>
	 */
	public static function packages() {
		return array(
			'm1_one' => array(
				'id'            => 'm1_one',
				'months'        => 1,
				'dual'          => false,
				'default_price' => 1950000,
				'store_prefix'  => 'm1',
				'fee_key'       => 'fee_m1_one',
				'label'         => 'اشتراک ۱ ماهه به‌صورت تک‌شیفت',
			),
			'm1_two' => array(
				'id'            => 'm1_two',
				'months'        => 1,
				'dual'          => true,
				'default_price' => 3900000,
				'store'         => 'm1_both',
				'fee_key'       => 'fee_m1_two',
				'label'         => 'اشتراک ۱ ماهه به‌صورت دو شیفت',
			),
			'm2_one' => array(
				'id'            => 'm2_one',
				'months'        => 2,
				'dual'          => false,
				'default_price' => 3600000,
				'store_prefix'  => 'm2',
				'fee_key'       => 'fee_m2_one',
				'label'         => 'اشتراک ۲ ماهه به‌صورت تک‌شیفت',
			),
			'm2_two' => array(
				'id'            => 'm2_two',
				'months'        => 2,
				'dual'          => true,
				'default_price' => 7200000,
				'store'         => 'm2_both',
				'fee_key'       => 'fee_m2_two',
				'label'         => 'اشتراک ۲ ماهه به‌صورت دو شیفت',
			),
			'm3_one' => array(
				'id'            => 'm3_one',
				'months'        => 3,
				'dual'          => false,
				'default_price' => 5250000,
				'store_prefix'  => 'm3',
				'fee_key'       => 'fee_m3_one',
				'label'         => 'اشتراک ۳ ماهه به‌صورت یک شیفت',
			),
		);
	}

	public static function package( $id ) {
		$all = self::packages();
		$id  = is_string( $id ) ? $id : '';
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	public static function package_id_from_plan( $plan ) {
		$map = array(
			'morning' => 'm1_one',
			'evening' => 'm1_one',
			'both'    => 'm1_two',
			'm1_am'   => 'm1_one',
			'm1_pm'   => 'm1_one',
			'm1_both' => 'm1_two',
			'm2_am'   => 'm2_one',
			'm2_pm'   => 'm2_one',
			'm2_both' => 'm2_two',
			'm3_am'   => 'm3_one',
			'm3_pm'   => 'm3_one',
		);
		$plan = is_string( $plan ) ? $plan : '';
		if ( isset( $map[ $plan ] ) ) {
			return $map[ $plan ];
		}
		return self::package( $plan ) ? $plan : '';
	}

	public static function package_price( $package_id ) {
		$pkg = self::package( $package_id );
		if ( ! $pkg ) {
			$package_id = self::package_id_from_plan( $package_id );
			$pkg        = self::package( $package_id );
		}
		if ( ! $pkg ) {
			return 0;
		}
		$fallback = (int) $pkg['default_price'];
		if ( class_exists( 'NCK_Settings' ) ) {
			$saved = (int) NCK_Settings::get( $pkg['fee_key'], $fallback );
			return $saved > 0 ? $saved : $fallback;
		}
		return $fallback;
	}

	/**
	 * بسته + شیفت را به کد ذخیره‌شده قرارداد تبدیل می‌کند.
	 */
	public static function compose_plan( $package, $shift = '' ) {
		$package = is_string( $package ) ? $package : '';
		$labels  = self::plan_labels();
		if ( isset( $labels[ $package ] ) && ! self::package( $package ) ) {
			return $package;
		}
		$pkg = self::package( $package );
		if ( ! $pkg ) {
			return '';
		}
		if ( ! empty( $pkg['dual'] ) ) {
			return isset( $pkg['store'] ) ? $pkg['store'] : '';
		}
		$slot = self::normalize_type( $shift );
		if ( ! $slot || empty( $pkg['store_prefix'] ) ) {
			return '';
		}
		return $pkg['store_prefix'] . ( self::MORNING === $slot ? '_am' : '_pm' );
	}

	public static function normalize_type( $type ) {
		$type = is_string( $type ) ? $type : '';
		return in_array( $type, array( self::MORNING, self::EVENING ), true ) ? $type : '';
	}

	public static function plan_types( $plan ) {
		$plan = is_string( $plan ) ? $plan : '';
		if ( in_array( $plan, array( 'both', 'm1_both', 'm2_both' ), true ) ) {
			return array( self::MORNING, self::EVENING );
		}
		if ( in_array( $plan, array( self::MORNING, 'm1_am', 'm2_am', 'm3_am' ), true ) ) {
			return array( self::MORNING );
		}
		if ( in_array( $plan, array( self::EVENING, 'm1_pm', 'm2_pm', 'm3_pm' ), true ) ) {
			return array( self::EVENING );
		}
		return array();
	}

	/** "۸:۰۰" یا "16:30" → دقیقه از نیمه‌شب، یا null */
	public static function parse_hhmm( $raw ) {
		$raw = NCK_Phone::latin_digits( trim( (string) $raw ) );
		if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', $raw, $m ) ) {
			return null;
		}
		$h = (int) $m[1];
		$i = (int) $m[2];
		if ( $h > 23 || $i > 59 ) {
			return null;
		}
		return ( $h * 60 ) + $i;
	}

	public static function format_hhmm( $minutes ) {
		$minutes = (int) $minutes;
		$h       = (int) floor( $minutes / 60 );
		$i       = $minutes % 60;
		return sprintf( '%d:%02d', $h, $i );
	}

	public static function minutes_of_day( DateTimeInterface $dt ) {
		return ( (int) $dt->format( 'G' ) * 60 ) + (int) $dt->format( 'i' );
	}

	/**
	 * شیفت جاری بر اساس ساعت مجموعه. خارج از ساعات: رشته خالی.
	 *
	 * @param array $hours morning_start, morning_end, evening_start, evening_end, grace_minutes
	 */
	public static function current_slot( DateTimeInterface $now, array $hours ) {
		$m     = self::minutes_of_day( $now );
		$grace = isset( $hours['grace_minutes'] ) ? max( 0, (int) $hours['grace_minutes'] ) : 15;
		$ms    = self::parse_hhmm( isset( $hours['morning_start'] ) ? $hours['morning_start'] : '8:00' );
		$me    = self::parse_hhmm( isset( $hours['morning_end'] ) ? $hours['morning_end'] : '13:00' );
		$es    = self::parse_hhmm( isset( $hours['evening_start'] ) ? $hours['evening_start'] : '16:30' );
		$ee    = self::parse_hhmm( isset( $hours['evening_end'] ) ? $hours['evening_end'] : '22:00' );
		if ( null === $ms || null === $me || null === $es || null === $ee ) {
			return '';
		}
		if ( $m >= ( $ms - $grace ) && $m <= $me ) {
			return self::MORNING;
		}
		if ( $m >= ( $es - $grace ) && $m <= $ee ) {
			return self::EVENING;
		}
		return '';
	}

	public static function remaining( $quota, $used ) {
		$quota = max( 0, (int) $quota );
		$used  = max( 0, (int) $used );
		return max( 0, $quota - $used );
	}

	/**
	 * وضعیت روز برای یک شیفت.
	 *
	 * @return array{ok:bool,code:string,title:string}
	 */
	public static function day_status( $shift, $jy, $jm, $jd, array $ctx ) {
		$shift = self::normalize_type( $shift );
		if ( ! $shift ) {
			return array( 'ok' => false, 'code' => 'bad_shift', 'title' => 'شیفت نامعتبر است.' );
		}

		$key = NCK_Jalali::format( $jy, $jm, $jd );

		$full = isset( $ctx['full_close'] ) && is_array( $ctx['full_close'] ) ? $ctx['full_close'] : array();
		if ( isset( $full[ $key ] ) ) {
			return array(
				'ok'    => false,
				'code'  => 'full_close',
				'title' => (string) $full[ $key ],
			);
		}

		$official = isset( $ctx['official'] ) ? (string) $ctx['official'] : '';
		$policy   = isset( $ctx['official_policy'] ) ? $ctx['official_policy'] : 'morning';
		if ( $official && 'none' !== $policy ) {
			if ( 'full' === $policy || self::MORNING === $shift ) {
				return array(
					'ok'    => false,
					'code'  => 'full' === $policy ? 'full_close' : 'official_morning',
					'title' => $official,
				);
			}
		}

		return array( 'ok' => true, 'code' => 'open', 'title' => '' );
	}

	/**
	 * آیا حضور مجاز است؟
	 *
	 * @return array{ok:bool,message:string}
	 */
	public static function can_check_in( array $args ) {
		$quota     = isset( $args['quota'] ) ? (int) $args['quota'] : 26;
		$used      = isset( $args['used'] ) ? (int) $args['used'] : 0;
		$has_plan  = ! empty( $args['has_plan'] );
		$already   = ! empty( $args['already'] );
		$day       = isset( $args['day'] ) && is_array( $args['day'] ) ? $args['day'] : array( 'ok' => true );
		$active    = ! empty( $args['member_active'] );

		if ( ! $active ) {
			return array( 'ok' => false, 'message' => 'عضویت این کاربر فعال نیست.' );
		}
		if ( ! $has_plan ) {
			return array( 'ok' => false, 'message' => 'برای این شیفت اشتراک جداگانه‌ای ثبت نشده است.' );
		}
		if ( empty( $day['ok'] ) ) {
			$code = isset( $day['code'] ) ? $day['code'] : '';
			if ( 'full_close' === $code ) {
				return array( 'ok' => false, 'message' => 'مجموعه در این روز تعطیل است' . ( ! empty( $day['title'] ) ? ' (' . $day['title'] . ').' : '.' ) );
			}
			if ( 'official_morning' === $code ) {
				return array( 'ok' => false, 'message' => 'در تعطیلات رسمی شیفت صبح تعطیل است' . ( ! empty( $day['title'] ) ? ' (' . $day['title'] . ').' : '.' ) );
			}
			return array( 'ok' => false, 'message' => 'در این روز امکان استفاده از این شیفت وجود ندارد.' );
		}
		if ( $already ) {
			return array( 'ok' => false, 'message' => 'این شیفت امروز قبلاً ثبت شده است.' );
		}
		if ( self::remaining( $quota, $used ) < 1 ) {
			return array( 'ok' => false, 'message' => 'سهمیه شیفت این ماه تمام شده است.' );
		}
		return array( 'ok' => true, 'message' => '' );
	}
}
