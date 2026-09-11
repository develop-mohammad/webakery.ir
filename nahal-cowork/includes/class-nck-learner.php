<?php
defined( 'ABSPATH' ) || exit;

/**
 * فرم پذیرش و شناخت فراگیر — مرکز مهارت‌های آینده نهال.
 */
class NCK_Learner {

	public static function center_name() {
		return 'مرکز توسعه مهارت‌های آینده، هوش مصنوعی و نوآوری نهال';
	}

	public static function tagline() {
		return 'توسعه سرمایه انسانی آینده با تلفیق مهارت‌های انسانی، هوش مصنوعی و نوآوری';
	}

	public static function slogan() {
		return 'هوای رشدت رو داریم';
	}

	public static function heard_options() {
		return array(
			'instagram' => 'اینستاگرام',
			'website'   => 'وب‌سایت',
			'google'    => 'گوگل',
			'friends'   => 'دوستان و آشنایان',
			'school'    => 'مدرسه',
			'city_ads'  => 'تبلیغات شهری',
			'events'    => 'شرکت در رویدادهای نهال',
			'returning' => 'قبلاً مخاطب نهال بوده‌ام',
		);
	}

	public static function payment_options() {
		return array(
			'site'   => 'سایت',
			'card'   => 'کارت به کارت',
			'onsite' => 'در محل کارت کشیده شد',
		);
	}

	public static function term_options() {
		return array(
			'english'    => 'زبان انگلیسی',
			'lego'       => 'لگوی آموزشی و رباتیک',
			'ai'         => 'برنامه‌نویسی و هوش مصنوعی',
			'painting'   => 'نقاشی',
			'sport_club' => 'باشگاه ورزشی',
		);
	}

	public static function seasonal_options() {
		return array(
			'summer_camp'     => 'کمپ تابستان',
			'mehr_boarding'   => 'پانسیون مهر',
			'summer_boarding' => 'پانسیون تابستان',
			'teen_club'       => 'باشگاه کارآفرینان نوجوان',
		);
	}

	public static function medical_options() {
		return array(
			'allergy'  => 'آلرژی',
			'food'     => 'حساسیت غذایی',
			'adhd'     => 'ADHD',
			'special'  => 'بیماری خاص',
			'asthma'   => 'آسم',
			'medicine' => 'مصرف دارو',
			'autism'   => 'اوتیسم',
			'other'    => 'مورد دیگر',
		);
	}

	public static function group_options() {
		return array(
			'leader'   => 'رهبر گروه می‌شود.',
			'coop'     => 'همکاری خوبی دارد.',
			'independent' => 'ترجیح می‌دهد مستقل کار کند.',
			'withdraw' => 'معمولاً مشارکت نمی‌کند.',
		);
	}

	public static function problem_options() {
		return array(
			'tries'    => 'خودش امتحان می‌کند.',
			'asks'     => 'از دیگران کمک می‌خواهد.',
			'waits'    => 'منتظر راهنمایی می‌ماند.',
			'quits'    => 'زود منصرف می‌شود.',
		);
	}

	public static function learning_options() {
		return array(
			'mixed'    => 'ترکیبی',
			'practical' => 'عملی',
			'auditory' => 'شنیداری',
			'visual'   => 'دیداری',
		);
	}

	public static function goal_options() {
		return array(
			'problem_solving' => 'حل مسئله',
			'future_ready'   => 'آمادگی برای آینده',
			'entrepreneur'   => 'کارآفرینی',
			'coding'         => 'برنامه‌نویسی',
			'ai'             => 'هوش مصنوعی',
			'responsibility' => 'مسئولیت‌پذیری',
			'social'         => 'تقویت مهارت‌های اجتماعی',
			'focus'          => 'افزایش تمرکز',
			'creativity'     => 'خلاقیت',
			'confidence'     => 'اعتمادبه‌نفس',
		);
	}

	public static function join_labels( array $keys, array $map ) {
		$out = array();
		foreach ( $keys as $k ) {
			if ( isset( $map[ $k ] ) ) {
				$out[] = $map[ $k ];
			}
		}
		return $out ? implode('، ', $out ) : '—';
	}

	public static function format_date( $raw ) {
		$p = NCK_Jalali::parse( $raw );
		if ( ! $p ) {
			return '';
		}
		return NCK_Jalali::format( $p['y'], $p['m'], $p['d'] );
	}

