<?php
defined( 'ABSPATH' ) || exit;

class WBCB_Ajax {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_wbcb_visitor_bootstrap', array( $this, 'visitor_bootstrap' ) );
		add_action( 'wp_ajax_nopriv_wbcb_visitor_bootstrap', array( $this, 'visitor_bootstrap' ) );

		add_action( 'wp_ajax_wbcb_visitor_send', array( $this, 'visitor_send' ) );
		add_action( 'wp_ajax_nopriv_wbcb_visitor_send', array( $this, 'visitor_send' ) );

		add_action( 'wp_ajax_wbcb_visitor_poll', array( $this, 'visitor_poll' ) );
		add_action( 'wp_ajax_nopriv_wbcb_visitor_poll', array( $this, 'visitor_poll' ) );

		add_action( 'wp_ajax_wbcb_admin_poll', array( $this, 'admin_poll' ) );
		add_action( 'wp_ajax_wbcb_admin_send', array( $this, 'admin_send' ) );
		add_action( 'wp_ajax_wbcb_admin_close', array( $this, 'admin_close' ) );
		add_action( 'wp_ajax_wbcb_admin_list', array( $this, 'admin_list' ) );
		add_action( 'wp_ajax_wbcb_test_notify', array( $this, 'test_notify' ) );
	}

	private function verify_visitor_nonce() {
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wbcb_visitor' ) ) {
			wp_send_json_error( array( 'message' => 'نشست منقضی شده — صفحه را رفرش کنید.' ), 403 );
		}
	}

	private function verify_admin() {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز' ), 403 );
		}
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wbcb_admin' ) ) {
			wp_send_json_error( array( 'message' => 'نشست منقضی شده — صفحه را رفرش کنید.' ), 403 );
		}
	}

	private function visitor_token_from_request() {
		$token = isset( $_REQUEST['token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['token'] ) ) : '';
		if ( $token ) {
			return $token;
		}
		if ( isset( $_COOKIE['wbcb_token'] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE['wbcb_token'] ) );
		}
		return '';
	}

	private function set_visitor_cookie( $token ) {
		if ( headers_sent() || ! $token ) {
			return;
		}
		$secure = is_ssl();
		setcookie(
			'wbcb_token',
			$token,
			array(
				'expires'  => time() + YEAR_IN_SECONDS,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => $secure,
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	public function visitor_bootstrap() {
		$this->verify_visitor_nonce();
		if ( ! WBCB_Settings::should_show_widget() && ! is_user_logged_in() ) {
			// Allow bootstrap if enabled globally but current user is admin (hidden widget)
		}

		$token = $this->visitor_token_from_request();
		if ( ! $token ) {
			$token = WBCB_Conversations::generate_token();
		}

		$name  = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$page  = esc_url_raw( wp_unslash( $_POST['page_url'] ?? '' ) );

		$conv = WBCB_Conversations::get_or_create(
			$token,
			array(
				'visitor_name'  => $name,
				'visitor_email' => $email,
				'page_url'      => $page ? $page : ( wp_get_referer() ?: home_url( '/' ) ),
			)
		);
		if ( ! $conv ) {
			wp_send_json_error( array( 'message' => 'شروع گفتگو ناموفق بود.' ) );
		}

		$this->set_visitor_cookie( $conv['visitor_token'] );

		if ( $name || $email ) {
			WBCB_Conversations::update_visitor(
				(int) $conv['id'],
				array(
					'visitor_name'  => $name ?: $conv['visitor_name'],
					'visitor_email' => $email ?: $conv['visitor_email'],
				)
			);
			$conv = WBCB_Conversations::get( (int) $conv['id'] );
		}

		$messages = WBCB_Messages::for_conversation( (int) $conv['id'], 0, 80 );
		if ( empty( $messages ) ) {
			$s       = WBCB_Settings::get();
			$welcome = trim( (string) ( $s['welcome'] ?? '' ) );
			if ( $welcome ) {
				WBCB_Messages::add( (int) $conv['id'], 'system', $welcome );
				$messages = WBCB_Messages::for_conversation( (int) $conv['id'], 0, 80 );
			}
		}

		wp_send_json_success(
			array(
				'token'        => $conv['visitor_token'],
				'conversation' => self::public_conversation( $conv ),
				'messages'     => WBCB_Messages::format_list( $messages ),
				'online'       => WBCB_Settings::is_online(),
			)
		);
	}

	public function visitor_send() {
		$this->verify_visitor_nonce();
		$token = $this->visitor_token_from_request();
		$conv  = WBCB_Conversations::get_by_token( $token );
		if ( ! $conv ) {
			wp_send_json_error( array( 'message' => 'گفتگو یافت نشد.' ) );
		}
		if ( 'closed' === $conv['status'] ) {
			wp_send_json_error( array( 'message' => 'این گفتگو بسته شده است.' ) );
		}

		$body = wp_unslash( $_POST['body'] ?? '' );
		$res  = WBCB_Messages::add( (int) $conv['id'], 'visitor', $body );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}

		$name  = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		if ( $name || $email ) {
			WBCB_Conversations::update_visitor(
				(int) $conv['id'],
				array(
					'visitor_name'  => $name,
					'visitor_email' => $email,
				)
			);
		}

		$fresh = WBCB_Conversations::get( (int) $conv['id'] );
		WBCB_Notify::new_visitor_message( is_array( $fresh ) ? $fresh : $conv, $body );

		$s = WBCB_Settings::get();
		if ( ! empty( $s['auto_reply'] ) && WBCB_Settings::is_online() ) {
			$last_admin = self::last_sender_message_count( (int) $conv['id'], 'admin' );
			if ( 0 === $last_admin ) {
				WBCB_Messages::add( (int) $conv['id'], 'system', $s['auto_reply'] );
			}
		}

		wp_send_json_success(
			array(
				'message_id' => $res,
				'messages'   => WBCB_Messages::format_list( WBCB_Messages::for_conversation( (int) $conv['id'], max( 0, (int) $res - 5 ), 10 ) ),
			)
		);
	}

	public function visitor_poll() {
		$this->verify_visitor_nonce();
		$token    = $this->visitor_token_from_request();
		$conv     = WBCB_Conversations::get_by_token( $token );
		$after_id = max( 0, (int) ( $_POST['after_id'] ?? 0 ) );
		if ( ! $conv ) {
			wp_send_json_success( array( 'messages' => array() ) );
		}
		$rows = WBCB_Messages::for_conversation( (int) $conv['id'], $after_id, 50 );
		wp_send_json_success(
			array(
				'messages'     => WBCB_Messages::format_list( $rows ),
				'conversation' => self::public_conversation( $conv ),
				'online'       => WBCB_Settings::is_online(),
			)
		);
	}

	public function admin_poll() {
		$this->verify_admin();
		$id       = (int) ( $_POST['conversation_id'] ?? 0 );
		$after_id = max( 0, (int) ( $_POST['after_id'] ?? 0 ) );
		$conv     = WBCB_Conversations::get( $id );
		if ( ! $conv ) {
			wp_send_json_error( array( 'message' => 'گفتگو یافت نشد.' ) );
		}
		WBCB_Conversations::mark_read( $id );
		$rows = WBCB_Messages::for_conversation( $id, $after_id, 80 );
		wp_send_json_success(
			array(
				'messages'     => WBCB_Messages::format_list( $rows ),
				'conversation' => self::admin_conversation( $conv ),
				'unread'       => WBCB_Conversations::unread_count(),
			)
		);
	}

	public function admin_send() {
		$this->verify_admin();
		$id   = (int) ( $_POST['conversation_id'] ?? 0 );
		$body = wp_unslash( $_POST['body'] ?? '' );
		$conv = WBCB_Conversations::get( $id );
		if ( ! $conv ) {
			wp_send_json_error( array( 'message' => 'گفتگو یافت نشد.' ) );
		}
		$res = WBCB_Messages::add( $id, 'admin', $body );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		WBCB_Conversations::mark_read( $id );
		wp_send_json_success(
			array(
				'message_id' => $res,
				'messages'   => WBCB_Messages::format_list( WBCB_Messages::for_conversation( $id, max( 0, (int) $res - 3 ), 5 ) ),
				'unread'     => WBCB_Conversations::unread_count(),
			)
		);
	}

	public function admin_close() {
		$this->verify_admin();
		$id = (int) ( $_POST['conversation_id'] ?? 0 );
		WBCB_Conversations::update_visitor( $id, array( 'status' => 'closed' ) );
		wp_send_json_success( array( 'unread' => WBCB_Conversations::unread_count() ) );
	}

	public function admin_list() {
		$this->verify_admin();
		$data = WBCB_Conversations::list_admin(
			array(
				'status'   => sanitize_key( wp_unslash( $_POST['status'] ?? '' ) ),
				'search'   => sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) ),
				'page'     => (int) ( $_POST['page'] ?? 1 ),
				'per_page' => 30,
			)
		);
		$items = array();
		foreach ( $data['items'] as $row ) {
			$items[] = self::admin_conversation( $row );
		}
		wp_send_json_success(
			array(
				'items'  => $items,
				'total'  => $data['total'],
				'unread' => WBCB_Conversations::unread_count(),
			)
		);
	}

	private static function public_conversation( array $conv ) {
		return array(
			'id'     => (int) $conv['id'],
			'status' => (string) $conv['status'],
			'name'   => (string) ( $conv['visitor_name'] ?: 'مهمان' ),
		);
	}

	private static function admin_conversation( array $conv ) {
		return array(
			'id'              => (int) $conv['id'],
			'status'          => (string) $conv['status'],
			'visitor_name'    => (string) ( $conv['visitor_name'] ?: 'مهمان' ),
			'visitor_email'   => (string) ( $conv['visitor_email'] ?? '' ),
			'page_url'        => (string) ( $conv['page_url'] ?? '' ),
			'unread_admin'    => ! empty( $conv['unread_admin'] ),
			'last_message_at' => (string) ( $conv['last_message_at'] ?? $conv['created_at'] ),
			'created_at'      => (string) $conv['created_at'],
		);
	}

	private static function last_sender_message_count( $conversation_id, $sender ) {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . WBCB_Messages::table() . ' WHERE conversation_id = %d AND sender = %s',
				$conversation_id,
				$sender
			)
		);
	}

	public function test_notify() {
		$this->verify_admin();
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز' ), 403 );
		}
		$channel = sanitize_key( wp_unslash( $_POST['channel'] ?? 'all' ) );
		if ( ! in_array( $channel, array( 'all', 'email', 'telegram', 'whatsapp' ), true ) ) {
			$channel = 'all';
		}
		$res = WBCB_Notify::send_test( $channel );
		if ( empty( $res['ok'] ) ) {
			wp_send_json_error( array( 'message' => $res['message'] ?? 'ارسال ناموفق' ) );
		}
		wp_send_json_success( array( 'message' => $res['message'] ) );
	}
}
