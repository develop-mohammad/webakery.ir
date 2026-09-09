<?php
defined( 'ABSPATH' ) || exit;

/**
 * کلاینت Telegram Bot API با پروکسی اختیاری (هاست ایران).
 */
class WBCN_Telegram {

	/**
	 * @param array<string,mixed> $params
	 * @return array{ok:bool,result?:mixed,description?:string}|\WP_Error
	 */
	public static function api( $method, array $params = array(), array $s = array() ) {
		$s     = $s ?: WBCN_Settings::get();
		$token = trim( (string) $s['bot_token'] );
		if ( $token === '' ) {
			return new WP_Error( 'cfg', 'توکن ربات خالی است. از @BotFather توکن بگیرید.' );
		}

		$url = 'https://api.telegram.org/bot' . rawurlencode( $token ) . '/' . ltrim( (string) $method, '/' );

		$proxy_cb = null;
		if ( ! empty( $s['proxy_enabled'] ) && ! empty( $s['proxy_host'] ) ) {
			$proxy_cb = function ( $handle ) use ( $s ) {
				if ( ! is_resource( $handle ) && ! ( $handle instanceof CurlHandle ) ) {
					return;
				}
				$host = trim( (string) $s['proxy_host'] );
				$port = (int) $s['proxy_port'];
				curl_setopt( $handle, CURLOPT_PROXY, $host );
				if ( $port > 0 ) {
					curl_setopt( $handle, CURLOPT_PROXYPORT, $port );
				}
				$user = trim( (string) $s['proxy_user'] );
				$pass = (string) $s['proxy_pass'];
				if ( $user !== '' ) {
					curl_setopt( $handle, CURLOPT_PROXYUSERPWD, $user . ':' . $pass );
				}
			};
			add_action( 'http_api_curl', $proxy_cb, 10, 1 );
		}

		$resp = wp_remote_post(
			$url,
			array(
				'timeout' => 25,
				'body'    => $params,
			)
		);

		if ( $proxy_cb ) {
			remove_action( 'http_api_curl', $proxy_cb, 10 );
		}

		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$data = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'tg', 'پاسخ تلگرام خوانده نشد. اگر هاست ایران است، پروکسی را روشن کنید. کد HTTP: ' . $code );
		}
		if ( empty( $data['ok'] ) ) {
			$err = (string) ( $data['description'] ?? 'خطای تلگرام' );
			return new WP_Error( 'tg', $err );
		}
		return $data;
	}

	/**
	 * @return true|\WP_Error
	 */
	public static function send_text( $text, $chat_id = '', array $extra = array(), array $s = array() ) {
		$s       = $s ?: WBCN_Settings::get();
		$chat_id = $chat_id !== '' ? $chat_id : WBCN_Settings::chat_id( $s );
		if ( $chat_id === '' ) {
			return new WP_Error( 'cfg', 'آیدی یا یوزرنیم کانال را در تنظیمات وارد کنید.' );
		}
		$body = array_merge(
			array(
				'chat_id'                  => $chat_id,
				'text'                     => $text,
				'parse_mode'               => 'HTML',
				'disable_web_page_preview' => ! empty( $s['disable_preview'] ) ? 'true' : 'false',
			),
			$extra
		);
		$res = self::api( 'sendMessage', $body, $s );
		return is_wp_error( $res ) ? $res : true;
	}

	/**
	 * @param string[] $parts
	 * @return true|\WP_Error
	 */
	public static function send_parts( array $parts, $chat_id = '', array $s = array() ) {
		$last = true;
		foreach ( $parts as $part ) {
			$part = trim( (string) $part );
			if ( $part === '' ) {
				continue;
			}
			$last = self::send_text( $part, $chat_id, array(), $s );
			if ( is_wp_error( $last ) ) {
				return $last;
			}
		}
		return $last;
	}

	/**
	 * @return true|\WP_Error
	 */
	public static function send_photo( $photo_url, $caption, $chat_id = '', array $s = array() ) {
		$s       = $s ?: WBCN_Settings::get();
		$chat_id = $chat_id !== '' ? $chat_id : WBCN_Settings::chat_id( $s );
		if ( $chat_id === '' ) {
			return new WP_Error( 'cfg', 'آیدی یا یوزرنیم کانال خالی است.' );
		}
		$caption = WBCN_Ideas::clip( $caption, 1000 );
		$res     = self::api(
			'sendPhoto',
			array(
				'chat_id'    => $chat_id,
				'photo'      => $photo_url,
				'caption'    => $caption,
				'parse_mode' => 'HTML',
			),
			$s
		);
		if ( is_wp_error( $res ) ) {
			return self::send_text( $caption, $chat_id, array(), $s );
		}
		return true;
	}

	/**
	 * @return array{ok:bool,username?:string,name?:string}|\WP_Error
	 */
	public static function get_me( array $s = array() ) {
		$res = self::api( 'getMe', array(), $s );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$r = $res['result'] ?? array();
		return array(
			'ok'       => true,
			'username' => (string) ( $r['username'] ?? '' ),
			'name'     => (string) ( $r['first_name'] ?? '' ),
			'id'       => (string) ( $r['id'] ?? '' ),
		);
	}

	/**
	 * @return true|\WP_Error
	 */
	public static function set_webhook( $url, array $s = array() ) {
		$res = self::api(
			'setWebhook',
			array(
				'url'             => $url,
				'drop_pending_updates' => 'true',
			),
			$s
		);
		return is_wp_error( $res ) ? $res : true;
	}

	/**
	 * @return true|\WP_Error
	 */
	public static function delete_webhook( array $s = array() ) {
		$res = self::api( 'deleteWebhook', array( 'drop_pending_updates' => 'true' ), $s );
		return is_wp_error( $res ) ? $res : true;
	}

	/**
	 * @return array|\WP_Error
	 */
	public static function get_updates( $offset = 0, array $s = array() ) {
		$res = self::api(
			'getUpdates',
			array(
				'offset'  => (int) $offset,
				'timeout' => 0,
				'limit'   => 20,
			),
			$s
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return is_array( $res['result'] ?? null ) ? $res['result'] : array();
	}

	/**
	 * دکمه‌های شیشه‌ای عضویت در کانال.
	 *
	 * @return array<string,mixed>
	 */
	public static function join_keyboard( array $s = array() ) {
		$s        = $s ?: WBCN_Settings::get();
		$user     = WBCN_Settings::channel_username( $s );
		$label    = $s['join_label'] ? $s['join_label'] : 'عضویت در کانال';
		$buttons  = array();
		if ( $user ) {
			$buttons[] = array(
				array(
					'text' => $label,
					'url'  => 'https://t.me/' . rawurlencode( $user ),
				),
			);
		}
		$site = home_url( '/' );
		$buttons[] = array(
			array(
				'text' => 'وب‌سایت',
				'url'  => $site,
			),
		);
		return array(
			'inline_keyboard' => $buttons,
		);
	}
}
