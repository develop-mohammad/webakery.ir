<?php
defined( 'ABSPATH' ) || exit;

class WBCB_Messages {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'wbcb_messages';
	}

	public static function now() {
		return current_time( 'mysql', true );
	}

	public static function add( $conversation_id, $sender, $body, $meta = array() ) {
		global $wpdb;
		$conversation_id = (int) $conversation_id;
		$sender          = sanitize_key( (string) $sender );
		$body            = wp_strip_all_tags( (string) $body );
		$body            = trim( $body );

		if ( $conversation_id <= 0 || '' === $body ) {
			return new WP_Error( 'msg', 'پیام خالی است.' );
		}
		if ( ! in_array( $sender, array( 'visitor', 'admin', 'system' ), true ) ) {
			return new WP_Error( 'sender', 'فرستنده نامعتبر است.' );
		}
		if ( strlen( $body ) > 8000 ) {
			return new WP_Error( 'long', 'پیام خیلی طولانی است.' );
		}

		$ok = $wpdb->insert(
			self::table(),
			array(
				'conversation_id' => $conversation_id,
				'sender'          => $sender,
				'body'            => $body,
				'meta'            => $meta ? wp_json_encode( $meta ) : null,
				'created_at'      => self::now(),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);
		if ( ! $ok ) {
			return new WP_Error( 'db', 'ذخیره پیام ناموفق بود.' );
		}

		$unread = ( 'visitor' === $sender );
		WBCB_Conversations::touch_message( $conversation_id, $unread );

		return (int) $wpdb->insert_id;
	}

	/**
	 * @return array<int,array>
	 */
	public static function for_conversation( $conversation_id, $after_id = 0, $limit = 100 ) {
		global $wpdb;
		$conversation_id = (int) $conversation_id;
		$after_id        = max( 0, (int) $after_id );
		$limit           = max( 1, min( 200, (int) $limit ) );

		if ( $conversation_id <= 0 ) {
			return array();
		}

		if ( $after_id > 0 ) {
			$sql = $wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE conversation_id = %d AND id > %d ORDER BY id ASC LIMIT %d',
				$conversation_id,
				$after_id,
				$limit
			);
		} else {
			$sql = $wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE conversation_id = %d ORDER BY id ASC LIMIT %d',
				$conversation_id,
				$limit
			);
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore
		return is_array( $rows ) ? $rows : array();
	}

	public static function format_row( array $row ) {
		$meta = array();
		if ( ! empty( $row['meta'] ) ) {
			$decoded = json_decode( (string) $row['meta'], true );
			if ( is_array( $decoded ) ) {
				$meta = $decoded;
			}
		}
		return array(
			'id'         => (int) $row['id'],
			'sender'     => (string) $row['sender'],
			'body'       => (string) $row['body'],
			'meta'       => $meta,
			'created_at' => (string) $row['created_at'],
			'time_label' => mysql2date( 'H:i', $row['created_at'], true ),
		);
	}

	public static function format_list( array $rows ) {
		$out = array();
		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$out[] = self::format_row( $row );
			}
		}
		return $out;
	}
}
