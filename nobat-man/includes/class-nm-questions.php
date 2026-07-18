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

	public static function all_categories() {
		if ( ! self::table_ready() ) {
			return array();
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$cols = $wpdb->get_col( 'SELECT DISTINCT category FROM ' . self::table() . ' ORDER BY category' );
		return $cols ?: array();
	}

	/**
	 * @return array{id:int,label:string,type:string,required:bool,category:string,options:string}
	 */
	public static function to_field( $q ) {
		return array(
			'id'       => (int) $q->id,
			'label'    => (string) $q->question,
			'type'     => (string) $q->type,
			'required' => (bool) (int) $q->is_required,
			'category' => (string) $q->category,
			'options'  => (string) ( $q->options ?? '' ),
		);
	}

	/**
	 * وضعیت تخته سوالات یک دسته.
	 *
	 * @return array{fields:array<int,array>,active:int[],available:int[],category:string}
	 */
	public static function board_for_category( $category ) {
		$category = sanitize_text_field( (string) $category );
		$fields   = array();
		$active   = array();
		$available = array();

		if ( ! self::table_ready() || '' === $category ) {
			return compact( 'fields', 'active', 'available', 'category' );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE category = %s ORDER BY sort_order ASC, id ASC',
			$category
		) );

		foreach ( $rows as $q ) {
			$fields[ (int) $q->id ] = self::to_field( $q );
			if ( (int) $q->is_active ) {
				$active[] = (int) $q->id;
			} else {
				$available[] = (int) $q->id;
			}
		}

		return compact( 'fields', 'active', 'available', 'category' );
	}

	/**
	 * ذخیره ترتیب و فعال بودن سوالات یک دسته.
	 *
	 * @param string   $category
	 * @param int[]    $active_ids
	 * @return true|WP_Error
	 */
	public static function save_board( $category, array $active_ids ) {
		if ( ! self::table_ready() ) {
			return new WP_Error( 'no_table', 'جدول سوالات ساخته نشده.' );
		}
		$category = sanitize_text_field( (string) $category );
		if ( '' === $category ) {
			return new WP_Error( 'cat', 'دسته‌بندی نامعتبر است.' );
		}

		global $wpdb;
		$table = self::table();
		$active_ids = array_values( array_unique( array_map( 'intval', $active_ids ) ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT id FROM ' . $table . ' WHERE category = %s',
			$category
		) );
		$valid = array_map( 'intval', wp_list_pluck( $rows, 'id' ) );

		foreach ( $active_ids as $id ) {
			if ( ! in_array( $id, $valid, true ) ) {
				return new WP_Error( 'bad_id', 'سوال نامعتبر در لیست فعال.' );
			}
		}

		$order = 10;
		foreach ( $active_ids as $id ) {
			$wpdb->update(
				$table,
				array(
					'is_active'  => 1,
					'sort_order' => $order,
				),
				array( 'id' => $id )
			);
			$order += 10;
		}

		$inactive = array_diff( $valid, $active_ids );
		foreach ( $inactive as $id ) {
			$wpdb->update(
				$table,
				array( 'is_active' => 0 ),
				array( 'id' => (int) $id )
			);
		}

		return true;
	}

	public static function get( $id ) {
		if ( ! self::table_ready() ) {
			return null;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ) );
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
