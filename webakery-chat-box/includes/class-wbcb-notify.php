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
		if ( get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, 45 );

		$text = self::build_text( $conv, $body );
		$s    = WBCB_Settings::get();

		if ( ! empty( $s['email_notify'] ) ) {
			self::send_email( $conv, $body, $text );
		}
		if ( ! empty( $s['tg_notify'] ) ) {
			self::send_telegram( $text, $s, false, $conv );
		}
		if ( ! empty( $s['wa_notify'] ) ) {
			$wa_text = $text;
			if ( ! empty( $conv['product_image'] ) ) {
				$wa_text .= "\nعکس محصول: " . $conv['product_image'];
			}
			self::send_whatsapp( $wa_text, $s );
		}
	}

	/**
	 * @param string $channel email|telegram|whatsapp|all
	 * @return array{ok:bool,message:string}
	 */
	public static function send_test( $channel = 'all' ) {
		$s    = WBCB_Settings::get();
		$text = "🧪 تست اعلان چت باکس\nسایت: " . home_url( '/' ) . "\nزمان: " . current_time( 'Y-m-d H:i' );
		$ok   = true;
		$msgs = array();

		if ( in_array( $channel, array( 'all', 'email' ), true ) && ( ! empty( $s['email_notify'] ) || 'email' === $channel ) ) {
			$to = ! empty( $s['email_to'] ) ? $s['email_to'] : get_option( 'admin_email' );
			if ( is_email( $to ) ) {
				$fake = array(
					'id'            => 0,
					'visitor_name'  => 'تست',
					'visitor_email' => $to,
					'product_name'  => 'محصول نمونه',
					'product_url'   => home_url( '/' ),
					'product_image' => '',
					'page_url'      => home_url( '/' ),
					'page_title'    => 'تست',
				);
				$sent   = self::send_email( $fake, 'این یک پیام تست است.', $text );
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
		$name   = ! empty( $conv['visitor_name'] ) ? $conv['visitor_name'] : 'مهمان';
		$email  = ! empty( $conv['visitor_email'] ) ? $conv['visitor_email'] : '';
		$page   = ! empty( $conv['page_url'] ) ? $conv['page_url'] : '';
		$ptitle = ! empty( $conv['page_title'] ) ? $conv['page_title'] : '';
		$pname  = ! empty( $conv['product_name'] ) ? $conv['product_name'] : '';
		$purl   = ! empty( $conv['product_url'] ) ? $conv['product_url'] : '';
		$link   = admin_url( 'admin.php?page=webakery-chat-box&conv=' . (int) ( $conv['id'] ?? 0 ) );
		$body   = wp_strip_all_tags( (string) $body );
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
		if ( $pname ) {
			$lines[] = '🛒 محصول: ' . $pname;
			if ( $purl ) {
				$lines[] = 'لینک محصول: ' . $purl;
			}
		} elseif ( $ptitle || $page ) {
			if ( $ptitle ) {
				$lines[] = '📄 صفحه: ' . $ptitle;
			}
			if ( $page ) {
				$lines[] = 'لینک صفحه: ' . $page;
			}
		}
		$lines[] = '';
		$lines[] = $body;
		$lines[] = '';
		$lines[] = 'پاسخ در پیشخوان:';
		$lines[] = $link;

		return implode( "\n", $lines );
	}

	/**
	 * @return bool
	 */
	private static function send_email( array $conv, $body, $text ) {
		$s  = WBCB_Settings::get();
		$to = ! empty( $s['email_to'] ) ? $s['email_to'] : get_option( 'admin_email' );
		if ( ! is_email( $to ) ) {
			return false;
		}
		$name = ! empty( $conv['visitor_name'] ) ? $conv['visitor_name'] : 'مهمان';
		$subj = sprintf(
			'[%s] پیام جدید چت از %s',
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$name
		);
		if ( ! empty( $conv['product_name'] ) ) {
			$subj .= ' — ' . $conv['product_name'];
		}

		$html = self::build_html_email( $conv, $body );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		return (bool) wp_mail( $to, $subj, $html, $headers );
	}

	private static function build_html_email( array $conv, $body ) {
		$name   = esc_html( ! empty( $conv['visitor_name'] ) ? $conv['visitor_name'] : 'مهمان' );
		$email  = esc_html( (string) ( $conv['visitor_email'] ?? '' ) );
		$pname  = esc_html( (string) ( $conv['product_name'] ?? '' ) );
		$purl   = esc_url( (string) ( $conv['product_url'] ?? '' ) );
		$pimg   = esc_url( (string) ( $conv['product_image'] ?? '' ) );
		$page   = esc_url( (string) ( $conv['page_url'] ?? '' ) );
		$ptitle = esc_html( (string) ( $conv['page_title'] ?? '' ) );
		$link   = esc_url( admin_url( 'admin.php?page=webakery-chat-box&conv=' . (int) ( $conv['id'] ?? 0 ) ) );
		$msg    = nl2br( esc_html( wp_strip_all_tags( (string) $body ) ) );
		$site   = esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );

		$product_block = '';
		if ( $pname || $pimg ) {
			$img_html = $pimg
				? '<img src="' . $pimg . '" alt="" width="120" height="120" style="display:block;border-radius:12px;object-fit:cover;border:1px solid #e2e8f0" />'
				: '';
			$product_block = '<tr><td style="padding:12px 0">'
				. '<table cellpadding="0" cellspacing="0" style="background:#f5f3ff;border-radius:14px;padding:12px;width:100%"><tr>'
				. ( $img_html ? '<td style="width:130px;vertical-align:top;padding:8px">' . $img_html . '</td>' : '' )
				. '<td style="vertical-align:top;padding:8px;font-family:Tahoma,Arial,sans-serif">'
				. '<div style="font-size:12px;color:#64748b">🛒 محصول در حال مشاهده</div>'
				. '<div style="font-size:16px;font-weight:700;color:#0f172a;margin:4px 0 8px">' . ( $pname ?: 'محصول' ) . '</div>'
				. ( $purl ? '<a href="' . $purl . '" style="color:#6d28d9;font-size:13px">مشاهده محصول</a>' : '' )
				. '</td></tr></table></td></tr>';
		} elseif ( $page ) {
			$product_block = '<tr><td style="padding:8px 0;font-family:Tahoma,Arial,sans-serif;font-size:13px;color:#64748b">صفحه: '
				. ( $ptitle ? $ptitle . ' — ' : '' )
				. '<a href="' . $page . '">' . $page . '</a></td></tr>';
		}

		return '<!DOCTYPE html><html lang="fa" dir="rtl"><body style="margin:0;background:#f8fafc;padding:24px">'
			. '<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">'
			. '<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;padding:24px;border:1px solid #e2e8f0">'
			. '<tr><td style="font-family:Tahoma,Arial,sans-serif">'
			. '<div style="font-size:12px;color:#6d28d9;font-weight:700">چت باکس · ' . $site . '</div>'
			. '<h1 style="margin:8px 0 4px;font-size:20px;color:#0f172a">پیام جدید از ' . $name . '</h1>'
			. ( $email ? '<div style="color:#64748b;font-size:13px;margin-bottom:12px">' . $email . '</div>' : '' )
			. $product_block
			. '<tr><td style="padding:14px 0"><div style="background:#f8fafc;border-radius:12px;padding:14px;font-size:14px;line-height:1.8;color:#0f172a">'
			. $msg
			. '</div></td></tr>'
			. '<tr><td style="padding-top:8px"><a href="' . $link . '" style="display:inline-block;background:#6d28d9;color:#fff;text-decoration:none;padding:12px 18px;border-radius:10px;font-weight:700;font-family:Tahoma,Arial,sans-serif">پاسخ در پیشخوان</a></td></tr>'
			. '</td></tr></table></td></tr></table></body></html>';
	}

	/**
	 * @param string     $text
	 * @param array      $s
	 * @param bool       $force
	 * @param array|null $conv
	 * @return true|\WP_Error
	 */
	public static function send_telegram( $text, array $s = array(), $force = false, $conv = null ) {
		$s = $s ?: WBCB_Settings::get();
		if ( ! $force && empty( $s['tg_notify'] ) ) {
			return new WP_Error( 'off', 'اعلان تلگرام خاموش است.' );
		}
		$token = trim( (string) ( $s['tg_bot_token'] ?? '' ) );
		$chat  = trim( (string) ( $s['tg_chat_id'] ?? '' ) );
		if ( '' === $token || '' === $chat ) {
			return new WP_Error( 'cfg', 'توکن ربات یا Chat ID تلگرام خالی است.' );
		}

		$photo = '';
		if ( is_array( $conv ) && ! empty( $conv['product_image'] ) ) {
			$photo = esc_url_raw( (string) $conv['product_image'] );
		}

		if ( $photo ) {
			$url  = 'https://api.telegram.org/bot' . rawurlencode( $token ) . '/sendPhoto';
			$resp = wp_remote_post(
				$url,
				array(
					'timeout' => 20,
					'body'    => array(
						'chat_id'    => $chat,
						'photo'      => $photo,
						'caption'    => function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 1000 ) : substr( $text, 0, 1000 ),
					),
				)
			);
			if ( ! is_wp_error( $resp ) ) {
				$code = (int) wp_remote_retrieve_response_code( $resp );
				$data = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
				if ( $code >= 200 && $code < 300 && ! empty( $data['ok'] ) ) {
					return true;
				}
			}
			// اگر sendPhoto شکست خورد، به متن برگرد
		}

		$url  = 'https://api.telegram.org/bot' . rawurlencode( $token ) . '/sendMessage';
		$resp = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'body'    => array(
					'chat_id'                  => $chat,
					'text'                     => $text,
					'disable_web_page_preview' => empty( $photo ),
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
			if ( $code >= 200 && $code < 300 ) {
				return true;
			}
			$data = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
			$err  = is_array( $data ) ? wp_json_encode( $data ) : 'خطای Ultramsg';
			return new WP_Error( 'wa', $err );
		}

		return new WP_Error( 'cfg', 'ارائه‌دهنده واتساپ نامعتبر است.' );
	}
}
