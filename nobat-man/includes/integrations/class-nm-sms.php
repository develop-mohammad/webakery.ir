<?php
defined( 'ABSPATH' ) || exit;

class NM_SMS {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public static function send_booking( $booking ) {
		if ( ! NM_Pro::is_active() ) {
			return false;
		}
		$provider = NM_Settings::get( 'sms_provider', 'ippanel' );
		$phone    = preg_replace( '/\D+/', '', (string) $booking->customer_phone );
		if ( ! $phone ) {
			return false;
		}

		$msg = sprintf(
			'نوبت شما تایید شد. کد %s — تاریخ %s ساعت %s — مشاوره آنلاین',
			$booking->booking_code,
			$booking->jalali_date,
			substr( $booking->start_time, 0, 5 )
		);

		switch ( $provider ) {
			case 'melipayamak':
				return self::melipayamak( $phone, $msg );
			case 'kavenegar':
				return self::kavenegar( $phone, $msg );
			case 'ippanel':
			default:
				return self::ippanel( $phone, $msg, $booking );
		}
	}

	private static function ippanel( $phone, $msg, $booking ) {
		$key  = NM_Settings::get( 'sms_api_key' );
		$from = NM_Settings::get( 'sms_sender' );
		if ( ! $key ) {
			return false;
		}
		$pattern = NM_Settings::get( 'sms_pattern' );
		if ( $pattern ) {
			$body = array(
				'code'      => $pattern,
				'sender'    => $from,
				'recipient' => $phone,
				'variable'  => array(
					'code' => $booking->booking_code,
					'date' => $booking->jalali_date,
					'time' => substr( $booking->start_time, 0, 5 ),
				),
			);
			$resp = wp_remote_post(
				'https://edge.ippanel.com/v1/api/send',
				array(
					'timeout' => 12,
					'headers' => array(
						'Authorization' => $key,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $body ),
				)
			);
			return ! is_wp_error( $resp );
		}
		$resp = wp_remote_post(
			'https://api2.ippanel.com/api/v1/sms/send/webservice/single',
			array(
				'timeout' => 12,
				'headers' => array(
					'Authorization' => $key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'recipient' => array( $phone ),
						'sender'    => $from,
						'message'   => $msg,
					)
				),
			)
		);
		return ! is_wp_error( $resp );
	}

	private static function kavenegar( $phone, $msg ) {
		$key = NM_Settings::get( 'sms_api_key' );
		if ( ! $key ) {
			return false;
		}
		$url  = sprintf( 'https://api.kavenegar.com/v1/%s/sms/send.json', rawurlencode( $key ) );
		$resp = wp_remote_post(
			$url,
			array(
				'timeout' => 12,
				'body'    => array(
					'receptor' => $phone,
					'message'  => $msg,
					'sender'   => NM_Settings::get( 'sms_sender' ),
				),
			)
		);
		return ! is_wp_error( $resp );
	}

	private static function melipayamak( $phone, $msg ) {
		$key = NM_Settings::get( 'sms_api_key' );
		if ( ! $key ) {
			return false;
		}
		$resp = wp_remote_post(
			'https://rest.payamak-panel.com/api/SendSMS/SendSMS',
			array(
				'timeout' => 12,
				'body'    => array(
					'username' => $key,
					'password' => NM_Settings::get( 'sms_sender' ),
					'to'       => $phone,
					'from'     => '',
					'text'     => $msg,
				),
			)
		);
		return ! is_wp_error( $resp );
	}
}
