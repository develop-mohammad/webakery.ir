<?php
defined( 'ABSPATH' ) || exit;

class NM_Specialist {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'nm_specialists';
	}

	public static function get( $id ) {
		global $wpdb;
		static $cache = array();
		$id = (int) $id;
		if ( isset( $cache[ $id ] ) ) {
			return $cache[ $id ];
		}
		if ( ! $id ) {
			return null;
		}
		$cache[ $id ] = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) );
		return $cache[ $id ];
	}

	public static function all_active( $business_id = null ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::table() . ' WHERE is_active = 1';
		$params = array();
		if ( null !== $business_id ) {
			$sql .= ' AND business_id = %d';
			$params[] = (int) $business_id;
		}
		$sql .= ' ORDER BY name ASC';
		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params );
		}
		return $wpdb->get_results( $sql );
	}

	public static function save( array $data, $id = 0 ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		$row = array(
			'user_id'             => (int) ( $data['user_id'] ?? 0 ) ?: null,
			'name'                => sanitize_text_field( $data['name'] ?? '' ),
			'slug'                => sanitize_title( $data['slug'] ?? ( $data['name'] ?? '' ) ),
			'skills'              => sanitize_textarea_field( $data['skills'] ?? '' ),
			'bio'                 => wp_kses_post( $data['bio'] ?? '' ),
			'avatar_id'           => (int) ( $data['avatar_id'] ?? 0 ) ?: null,
			'price'               => (int) ( $data['price'] ?? NM_Settings::get( 'default_price', 0 ) ),
			'duration'            => (int) ( $data['duration'] ?? NM_Settings::get( 'default_duration', 60 ) ),
			'buffer_minutes'      => (int) ( $data['buffer_minutes'] ?? NM_Settings::get( 'buffer_minutes', 10 ) ),
			'business_id'         => (int) ( $data['business_id'] ?? 0 ) ?: null,
			'google_calendar_id'  => sanitize_text_field( $data['google_calendar_id'] ?? '' ),
			'is_active'           => empty( $data['is_active'] ) ? 0 : 1,
			'updated_at'          => $now,
		);
		if ( empty( $row['name'] ) ) {
			return new WP_Error( 'name', 'نام متخصص الزامی است.' );
		}

		// محدودیت نسخه رایگان: فقط ۱ متخصص
		if ( ! $id && ! NM_Pro::is_active() ) {
			$count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() );
			if ( $count >= 1 ) {
				return new WP_Error( 'pro', 'برای افزودن متخصص بیشتر به نسخه پرو نیاز دارید.' );
			}
		}

		if ( $id ) {
			$wpdb->update( self::table(), $row, array( 'id' => (int) $id ) );
			return self::get( $id );
		}
		$row['created_at'] = $now;
		$wpdb->insert( self::table(), $row );
		return self::get( (int) $wpdb->insert_id );
	}

	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ) );
	}

	public static function default_or_first() {
		$list = self::all_active();
		return $list ? $list[0] : null;
	}
}
