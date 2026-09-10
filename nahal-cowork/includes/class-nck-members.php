<?php
defined( 'ABSPATH' ) || exit;

class NCK_Members {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'nck_members';
	}

	public static function now() {
		return current_time( 'mysql' );
	}

	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ),
			ARRAY_A
		);
		return $row ? $row : null;
	}

	public static function by_phone( $phone ) {
		$phone = NCK_Phone::normalize( $phone );
		if ( ! $phone ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE phone = %s', $phone ),
			ARRAY_A
		);
		return $row ? $row : null;
	}

	public static function upsert( $name, $honorific, $phone, $national_id = '' ) {
		$phone = NCK_Phone::normalize( $phone );
		$name  = sanitize_text_field( $name );
		if ( ! $phone || $name === '' ) {
			return null;
		}
		$honorific = in_array( $honorific, array( 'mr', 'ms' ), true ) ? $honorific : 'mr';
		$nid       = NCK_Hall::normalize_nid( $national_id );
		$existing  = self::by_phone( $phone );
		$now       = self::now();

		global $wpdb;
		if ( $existing ) {
			$data = array(
				'full_name'  => $name,
				'honorific'  => $honorific,
				'status'     => 'active',
				'updated_at' => $now,
			);
			$fmt = array( '%s', '%s', '%s', '%s' );
			if ( $nid ) {
				$data['national_id'] = $nid;
				$fmt[]                = '%s';
			}
			$wpdb->update(
				self::table(),
				$data,
				array( 'id' => (int) $existing['id'] ),
				$fmt,
				array( '%d' )
			);
			return self::get( (int) $existing['id'] );
		}

		$wpdb->insert(
			self::table(),
			array(
				'full_name'    => $name,
				'honorific'    => $honorific,
				'phone'        => $phone,
				'national_id'  => $nid ? $nid : '',
				'status'       => 'active',
				'notes'        => '',
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return self::get( (int) $wpdb->insert_id );
	}

	public static function set_status( $id, $status ) {
		$status = in_array( $status, array( 'active', 'inactive' ), true ) ? $status : 'active';
		global $wpdb;
		$wpdb->update(
			self::table(),
			array(
				'status'     => $status,
				'updated_at' => self::now(),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function count( $status = '' ) {
		global $wpdb;
		$sql = 'SELECT COUNT(*) FROM ' . self::table();
		if ( $status ) {
			return (int) $wpdb->get_var( $wpdb->prepare( $sql . ' WHERE status = %s', $status ) );
		}
		return (int) $wpdb->get_var( $sql );
	}

	public static function search( $q = '', $limit = 50, $offset = 0 ) {
		global $wpdb;
		$limit  = max( 1, min( 200, (int) $limit ) );
		$offset = max( 0, (int) $offset );
		$q      = trim( (string) $q );
		if ( $q === '' ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d OFFSET %d',
					$limit,
					$offset
				),
				ARRAY_A
			);
		}
		$like = '%' . $wpdb->esc_like( $q ) . '%';
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE full_name LIKE %s OR phone LIKE %s ORDER BY id DESC LIMIT %d OFFSET %d',
				$like,
				$like,
				$limit,
				$offset
			),
			ARRAY_A
		);
	}
}
