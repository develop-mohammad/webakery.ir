<?php
defined( 'ABSPATH' ) || exit;

/**
 * ارسال پیامک OTP از طریق پنل‌های ایرانی.
 */
class WBL_SMS {

	/**
	 * @param string $phone شماره نرمال‌شده (مثلاً 0912...).
	 * @param string $code  کد OTP.
	 * @return true|WP_Error
	 */
	public static function send_otp( $phone, $code ) {
		$provider = WBL_Settings::get( 'sms_provider', 'melipayamak' );
		$message  = str_replace( '{code}', $code, (string) WBL_Settings::get( 'sms_message', 'کد ورود شما: {code}' ) );

		switch ( $provider ) {
			case 'kavenegar':
				return self::kavenegar( $phone, $code, $message );
			case 'ippanel':
				return self::ippanel( $phone, $code, $message );
			case 'ghasedak':
				return self::ghasedak( $phone, $code, $message );
			case 'melipayamak':
			default:
				return self::melipayamak( $phone, $code, $message );
		}
	}

	public static function providers() {
		return array(
			'melipayamak' => 'ملی‌پیامک (Melipayamak)',
			'ippanel'     => 'IPPanel / فراز اس‌ام‌اس',
			'kavenegar'   => 'کاوه‌نگار',
			'ghasedak'    => 'قاصدک',
		);
	}

	private static function melipayamak( $phone, $code, $message ) {
		$user = WBL_Settings::get( 'sms_username' );
		$pass = WBL_Settings::get( 'sms_password' );
		if ( ! $user || ! $pass ) {
			return new WP_Error( 'wbl_sms_creds', 'نام کاربری و رمز ملی‌پیامک را در تنظیمات وارد کنید.' );
		}

		$pattern = trim( (string) WBL_Settings::get( 'sms_pattern' ) );
		if ( $pattern ) {
			$resp = wp_remote_post(
				'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber',
				array(
					'timeout' => 15,
					'body'    => array(
						'username' => $user,
						'password' => $pass,
						'text'     => $code,
						'to'       => $phone,
						'bodyId'   => $pattern,
					),
				)
			);
		} else {
			$from = WBL_Settings::get( 'sms_sender' );
			$resp = wp_remote_post(
				'https://rest.payamak-panel.com/api/SendSMS/SendSMS',
				array(
					'timeout' => 15,
					'body'    => array(
						'username' => $user,
						'password' => $pass,
						'to'       => $phone,
						'from'     => $from,
						'text'     => $message,
						'isflash'  => false,
					),
				)
			);
		}

		return self::check_response( $resp, 'ملی‌پیامک' );
	}

	private static function ippanel( $phone, $code, $message ) {
		$key  = WBL_Settings::get( 'sms_api_key' );
		$from = WBL_Settings::get( 'sms_sender' );
		if ( ! $key ) {
			return new WP_Error( 'wbl_sms_creds', 'API Key پنل IPPanel را وارد کنید.' );
		}

		$pattern = trim( (string) WBL_Settings::get( 'sms_pattern' ) );
		$var     = WBL_Settings::get( 'sms_pattern_var', 'code' ) ?: 'code';

		if ( $pattern ) {
			$resp = wp_remote_post(
				'https://edge.ippanel.com/v1/api/send',
				array(
					'timeout' => 15,
					'headers' => array(
						'Authorization' => $key,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'code'      => $pattern,
							'sender'    => $from,
							'recipient' => $phone,
							'variable'  => array( $var => $code ),
						)
					),
				)
			);
		} else {
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
							'sender'    => $from,
							'message'   => $message,
						)
					),
				)
			);
		}

		return self::check_response( $resp, 'IPPanel' );
	}

	private static function kavenegar( $phone, $code, $message ) {
		$key = WBL_Settings::get( 'sms_api_key' );
		if ( ! $key ) {
			return new WP_Error( 'wbl_sms_creds', 'API Key کاوه‌نگار را وارد کنید.' );
		}

		$pattern = trim( (string) WBL_Settings::get( 'sms_pattern' ) );
		if ( $pattern ) {
			$url  = sprintf( 'https://api.kavenegar.com/v1/%s/verify/lookup.json', rawurlencode( $key ) );
			$resp = wp_remote_post(
				$url,
				array(
					'timeout' => 15,
					'body'    => array(
						'receptor' => $phone,
						'token'    => $code,
						'template' => $pattern,
					),
				)
			);
		} else {
			$url  = sprintf( 'https://api.kavenegar.com/v1/%s/sms/send.json', rawurlencode( $key ) );
			$resp = wp_remote_post(
				$url,
				array(
					'timeout' => 15,
					'body'    => array(
						'receptor' => $phone,
						'message'  => $message,
						'sender'   => WBL_Settings::get( 'sms_sender' ),
					),
				)
			);
		}

		return self::check_response( $resp, 'کاوه‌نگار' );
	}

	private static function ghasedak( $phone, $code, $message ) {
		$key = WBL_Settings::get( 'sms_api_key' );
		if ( ! $key ) {
			return new WP_Error( 'wbl_sms_creds', 'API Key قاصدک را وارد کنید.' );
		}

		$pattern = trim( (string) WBL_Settings::get( 'sms_pattern' ) );
		if ( $pattern ) {
			$resp = wp_remote_post(
				'https://gateway.ghasedak.me/rest/api/v1/WebService/SendOTP',
				array(
					'timeout' => 15,
					'headers' => array(
						'ApiKey'       => $key,
						'Content-Type' => 'application/json',
						'Accept'       => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'templateName' => $pattern,
							'receptors'    => array( array( 'mobile' => $phone ) ),
							'param1'       => $code,
						)
					),
				)
			);
		} else {
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
							'message'  => $message,
							'receptor' => $phone,
							'linenumber' => WBL_Settings::get( 'sms_sender' ),
						)
					),
				)
			);
		}

		return self::check_response( $resp, 'قاصدک' );
	}

	/**
	 * @param array|WP_Error $resp
	 * @param string         $label
	 * @return true|WP_Error
	 */
	private static function check_response( $resp, $label ) {
		if ( is_wp_error( $resp ) ) {
			return new WP_Error( 'wbl_sms_http', sprintf( 'خطا در ارتباط با %s: %s', $label, $resp->get_error_message() ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		$body = (string) wp_remote_retrieve_body( $resp );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'wbl_sms_http', sprintf( 'پاسخ نامعتبر از %s (کد %d). %s', $label, $code, wp_strip_all_tags( substr( $body, 0, 120 ) ) ) );
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return true;
		}

		// ملی‌پیامک: Value منفی = خطا.
		if ( isset( $data['Value'] ) && is_numeric( $data['Value'] ) && (int) $data['Value'] < 0 ) {
			return new WP_Error( 'wbl_sms_api', sprintf( 'خطای %s: کد %s', $label, $data['Value'] ) );
		}

		// کاوه‌نگار: return.status باید 200 باشد.
		if ( isset( $data['return']['status'] ) ) {
			$st = (int) $data['return']['status'];
			if ( $st && 200 !== $st ) {
				$msg = $data['return']['message'] ?? ( 'کد ' . $st );
				return new WP_Error( 'wbl_sms_api', sprintf( '%s: %s', $label, $msg ) );
			}
		}

		return true;
	}
}
