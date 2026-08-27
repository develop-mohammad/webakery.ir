<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ارسال پیامک هشدار از پنل‌های ایرانی — متن ساده، بدون OTP.
 */
class WBE_SMS {

	public static function providers() {
		return array(
			'melipayamak' => 'ملی‌پیامک',
			'ippanel'     => 'IPPanel / فراز اس‌ام‌اس',
			'kavenegar'   => 'کاوه‌نگار',
			'ghasedak'    => 'قاصدک',
		);
	}

	/**
	 * @param string $phone
	 * @param string $message
	 * @return true|WP_Error
	 */
	public static function send( $phone, $message ) {
		$phone   = WBE_Engine::normalize_phone( $phone );
		$message = trim( wp_strip_all_tags( (string) $message ) );
		if ( $phone === '' ) {
			return new WP_Error( 'wbe_sms_phone', 'شماره موبایل مدیر را به صورت 09xxxxxxxxx وارد کنید.' );
		}
		if ( $message === '' ) {
			return new WP_Error( 'wbe_sms_text', 'متن پیامک خالی است.' );
		}
		if ( function_exists( 'mb_substr' ) ) {
			$message = mb_substr( $message, 0, 400 );
		} else {
			$message = substr( $message, 0, 400 );
		}
		$s        = WBE_Settings::get();
		$provider = isset( $s['sms_provider'] ) ? $s['sms_provider'] : 'melipayamak';
		switch ( $provider ) {
			case 'kavenegar':
				return self::kavenegar( $phone, $message, $s );
			case 'ippanel':
				return self::ippanel( $phone, $message, $s );
			case 'ghasedak':
				return self::ghasedak( $phone, $message, $s );
			case 'melipayamak':
			default:
				return self::melipayamak( $phone, $message, $s );
		}
	}

	private static function melipayamak( $phone, $message, $s ) {
		$user = trim( (string) $s['sms_username'] );
		$pass = (string) $s['sms_password'];
		if ( $user === '' || $pass === '' ) {
			return new WP_Error( 'wbe_sms_creds', 'نام کاربری و رمز ملی‌پیامک را وارد کنید.' );
		}
		$resp = wp_remote_post(
			'https://rest.payamak-panel.com/api/SendSMS/SendSMS',
			array(
				'timeout' => 15,
				'body'    => array(
					'username' => $user,
					'password' => $pass,
					'to'       => $phone,
					'from'     => $s['sms_sender'],
					'text'     => $message,
					'isflash'  => false,
				),
			)
		);
		return self::ok( $resp, 'ملی‌پیامک' );
	}

	private static function ippanel( $phone, $message, $s ) {
		$key = trim( (string) $s['sms_api_key'] );
		if ( $key === '' ) {
			return new WP_Error( 'wbe_sms_creds', 'API Key پنل IPPanel را وارد کنید.' );
		}
		$resp = wp_remote_post(
			'https://api2.ippanel.com/api/v1/sms/send/webservice/single',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => $key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'recipient' => array( $phone ),
						'sender'    => $s['sms_sender'],
						'message'   => $message,
					)
				),
			)
		);
		return self::ok( $resp, 'IPPanel' );
	}

	private static function kavenegar( $phone, $message, $s ) {
		$key = trim( (string) $s['sms_api_key'] );
		if ( $key === '' ) {
			return new WP_Error( 'wbe_sms_creds', 'API Key کاوه‌نگار را وارد کنید.' );
		}
		$url  = sprintf( 'https://api.kavenegar.com/v1/%s/sms/send.json', rawurlencode( $key ) );
		$resp = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'body'    => array(
					'receptor' => $phone,
					'message'  => $message,
					'sender'   => $s['sms_sender'],
				),
			)
		);
		return self::ok( $resp, 'کاوه‌نگار' );
	}

	private static function ghasedak( $phone, $message, $s ) {
		$key = trim( (string) $s['sms_api_key'] );
		if ( $key === '' ) {
			return new WP_Error( 'wbe_sms_creds', 'API Key قاصدک را وارد کنید.' );
		}
		$resp = wp_remote_post(
			'https://gateway.ghasedak.me/rest/api/v1/WebService/SendSingleSMS',
			array(
				'timeout' => 15,
				'headers' => array(
					'ApiKey'       => $key,
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'message'    => $message,
						'receptor'   => $phone,
						'linenumber' => $s['sms_sender'],
					)
				),
			)
		);
		return self::ok( $resp, 'قاصدک' );
	}

	private static function ok( $resp, $label ) {
		if ( is_wp_error( $resp ) ) {
			return new WP_Error( 'wbe_sms_http', sprintf( 'خطا در ارتباط با %s: %s', $label, $resp->get_error_message() ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		if ( $code < 200 || $code >= 300 ) {
			$body = wp_remote_retrieve_body( $resp );
			return new WP_Error( 'wbe_sms_http', sprintf( 'پاسخ نامعتبر از %s (کد %d).', $label, $code ) . ' ' . wp_strip_all_tags( substr( (string) $body, 0, 120 ) ) );
		}
		return true;
	}
}
