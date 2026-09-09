<?php
defined( 'ABSPATH' ) || exit;

/**
 * اینتنت جستجو از خودِ عبارت (قانونی، نه حدس حجم).
 */
class WBGS_Intent {

	const INFORMATIONAL = 'informational';
	const COMMERCIAL    = 'commercial';
	const TRANSACTIONAL = 'transactional';
	const NAVIGATIONAL  = 'navigational';

	/**
	 * @return array<string,string>
	 */
	public static function labels() {
		return array(
			self::INFORMATIONAL => 'اطلاعاتی',
			self::COMMERCIAL    => 'تجاری',
			self::TRANSACTIONAL => 'تراکنشی',
			self::NAVIGATIONAL  => 'ناوبری',
		);
	}

	/**
	 * @param string $keyword
	 * @return string
	 */
	public static function classify( $keyword ) {
		$text = WBGS_Suggest::normalize_seed( $keyword );
		if ( $text === '' ) {
			return self::COMMERCIAL;
		}

		$nav = array( 'دیجی کالا', 'دیجیکالا', 'آمازون', 'دیوار', 'اینستاگرام', 'ترب', 'بامیلو', 'اسنپ', 'گوگل', 'youtube', 'amazon', 'digikala', 'instagram', '.com', '.ir' );
		foreach ( $nav as $n ) {
			if ( false !== mb_stripos( $text, $n ) ) {
				return self::NAVIGATIONAL;
			}
		}

		$trans = array( 'خرید', 'فروش', 'سفارش', 'ارزان', 'تخفیف', 'قیمت', 'پرداخت', 'فروشگاه', 'اینترنتی', 'آنلاین', 'buy', 'price', 'cheap', 'order', 'shop' );
		foreach ( $trans as $n ) {
			if ( self::has_word( $text, $n ) ) {
				return self::TRANSACTIONAL;
			}
		}

		$info = array( 'چیست', 'چیه', 'چگونه', 'چطور', 'چرا', 'یعنی', 'آموزش', 'راهنما', 'معنی', 'تعریف', 'روش', 'نحوه', 'what', 'how', 'why', 'tutorial' );
		foreach ( $info as $n ) {
			if ( self::has_word( $text, $n ) ) {
				return self::INFORMATIONAL;
			}
		}

		$comm = array( 'بهترین', 'مقایسه', 'بررسی', 'انواع', 'مدل', 'تفاوت', 'کدام', 'بهتره', 'review', 'best', 'vs', 'compare' );
		foreach ( $comm as $n ) {
			if ( self::has_word( $text, $n ) ) {
				return self::COMMERCIAL;
			}
		}

		return self::COMMERCIAL;
	}

	/**
	 * @param string $keyword
	 * @return string
	 */
	public static function label( $keyword ) {
		$labels = self::labels();
		$key    = self::classify( $keyword );
		return isset( $labels[ $key ] ) ? $labels[ $key ] : $labels[ self::COMMERCIAL ];
	}

	/**
	 * @param array<int,array> $rows
	 * @return array<int,array>
	 */
	public static function attach( $rows ) {
		$out = array();
		foreach ( (array) $rows as $row ) {
			$text            = isset( $row['text'] ) ? (string) $row['text'] : '';
			$row['intent']   = self::classify( $text );
			$row['intent_fa'] = self::label( $text );
			$out[]           = $row;
		}
		return $out;
	}

	/**
	 * @param string $text
	 * @param string $needle
	 */
	private static function has_word( $text, $needle ) {
		if ( false !== strpos( $needle, '.' ) ) {
			return false !== mb_stripos( $text, $needle );
		}
		return (bool) preg_match( '/' . preg_quote( $needle, '/' ) . '/ui', $text );
	}
}
