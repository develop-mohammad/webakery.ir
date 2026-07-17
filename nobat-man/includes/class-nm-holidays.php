<?php
defined( 'ABSPATH' ) || exit;

/**
 * تعطیلات رسمی ایران بر اساس تقویم شمسی.
 * تعطیلات ثابت شمسی + تعطیلات قمری رایج (قابل به‌روزرسانی از تنظیمات).
 */
class NM_Holidays {

	/** تعطیلات ثابت شمسی: ماه => [روز => عنوان] */
	public static function fixed() {
		return array(
			1  => array( 1 => 'عید نوروز', 2 => 'عید نوروز', 3 => 'عید نوروز', 4 => 'عید نوروز', 12 => 'روز جمهوری اسلامی', 13 => 'روز طبیعت' ),
			3  => array( 14 => 'رحلت امام خمینی', 15 => 'قیام ۱۵ خرداد' ),
			11 => array( 22 => 'پیروزی انقلاب اسلامی' ),
			12 => array( 29 => 'ملی شدن صنعت نفت' ),
		);
	}

	/**
	 * تعطیلات قمری تقریبی برای سال‌های ۱۴۰۳–۱۴۰۶ (قابل override در تنظیمات).
	 * کلید: Y/m/d شمسی
	 */
	public static function lunar_defaults() {
		return array(
			// ۱۴۰۴
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
			// ۱۴۰۵ (برآورد — ادمین می‌تواند اصلاح کند)
			'1405/01/01' => 'عید نوروز',
		);
	}

	public static function all_for_year( $jy ) {
		$out = array();
		foreach ( self::fixed() as $jm => $days ) {
			foreach ( $days as $jd => $title ) {
				$key = NM_Jalali::format( $jy, $jm, $jd );
				$out[ $key ] = $title;
			}
		}

		$custom = (array) NM_Settings::get( 'custom_holidays', array() );
		$lunar  = array_merge( self::lunar_defaults(), $custom );
		foreach ( $lunar as $date => $title ) {
			if ( 0 === strpos( (string) $date, (string) $jy . '/' ) ) {
				$out[ $date ] = $title;
			}
		}

		/**
		 * فیلتر برای افزودن تعطیلات سفارشی.
		 */
		return apply_filters( 'nm_holidays_year', $out, $jy );
	}

	public static function is_holiday( $jy, $jm, $jd ) {
		$key = NM_Jalali::format( $jy, $jm, $jd );
		$list = self::all_for_year( $jy );
		return isset( $list[ $key ] ) ? $list[ $key ] : false;
	}

	public static function is_friday( $jy, $jm, $jd ) {
		return 6 === NM_Jalali::weekday( $jy, $jm, $jd );
	}
}
