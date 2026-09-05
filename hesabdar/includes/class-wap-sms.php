<?php
defined( 'ABSPATH' ) || exit;

/**
 * ارسال پیامک از طریق ملی‌پیامک برای Hesabdar.
 */
class WAP_SMS {

	const OPTION = 'wap_payment_sms';

	public static function defaults(): array {
		return array(
			'enabled'          => 0,
			'only_zarinpal'    => 1,
			'username'         => '',
			'password'         => '',
			'sender'           => '',
			'pattern'          => '',
			'recipients'       => '',
			'message'          => "پرداخت موفق زرین‌پال\nسفارش #{order_id}\nمبلغ: {total} تومان\nخریدار: {customer}\nروش: {payment}",
			'merchant_note'    => '',
		);
	}

	public static function get( $key = null, $default = null ) {
		$opts = get_option( self::OPTION, array() );
		if ( ! is_array( $opts ) ) {
			$opts = array();
		}
		$opts = array_merge( self::defaults(), $opts );
		if ( $key === null ) {
			return $opts;
		}
		return array_key_exists( $key, $opts ) ? $opts[ $key ] : $default;
	}

	public static function save( array $input ): array {
		$cur  = self::get();
		$out  = self::defaults();
		$out['enabled']       = ! empty( $input['enabled'] ) ? 1 : 0;
		$out['only_zarinpal'] = ! empty( $input['only_zarinpal'] ) ? 1 : 0;
		$out['username']      = sanitize_text_field( $input['username'] ?? '' );
		// رمز خالی = حفظ رمز قبلی
		$pass = (string) ( $input['password'] ?? '' );
		$out['password']      = ( $pass !== '' ) ? $pass : (string) ( $cur['password'] ?? '' );
		$out['sender']        = sanitize_text_field( $input['sender'] ?? '' );
		$out['pattern']       = sanitize_text_field( $input['pattern'] ?? '' );
		$out['recipients']    = sanitize_textarea_field( $input['recipients'] ?? '' );
		$out['message']       = sanitize_textarea_field( $input['message'] ?? $out['message'] );
		$out['merchant_note'] = sanitize_text_field( $input['merchant_note'] ?? '' );
		update_option( self::OPTION, $out, false );
		return $out;
	}

	/** تبدیل ارقام فارسی/عربی و نرمال به 09xxxxxxxxx */
	public static function normalize_phone( string $raw ): string {
		$map = array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
			'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
			'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		);
		$raw = strtr( $raw, $map );
		$digits = preg_replace( '/\D+/', '', $raw );
		if ( strpos( $digits, '0098' ) === 0 ) {
			$digits = substr( $digits, 4 );
		} elseif ( strpos( $digits, '98' ) === 0 && strlen( $digits ) >= 12 ) {
			$digits = substr( $digits, 2 );
		}
		if ( strlen( $digits ) === 10 && $digits[0] === '9' ) {
			$digits = '0' . $digits;
		}
		if ( preg_match( '/^09\d{9}$/', $digits ) ) {
			return $digits;
		}
		return '';
	}

	/** @return array<int,string> */
	public static function recipient_list(): array {
		$raw = (string) self::get( 'recipients', '' );
		$parts = preg_split( '/[\s,;]+/', $raw );
		$out = array();
		foreach ( (array) $parts as $p ) {
			$n = self::normalize_phone( (string) $p );
			if ( $n !== '' ) {
				$out[ $n ] = $n;
			}
		}
		return array_values( $out );
	}

	/**
	 * @return true|WP_Error
	 */
	public static function send( string $phone, string $message ) {
		$phone = self::normalize_phone( $phone );
		if ( $phone === '' ) {
			return new WP_Error( 'wap_sms_phone', 'شماره موبایل نامعتبر است.' );
		}
		$user = (string) self::get( 'username' );
		$pass = (string) self::get( 'password' );
		if ( $user === '' || $pass === '' ) {
			return new WP_Error( 'wap_sms_creds', 'نام کاربری و رمز ملی‌پیامک تنظیم نشده است.' );
		}

		$pattern = trim( (string) self::get( 'pattern' ) );
		if ( $pattern !== '' ) {
			// پترن خدماتی: معمولاً فقط متغیرها را می‌فرستد؛ اینجا کل متن را به‌عنوان text می‌فرستیم
			$resp = wp_remote_post(
				'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber',
				array(
					'timeout' => 20,
					'body'    => array(
						'username' => $user,
						'password' => $pass,
						'text'     => $message,
						'to'       => $phone,
						'bodyId'   => $pattern,
					),
				)
			);
		} else {
			$from = (string) self::get( 'sender' );
			$resp = wp_remote_post(
				'https://rest.payamak-panel.com/api/SendSMS/SendSMS',
				array(
					'timeout' => 20,
					'body'    => array(
						'username' => $user,
						'password' => $pass,
						'to'       => $phone,
						'from'     => $from,
						'text'     => $message,
						'isflash'  => 'false',
					),
				)
			);
		}

		if ( is_wp_error( $resp ) ) {
			return new WP_Error( 'wap_sms_http', 'خطا در اتصال به ملی‌پیامک: ' . $resp->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		$body = wp_remote_retrieve_body( $resp );
		$data = json_decode( $body, true );
		// API ملی‌پیامک معمولاً عدد برگشتی > 0 یعنی موفقیت، یا Value/RetStatus
		$ok = false;
		if ( is_array( $data ) ) {
			if ( isset( $data['Value'] ) && is_numeric( $data['Value'] ) && (float) $data['Value'] > 0 ) {
				$ok = true;
			}
			if ( isset( $data['RetStatus'] ) && (int) $data['RetStatus'] === 1 ) {
				$ok = true;
			}
			if ( isset( $data['StrRetStatus'] ) && strtolower( (string) $data['StrRetStatus'] ) === 'ok' ) {
				$ok = true;
			}
		} elseif ( is_numeric( trim( (string) $body ) ) && (float) $body > 0 ) {
			$ok = true;
		}
		if ( $code >= 200 && $code < 300 && $ok ) {
			return true;
		}
		$snippet = wp_strip_all_tags( substr( (string) $body, 0, 160 ) );
		return new WP_Error( 'wap_sms_fail', 'ارسال پیامک ناموفق بود. پاسخ پنل: ' . $snippet );
	}

	/**
	 * @param array<string,string> $vars
	 */
	public static function render_message( array $vars ): string {
		$tpl = (string) self::get( 'message' );
		foreach ( $vars as $k => $v ) {
			$tpl = str_replace( '{' . $k . '}', (string) $v, $tpl );
		}
		return $tpl;
	}
}
