<?php
defined( 'ABSPATH' ) || exit;

/**
 * اعلان پیام جدید چت به ایمیل / تلگرام / واتساپ.
 */
class WBCB_Notify {

	/**
	 * ارسال اعلان برای پیام بازدیدکننده.
	 *
	 * @param array  $conv
	 * @param string $body
	 */
	public static function new_visitor_message( array $conv, $body ) {
		$key = 'wbcb_notify_' . (int) ( $conv['id'] ?? 0 );
		// جلوگیری از اسپم اعلان برای پیام‌های پشت‌سرهم همان گفتگو
		if ( get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, 45 ); // ۴۵ ثانیه

		$text = self::build_text( $conv, $body );
		$s    = WBCB_Settings::get();

		if ( ! empty( $s['email_notify'] ) ) {
			self::send_email( $conv, $body, $text );
		}
		if ( ! empty( $s['tg_notify'] ) ) {
			self::send_telegram( $text, $s );
		}
		if ( ! empty( $s['wa_notify'] ) ) {
			self::send_whatsapp( $text, $s );
		}
	}

	/**
	 * تست دستی از تنظیمات.
	 *
	 * @param string $channel email|telegram|whatsapp|all
	 * @return array{ok:bool,message:string}
	 */
	public static function send_test( $channel = 'all' ) {
		$s    = WBCB_Settings::get();
		$text = "🧪 تست اعلان چت باکس\nسایت: " . home_url( '/' ) . "\nزمان: " . current_time( 'Y-m-d H:i' );
		$ok   = true;
		$msgs = array();

		if ( in_array( $channel, array( 'all', 'email' ), true ) && ! empty( $s['email_notify'] ) ) {
			$to = ! empty( $s['email_to'] ) ? $s['email_to'] : get_option( 'admin_email' );
			if ( is_email( $to ) ) {
				$sent = wp_mail( $to, '[چت باکس] تست اعلان', $text );
				$msgs[] = $sent ? 'ایمیل ارسال شد.' : 'ایمیل ناموفق بود.';
				$ok     = $ok && $sent;
			} else {
				$msgs[] = 'ایمیل مقصد نامعتبر است.';
				$ok     = false;
			}
		}

		if ( in_array( $channel, array( 'all', 'telegram' ), true ) ) {
			if ( 'telegram' === $channel || ! empty( $s['tg_notify'] ) || ( $s['tg_bot_token'] && $s['tg_chat_id'] ) ) {
				$res = self::send_telegram( $text, $s, true );
				if ( is_wp_error( $res ) ) {
					$msgs[] = 'تلگرام: ' . $res->get_error_message();
					$ok     = false;
				} else {
					$msgs[] = 'تلگرام ارسال شد.';
				}
			}
		}

		if ( in_array( $channel, array( 'all', 'whatsapp' ), true ) ) {
			$has_wa = ! empty( $s['wa_notify'] ) || ! empty( $s['wa_notify_phone'] ) || ! empty( $s['wa_callmebot_key'] );
			if ( 'whatsapp' === $channel || $has_wa ) {
				$res = self::send_whatsapp( $text, $s, true );
				if ( is_wp_error( $res ) ) {
					$msgs[] = 'واتساپ: ' . $res->get_error_message();
					$ok     = false;
				} else {
					$msgs[] = 'واتساپ ارسال شد.';
				}
			}
		}

		return array(
			'ok'      => $ok,
			'message' => implode( ' ', $msgs ) ?: 'چیزی برای ارسال تنظیم نشده.',
		);
	}

	private static function build_text( array $conv, $body ) {
		$name  = ! empty( $conv['visitor_name'] ) ? $conv['visitor_name'] : 'مهمان';
		$email = ! empty( $conv['visitor_email'] ) ? $conv['visitor_email'] : '';
		$page  = ! empty( $conv['page_url'] ) ? $conv['page_url'] : '';
		$link  = admin_url( 'admin.php?page=webakery-chat-box&conv=' . (int) ( $conv['id'] ?? 0 ) );
		$body  = wp_strip_all_tags( (string) $body );
		if ( function_exists( 'mb_substr' ) ) {
			$body = mb_substr( $body, 0, 900 );
		} else {
			$body = substr( $body, 0, 900 );
		}

		$lines   = array();
		$lines[] = '💬 پیام جدید چت باکس';
		$lines[] = 'سایت: ' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$lines[] = 'از: ' . $name;
		if ( $email ) {
			$lines[] = 'ایمیل: ' . $email;
		}
		if ( $page ) {
			$lines[] = 'صفحه: ' . $page;
		}
		$lines[] = '';
		$lines[] = $body;
		$lines[] = '';
		$lines[] = 'پاسخ در پیشخوان:';
		$lines[] = $link;

		return implode( "\n", $lines );
	}

	private static function send_email( array $conv, $body, $text ) {
		$s  = WBCB_Settings::get();
		$to = ! empty( $s['email_to'] ) ? $s['email_to'] : get_option( 'admin_email' );
		if ( ! is_email( $to ) ) {
			return;
		}
		$name = ! empty( $conv['visitor_name'] ) ? $conv['visitor_name'] : 'مهمان';
		$subj = sprintf(
			'[%s] پیام جدید چت از %s',
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$name
		);
		wp_mail( $to, $subj, $text );
	}

