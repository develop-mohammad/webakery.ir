<?php
defined( 'ABSPATH' ) || exit;

class NM_Pro {

	public static function is_active() {
		if ( ! class_exists( 'WB_License' ) ) {
			return false;
		}
		return WB_License::is_active( NM_PRODUCT );
	}

	public static function is_licensed() {
		return class_exists( 'WB_License' ) && WB_License::is_valid( NM_PRODUCT );
	}

	public static function require_pro() {
		if ( self::is_active() ) {
			return true;
		}
		return new WP_Error( 'pro_required', 'این قابلیت فقط در نسخه پرو فعال است. لایسنس را از webakery.ir فعال کنید.' );
	}

	public static function feature_list() {
		return array(
			'multi_specialist' => 'متخصصین و مهارت‌های متعدد',
			'multi_business'   => 'بیزینس‌های مختلف (ویلا، خانه، مشاوره و...)',
			'templates'        => 'قالب‌های آماده فیلدهای شخصی',
			'tickets'          => 'تیکت مراجعه‌کننده به متخصص',
			'subscriptions'    => 'اشتراک ماهانه رزرو',
			'installments'     => 'پرداخت قسطی',
			'sms'              => 'پیامک پنل‌های ایرانی',
			'google_calendar'  => 'گوگل کلندر',
			'variable_pricing' => 'قیمت متغیر روز/ساعت',
			'invoice'          => 'صدور فاکتور',
			'export'           => 'خروجی لیست خریدها',
			'buffer'           => 'وقفه بین رزروها',
		);
	}
}
