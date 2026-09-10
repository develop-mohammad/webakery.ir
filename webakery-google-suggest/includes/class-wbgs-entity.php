<?php
defined( 'ABSPATH' ) || exit;

/**
 * تقسیم عبارت واقعی گوگل به دسته محصول / محصول / برند.
 * حدس حجم یا کیورد ساختگی نیست؛ از خودِ واژه‌های عبارت است.
 */
class WBGS_Entity {

	const CATEGORY = 'category';
	const PRODUCT  = 'product';
	const BRAND    = 'brand';
	const OTHER    = 'other';

	/**
	 * @return array<string,string>
	 */
	public static function labels() {
		return array(
			self::CATEGORY => 'دسته محصول',
			self::PRODUCT  => 'محصول',
			self::BRAND    => 'برند',
			self::OTHER    => 'سایر',
		);
	}

	/**
	 * @return string[]
	 */
	public static function categories() {
		return array(
			'کفش', 'کتانی', 'کتونی', 'دمپایی', 'صندل', 'بوت', 'چکمه',
			'کیف', 'کوله', 'لباس', 'شلوار', 'پیراهن', 'مانتو', 'پالتو',
			'کاپشن', 'هودی', 'تیشرت', 'تی شرت', 'روسری', 'شال', 'جوراب',
			'طلا', 'جواهر', 'ساعت', 'عینک', 'انگشتر', 'گردنبند',
			'گوشی', 'موبایل', 'تبلت', 'لپ تاپ', 'لپ‌تاپ', 'کامپیوتر',
			'هدفون', 'هندزفری', 'تلویزیون', 'مانیتور', 'کارت گرافیک',
			'پردازنده', 'مادربرد', 'پرینتر', 'دوربین', 'کنسول',
			'یخچال', 'لباسشویی', 'جاروبرقی', 'کولر', 'پلوپز',
			'چای ساز', 'چای‌ساز', 'قهوه ساز', 'ماکروویو', 'اجاق',
			'عطر', 'ادکلن', 'کرم', 'شامپو', 'لوازم آرایش', 'رژ', 'ریمل',
			'مبل', 'میز', 'صندلی', 'فرش', 'پرده', 'تشک', 'بالش',
			'خودرو', 'موتور سیکلت', 'موتورسیکلت', 'لاستیک',
			'پیتزا', 'برگر', 'قهوه', 'خشکبار', 'کتاب',
			'اسباب بازی', 'اسباب‌بازی', 'دوچرخه', 'اسکوتر',
			'مکمل', 'ویتامین',
		);
	}

	/**
	 * برند سازنده کالا (نه مارکت‌پلیس).
	 *
	 * @return string[]
	 */
	public static function makers() {
		return array(
			'نایک', 'آدیداس', 'پوما', 'جردن', 'ونس', 'هامتو',
			'اسکیچرز', 'نیو بالانس', 'ریباک', 'سامسونگ', 'اپل',
			'شیائومی', 'هواوی', 'سونی', 'ال جی', 'نوکیا', 'آنر',
			'وان پلاس', 'ایسوس', 'لنوو', 'اچ پی',
			'گوچی', 'زارا',
			'nike', 'adidas', 'puma', 'samsung', 'apple',
			'xiaomi', 'huawei', 'sony', 'asus', 'lenovo',
		);
	}

	/**
	 * خط محصول که خودش کالا است (آیفون، گلکسی).
	 *
	 * @return string[]
	 */
	public static function lines() {
		return array(
			'آیفون', 'گلکسی', 'ایرپاد', 'ایرپادز', 'مک بوک', 'مک‌بوک',
			'آیپد', 'پلی استیشن', 'ایکس باکس', 'ایرمکس', 'ردمی',
			'پوکو', 'پیکسل', 'سرفیس',
			'iphone', 'galaxy', 'airpods', 'macbook', 'ipad',
			'playstation', 'xbox', 'airmax', 'redmi', 'poco',
			'pixel', 'surface',
		);
	}

	/**
	 * فروشگاه، اپ و مقصد — اگر تنها باشند برندند؛ کنار دسته، دسته می‌مانند.
	 *
	 * @return string[]
	 */
	public static function markets() {
		return array(
			'دیجی کالا', 'دیجیکالا', 'آمازون', 'دیوار', 'ترب', 'بامیلو',
			'اسنپ', 'اسنپ فود', 'کافه بازار', 'مایکت', 'اینستاگرام',
			'تلگرام', 'واتساپ', 'یوتیوب', 'گوگل', 'روبیکا', 'باسلام',
			'تکنولایف', 'جی اس ام',
			'amazon', 'digikala', 'instagram', 'youtube', 'telegram',
			'divar', 'torob',
		);
	}

	/**
	 * @return string[]
	 */
	public static function dest_markers() {
		return array(
			'سایت', 'وبسایت', 'وب سایت', 'ورود', 'لاگین', 'لوگین',
			'اپلیکیشن', 'پشتیبانی', 'دانلود', 'آدرس', 'صفحه رسمی',
			'کانال', 'پیج رسمی',
			'login', 'support', 'download', 'official',
		);
	}

