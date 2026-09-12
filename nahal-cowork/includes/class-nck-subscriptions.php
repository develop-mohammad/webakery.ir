<?php
defined( 'ABSPATH' ) || exit;

class NCK_Subscriptions {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'nck_subscriptions';
	}

	public static function for_member( $member_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE member_id = %d ORDER BY id DESC',
				(int) $member_id
			),
			ARRAY_A
		);
	}

	public static function active_for_shift( $member_id, $shift ) {
		$shift = NCK_Shifts::normalize_type( $shift );
		if ( ! $shift ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE member_id = %d AND shift_type = %s AND status = %s ORDER BY id DESC LIMIT 1',
				(int) $member_id,
				$shift,
				'active'
			),
			ARRAY_A
		);
		return $row ? $row : null;
	}

	public static function replace_for_contract( $member_id, $contract_id, $plan, $quota ) {
		$types = NCK_Shifts::plan_types( $plan );
		if ( ! $types ) {
			return array();
		}
		global $wpdb;
		$wpdb->update(
			self::table(),
			array( 'status' => 'replaced' ),
			array(
				'member_id' => (int) $member_id,
				'status'    => 'active',
			),
			array( '%s' ),
			array( '%d', '%s' )
		);

		$created = array();
		$now     = current_time( 'mysql' );
		$quota   = min( 62, max( 1, (int) $quota ) );
		foreach ( $types as $type ) {
			$wpdb->insert(
				self::table(),
				array(
					'member_id'        => (int) $member_id,
					'contract_id'      => (int) $contract_id,
					'shift_type'       => $type,
					'shifts_per_month' => $quota,
					'status'           => 'active',
					'created_at'      => $now,
				),
				array( '%d', '%d', '%s', '%d', '%s', '%s' )
			);
			$created[] = (int) $wpdb->insert_id;
		}
		return $created;
	}

	public static function snapshot( $member_id, $jy = null, $jm = null ) {
		if ( null === $jy || null === $jm ) {
			$t  = NCK_Jalali::today();
			$jy = $t['y'];
			$jm = $t['m'];
		}
		$out = array();
		foreach ( NCK_Shifts::labels() as $type => $label ) {
			$sub  = self::active_for_shift( $member_id, $type );
			$used = NCK_Attendance::used_in_month( $member_id, $type, $jy, $jm );
			$quota = $sub ? (int) $sub['shifts_per_month'] : 0;
			$out[ $type ] = array(
				'label'     => $label,
				'has_plan'  => (bool) $sub,
				'sub_id'    => $sub ? (int) $sub['id'] : 0,
				'quota'     => $quota,
				'used'      => $used,
				'remaining' => $sub ? NCK_Shifts::remaining( $quota, $used ) : 0,
			);
		}
		return $out;
	}
}
