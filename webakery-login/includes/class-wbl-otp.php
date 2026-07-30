<?php
defined( 'ABSPATH' ) || exit;

/**
 * تولید، ذخیره و اعتبارسنجی کد OTP.
 */
class WBL_OTP {

	/**
	 * نرمال‌سازی شماره موبایل ایران به فرمت 09xxxxxxxxx.
	 *
	 * @param string $raw
	 * @return string|WP_Error
	 */
	public static function normalize_phone( $raw ) {
		$raw = trim( (string) $raw );
		$raw = str_replace( array( ' ', '-', '_', '(', ')' ), '', $raw );
		$raw = self::fa_to_en_digits( $raw );
		$raw = preg_replace( '/\D+/', '', $raw );

		if ( 0 === strpos( $raw, '0098' ) ) {
			$raw = '0' . substr( $raw, 4 );
		} elseif ( 0 === strpos( $raw, '98' ) && 12 === strlen( $raw ) ) {
			$raw = '0' . substr( $raw, 2 );
		} elseif ( 10 === strlen( $raw ) && '9' === $raw[0] ) {
			$raw = '0' . $raw;
		}

		if ( ! preg_match( '/^09\d{9}$/', $raw ) ) {
			return new WP_Error( 'wbl_bad_phone', 'شماره موبایل معتبر نیست. مثال: ۰۹۱۲۳۴۵۶۷۸۹' );
		}

		return $raw;
	}

	public static function fa_to_en_digits( $str ) {
		$map = array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
			'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
			'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		);
		return strtr( (string) $str, $map );
	}

	public static function generate_code( $length = null ) {
		$len = $length ? (int) $length : (int) WBL_Settings::get( 'otp_length', 5 );
		$len = min( 8, max( 4, $len ) );
		$min = (int) pow( 10, $len - 1 );
		$max = (int) pow( 10, $len ) - 1;
		return (string) wp_rand( $min, $max );
	}

	private static function key( $phone ) {
		return 'wbl_otp_' . md5( $phone );
	}

	private static function rate_key( $phone ) {
		return 'wbl_rate_' . md5( $phone . gmdate( 'Y-m-d' ) );
	}

	private static function ip_rate_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '0';
		return 'wbl_ip_' . md5( $ip . gmdate( 'Y-m-d-H' ) );
	}

	/**
	 * ارسال OTP جدید.
	 *
	 * @param string $phone
	 * @return array|WP_Error
	 */
	public static function send( $phone ) {
		$phone = self::normalize_phone( $phone );
		if ( is_wp_error( $phone ) ) {
			return $phone;
		}

		$ip_count = (int) get_transient( self::ip_rate_key() );
		$ip_limit = (int) WBL_Settings::get( 'otp_ip_hourly_limit', 20 );
		if ( $ip_count >= $ip_limit ) {
			return new WP_Error( 'wbl_otp_ip', 'تعداد درخواست از این آدرس بیش از حد مجاز است. کمی بعد تلاش کنید.' );
		}

		$wait = (int) WBL_Settings::get( 'otp_resend_wait', 60 );
		$prev = get_transient( self::key( $phone ) );
		if ( is_array( $prev ) && ! empty( $prev['sent_at'] ) ) {
			$elapsed = time() - (int) $prev['sent_at'];
			if ( $elapsed < $wait ) {
				return new WP_Error(
					'wbl_otp_wait',
					sprintf( 'لطفاً %d ثانیه دیگر صبر کنید.', $wait - $elapsed ),
					array( 'wait' => $wait - $elapsed )
				);
			}
		}

		$daily = (int) get_transient( self::rate_key( $phone ) );
		$limit = (int) WBL_Settings::get( 'otp_daily_limit', 10 );
		if ( $daily >= $limit ) {
			return new WP_Error( 'wbl_otp_limit', 'سقف ارسال روزانه برای این شماره پر شده است.' );
		}

		$code = self::generate_code();
		$ttl  = (int) WBL_Settings::get( 'otp_ttl', 120 );

		$result = WBL_SMS::send_otp( $phone, $code );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		set_transient(
			self::key( $phone ),
			array(
				'hash'     => wp_hash_password( $code ),
				'sent_at'  => time(),
				'attempts' => 0,
				'phone'    => $phone,
			),
			$ttl
		);

		set_transient( self::rate_key( $phone ), $daily + 1, DAY_IN_SECONDS );
		set_transient( self::ip_rate_key(), $ip_count + 1, HOUR_IN_SECONDS );

		return array(
			'ok'      => true,
			'phone'   => $phone,
			'ttl'     => $ttl,
			'wait'    => $wait,
			'message' => sprintf( 'کد تأیید به %s ارسال شد.', self::mask( $phone ) ),
		);
	}

	/**
	 * @param string $phone
	 * @param string $code
	 * @return true|WP_Error
	 */
	public static function verify( $phone, $code ) {
		$phone = self::normalize_phone( $phone );
		if ( is_wp_error( $phone ) ) {
			return $phone;
		}

		$code = self::fa_to_en_digits( $code );
		$code = preg_replace( '/\D+/', '', (string) $code );
		$data = get_transient( self::key( $phone ) );
		if ( ! is_array( $data ) || empty( $data['hash'] ) ) {
			return new WP_Error( 'wbl_otp_expired', 'کد منقضی شده یا ارسال نشده است. دوباره درخواست کنید.' );
		}

		$max = (int) WBL_Settings::get( 'otp_max_attempts', 5 );
		if ( (int) $data['attempts'] >= $max ) {
			delete_transient( self::key( $phone ) );
			return new WP_Error( 'wbl_otp_locked', 'تعداد تلاش‌ها بیش از حد مجاز بود. کد جدید بگیرید.' );
		}

		$data['attempts'] = (int) $data['attempts'] + 1;
		$ttl_left         = max( 30, (int) WBL_Settings::get( 'otp_ttl', 120 ) - ( time() - (int) $data['sent_at'] ) );
		set_transient( self::key( $phone ), $data, $ttl_left );

		if ( ! wp_check_password( $code, $data['hash'] ) ) {
			$left = $max - (int) $data['attempts'];
			return new WP_Error( 'wbl_otp_wrong', sprintf( 'کد نادرست است. %d تلاش باقی مانده.', max( 0, $left ) ) );
		}

		delete_transient( self::key( $phone ) );
		return true;
	}

	public static function mask( $phone ) {
		$phone = (string) $phone;
		if ( strlen( $phone ) < 8 ) {
			return $phone;
		}
		return substr( $phone, 0, 4 ) . '***' . substr( $phone, -3 );
	}
}
