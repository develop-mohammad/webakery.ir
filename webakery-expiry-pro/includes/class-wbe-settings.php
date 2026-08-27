<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * تنظیمات سراسری افزونه.
 */
class WBE_Settings {

	const OPTION = 'wbe_settings';

	public static function defaults() {
		return array(
			'calendar'             => 'jalali',
			'near_expiry_days'     => 60,
			'show_near_price'      => 1,
			'show_in_description'  => 1,
			'alert_soon_days'      => 7,
			'alert_month_days'     => 30,
			'alert_two_month_days' => 60,
			'dash_alarm'           => 1,
			'dash_widget'          => 1,
			'email_alert'          => 1,
			'email_to'             => '',
			'sms_alert'            => 0,
			'sms_phone'            => '',
			'sms_provider'         => 'melipayamak',
			'sms_username'         => '',
			'sms_password'         => '',
			'sms_api_key'          => '',
			'sms_sender'           => '',
		);
	}

	public static function get() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	public static function calendar() {
		$s = self::get();
		return ( 'gregorian' === $s['calendar'] ) ? 'gregorian' : 'jalali';
	}

	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$d     = self::defaults();
		$out   = array(
			'calendar'             => ( isset( $input['calendar'] ) && 'gregorian' === $input['calendar'] ) ? 'gregorian' : 'jalali',
			'near_expiry_days'     => max( 1, min( 3650, (int) ( $input['near_expiry_days'] ?? $d['near_expiry_days'] ) ) ),
			'show_near_price'      => empty( $input['show_near_price'] ) ? 0 : 1,
			'show_in_description'  => empty( $input['show_in_description'] ) ? 0 : 1,
			'alert_soon_days'      => max( 0, min( 365, (int) ( $input['alert_soon_days'] ?? $d['alert_soon_days'] ) ) ),
			'alert_month_days'     => max( 1, min( 365, (int) ( $input['alert_month_days'] ?? $d['alert_month_days'] ) ) ),
			'alert_two_month_days' => max( 1, min( 730, (int) ( $input['alert_two_month_days'] ?? $d['alert_two_month_days'] ) ) ),
			'dash_alarm'           => empty( $input['dash_alarm'] ) ? 0 : 1,
			'dash_widget'          => empty( $input['dash_widget'] ) ? 0 : 1,
			'email_alert'          => empty( $input['email_alert'] ) ? 0 : 1,
			'email_to'             => sanitize_email( isset( $input['email_to'] ) ? $input['email_to'] : '' ),
			'sms_alert'            => empty( $input['sms_alert'] ) ? 0 : 1,
			'sms_phone'            => WBE_Engine::normalize_phone( isset( $input['sms_phone'] ) ? $input['sms_phone'] : '' ),
			'sms_provider'         => sanitize_key( isset( $input['sms_provider'] ) ? $input['sms_provider'] : 'melipayamak' ),
			'sms_username'         => sanitize_text_field( isset( $input['sms_username'] ) ? $input['sms_username'] : '' ),
			'sms_password'         => isset( $input['sms_password'] ) ? (string) $input['sms_password'] : '',
			'sms_api_key'          => sanitize_text_field( isset( $input['sms_api_key'] ) ? $input['sms_api_key'] : '' ),
			'sms_sender'           => sanitize_text_field( isset( $input['sms_sender'] ) ? $input['sms_sender'] : '' ),
		);
		$allowed = array( 'melipayamak', 'ippanel', 'kavenegar', 'ghasedak' );
		if ( ! in_array( $out['sms_provider'], $allowed, true ) ) {
			$out['sms_provider'] = 'melipayamak';
		}
		if ( $out['alert_month_days'] < $out['alert_soon_days'] ) {
			$out['alert_month_days'] = $out['alert_soon_days'];
		}
		if ( $out['alert_two_month_days'] < $out['alert_month_days'] ) {
			$out['alert_two_month_days'] = $out['alert_month_days'];
		}
		$out['near_expiry_days'] = max( $out['near_expiry_days'], $out['alert_two_month_days'] );
		return $out;
	}
}
