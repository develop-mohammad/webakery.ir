<?php
defined( 'ABSPATH' ) || exit;

/**
 * دسته‌بندی سئو از خودِ عبارت گوگل — بدون ساخت کیورد.
 */
class WBGS_Taxonomy {

	const SHORT = 'short';
	const MID   = 'mid';
	const LONG  = 'long';

	/**
	 * @return array<string,string>
	 */
	public static function length_labels() {
		return array(
			self::SHORT => 'کوتاه',
			self::MID   => 'میان‌رده',
			self::LONG  => 'طولانی',
		);
	}

	/**
	 * @return array<string,string>
	 */
	public static function extra_labels() {
		return array(
			'geo'       => 'محلی',
			'seasonal'  => 'فصلی یا موقت',
			'lsi'       => 'LSI / ارتباط معنایی',
			'branded'   => 'برند شده',
			'unbranded' => 'بدون برند',
		);
	}

	/**
	 * پنج محور دسته‌بندی سئو (عنوان فیلتر و نمای دسته‌بندی).
	 *
	 * @return array<string,string>
	 */
	public static function axis_titles() {
		return array(
			'length'   => 'طول و حجم جستجو',
			'intent'   => 'قصد کاربر از جستجو',
			'geo_time' => 'موقعیت جغرافیایی و زمان',
			'semantic' => 'مفهوم و ارتباط',
			'brand'    => 'نام برند',
			'affix'    => 'پیشوند و پسوند',
		);
	}

	/**
	 * ۱–۲ کوتاه، ۳ میان‌رده، ۴+ طولانی.
	 *
	 * @param string $text
	 * @return string
	 */
	public static function length( $text ) {
		$n = WBGS_Suggest::word_count( $text );
		if ( $n >= 4 ) {
			return self::LONG;
		}
		if ( 3 === $n ) {
			return self::MID;
		}
		return self::SHORT;
	}

	/**
	 * @return string[]
	 */
	public static function geo_markers() {
		return array(
			'تهران', 'اصفهان', 'شیراز', 'مشهد', 'تبریز', 'کرج', 'قم', 'اهواز',
			'کرمان', 'یزد', 'رشت', 'ساری', 'ارومیه', 'همدان', 'کرمانشاه',
			'بندرعباس', 'بوشهر', 'زاهدان', 'سنندج', 'اراک', 'قزوین', 'زنجان',
			'گرگان', 'اردبیل', 'ایلام', 'شهرکرد', 'یاسوج', 'بجنورد', 'سمنان',
			'خرم آباد', 'خرم‌آباد', 'کیش', 'قشم', 'ایران', 'ایرانی',
			'شمال', 'جنوب', 'تهرانپارس', 'شهر',
		);
	}

	/**
	 * @return string[]
	 */
	public static function seasonal_markers() {
		return array(
			'نوروز', 'نوروزی', 'یلدا', 'رمضان', 'محرم', 'عاشورا', 'فطر', 'قربان',
			'عید', 'تابستان', 'تابستانه', 'زمستان', 'زمستانه', 'پاییز', 'پاییزه',
			'بهار', 'بهاره', 'مدرسه', 'مهرماه', 'جمعه سیاه', 'بلک فرایدی',
			'کریسمس', 'ولنتاین', 'شب یلدا', 'حراج یلدا', 'فصلی',
		);
	}

	/**
	 * @return string[]
	 */
	public static function brand_markers() {
		return array(
			'دیجی کالا', 'دیجیکالا', 'آمازون', 'دیوار', 'اینستاگرام', 'اسنپ',
			'ترب', 'بامیلو', 'نایک', 'آدیداس', 'پوما', 'جردن', 'ونس', 'هامتو',
			'اسکیچرز', 'نیو بالانس', 'ریباک', 'سامسونگ', 'آیفون', 'اپل',
			'شیائومی', 'هواوی', 'سونی', 'گوگل', 'یوتیوب', 'تلگرام', 'واتساپ',
			'amazon', 'nike', 'adidas', 'samsung', 'iphone', 'apple', 'digikala',
			'instagram', 'youtube',
		);
	}

	/**
	 * @param string $text
	 * @param string[] $needles
	 * @return bool
	 */
	public static function has_any( $text, $needles ) {
		$text = WBGS_Suggest::normalize_seed( $text );
		if ( $text === '' ) {
			return false;
		}
		foreach ( (array) $needles as $n ) {
			if ( $n !== '' && false !== mb_stripos( $text, $n ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $text
	 * @return bool
	 */
	public static function is_geo( $text ) {
		return self::has_any( $text, self::geo_markers() );
	}

	/**
	 * @param string $text
	 * @return bool
	 */
	public static function is_seasonal( $text ) {
		return self::has_any( $text, self::seasonal_markers() );
	}

	/**
	 * @param string $text
	 * @return bool
	 */
	public static function is_branded( $text ) {
		return self::has_any( $text, self::brand_markers() );
	}

	/**
	 * LSI: عبارت گوگل که کیورد پایه را در خودش ندارد (هم‌خانواده / مرتبط).
	 *
	 * @param string $seed
	 * @param string $text
	 * @return bool
	 */
	public static function is_lsi( $seed, $text ) {
		$seed = WBGS_Suggest::normalize_seed( $seed );
		$text = WBGS_Suggest::normalize_seed( $text );
		if ( $seed === '' || $text === '' || $seed === $text ) {
			return false;
		}
		return false === mb_stripos( $text, $seed );
	}

	/**
	 * @param string $seed
	 * @param string $text
	 * @return array<string,mixed>
	 */
	public static function classify( $seed, $text ) {
		$length = self::length( $text );
		$labels = self::length_labels();
		$extras = self::extra_labels();
		$geo    = self::is_geo( $text );
		$season = self::is_seasonal( $text );
		$brand  = self::is_branded( $text );
		$lsi    = self::is_lsi( $seed, $text );
		$affix  = class_exists( 'WBGS_Affixes' ) ? WBGS_Affixes::classify( $text ) : array( 'affixes' => array(), 'affix_fa' => '' );
		return array(
			'length'       => $length,
			'length_fa'    => isset( $labels[ $length ] ) ? $labels[ $length ] : '',
			'geo'          => $geo,
			'seasonal'     => $season,
			'lsi'          => $lsi,
			'branded'      => $brand,
			'unbranded'    => ! $brand,
			'geo_fa'       => $geo ? $extras['geo'] : '',
			'seasonal_fa'  => $season ? $extras['seasonal'] : '',
			'lsi_fa'       => $lsi ? $extras['lsi'] : '',
			'brand_fa'     => $brand ? $extras['branded'] : $extras['unbranded'],
			'affixes'      => $affix['affixes'],
			'affix_fa'     => $affix['affix_fa'],
		);
	}

	/**
	 * @param string           $seed
	 * @param array<int,array> $rows
	 * @return array<int,array>
	 */
	public static function attach( $seed, $rows ) {
		$out = array();
		foreach ( (array) $rows as $row ) {
			$text = isset( $row['text'] ) ? (string) $row['text'] : '';
			$tax  = self::classify( $seed, $text );
			$out[] = array_merge( $row, $tax );
		}
		return $out;
	}
}
