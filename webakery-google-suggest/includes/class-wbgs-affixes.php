<?php
defined( 'ABSPATH' ) || exit;

/**
 * پیشوند و پسوند رایج فارسی برای کوئری سجست و طبقه‌بندی عبارت واقعی گوگل.
 * خودِ کیورد را نمی‌سازد.
 */
class WBGS_Affixes {

	/**
	 * گروه‌های طبقه‌بندی‌شده.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function groups() {
		return array(
			'buy'         => array(
				'title'   => 'خرید',
				'sub'     => 'پیشوند و پسوند معامله',
				'place'   => 'both',
				'match'   => 'sub',
				'axis'    => 'semantic',
				'probes'  => array( 'خرید', 'فروش', 'سفارش' ),
				'markers' => array( 'خرید', 'فروش', 'سفارش', 'پرداخت' ),
			),
			'price'       => array(
				'title'   => 'قیمت',
				'sub'     => 'نرخ و هزینه',
				'place'   => 'both',
				'match'   => 'sub',
				'axis'    => 'semantic',
				'probes'  => array( 'قیمت', 'نرخ', 'هزینه' ),
				'markers' => array( 'قیمت', 'نرخ', 'هزینه', 'تومان' ),
			),
			'question'    => array(
				'title'   => 'سوالی',
				'sub'     => 'چطور، چگونه، چیست، چرا',
				'place'   => 'both',
				'match'   => 'sub',
				'axis'    => 'semantic',
				'probes'  => array( 'چیست', 'چیه', 'چگونه', 'چطور', 'چرا', 'آیا' ),
				'markers' => array(
					'چیست', 'چیه', 'چگونه', 'چطور', 'چطوری', 'چرا', 'آیا',
					'یعنی', 'معنی', 'تعریف', 'نحوه', 'روش', 'چه زمانی', 'کجا',
				),
			),
			'superlative' => array(
				'title'   => 'ترین و بهترین',
				'sub'     => 'صفت عالی',
				'place'   => 'both',
				'match'   => 'sub',
				'axis'    => 'semantic',
				'probes'  => array( 'بهترین', 'برترین', 'ترین', 'ارزانترین', 'جدیدترین', 'پرفروشترین' ),
				'markers' => array(
					'ترین', 'بهترین', 'برترین', 'ارزانترین', 'گرانترین',
					'بزرگترین', 'نزدیکترین', 'جدیدترین', 'قدیمیترین',
					'معروفترین', 'پرفروشترین', 'مناسبترین',
				),
			),
			'compare'     => array(
				'title'   => 'مقایسه و بررسی',
				'sub'     => 'تحقیق قبل از خرید',
				'place'   => 'both',
				'match'   => 'sub',
				'axis'    => 'semantic',
				'probes'  => array( 'مقایسه', 'بررسی', 'انواع', 'مدل', 'تفاوت' ),
				'markers' => array( 'مقایسه', 'بررسی', 'انواع', 'مدل', 'تفاوت', 'مزایا', 'معایب', 'کدام' ),
			),
			'city'        => array(
				'title'   => 'شهر',
				'sub'     => 'پسوند محل',
				'place'   => 'suffix',
				'match'   => 'sub',
				'axis'    => 'semantic',
				'probes'  => array(
					'شهر', 'تهران', 'مشهد', 'اصفهان', 'شیراز', 'تبریز',
					'کرج', 'قم', 'اهواز', 'رشت', 'نزدیک من',
				),
				'markers' => array(
					'شهر', 'تهران', 'مشهد', 'اصفهان', 'شیراز', 'تبریز',
					'کرج', 'قم', 'اهواز', 'رشت', 'نزدیک من', 'نزدیکترین',
				),
			),
			'audience'    => array(
				'title'   => 'مخاطب',
				'sub'     => 'پسوند جنسیت و سن',
				'place'   => 'suffix',
				'match'   => 'sub',
				'axis'    => 'semantic',
				'probes'  => array( 'مردانه', 'زنانه', 'بچگانه', 'کودک' ),
				'markers' => array( 'مردانه', 'زنانه', 'بچگانه', 'کودکانه', 'نوجوان', 'بزرگسال' ),
			),
			'quality'     => array(
				'title'   => 'کیفیت',
				'sub'     => 'ارزان، اصل، رایگان',
				'place'   => 'both',
				'match'   => 'sub',
				'axis'    => 'semantic',
				'probes'  => array( 'ارزان', 'اصل', 'رایگان', 'خوب', 'مناسب' ),
				'markers' => array( 'ارزان', 'اصل', 'اورجینال', 'رایگان', 'خوب', 'مناسب', 'تقلبی', 'دست دوم' ),
			),
			'channel'     => array(
				'title'   => 'کانال خرید',
				'sub'     => 'آنلاین و حضوری',
				'place'   => 'both',
				'match'   => 'sub',
				'axis'    => 'semantic',
				'probes'  => array( 'آنلاین', 'اینترنتی', 'سایت', 'حضوری' ),
				'markers' => array( 'آنلاین', 'اینترنتی', 'سایت', 'حضوری', 'فروشگاه', 'نمایندگی' ),
			),
			'learn'       => array(
				'title'   => 'آموزش',
				'sub'     => 'راهنما و فیلم',
				'place'   => 'both',
				'match'   => 'sub',
				'axis'    => 'semantic',
				'probes'  => array( 'آموزش', 'راهنما', 'فیلم' ),
				'markers' => array( 'آموزش', 'راهنما', 'فیلم', 'ویدیو', 'دانلود', 'عکس' ),
			),
			'service'     => array(
				'title'   => 'خدمات',
				'sub'     => 'پیشوند تعمیر و نصب',
				'place'   => 'prefix',
				'match'   => 'sub',
				'axis'    => 'semantic',
				'probes'  => array( 'تعمیر', 'نصب', 'خدمات' ),
				'markers' => array( 'تعمیر', 'نصب', 'خدمات', 'گارانتی' ),
			),
			'time'        => array(
				'title'   => 'زمان',
				'sub'     => 'پسوند زمانی',
				'place'   => 'suffix',
				'match'   => 'sub',
				'axis'    => 'semantic',
				'probes'  => array( 'امروز', 'امسال', 'فوری' ),
				'markers' => array( 'امروز', 'امسال', 'فوری', 'الان' ),
			),
			'morph_prefix'=> array(
				'title'   => 'پیشوند ساختواژی',
				'sub'     => 'باز، هم، نا، بی، ضد، پیش…',
				'place'   => 'prefix',
				'match'   => 'first',
				'axis'    => 'affix',
				'probes'  => array( 'باز', 'هم', 'نا', 'بی', 'ضد', 'پیش', 'فرا', 'غیر' ),
				'markers' => array( 'باز', 'هم', 'نا', 'بی', 'ضد', 'پیش', 'فرا', 'غیر' ),
			),
			'morph_suffix'=> array(
				'title'   => 'پسوند ساختواژی',
				'sub'     => 'ترین، ها، های، انه…',
				'place'   => 'suffix',
				'match'   => 'last',
				'axis'    => 'affix',
				'probes'  => array( 'ترین', 'ها', 'های', 'انه', 'سازی', 'کننده' ),
				'markers' => array( 'ترین', 'ها', 'های', 'انه', 'سازی', 'کننده' ),
			),
		);
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public static function public_map() {
		$out = array();
		foreach ( self::groups() as $id => $group ) {
			$out[ $id ] = array(
				'title'   => $group['title'],
				'sub'     => $group['sub'],
				'markers' => $group['markers'],
				'match'   => $group['match'],
				'axis'    => $group['axis'],
			);
		}
		return $out;
	}

	/**
	 * @return string[]
	 */
	public static function probe_list() {
		$seen = array();
		foreach ( self::groups() as $group ) {
			foreach ( (array) $group['probes'] as $probe ) {
				$probe = WBGS_Suggest::normalize_seed( $probe );
				if ( $probe !== '' ) {
					$seen[ $probe ] = true;
				}
			}
		}
		return array_keys( $seen );
	}

