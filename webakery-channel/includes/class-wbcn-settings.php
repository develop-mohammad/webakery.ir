<?php
defined( 'ABSPATH' ) || exit;

class WBCN_Settings {

	const OPTION = 'wbcn_settings';

	public static function defaults() {
		return array(
			'bot_token'       => '',
			'channel'         => '',
			'proxy_enabled'   => 0,
			'proxy_host'      => '',
			'proxy_port'      => '',
			'proxy_user'      => '',
			'proxy_pass'      => '',
			'signature'       => 1,
			'auto_post'       => 1,
			'auto_photo'      => 1,
			'auto_excerpt'    => 1,
			'post_types'      => array( 'post' ),
			'daily_ideas'     => 0,
			'daily_hour'      => 10,
			'polling'         => 0,
			'webhook_secret'  => '',
			'admin_ids'       => '',
			'welcome'         => 'سلام! اینجا ربات کانال است. عضو شوید تا نکته‌های کاربردی همان مطلب‌های سایت را کوتاه و قابل‌استفاده بگیرید.',
			'join_label'      => 'عضویت در کانال',
			'include_link'    => 1,
			'disable_preview' => 1,
		);
	}

	public static function get() {
		$s = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		if ( empty( $s['webhook_secret'] ) ) {
			$s['webhook_secret'] = wp_generate_password( 20, false, false );
			update_option( self::OPTION, $s, false );
		}
		return $s;
	}

	public static function sanitize( $input ) {
		$d   = self::defaults();
		$in  = is_array( $input ) ? $input : array();
		$out = self::get();

		$out['bot_token'] = preg_replace( '/[^0-9A-Za-z_\-:]/', '', (string) ( $in['bot_token'] ?? $out['bot_token'] ) );
		$out['channel']   = sanitize_text_field( $in['channel'] ?? $out['channel'] );
		$out['channel']   = preg_replace( '/\s+/', '', $out['channel'] );

		$out['proxy_enabled'] = empty( $in['proxy_enabled'] ) ? 0 : 1;
		$out['proxy_host']    = sanitize_text_field( $in['proxy_host'] ?? '' );
		$out['proxy_port']    = preg_replace( '/\D+/', '', (string) ( $in['proxy_port'] ?? '' ) );
		$out['proxy_user']    = sanitize_text_field( $in['proxy_user'] ?? '' );
		if ( isset( $in['proxy_pass'] ) && $in['proxy_pass'] !== '' ) {
			$out['proxy_pass'] = (string) $in['proxy_pass'];
		}

		$out['signature']    = empty( $in['signature'] ) ? 0 : 1;
		$out['auto_post']    = empty( $in['auto_post'] ) ? 0 : 1;
		$out['auto_photo']   = empty( $in['auto_photo'] ) ? 0 : 1;
		$out['auto_excerpt'] = empty( $in['auto_excerpt'] ) ? 0 : 1;
		$out['daily_ideas']  = empty( $in['daily_ideas'] ) ? 0 : 1;
		$out['polling']      = empty( $in['polling'] ) ? 0 : 1;
		$out['include_link'] = empty( $in['include_link'] ) ? 0 : 1;
		$out['disable_preview'] = empty( $in['disable_preview'] ) ? 0 : 1;

		$hour = (int) ( $in['daily_hour'] ?? $d['daily_hour'] );
		$out['daily_hour'] = max( 0, min( 23, $hour ) );

		$types = $in['post_types'] ?? $out['post_types'];
		if ( ! is_array( $types ) ) {
			$types = array_filter( array_map( 'sanitize_key', explode( ',', (string) $types ) ) );
		} else {
			$types = array_values( array_filter( array_map( 'sanitize_key', $types ) ) );
		}
		$allowed = array( 'post', 'page', 'product' );
		$out['post_types'] = array_values( array_intersect( $types, $allowed ) );
		if ( ! $out['post_types'] ) {
			$out['post_types'] = array( 'post' );
		}

		$out['admin_ids'] = preg_replace( '/[^0-9,\s\-]/', '', (string) ( $in['admin_ids'] ?? '' ) );
		$out['welcome']   = sanitize_textarea_field( $in['welcome'] ?? $d['welcome'] );
		$out['join_label'] = sanitize_text_field( $in['join_label'] ?? $d['join_label'] );

		if ( ! empty( $in['webhook_secret'] ) ) {
			$out['webhook_secret'] = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $in['webhook_secret'] );
		}
		if ( $out['webhook_secret'] === '' ) {
			$out['webhook_secret'] = wp_generate_password( 20, false, false );
		}

		return $out;
	}

	public static function channel_username( array $s = array() ) {
		$s       = $s ?: self::get();
		$channel = ltrim( trim( (string) $s['channel'] ), '@' );
		if ( $channel === '' || preg_match( '/^-?\d+$/', $channel ) ) {
			return '';
		}
		return $channel;
	}

	public static function chat_id( array $s = array() ) {
		$s = $s ?: self::get();
		$c = trim( (string) $s['channel'] );
		if ( $c === '' ) {
			return '';
		}
		if ( preg_match( '/^-?\d+$/', $c ) ) {
			return $c;
		}
		return '@' . ltrim( $c, '@' );
	}

	public static function admin_id_list( array $s = array() ) {
		$s   = $s ?: self::get();
		$raw = preg_split( '/[,\s]+/', (string) $s['admin_ids'] );
		$ids = array();
		foreach ( (array) $raw as $id ) {
			$id = trim( (string) $id );
			if ( $id !== '' && preg_match( '/^-?\d+$/', $id ) ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	public static function webhook_url( array $s = array() ) {
		$s = $s ?: self::get();
		return rest_url( 'webakery-channel/v1/hook?secret=' . rawurlencode( $s['webhook_secret'] ) );
	}
}
