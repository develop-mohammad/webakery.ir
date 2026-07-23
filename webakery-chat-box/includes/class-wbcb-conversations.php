<?php
defined( 'ABSPATH' ) || exit;

class WBCB_Conversations {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'wbcb_conversations';
	}

	public static function now() {
		return current_time( 'mysql', true );
	}

	public static function generate_token() {
		return substr( hash( 'sha256', wp_generate_password( 24, true, true ) . microtime( true ) ), 0, 48 );
	}

	public static function get_by_token( $token ) {
		global $wpdb;
		$token = sanitize_text_field( (string) $token );
		if ( strlen( $token ) < 16 ) {
			return null;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE visitor_token = %s LIMIT 1',
				$token
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	public static function get( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return null;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE id = %d LIMIT 1',
				$id
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	public static function get_or_create( $token, array $data = array() ) {
		$existing = self::get_by_token( $token );
		if ( $existing ) {
			return $existing;
		}
		global $wpdb;
		$now   = self::now();
		$token = $token ? sanitize_text_field( $token ) : self::generate_token();
		$wpdb->insert(
			self::table(),
			array(
				'visitor_token'   => $token,
				'visitor_name'    => sanitize_text_field( $data['visitor_name'] ?? '' ),
				'visitor_email'   => sanitize_email( $data['visitor_email'] ?? '' ),
				'page_url'        => esc_url_raw( $data['page_url'] ?? '' ),
				'status'          => 'open',
				'unread_admin'    => 0,
				'last_message_at' => null,
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		return self::get_by_token( $token );
	}

	public static function update_visitor( $id, array $data ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$fields = array(
			'updated_at' => self::now(),
		);
		$format = array( '%s' );
		if ( array_key_exists( 'visitor_name', $data ) ) {
			$fields['visitor_name'] = sanitize_text_field( (string) $data['visitor_name'] );
			$format[]               = '%s';
		}
		if ( array_key_exists( 'visitor_email', $data ) ) {
			$fields['visitor_email'] = sanitize_email( (string) $data['visitor_email'] );
			$format[]                = '%s';
		}
		if ( array_key_exists( 'page_url', $data ) ) {
			$fields['page_url'] = esc_url_raw( (string) $data['page_url'] );
			$format[]           = '%s';
		}
		if ( array_key_exists( 'status', $data ) ) {
			$st = sanitize_key( (string) $data['status'] );
			$fields['status'] = in_array( $st, array( 'open', 'closed' ), true ) ? $st : 'open';
			$format[]         = '%s';
		}
		return false !== $wpdb->update( self::table(), $fields, array( 'id' => $id ), $format, array( '%d' ) );
	}

	public static function touch_message( $id, $unread_admin = null ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return;
		}
		$data = array(
			'last_message_at' => self::now(),
			'updated_at'      => self::now(),
		);
		$fmt  = array( '%s', '%s' );
		if ( null !== $unread_admin ) {
			$data['unread_admin'] = $unread_admin ? 1 : 0;
			$fmt[]                = '%d';
		}
		$wpdb->update( self::table(), $data, array( 'id' => $id ), $fmt, array( '%d' ) );
	}

	public static function mark_read( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return;
		}
		$wpdb->update(
			self::table(),
			array(
				'unread_admin' => 0,
				'updated_at'   => self::now(),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * @return array{items:array,total:int}
	 */
	public static function list_admin( $args = array() ) {
		global $wpdb;
		$status = sanitize_key( $args['status'] ?? '' );
		$search = sanitize_text_field( $args['search'] ?? '' );
		$page   = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per    = max( 5, min( 50, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset = ( $page - 1 ) * $per;

		$where = array( '1=1' );
		$bind  = array();

		if ( $status && in_array( $status, array( 'open', 'closed' ), true ) ) {
			$where[] = 'status = %s';
			$bind[]  = $status;
		}
		if ( $search ) {
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$where[] = '(visitor_name LIKE %s OR visitor_email LIKE %s OR visitor_token LIKE %s)';
			$bind[]  = $like;
			$bind[]  = $like;
			$bind[]  = $like;
		}

		$sql_count = 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE ' . implode( ' AND ', $where );
		if ( $bind ) {
			$sql_count = $wpdb->prepare( $sql_count, $bind ); // phpcs:ignore
		}
		$total = (int) $wpdb->get_var( $sql_count ); // phpcs:ignore

		$sql = 'SELECT * FROM ' . self::table() . ' WHERE ' . implode( ' AND ', $where )
			. ' ORDER BY COALESCE(last_message_at, created_at) DESC LIMIT %d OFFSET %d';
		$bind[] = $per;
		$bind[] = $offset;
		$rows   = $wpdb->get_results( $wpdb->prepare( $sql, $bind ), ARRAY_A ); // phpcs:ignore

		return array(
			'items' => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	public static function unread_count() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE unread_admin = 1 AND status = \'open\'' ); // phpcs:ignore
	}
}
