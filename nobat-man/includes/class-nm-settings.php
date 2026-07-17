<?php
defined( 'ABSPATH' ) || exit;

class NM_Settings {

	const OPTION = 'nm_settings';

	public static function defaults() {
		return array(
			'business_name'         => 'مشاوره آنلاین',
			'currency_label'        => 'تومان',
			'default_price'         => 500000,
			'default_duration'      => 60,
			'min_duration'          => 5,
			'max_duration'          => 300,
			'buffer_minutes'        => 10,
			'slot_step'             => 15,
			'closed_weekdays'       => array( 6 ), // جمعه
			'block_holidays'        => 1,
			'custom_holidays'       => array(),
			'thank_you_text'        => "از رزرو شما سپاسگزاریم.\nنوبت شما در <strong>سایت مشاوره آنلاین</strong> ثبت شد.\nکد پیگیری: {booking_code}\nتاریخ: {jalali_date} ساعت {start_time}\n<a href=\"{site_url}\">بازگشت به سایت</a>",
			'thank_you_page'        => 0,
			'primary_color'         => '#6d28d9',
			'accent_color'          => '#06b6d4',
			'enable_voice'          => 1,
			'enable_photo'          => 1,
			'max_upload_mb'         => 8,
			'require_email'         => 1,
			'require_city'          => 1,
			'require_gender'        => 1,
			'pending_ttl_hours'     => 24,
			'wc_product_id'         => 0,
			'notify_email'          => 1,
			'notify_sms'            => 0,
			'sms_provider'          => 'ippanel',
			'sms_api_key'           => '',
			'sms_sender'            => '',
			'sms_pattern'           => '',
			'admin_email'           => '',
			'google_client_id'      => '',
			'google_client_secret'  => '',
			'google_refresh_token'  => '',
			'enable_installments'   => 0,
			'installment_count'     => 2,
			'appearance'            => array(
				'font'       => 'vazirmatn',
				'radius'     => '16',
				'card_shadow'=> '1',
			),
		);
	}

	public static function all() {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	public static function get( $key, $default = null ) {
		$all = self::all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}
		return $default;
	}

	public static function update( array $data ) {
		$current = self::all();
		$merged  = array_merge( $current, $data );
		update_option( self::OPTION, $merged, false );
		return $merged;
	}

	public static function format_price( $amount ) {
		$amount = (int) $amount;
		$label  = self::get( 'currency_label', 'تومان' );
		return number_format_i18n( $amount ) . ' ' . $label;
	}
}