	/**
	 * Telegram Bot API: https://api.telegram.org/bot{TOKEN}/sendMessage
	 *
	 * @param string $text
	 * @param array  $s
	 * @param bool   $force
	 * @return true|\WP_Error
	 */
	public static function send_telegram( $text, array $s = array(), $force = false ) {
		$s = $s ?: WBCB_Settings::get();
		if ( ! $force && empty( $s['tg_notify'] ) ) {
			return new WP_Error( 'off', 'اعلان تلگرام خاموش است.' );
		}
		$token  = trim( (string) ( $s['tg_bot_token'] ?? '' ) );
		$chat   = trim( (string) ( $s['tg_chat_id'] ?? '' ) );
		if ( '' === $token || '' === $chat ) {
			return new WP_Error( 'cfg', 'توکن ربات یا Chat ID تلگرام خالی است.' );
		}

		$url  = 'https://api.telegram.org/bot' . rawurlencode( $token ) . '/sendMessage';
		$resp = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'body'    => array(
					'chat_id'                  => $chat,
					'text'                     => $text,
					'disable_web_page_preview' => true,
				),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		$data = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		if ( $code >= 200 && $code < 300 && ! empty( $data['ok'] ) ) {
			return true;
		}
		$err = is_array( $data ) ? ( $data['description'] ?? 'خطای تلگرام' ) : 'خطای تلگرام';
		return new WP_Error( 'tg', $err );
	}

	/**
	 * واتساپ اعلان:
	 * 1) CallMeBot (رایگان شخصی) — نیاز به apikey
	 * 2) یا لینک wa.me برای fallback در پاسخ تست
	 *
	 * CallMeBot: پیام به +34 644 66 92 45 با متن "I allow callmebot to send me messages"
	 * سپس API KEY دریافت می‌شود.
	 *
	 * @param string $text
	 * @param array  $s
	 * @param bool   $force
	 * @return true|\WP_Error
	 */
	public static function send_whatsapp( $text, array $s = array(), $force = false ) {
		$s = $s ?: WBCB_Settings::get();
		if ( ! $force && empty( $s['wa_notify'] ) ) {
			return new WP_Error( 'off', 'اعلان واتساپ خاموش است.' );
		}

		$phone = preg_replace( '/\D+/', '', (string) ( $s['wa_notify_phone'] ?? $s['whatsapp'] ?? '' ) );
		$phone = ltrim( (string) $phone, '0' );
		$key   = trim( (string) ( $s['wa_callmebot_key'] ?? '' ) );
		$mode  = sanitize_key( $s['wa_provider'] ?? 'callmebot' );

		if ( '' === $phone ) {
			return new WP_Error( 'cfg', 'شماره واتساپ اعلان خالی است.' );
		}

		if ( 'callmebot' === $mode ) {
			if ( '' === $key ) {
				return new WP_Error( 'cfg', 'API Key کالمی‌بات خالی است. راهنما در تنظیمات را ببینید.' );
			}
			$url  = add_query_arg(
				array(
					'phone'  => $phone,
					'text'   => rawurlencode( $text ),
					'apikey' => $key,
				),
				'https://api.callmebot.com/whatsapp.php'
			);
			// CallMeBot expects query already encoded in some cases — use unencoded text param via body-less GET
			$url  = 'https://api.callmebot.com/whatsapp.php?phone=' . rawurlencode( $phone )
				. '&text=' . rawurlencode( $text )
				. '&apikey=' . rawurlencode( $key );
			$resp = wp_remote_get( $url, array( 'timeout' => 20 ) );
			if ( is_wp_error( $resp ) ) {
				return $resp;
			}
			$code = (int) wp_remote_retrieve_response_code( $resp );
			$body = (string) wp_remote_retrieve_body( $resp );
			if ( $code >= 200 && $code < 300 && false === stripos( $body, 'ERROR' ) ) {
				return true;
			}
			return new WP_Error( 'wa', 'واتساپ: ' . wp_strip_all_tags( substr( $body, 0, 200 ) ) );
		}

		if ( 'ultramsg' === $mode ) {
			$instance = trim( (string) ( $s['wa_ultramsg_instance'] ?? '' ) );
			$token    = trim( (string) ( $s['wa_ultramsg_token'] ?? '' ) );
			if ( '' === $instance || '' === $token ) {
				return new WP_Error( 'cfg', 'Instance یا Token اولترامسج خالی است.' );
			}
			$url  = sprintf( 'https://api.ultramsg.com/%s/messages/chat', rawurlencode( $instance ) );
			$resp = wp_remote_post(
				$url,
				array(
					'timeout' => 20,
					'body'    => array(
						'token' => $token,
						'to'    => '+' . $phone,
						'body'  => $text,
					),
				)
			);
			if ( is_wp_error( $resp ) ) {
				return $resp;
			}
			$code = (int) wp_remote_retrieve_response_code( $resp );
			$data = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
			if ( $code >= 200 && $code < 300 ) {
				return true;
			}
			$err = is_array( $data ) ? wp_json_encode( $data ) : 'خطای Ultramsg';
			return new WP_Error( 'wa', $err );
		}

		return new WP_Error( 'cfg', 'ارائه‌دهنده واتساپ نامعتبر است.' );
	}
}
