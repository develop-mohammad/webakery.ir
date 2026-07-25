<?php
defined( 'ABSPATH' ) || exit;

class WBCB_Settings {

	const OPTION = 'wbcb_settings';

	public static function defaults() {
		return array(
			'enabled'                => 1,
			'title'                  => 'پشتیبانی آنلاین',
			'subtitle'               => 'معمولاً در کمتر از چند دقیقه پاسخ می‌دهیم',
			'welcome'                => 'سلام! 👋 چطور می‌تونیم کمکتون کنیم؟',
			'placeholder'            => 'پیام خود را بنویسید…',
			'ask_name'               => 1,
			'ask_email'              => 0,
			'primary'                => '#6d28d9',
			'position'               => 'left',
			'show_on'                => 'all',
			'hide_logged_in_admins'  => 0,
			'email_notify'           => 1,
			'email_to'               => '',
			'whatsapp'               => '',
			'telegram'               => '',
			'tg_notify'              => 0,
			'tg_bot_token'           => '',
			'tg_chat_id'             => '',
			'wa_notify'              => 0,
			'wa_provider'            => 'callmebot',
			'wa_notify_phone'        => '',
			'wa_callmebot_key'       => '',
			'wa_ultramsg_instance'   => '',
			'wa_ultramsg_token'      => '',
			'offline_note'           => 'در حال حاضر آفلاین هستیم؛ پیام بگذارید تا برگردیم پاسخ می‌دهیم.',
			'business_hours_enabled' => 0,
			'business_hours'         => '9-18',
			'auto_reply'             => '',
		);
	}

	public static function get() {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	public static function sanitize( $input ) {
		$d   = self::defaults();
		$out = array();

		$out['enabled'] = ! empty( $input['enabled'] ) ? 1 : 0;
		$out['title']   = sanitize_text_field( $input['title'] ?? $d['title'] );
		$out['subtitle'] = sanitize_text_field( $input['subtitle'] ?? $d['subtitle'] );
		$out['welcome'] = sanitize_textarea_field( $input['welcome'] ?? $d['welcome'] );
		$out['placeholder'] = sanitize_text_field( $input['placeholder'] ?? $d['placeholder'] );
		$out['ask_name']  = ! empty( $input['ask_name'] ) ? 1 : 0;
		$out['ask_email'] = ! empty( $input['ask_email'] ) ? 1 : 0;

		$color = sanitize_hex_color( $input['primary'] ?? $d['primary'] );
		$out['primary'] = $color ? $color : $d['primary'];

		$pos = sanitize_key( $input['position'] ?? 'left' );
		$out['position'] = in_array( $pos, array( 'left', 'right' ), true ) ? $pos : 'left';

		$show = sanitize_key( $input['show_on'] ?? 'all' );
		$out['show_on'] = in_array( $show, array( 'all', 'front', 'shop' ), true ) ? $show : 'all';

		$out['hide_logged_in_admins'] = ! empty( $input['hide_logged_in_admins'] ) ? 1 : 0;
		$out['email_notify'] = ! empty( $input['email_notify'] ) ? 1 : 0;
		$out['email_to']     = sanitize_email( $input['email_to'] ?? '' );

		$out['whatsapp'] = preg_replace( '/[^0-9+]/', '', (string) ( $input['whatsapp'] ?? '' ) );
		$out['telegram'] = sanitize_text_field( $input['telegram'] ?? '' );

		$out['tg_notify']    = ! empty( $input['tg_notify'] ) ? 1 : 0;
		$out['tg_bot_token'] = sanitize_text_field( $input['tg_bot_token'] ?? '' );
		$out['tg_chat_id']   = sanitize_text_field( $input['tg_chat_id'] ?? '' );

		$out['wa_notify']       = ! empty( $input['wa_notify'] ) ? 1 : 0;
		$wa_provider            = sanitize_key( $input['wa_provider'] ?? 'callmebot' );
		$out['wa_provider']     = in_array( $wa_provider, array( 'callmebot', 'ultramsg' ), true ) ? $wa_provider : 'callmebot';
		$out['wa_notify_phone'] = preg_replace( '/\D+/', '', (string) ( $input['wa_notify_phone'] ?? '' ) );
		$out['wa_callmebot_key'] = sanitize_text_field( $input['wa_callmebot_key'] ?? '' );
		$out['wa_ultramsg_instance'] = sanitize_text_field( $input['wa_ultramsg_instance'] ?? '' );
		$out['wa_ultramsg_token']    = sanitize_text_field( $input['wa_ultramsg_token'] ?? '' );

		$out['offline_note'] = sanitize_textarea_field( $input['offline_note'] ?? $d['offline_note'] );
		$out['business_hours_enabled'] = ! empty( $input['business_hours_enabled'] ) ? 1 : 0;
		$out['business_hours'] = sanitize_text_field( $input['business_hours'] ?? '9-18' );
		$out['auto_reply'] = sanitize_textarea_field( $input['auto_reply'] ?? '' );

		return $out;
	}

	/**
	 * دلیل مخفی بودن ویجت — خالی یعنی باید نمایش داده شود.
	 *
	 * @return string
	 */
	public static function widget_hide_reason() {
		$s = self::get();
		if ( empty( $s['enabled'] ) ) {
			return 'disabled';
		}
		if ( is_admin() ) {
			return 'wp_admin';
		}
		if ( class_exists( 'WBCB_Plugin' ) && ! WBCB_Plugin::is_licensed() ) {
			return 'license';
		}
		if ( ! empty( $s['hide_logged_in_admins'] ) && current_user_can( 'manage_options' ) ) {
			return 'admin_logged_in';
		}
		$show = $s['show_on'] ?? 'all';
		if ( 'shop' === $show ) {
			$ok = function_exists( 'is_woocommerce' ) && ( is_shop() || is_product() || is_cart() || is_checkout() || is_account_page() );
			return $ok ? '' : 'show_on_shop';
		}
		if ( 'front' === $show ) {
			return ( is_front_page() || is_home() ) ? '' : 'show_on_front';
		}
		return '';
	}

	public static function should_show_widget() {
		return self::widget_hide_reason() === '';
	}

	public static function is_online() {
		$s = self::get();
		if ( empty( $s['business_hours_enabled'] ) ) {
			return true;
		}
		$range = (string) ( $s['business_hours'] ?? '9-18' );
		if ( ! preg_match( '/^(\d{1,2})\s*-\s*(\d{1,2})$/', $range, $m ) ) {
			return true;
		}
		$start = (int) $m[1];
		$end   = (int) $m[2];
		$hour  = (int) current_time( 'G' );
		if ( $start <= $end ) {
			return $hour >= $start && $hour < $end;
		}
		return $hour >= $start || $hour < $end;
	}
}