	/**
	 * کوئری پیشوند/پسوند برای گوگل — نه کیورد ساختگی.
	 *
	 * @param string $seed
	 * @return string[]
	 */
	public static function queries_for( $seed ) {
		$seed = WBGS_Suggest::normalize_seed( $seed );
		if ( $seed === '' ) {
			return array();
		}
		$seen = array();
		foreach ( self::groups() as $group ) {
			$place = isset( $group['place'] ) ? $group['place'] : 'both';
			foreach ( (array) $group['probes'] as $mod ) {
				$mod = WBGS_Suggest::normalize_seed( $mod );
				if ( $mod === '' || $mod === $seed ) {
					continue;
				}
				if ( 'suffix' !== $place ) {
					$seen[ $mod . ' ' . $seed ] = true;
				}
				if ( 'prefix' !== $place ) {
					$seen[ $seed . ' ' . $mod ] = true;
				}
			}
		}
		return array_keys( $seen );
	}

	/**
	 * @param string $text
	 * @param string $marker
	 * @param string $mode
	 * @return bool
	 */
	public static function marker_hits( $text, $marker, $mode = 'sub' ) {
		$text   = WBGS_Suggest::normalize_seed( $text );
		$marker = WBGS_Suggest::normalize_seed( $marker );
		if ( $text === '' || $marker === '' ) {
			return false;
		}
		$parts = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $parts ) || ! $parts ) {
			return false;
		}
		$first = $parts[0];
		$last  = $parts[ count( $parts ) - 1 ];

		if ( 'first' === $mode ) {
			return mb_strtolower( $first ) === mb_strtolower( $marker );
		}
		if ( 'last' === $mode ) {
			if ( mb_strtolower( $last ) === mb_strtolower( $marker ) ) {
				return true;
			}
			if ( mb_strlen( $marker ) >= 3 && mb_strlen( $last ) > mb_strlen( $marker ) ) {
				$tail = mb_substr( $last, -1 * mb_strlen( $marker ) );
				return mb_strtolower( $tail ) === mb_strtolower( $marker );
			}
			return false;
		}
		return false !== mb_stripos( $text, $marker );
	}

	/**
	 * @param string $text
	 * @param array  $group
	 * @return bool
	 */
	public static function group_hits( $text, $group ) {
		$mode = isset( $group['match'] ) ? $group['match'] : 'sub';
		foreach ( (array) $group['markers'] as $marker ) {
			if ( self::marker_hits( $text, $marker, $mode ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $text
	 * @return string[]
	 */
	public static function match_ids( $text ) {
		$ids = array();
		foreach ( self::groups() as $id => $group ) {
			if ( self::group_hits( $text, $group ) ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * @param string $text
	 * @return string[]
	 */
	public static function match_titles( $text ) {
		$titles = array();
		$groups = self::groups();
		foreach ( self::match_ids( $text ) as $id ) {
			if ( isset( $groups[ $id ]['title'] ) ) {
				$titles[] = $groups[ $id ]['title'];
			}
		}
		return $titles;
	}

	/**
	 * @param string $text
	 * @return array<string,mixed>
	 */
	public static function classify( $text ) {
		$ids    = self::match_ids( $text );
		$titles = self::match_titles( $text );
		return array(
			'affixes'   => $ids,
			'affix_fa'  => implode( '، ', $titles ),
		);
	}
}
