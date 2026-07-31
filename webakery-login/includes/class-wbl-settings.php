<?php
defined( 'ABSPATH' ) || exit;

class WBL_Settings {

	const OPTION = 'wbl_settings';

	public static function defaults() {
		return array(
			'enable_phone'         => 1,
			'enable_google'        => 1,
			'auto_register'        => 1,
			'default_role'         => 'subscriber',
			'redirect_after'       => '',
			'replace_wp_login'     => 0,
			'login_page_id'        => 0,
			'otp_length'           => 5,
			'otp_ttl'              => 120,
			'otp_resend_wait'      => 60,
			'otp_max_attempts'     => 5,
			'otp_daily_limit'      => 10,
			'sms_provider'         => 'melipayamak',
			'sms_username'         => '',
			'sms_password'         => '',
			'sms_api_key'          => '',
			'sms_sender'           => '',
			'sms_pattern'          => '',
			'sms_pattern_var'      => 'code',
			'sms_message'          => 'کد ورود شما: {code}',
			'google_client_id'     => '',
			'google_client_secret' => '',
			'form_title'           => 'ورود به حساب',
			'form_subtitle'        => 'شماره موبایل یا حساب گوگل',
			'phone_placeholder'    => '۰۹۱۲۳۴۵۶۷۸۹',
			'primary_color'        => '#0d9488',
			// ظاهر / قالب
			'template_layout'      => 'split',
			'animation_style'      => 'hybrid',
			'show_phone_visual'    => 1,
			'brand_headline'       => 'ورود در چند ثانیه',
			'brand_text'           => 'با شماره موبایل یا جیمیل وارد شوید — بدون رمز پیچیده.',
			'glass_blur'           => 18,
			'glass_radius'         => 28,
			'panel_color_a'        => '#0b3d3a',
			'panel_color_b'        => '#0f766e',
			'custom_css'           => '',
		);
	}

	public static function layouts() {
		return array(
			'form'     => 'فقط فرم شیشه‌ای',
			'split'    => 'اسپلیت (برند + گوشی OTP + فرم)',
			'centered' => 'فرم وسط‌چین روی اتمسفر',
		);
	}

	public static function animations() {
		return array(
			'ios'      => 'iOS — اسپرینگ نرم و فشار دکمه',
			'telegram' => 'تلگرام — حباب پیام و اسلاید سریع',
			'hybrid'   => 'ترکیبی (پیشنهادی)',
			'none'     => 'بدون انیمیشن',
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

		$bools = array( 'enable_phone', 'enable_google', 'auto_register', 'replace_wp_login', 'show_phone_visual' );
		foreach ( $bools as $k ) {
			if ( array_key_exists( $k, $input ) ) {
				$out[ $k ] = empty( $input[ $k ] ) ? 0 : 1;
			}
		}

		$ints = array( 'otp_length', 'otp_ttl', 'otp_resend_wait', 'otp_max_attempts', 'otp_daily_limit', 'login_page_id', 'glass_blur', 'glass_radius' );
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
		$out['glass_blur']       = min( 40, max( 6, (int) $out['glass_blur'] ) );
		$out['glass_radius']     = min( 40, max( 8, (int) $out['glass_radius'] ) );

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
			'template_layout',
			'animation_style',
			'brand_headline',
			'brand_text',
			'panel_color_a',
			'panel_color_b',
		);
		foreach ( $text as $k ) {
			if ( array_key_exists( $k, $input ) ) {
				$out[ $k ] = sanitize_text_field( wp_unslash( $input[ $k ] ) );
			}
		}

		if ( array_key_exists( 'custom_css', $input ) ) {
			$css = wp_unslash( $input['custom_css'] );
			$css = wp_strip_all_tags( $css );
			$out['custom_css'] = $css;
		}

		$allowed_providers = array( 'melipayamak', 'ippanel', 'kavenegar', 'ghasedak' );
		if ( ! in_array( $out['sms_provider'], $allowed_providers, true ) ) {
			$out['sms_provider'] = 'melipayamak';
		}
		if ( ! isset( self::layouts()[ $out['template_layout'] ] ) ) {
			$out['template_layout'] = 'split';
		}
		if ( ! isset( self::animations()[ $out['animation_style'] ] ) ) {
			$out['animation_style'] = 'hybrid';
		}

		$roles = wp_roles()->get_names();
		if ( ! isset( $roles[ $out['default_role'] ] ) ) {
			$out['default_role'] = 'subscriber';
		}

		foreach ( array( 'primary_color', 'panel_color_a', 'panel_color_b' ) as $ck ) {
			if ( ! preg_match( '/^#[0-9A-Fa-f]{6}$/', $out[ $ck ] ) ) {
				$out[ $ck ] = $d[ $ck ];
			}
		}

		update_option( self::OPTION, $out, false );
		return $out;
	}
}
