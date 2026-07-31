<?php
defined( 'ABSPATH' ) || exit;

class WBL_Settings {

	const OPTION = 'wbl_settings';

	public static function defaults() {
		return array(
			'enable_phone'       => 1,
			'enable_google'      => 1,
			'auto_register'      => 1,
			'default_role'       => 'subscriber',
			'redirect_after'     => '',
			'replace_wp_login'   => 0,
			'login_page_id'      => 0,
			'otp_length'         => 5,
			'otp_ttl'            => 120,
			'otp_resend_wait'    => 60,
			'otp_max_attempts'   => 5,
			'otp_daily_limit'    => 10,
			'sms_provider'       => 'melipayamak',
			'sms_username'       => '',
			'sms_password'       => '',
			'sms_api_key'        => '',
			'sms_sender'         => '',
			'sms_pattern'        => '',
			'sms_pattern_var'    => 'code',
			'sms_message'        => 'کد ورود شما: {code}',
			'google_client_id'   => '',
			'google_client_secret' => '',
			'form_title'         => 'ورود',
			'form_subtitle'      => 'شماره موبایل یا حساب گوگل',
			'phone_placeholder'  => '۰۹۱۲۳۴۵۶۷۸۹',
			'primary_color'      => '#0d9488',
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

	public static function save( array $input ) {
		$d   = self::defaults();
		$out = self::all();

		$bools = array( 'enable_phone', 'enable_google', 'auto_register', 'replace_wp_login' );
		foreach ( $bools as $k ) {
			if ( array_key_exists( $k, $input ) ) {
				$out[ $k ] = empty( $input[ $k ] ) ? 0 : 1;
			}
		}

		$ints = array( 'otp_length', 'otp_ttl', 'otp_resend_wait', 'otp_max_attempts', 'otp_daily_limit', 'login_page_id' );
		foreach ( $ints as $k ) {
			if ( array_key_exists( $k, $input ) ) {
				$out[ $k ] = max( 0, (int) $input[ $k ] );
			}
		}

		$out['otp_length']       = min( 8, max( 4, (int) $out['otp_length'] ) );
		$out['otp_ttl']          = min( 600, max( 60, (int) $out['otp_ttl'] ) );
		$out['otp_resend_wait']  = min( 300, max( 30, (int) $out['otp_resend_wait'] ) );
		$out['otp_max_attempts'] = min( 20, max( 3, (int) $out['otp_max_attempts'] ) );
		$out['otp_daily_limit']  = min( 50, max( 1, (int) $out['otp_daily_limit'] ) );

		$text = array(
			'sms_provider',
			'sms_username',
			'sms_password',
			'sms_api_key',
			'sms_sender',
			'sms_pattern',
			'sms_pattern_var',
			'sms_message',
			'google_client_id',
			'google_client_secret',
			'form_title',
			'form_subtitle',
			'phone_placeholder',
			'primary_color',
			'default_role',
			'redirect_after',
		);
		foreach ( $text as $k ) {
			if ( array_key_exists( $k, $input ) ) {
				$out[ $k ] = sanitize_text_field( wp_unslash( $input[ $k ] ) );
			}
		}

		$allowed_providers = array( 'melipayamak', 'ippanel', 'kavenegar', 'ghasedak' );
		if ( ! in_array( $out['sms_provider'], $allowed_providers, true ) ) {
			$out['sms_provider'] = 'melipayamak';
		}

		$roles = wp_roles()->get_names();
		if ( ! isset( $roles[ $out['default_role'] ] ) ) {
			$out['default_role'] = 'subscriber';
		}

		if ( ! preg_match( '/^#[0-9A-Fa-f]{6}$/', $out['primary_color'] ) ) {
			$out['primary_color'] = $d['primary_color'];
		}

		update_option( self::OPTION, $out, false );
		return $out;
	}
}