	/**
	 * @return string[]
	 */
	public static function info_markers() {
		return array(
			'چیست', 'چیه', 'چگونه', 'چطور', 'چطوری', 'چرا', 'یعنی',
			'آموزش', 'راهنما', 'معنی', 'تعریف', 'روش', 'نحوه',
			'what', 'how', 'why', 'tutorial',
		);
	}

	/**
	 * @return string[]
	 */
	public static function trans_markers() {
		return array(
			'خرید', 'فروش', 'سفارش', 'ارزان', 'تخفیف', 'قیمت',
			'پرداخت', 'فروشگاه', 'اینترنتی', 'آنلاین',
			'buy', 'price', 'cheap', 'order', 'shop',
		);
	}

	/**
	 * @return string[]
	 */
	public static function comm_markers() {
		return array(
			'بهترین', 'مقایسه', 'بررسی', 'انواع', 'مدل', 'تفاوت', 'کدام',
			'بهتره', 'مزایا', 'معایب',
			'review', 'best', 'compare',
		);
	}

	/**
	 * @return string[]
	 */
	public static function quality_markers() {
		return array( 'اصل', 'اورجینال', 'تقلبی', 'فیک', 'کپی' );
	}

	/**
	 * @return string[]
	 */
	public static function stopwords() {
		return array( 'در', 'به', 'از', 'و', 'با', 'برای', 'روی', 'را', 'که', 'این', 'آن', 'یک' );
	}

	/**
	 * @return string[]
	 */
	public static function tlds() {
		return array( '.com', '.ir', '.org' );
	}

	/**
	 * @return string[]
	 */
	public static function model_words() {
		return array(
			'پرو', 'مکس', 'اولترا', 'پلاس', 'مینی', 'ایرمکس', 'جردن',
			'pro', 'max', 'ultra', 'plus', 'mini',
		);
	}

	/**
	 * برند سازنده + مارکت + خط محصول برای برچسب «برند شده».
	 *
	 * @return string[]
	 */
	public static function brand_tokens() {
		return self::unique_list(
			array_merge( self::makers(), self::markets(), self::lines() )
		);
	}

	/**
	 * واژه‌نامه برای JS تا همان قانون PHP را تکرار کند.
	 *
	 * @return array<string,string[]>
	 */
	public static function public_lexicon() {
		return array(
			'categories' => self::categories(),
			'makers'     => self::makers(),
			'markets'    => self::markets(),
			'lines'      => self::lines(),
			'dest'       => self::dest_markers(),
			'info'       => self::info_markers(),
			'trans'      => self::trans_markers(),
			'comm'       => self::comm_markers(),
			'quality'    => self::quality_markers(),
			'stop'       => self::stopwords(),
			'tlds'       => self::tlds(),
			'modelWords' => self::model_words(),
		);
	}

