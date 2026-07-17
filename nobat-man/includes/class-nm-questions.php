<?php
defined( 'ABSPATH' ) || exit;

class NM_Questions {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'nm_questions';
	}

	public static function categories() {
		global $wpdb;
		$cols = $wpdb->get_col( 'SELECT DISTINCT category FROM ' . self::table() . ' WHERE is_active = 1 ORDER BY category' );
		return $cols ?: array();
	}

	public static function by_category( $category = '' ) {
		global $wpdb;
		if ( $category ) {
			return $wpdb->get_results( $wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE is_active = 1 AND category = %s ORDER BY sort_order ASC, id ASC',
				$category
			) );
		}
		return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' WHERE is_active = 1 ORDER BY category, sort_order ASC' );
	}

	public static function all() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY category, sort_order ASC, id ASC' );
	}

	public static function save( array $data, $id = 0 ) {
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
			$wpdb->update( self::table(), $row, array( 'id' => (int) $id ) );
			return (int) $id;
		}
		$wpdb->insert( self::table(), $row );
		return (int) $wpdb->insert_id;
	}

	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ) );
	}
}
