<?php
defined( 'ABSPATH' ) || exit;

/**
 * وب‌هوک و دستورهای ربات برای اعضا و مدیر کانال.
 */
class WBCN_Webhook {

	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			'webakery-channel/v1',
			'/hook',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * @param WP_REST_Request $req
	 * @return WP_REST_Response
	 */
	public static function rest( $req ) {
		$s      = WBCN_Settings::get();
		$secret = (string) $req->get_param( 'secret' );
		if ( $secret === '' || ! hash_equals( (string) $s['webhook_secret'], $secret ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 403 );
		}
		$body = $req->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = json_decode( (string) $req->get_body(), true );
		}
		if ( is_array( $body ) ) {
			self::handle_update( $body, $s );
		}
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * @param array<string,mixed> $update
	 */
	public static function handle_update( array $update, array $s = array() ) {
		$s = $s ?: WBCN_Settings::get();
		if ( empty( $s['bot_token'] ) ) {
			return;
		}

		$msg = $update['message'] ?? ( $update['channel_post'] ?? null );
		if ( ! is_array( $msg ) ) {
			return;
		}

		$chat_id = (string) ( $msg['chat']['id'] ?? '' );
		$type    = (string) ( $msg['chat']['type'] ?? '' );
		$text    = trim( (string) ( $msg['text'] ?? '' ) );
		$from    = (string) ( $msg['from']['id'] ?? '' );
		if ( $text === '' || $chat_id === '' ) {
			return;
		}

		// در کانال چیزی جواب نده — فقط چت خصوصی ربات.
		if ( in_array( $type, array( 'channel', 'group', 'supergroup' ), true ) ) {
			return;
		}

		$cmd = self::command( $text );
		if ( $cmd === '' ) {
			$cmd = 'start';
		}

		if ( $cmd === 'idea' || $cmd === 'send' || strpos( $cmd, 'send_' ) === 0 ) {
			if ( ! self::is_admin( $from, $s ) ) {
				self::reply( $chat_id, 'این دستور فقط برای مدیر کانال است. آیدی تلگرام‌تان را در تنظیمات افزونه بگذارید.', $s );
				return;
			}
		}

		switch ( $cmd ) {
			case 'start':
			case 'help':
				self::cmd_start( $chat_id, $s );
				break;
			case 'latest':
				self::cmd_latest( $chat_id, $s );
				break;
			case 'idea':
				self::cmd_idea( $chat_id, $s, false );
				break;
			case 'send':
				self::cmd_idea( $chat_id, $s, true );
				break;
			default:
				if ( strpos( $text, '/' ) !== 0 && self::len( $text ) >= 2 ) {
					self::cmd_search( $chat_id, $text, $s );
				} else {
					self::cmd_start( $chat_id, $s );
				}
		}
	}

	public static function command( $text ) {
		if ( ! preg_match( '/^\/([a-zA-Z0-9_]+)/', trim( (string) $text ), $m ) ) {
			return '';
		}
		return strtolower( $m[1] );
	}

	public static function is_admin( $from, array $s ) {
		$ids = WBCN_Settings::admin_id_list( $s );
		return $from !== '' && in_array( (string) $from, $ids, true );
	}

	private static function reply( $chat_id, $text, array $s, $keyboard = null ) {
		$extra = array();
		if ( is_array( $keyboard ) ) {
			$extra['reply_markup'] = wp_json_encode( $keyboard );
		}
		WBCN_Telegram::send_text( $text, $chat_id, $extra, $s );
	}

	private static function cmd_start( $chat_id, array $s ) {
		$welcome = $s['welcome'] !== '' ? $s['welcome'] : 'سلام! به ربات کانال خوش آمدید.';
		$help    = "\n\nدستورها:\n"
			. "/start — راهنما و عضویت\n"
			. "/latest — تازه‌ترین مطالب سایت\n"
			. "یا هر عبارتی بنویسید تا در سایت جستجو شود.";
		if ( WBCN_Settings::admin_id_list( $s ) ) {
			$help .= "\n\nمدیر:\n/idea پیش‌نمایش ایده\n/send ارسال ایده امروز به کانال";
		}
		self::reply( $chat_id, WBCN_Ideas::escape( $welcome ) . $help, $s, WBCN_Telegram::join_keyboard( $s ) );
	}

	private static function cmd_latest( $chat_id, array $s ) {
		if ( ! WBCN_Plugin::licensed() ) {
			self::reply( $chat_id, 'افزونه در دوره آزمایشی/لایسنس فعال نیست.', $s );
			return;
		}
		$items = WBCN_Content::recent_items( 5 );
		if ( ! $items ) {
			self::reply( $chat_id, 'هنوز مطلب منتشرشده‌ای نیست.', $s );
			return;
		}
		$lines = array( '📚 <b>تازه‌ترین مطالب</b>' );
		foreach ( $items as $item ) {
			$url     = WBCN_Ideas::with_utm( $item['url'] );
			$lines[] = '• <a href="' . WBCN_Ideas::escape( $url ) . '">' . WBCN_Ideas::escape( $item['title'] ) . '</a>';
		}
		self::reply( $chat_id, implode( "\n", $lines ), $s, WBCN_Telegram::join_keyboard( $s ) );
	}

	private static function cmd_search( $chat_id, $q, array $s ) {
		if ( ! WBCN_Plugin::licensed() ) {
			return;
		}
		$items = WBCN_Content::search_items( $q, 5 );
		if ( ! $items ) {
			self::reply( $chat_id, 'چیزی با این عبارت پیدا نشد. /latest را امتحان کنید.', $s );
			return;
		}
		$lines = array( '🔎 <b>نتیجه جستجو</b>' );
		foreach ( $items as $item ) {
			$url     = WBCN_Ideas::with_utm( $item['url'] );
			$lines[] = '• <a href="' . WBCN_Ideas::escape( $url ) . '">' . WBCN_Ideas::escape( $item['title'] ) . '</a>';
		}
		self::reply( $chat_id, implode( "\n", $lines ), $s );
	}

	private static function cmd_idea( $chat_id, array $s, $send_channel ) {
		$item = WBCN_Content::pick_for_daily();
		if ( ! $item ) {
			$item = WBCN_Ideas::sample_item();
		}
		$chan  = ! empty( $s['signature'] ) ? WBCN_Settings::channel_username( $s ) : '';
		$built = WBCN_Ideas::build( $item, 'tip', $chan );
		self::reply( $chat_id, $built['text'], $s );
		if ( $send_channel ) {
			$res = WBCN_Telegram::send_parts( $built['parts'], '', $s );
			if ( is_wp_error( $res ) ) {
				self::reply( $chat_id, 'ارسال به کانال نشد: ' . $res->get_error_message(), $s );
			} else {
				self::reply( $chat_id, 'به کانال ارسال شد.', $s );
				if ( ! empty( $item['id'] ) ) {
					WBCN_Content::mark_daily_used( (int) $item['id'] );
				}
			}
		}
	}

	private static function len( $text ) {
		return WBCN_Ideas::len( $text );
	}
}
