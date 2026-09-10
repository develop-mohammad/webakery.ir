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

	public static function default_halls() {
		return array( 'سالن اصلی', 'سالن همایش' );
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

		$hall = trim( isset( $in['hall_name'] ) ? (string) $in['hall_name'] : '' );
		if ( function_exists( 'sanitize_text_field' ) ) {
			$name = sanitize_text_field( $name );
			$hall = sanitize_text_field( $hall );
		}
		if ( $hall === '' ) {
			return array( 'ok' => false, 'message' => 'نام سالن را وارد کنید.' );
		}

		$amount = self::parse_amount( isset( $in['amount'] ) ? $in['amount'] : '' );
		if ( $amount < 1 ) {
			return array( 'ok' => false, 'message' => 'مبلغ اجاره را وارد کنید.' );
		}

		$date = NCK_Jalali::parse( isset( $in['event_date'] ) ? $in['event_date'] : '' );
		if ( ! $date ) {
			return array( 'ok' => false, 'message' => 'تاریخ برگزاری را به صورت ۱۴۰۴/۰۶/۲۰ وارد کنید.' );
		}

		$start = NCK_Shifts::parse_hhmm( isset( $in['start_hour'] ) ? $in['start_hour'] : '' );
		$end   = NCK_Shifts::parse_hhmm( isset( $in['end_hour'] ) ? $in['end_hour'] : '' );
		if ( null === $start || null === $end ) {
			return array( 'ok' => false, 'message' => 'ساعت شروع و پایان را به صورت ۱۶:۳۰ وارد کنید.' );
		}
		if ( $end <= $start ) {
			return array( 'ok' => false, 'message' => 'ساعت پایان باید بعد از ساعت شروع باشد.' );
		}

		$chairs = isset( $in['chairs'] ) ? (int) NCK_Phone::latin_digits( $in['chairs'] ) : 0;
		if ( $chairs < 1 ) {
			return array( 'ok' => false, 'message' => 'تعداد صندلی را وارد کنید.' );
		}

		$honorific = isset( $in['honorific'] ) ? (string) $in['honorific'] : 'mr';
		$honorific = in_array( $honorific, array( 'mr', 'ms' ), true ) ? $honorific : 'mr';

		$start_s = isset( $in['start_hour'] ) ? NCK_Phone::latin_digits( $in['start_hour'] ) : '';
		$end_s   = isset( $in['end_hour'] ) ? NCK_Phone::latin_digits( $in['end_hour'] ) : '';

		return array(
			'ok'      => true,
			'message' => '',
			'payload' => array(
				'kind'         => 'hall',
				'honorific'    => $honorific,
				'name'         => $name,
				'national_id'  => $nid,
				'phone'        => $phone,
				'hall_name'    => $hall,
				'amount'       => $amount,
				'event_date'   => NCK_Jalali::format( $date['y'], $date['m'], $date['d'] ),
				'start_hour'   => $start_s,
				'end_hour'     => $end_s,
				'chairs'       => $chairs,
				'projector'    => ! empty( $in['projector'] ) ? 1 : 0,
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