	/**
	 * یکسان‌سازی ی/ک، ارقام و نیم‌فاصله برای تطبیق.
	 *
	 * @param string $text
	 * @return string
	 */
	public static function fold( $text ) {
		$text = class_exists( 'WBGS_Suggest' ) ? WBGS_Suggest::normalize_seed( $text ) : trim( (string) $text );
		$from = array(
			'ي', 'ى', 'ك',
			'۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹',
			'٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩',
		);
		$to   = array(
			'ی', 'ی', 'ک',
			'0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
			'0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
		);
		$text = str_replace( $from, $to, $text );
		$text = str_replace( array( "\xE2\x80\x8C", "\xE2\x80\x8E", "\xE2\x80\x8F" ), ' ', $text );
		if ( function_exists( 'mb_strtolower' ) ) {
			$text = mb_strtolower( $text, 'UTF-8' );
		} else {
			$text = strtolower( $text );
		}
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * @param string   $text
	 * @param string[] $needles
	 * @return bool
	 */
	public static function has_any( $text, $needles ) {
		$text = self::fold( $text );
		if ( $text === '' ) {
			return false;
		}
		foreach ( (array) $needles as $n ) {
			$n = self::fold( (string) $n );
			if ( $n !== '' && false !== mb_stripos( $text, $n ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $keyword
	 * @return array<string,bool>
	 */
	public static function inspect( $keyword ) {
		$text = self::fold( $keyword );
		$out  = array(
			'has_category' => self::has_any( $text, self::categories() ),
			'has_maker'    => self::has_any( $text, self::makers() ),
			'has_market'   => self::has_any( $text, self::markets() ),
			'has_line'     => self::has_any( $text, self::lines() ),
			'has_model'    => false,
			'is_dest'      => self::has_any( $text, self::dest_markers() ),
			'has_info'     => self::has_any( $text, self::info_markers() ),
			'has_trans'    => self::has_any( $text, self::trans_markers() ),
			'has_comm'     => self::has_any( $text, self::comm_markers() ),
			'has_tld'      => self::has_any( $text, self::tlds() ),
			'is_brand_only'=> false,
		);
		$out['has_model']     = self::detect_model( $text, $out['has_maker'], $out['has_line'] );
		$out['is_brand_only'] = self::detect_brand_only( $text, $out );
		return $out;
	}

	/**
	 * @param string $keyword
	 * @return string
	 */
	public static function classify( $keyword ) {
		$text = self::fold( $keyword );
		if ( $text === '' ) {
			return self::OTHER;
		}
		$f = self::inspect( $text );

		if ( $f['is_dest'] && ( $f['has_maker'] || $f['has_market'] || $f['has_tld'] ) && ! $f['has_category'] && ! $f['has_model'] && ! $f['has_line'] ) {
			return self::BRAND;
		}
		if ( $f['is_brand_only'] ) {
			return self::BRAND;
		}
		if ( $f['has_line'] || $f['has_model'] || ( $f['has_maker'] && $f['has_category'] ) ) {
			return self::PRODUCT;
		}
		if ( $f['has_category'] ) {
			return self::CATEGORY;
		}
		if ( $f['has_maker'] || $f['has_market'] || $f['has_tld'] ) {
			return self::BRAND;
		}
		return self::OTHER;
	}

	/**
	 * @param string $keyword
	 * @return string
	 */
	public static function label( $keyword ) {
		$labels = self::labels();
		$key    = self::classify( $keyword );
		return isset( $labels[ $key ] ) ? $labels[ $key ] : $labels[ self::OTHER ];
	}

	/**
	 * @param array<int,array> $rows
	 * @return array<int,array>
	 */
	public static function attach( $rows ) {
		$out = array();
		foreach ( (array) $rows as $row ) {
			$text             = isset( $row['text'] ) ? (string) $row['text'] : '';
			$row['entity']    = self::classify( $text );
			$row['entity_fa'] = self::label( $text );
			$out[]            = $row;
		}
		return $out;
	}

	/**
	 * @param string $text
	 * @param bool   $has_maker
	 * @param bool   $has_line
	 * @return bool
	 */
	private static function detect_model( $text, $has_maker, $has_line ) {
		if ( ! $has_maker && ! $has_line ) {
			return false;
		}
		if ( self::has_any( $text, self::model_words() ) ) {
			return true;
		}
		if ( preg_match( '/[a-z]{1,3}\s*-?\s*[0-9]{1,4}/u', $text ) ) {
			return true;
		}
		if ( ! preg_match_all( '/[0-9]{1,4}/u', $text, $m ) ) {
			return false;
		}
		foreach ( $m[0] as $raw ) {
			$n = (int) $raw;
			if ( $n >= 1300 && $n <= 1410 ) {
				continue;
			}
			if ( $n >= 1990 && $n <= 2035 ) {
				continue;
			}
			if ( $n <= 0 ) {
				continue;
			}
			return true;
		}
		return false;
	}

	/**
	 * @param string             $text
	 * @param array<string,bool> $flags
	 * @return bool
	 */
	private static function detect_brand_only( $text, $flags ) {
		if ( ! empty( $flags['has_category'] ) || ! empty( $flags['has_line'] ) || ! empty( $flags['has_model'] ) ) {
			return false;
		}
		if ( empty( $flags['has_maker'] ) && empty( $flags['has_market'] ) && empty( $flags['has_tld'] ) ) {
			return false;
		}
		$rest = self::strip_list(
			$text,
			array_merge(
				self::stopwords(),
				self::dest_markers(),
				self::info_markers(),
				self::trans_markers(),
				self::comm_markers(),
				self::quality_markers(),
				self::makers(),
				self::markets(),
				self::tlds()
			)
		);
		return $rest === '';
	}

	/**
	 * @param string   $text
	 * @param string[] $needles
	 * @return string
	 */
	private static function strip_list( $text, $needles ) {
		$text    = self::fold( $text );
		$needles = self::by_len( $needles );
		foreach ( $needles as $n ) {
			$n = self::fold( $n );
			if ( $n === '' ) {
				continue;
			}
			$text = preg_replace( '/' . preg_quote( $n, '/' ) . '/ui', ' ', $text );
		}
		return trim( (string) preg_replace( '/\s+/u', ' ', (string) $text ) );
	}

	/**
	 * @param string[] $items
	 * @return string[]
	 */
	private static function by_len( $items ) {
		$items = array_values( array_filter( array_map( 'strval', (array) $items ) ) );
		usort(
			$items,
			function ( $a, $b ) {
				return mb_strlen( $b ) - mb_strlen( $a );
			}
		);
		return $items;
	}

	/**
	 * @param string[] $items
	 * @return string[]
	 */
	private static function unique_list( $items ) {
		$out = array();
		foreach ( (array) $items as $item ) {
			$item = (string) $item;
			if ( $item !== '' && ! in_array( $item, $out, true ) ) {
				$out[] = $item;
			}
		}
		return $out;
	}
}
