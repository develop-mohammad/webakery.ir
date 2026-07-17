<?php
defined( 'ABSPATH' ) || exit;

class NM_Business {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'nm_businesses';
	}

	public static function all() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY id DESC' );
	}

	public static function save( array $data, $id = 0 ) {
		if ( ! NM_Pro::is_active() ) return NM_Pro::require_pro();
		global $wpdb;
		$row = array(
			'name'          => sanitize_text_field( $data['name'] ?? '' ),
			'type'          => sanitize_key( $data['type'] ?? 'consulting' ),
			'owner_user_id' => (int) ( $data['owner_user_id'] ?? 0 ) ?: null,
			'settings'      => wp_json_encode( $data['settings'] ?? array() ),
			'is_active'     => empty( $data['is_active'] ) && isset( $data['is_active'] ) ? 0 : 1,
		);
		if ( empty( $row['name'] ) ) return new WP_Error( 'name', 'نام بیزینس الزامی است' );
		if ( $id ) {
			$wpdb->update( self::table(), $row, array( 'id' => (int) $id ) );
			return (int) $id;
		}
		$row['created_at'] = current_time( 'mysql' );
		$wpdb->insert( self::table(), $row );
		return (int) $wpdb->insert_id;
	}
}
