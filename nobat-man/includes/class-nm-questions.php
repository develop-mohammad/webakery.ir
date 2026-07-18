<?php
defined( 'ABSPATH' ) || exit;

class NM_Questions {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'nm_questions';
	}

	public static function table_ready() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	public static function categories() {
		if ( ! self::table_ready() ) {
			return array();
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$cols = $wpdb->get_col( 'SELECT DISTINCT category FROM ' . self::table() . ' WHERE is_active = 1 ORDER BY category' );
		return $cols ?: array();
	}

	public static function by_category( $category = '' ) {
		if ( ! self::table_ready() ) {
			return array();
		}
		global $wpdb;
		if ( $category ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			return $wpdb->get_results( $wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE is_active = 1 AND category = %s ORDER BY sort_order ASC, id ASC',
				$category
			) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' WHERE is_active = 1 ORDER BY category, sort_order ASC, id ASC' );
	}

	public static function all() {
		if ( ! self::table_ready() ) {
			return array();
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY category, sort_order ASC, id ASC' );
	}

	/**
	 * بررسی پاسخ سوالات اجباری یک دسته.
	 *
	 * @param string              $category
	 * @param array<int,string>   $answers
	 * @return true|WP_Error
	 */
	public static function validate_answers( $category, array $answers ) {
		$category = sanitize_text_field( (string) $category );
		if ( '' === $category ) {
			return true;
		}
		foreach ( self::by_category( $category ) as $q ) {
			if ( ! (int) $q->is_required ) {
				continue;
			}
			$val = isset( $answers[ (int) $q->id ] ) ? trim( (string) $answers[ (int) $q->id ] ) : '';
			if ( '' === $val ) {
				return new WP_Error(
					'answer_required',
					'لطفاً به سوال «' . sanitize_text_field( $q->question ) . '» پاسخ دهید.'
				);
			}
		}
		return true;
	}

	public static function save( array $data, $id = 0 ) {
		if ( ! self::table_ready() ) {
			return new WP_Error( 'no_table', 'جدول سوالات ساخته نشده. افزونه را غیرفعال و دوباره فعال کنید.' );
		}
		global $wpdb;
		$row = array(
			'category'    => sanitize_text_field( $data['category'] ?? 'سایر' ),
			'question'    => sanitize_text_field( $data['question'] ?? '' ),
			'type'        => sanitize_key( $data['type'] ?? 'text' ),
			'options'     => is_array( $data['options'] ?? null ) ? wp_json_encode( $data['options'] ) : (string) ( $data['options'] ?? '' ),
			'is_required' => empty( $data['is_required'] ) ? 0 : 1,
			'sort_order'  => (int) ( $data['sort_order'] ?? 0 ),
			'is_active'   => empty( $data['is_active'] ) && isset( $data['is_active'] ) ? 0 : 1,
		);
		if ( empty( $row['question'] ) ) {
			return new WP_Error( 'q', 'متن سوال الزامی است.' );
		}
		if ( $id ) {
			$ok = $wpdb->update( self::table(), $row, array( 'id' => (int) $id ) );
			if ( false === $ok ) {
				return new WP_Error( 'db', 'خطا در ذخیره سوال.' );
			}
			return (int) $id;
		}
		$ok = $wpdb->insert( self::table(), $row );
		if ( ! $ok ) {
			return new WP_Error( 'db', 'خطا در ذخیره سوال.' );
		}
		return (int) $wpdb->insert_id;
	}

	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ) );
	}
}
