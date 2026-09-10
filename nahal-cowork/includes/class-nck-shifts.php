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
		);
	}

	public static function normalize_type( $type ) {
		$type = is_string( $type ) ? $type : '';
		return in_array( $type, array( self::MORNING, self::EVENING ), true ) ? $type : '';
	}

	public static function plan_types( $plan ) {
		if ( 'both' === $plan ) {
			return array( self::MORNING, self::EVENING );
		}
		$one = self::normalize_type( $plan );
		return $one ? array( $one ) : array();
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