	/**
	 * @return array{ok:bool,message:string,payload?:array}
	 */
	public static function validate( array $in ) {
		$name = self::text( isset( $in['name'] ) ? $in['name'] : '' );
		if ( $name === '' || self::len( $name ) < 3 ) {
			return array( 'ok' => false, 'message' => 'نام و نام خانوادگی فراگیر را کامل وارد کنید.' );
		}

		$nid = NCK_Hall::normalize_nid( isset( $in['national_id'] ) ? $in['national_id'] : '' );
		if ( ! $nid ) {
			return array( 'ok' => false, 'message' => 'کد ملی فراگیر معتبر نیست. باید ۱۰ رقم و مطابق الگوریتم ملی باشد.' );
		}

		$birth = self::format_date( isset( $in['birth_date'] ) ? $in['birth_date'] : '' );
		if ( $birth === '' ) {
			return array( 'ok' => false, 'message' => 'تاریخ تولد را به صورت ۱۴۰۴/۰۶/۲۰ وارد کنید.' );
		}

		$grade  = self::text( isset( $in['grade'] ) ? $in['grade'] : '' );
		$school = self::text( isset( $in['school'] ) ? $in['school'] : '' );
		$addr   = self::text( isset( $in['address'] ) ? $in['address'] : '' );
		if ( $grade === '' ) {
			return array( 'ok' => false, 'message' => 'پایه تحصیلی را وارد کنید.' );
		}
		if ( $school === '' ) {
			return array( 'ok' => false, 'message' => 'نام مدرسه را وارد کنید.' );
		}
		if ( $addr === '' ) {
			return array( 'ok' => false, 'message' => 'آدرس را وارد کنید.' );
		}

		$learner_phone = NCK_Phone::normalize( isset( $in['phone'] ) ? $in['phone'] : '' );
		$father_name    = self::text( isset( $in['father_name'] ) ? $in['father_name'] : '' );
		$father_job     = self::text( isset( $in['father_job'] ) ? $in['father_job'] : '' );
		$father_phone   = NCK_Phone::normalize( isset( $in['father_phone'] ) ? $in['father_phone'] : '' );
		$mother_name    = self::text( isset( $in['mother_name'] ) ? $in['mother_name'] : '' );
		$mother_job     = self::text( isset( $in['mother_job'] ) ? $in['mother_job'] : '' );
		$mother_phone   = NCK_Phone::normalize( isset( $in['mother_phone'] ) ? $in['mother_phone'] : '' );

		if ( $father_name === '' && $mother_name === '' ) {
			return array( 'ok' => false, 'message' => 'نام حداقل یکی از والدین را وارد کنید.' );
		}
		if ( isset( $in['father_phone'] ) && trim( (string) $in['father_phone'] ) !== '' && ! $father_phone ) {
			return array( 'ok' => false, 'message' => 'شماره تماس پدر معتبر نیست.' );
		}
		if ( isset( $in['mother_phone'] ) && trim( (string) $in['mother_phone'] ) !== '' && ! $mother_phone ) {
			return array( 'ok' => false, 'message' => 'شماره تماس مادر معتبر نیست.' );
		}
		if ( isset( $in['phone'] ) && trim( (string) $in['phone'] ) !== '' && ! $learner_phone ) {
			return array( 'ok' => false, 'message' => 'شماره تماس فراگیر معتبر نیست.' );
		}

		$contact = $learner_phone ? $learner_phone : ( $father_phone ? $father_phone : $mother_phone );
		if ( ! $contact ) {
			return array( 'ok' => false, 'message' => 'حداقل یک شماره تماس معتبر (فراگیر یا والدین) لازم است.' );
		}

		$heard     = self::pick_list( isset( $in['heard'] ) ? $in['heard'] : array(), self::heard_options() );
		$payment   = self::pick_one( isset( $in['payment'] ) ? $in['payment'] : '', self::payment_options() );
		if ( ! $payment ) {
			$payment = 'site';
		}

		$pay_date = '';
		if ( isset( $in['pay_date'] ) && trim( (string) $in['pay_date'] ) !== '' ) {
			$pay_date = self::format_date( $in['pay_date'] );
			if ( $pay_date === '' ) {
				return array( 'ok' => false, 'message' => 'تاریخ پرداخت را به صورت ۱۴۰۴/۰۶/۲۰ وارد کنید.' );
			}
		}

		$pay_amount = 0;
		if ( isset( $in['pay_amount'] ) && trim( (string) $in['pay_amount'] ) !== '' ) {
			$pay_amount = NCK_Hall::parse_amount( $in['pay_amount'] );
		}

		$admit_date = '';
		if ( isset( $in['admit_date'] ) && trim( (string) $in['admit_date'] ) !== '' ) {
			$admit_date = self::format_date( $in['admit_date'] );
			if ( $admit_date === '' ) {
				return array( 'ok' => false, 'message' => 'تاریخ پذیرش را به صورت ۱۴۰۴/۰۶/۲۰ وارد کنید.' );
			}
		}

		$term      = self::pick_list( isset( $in['term'] ) ? $in['term'] : array(), self::term_options() );
		$seasonal  = self::pick_list( isset( $in['seasonal'] ) ? $in['seasonal'] : array(), self::seasonal_options() );
		if ( ! $term && ! $seasonal ) {
			return array( 'ok' => false, 'message' => 'حداقل یک دوره ترمی یا فصلی را انتخاب کنید.' );
		}

		$medical = self::pick_list( isset( $in['medical'] ) ? $in['medical'] : array(), self::medical_options() );
		$notes   = self::textarea( isset( $in['medical_notes'] ) ? $in['medical_notes'] : '' );

		$group    = self::pick_one( isset( $in['group_work'] ) ? $in['group_work'] : '', self::group_options() );
		$problem  = self::pick_one( isset( $in['problem'] ) ? $in['problem'] : '', self::problem_options() );
		$learning = self::pick_one( isset( $in['learning'] ) ? $in['learning'] : '', self::learning_options() );
		if ( ! $group ) {
			return array( 'ok' => false, 'message' => 'وضعیت کار گروهی را انتخاب کنید.' );
		}
		if ( ! $problem ) {
			return array( 'ok' => false, 'message' => 'واکنش به مسئله جدید را انتخاب کنید.' );
		}
		if ( ! $learning ) {
			return array( 'ok' => false, 'message' => 'سبک یادگیری را انتخاب کنید.' );
		}

		$goals = self::pick_list( isset( $in['goals'] ) ? $in['goals'] : array(), self::goal_options() );
		if ( ! $goals ) {
			return array( 'ok' => false, 'message' => 'حداقل یک هدف ثبت‌نام را انتخاب کنید.' );
		}

		if ( empty( $in['agree_rules'] ) ) {
			return array( 'ok' => false, 'message' => 'قوانین آموزشی و انضباطی نهال را بپذیرید.' );
		}
		if ( empty( $in['agree_contact'] ) ) {
			return array( 'ok' => false, 'message' => 'مسئولیت به‌روزرسانی اطلاعات تماس را بپذیرید.' );
		}
		if ( empty( $in['agree_photo'] ) ) {
			return array( 'ok' => false, 'message' => 'برای ثبت فرم، موافقت استفاده از تصویر لازم است.' );
		}

		$sign_date = '';
		if ( isset( $in['sign_date'] ) && trim( (string) $in['sign_date'] ) !== '' ) {
			$sign_date = self::format_date( $in['sign_date'] );
			if ( $sign_date === '' ) {
				return array( 'ok' => false, 'message' => 'تاریخ امضا را به صورت ۱۴۰۴/۰۶/۲۰ وارد کنید.' );
			}
		}

		return array(
			'ok'      => true,
			'message' => '',
			'payload' => array(
				'kind'          => 'learner',
				'heard'         => $heard,
				'payment'       => $payment,
				'pay_date'      => $pay_date,
				'pay_ref'       => class_exists( 'NCK_Pay' ) ? NCK_Pay::make_ref() : self::text( isset( $in['pay_ref'] ) ? $in['pay_ref'] : '' ),
				'pay_amount'    => $pay_amount,
				'learner_code'  => self::text( isset( $in['learner_code'] ) ? $in['learner_code'] : '' ),
				'admit_date'    => $admit_date,
				'staff_name'    => self::text( isset( $in['staff_name'] ) ? $in['staff_name'] : '' ),
				'class_limit'   => self::text( isset( $in['class_limit'] ) ? $in['class_limit'] : '' ),
				'name'          => $name,
				'birth_date'    => $birth,
				'national_id'   => $nid,
				'grade'         => $grade,
				'school'        => $school,
				'phone'         => $learner_phone ? $learner_phone : '',
				'address'       => $addr,
				'father_name'   => $father_name,
				'father_job'    => $father_job,
				'father_phone'  => $father_phone ? $father_phone : '',
				'mother_name'   => $mother_name,
				'mother_job'    => $mother_job,
				'mother_phone'  => $mother_phone ? $mother_phone : '',
				'contact_phone' => $contact,
				'term'          => $term,
				'seasonal'      => $seasonal,
				'medical'       => $medical,
				'medical_notes' => $notes,
				'group_work'    => $group,
				'problem'       => $problem,
				'learning'      => $learning,
				'goals'         => $goals,
				'agree_rules'   => 1,
				'agree_contact' => 1,
				'agree_photo'   => 1,
				'sign_date'     => $sign_date,
			),
		);
	}

	public static function pick_list( $raw, array $allowed ) {
		if ( ! is_array( $raw ) ) {
			$raw = ( $raw === '' || null === $raw ) ? array() : array( $raw );
		}
		$out = array();
		foreach ( $raw as $v ) {
			$k = self::key( $v );
			if ( isset( $allowed[ $k ] ) ) {
				$out[] = $k;
			}
		}
		return array_values( array_unique( $out ) );
	}

	public static function pick_one( $raw, array $allowed ) {
		$k = self::key( $raw );
		return isset( $allowed[ $k ] ) ? $k : '';
	}

	private static function key( $v ) {
		$v = strtolower( trim( (string) $v ) );
		$v = preg_replace( '/[^a-z0-9_\-]/', '', $v );
		return $v;
	}

	private static function text( $v ) {
		$v = trim( (string) $v );
		$v = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v );
		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $v );
		}
		return $v;
	}

	private static function textarea( $v ) {
		$v = (string) $v;
		if ( function_exists( 'sanitize_textarea_field' ) ) {
			return sanitize_textarea_field( $v );
		}
		return trim( preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v ) );
	}

	private static function len( $v ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $v ) : strlen( $v );
	}
}
