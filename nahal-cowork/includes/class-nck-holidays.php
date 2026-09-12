<?php
defined( 'ABSPATH' ) || exit;

/**
 * تعطیلات رسمی ایران. در تعطیل رسمی شیفت صبح بسته است مگر خلاف آن در تنظیمات.
 */
class NCK_Holidays {

	public static function fixed() {
		return array(
			1  => array( 1 => 'عید نوروز', 2 => 'عید نوروز', 3 => 'عید نوروز', 4 => 'عید نوروز', 12 => 'روز جمهوری اسلامی', 13 => 'روز طبیعت' ),
			3  => array( 14 => 'رحلت امام خمینی', 15 => 'قیام ۱۵ خرداد' ),
			11 => array( 22 => 'پیروزی انقلاب اسلامی' ),
			12 => array( 29 => 'ملی شدن صنعت نفت' ),
		);
	}

	public static function lunar_defaults() {
		return array(
			'1404/01/10' => 'عید فطر',
			'1404/01/11' => 'عید فطر (تعطیلی)',
			'1404/03/14' => 'عید قربان',
			'1404/03/22' => 'عید غدیر',
			'1404/04/14' => 'تاسوعا',
			'1404/04/15' => 'عاشورا',
			'1404/05/23' => 'اربعین',
			'1404/06/02' => 'رحلت رسول اکرم و شهادت امام حسن',
			'1404/06/04' => 'شهادت امام رضا',
			'1404/06/12' => 'شهادت امام حسن عسکری',
			'1404/06/21' => 'میلاد رسول اکرم',
			'1404/10/27' => 'شهادت حضرت فاطمه',
			'1404/12/19' => 'ولادت امام علی',
			'1404/12/23' => 'مبعث',
			'1405/01/01' => 'عید فطر و عید نوروز',
			'1405/01/02' => 'عید فطر (روز دوم)',
			'1405/01/25' => 'شهادت امام جعفر صادق',
			'1405/03/06' => 'عید قربان',
			'1405/03/14' => 'عید غدیر',
			'1405/04/03' => 'تاسوعا',
			'1405/04/04' => 'عاشورا',
			'1405/05/13' => 'اربعین',
			'1405/05/21' => 'رحلت رسول اکرم و شهادت امام حسن',
			'1405/05/22' => 'شهادت امام رضا',
			'1405/05/30' => 'شهادت امام حسن عسکری',
			'1405/06/08' => 'میلاد رسول اکرم و امام صادق',
			'1405/08/22' => 'شهادت حضرت فاطمه',
			'1405/10/02' => 'ولادت امام علی',
			'1405/10/16' => 'مبعث',
			'1405/11/04' => 'نیمه شعبان',
			'1405/12/09' => 'شهادت امام علی',
			'1405/12/19' => 'عید فطر',
			'1405/12/20' => 'عید فطر (روز دوم)',
		);
	}

	/**
	 * @param string[] $extra تاریخ‌های شمسی YYYY/MM/DD => عنوان
	 * @return array<string,string>
	 */
	public static function all_for_year( $jy, array $extra = array() ) {
		$jy  = (int) $jy;
		$out = array();
		foreach ( self::fixed() as $jm => $days ) {
			foreach ( $days as $jd => $title ) {
				$out[ NCK_Jalali::format( $jy, $jm, $jd ) ] = $title;
			}
		}
		$lunar = array_merge( self::lunar_defaults(), $extra );
		foreach ( $lunar as $date => $title ) {
			$parsed = NCK_Jalali::parse( (string) $date );
			if ( ! $parsed || (int) $parsed['y'] !== $jy ) {
				continue;
			}
			$out[ NCK_Jalali::format( $parsed['y'], $parsed['m'], $parsed['d'] ) ] = (string) $title;
		}
		return $out;
	}

	public static function official_title( $jy, $jm, $jd, array $extra = array() ) {
		$key  = NCK_Jalali::format( $jy, $jm, $jd );
		$list = self::all_for_year( $jy, $extra );
		return isset( $list[ $key ] ) ? $list[ $key ] : '';
	}

	public static function parse_full_close_list( $raw ) {
		$out = array();
		$raw = str_replace( array( "\r\n", "\r" ), "\n", (string) $raw );
		foreach ( explode( "\n", $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			$parsed = NCK_Jalali::parse( $parts[0] );
			if ( ! $parsed ) {
				continue;
			}
			$key         = NCK_Jalali::format( $parsed['y'], $parsed['m'], $parsed['d'] );
			$out[ $key ] = isset( $parts[1] ) && $parts[1] !== '' ? $parts[1] : 'تعطیلی کامل مجموعه';
		}
		return $out;
	}
}
