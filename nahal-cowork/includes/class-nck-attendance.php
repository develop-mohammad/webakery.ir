<?php
defined( 'ABSPATH' ) || exit;

class NCK_Attendance {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'nck_attendances';
	}

	public static function used_in_month( $member_id, $shift, $jy, $jm ) {
		$shift = NCK_Shifts::normalize_type( $shift );
		if ( ! $shift ) {
			return 0;
		}
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::table() . ' WHERE member_id = %d AND shift_type = %s AND jalali_year = %d AND jalali_month = %d',
				(int) $member_id,
				$shift,
				(int) $jy,
				(int) $jm
			)
		);
	}

	public static function exists( $member_id, $g_date, $shift ) {
		global $wpdb;
		$id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . self::table() . ' WHERE member_id = %d AND shift_date = %s AND shift_type = %s',
				(int) $member_id,
				$g_date,
				$shift
			)
		);
		return (int) $id;
	}

	public static function today_counts( $g_date ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT shift_type, COUNT(*) AS c FROM ' . self::table() . ' WHERE shift_date = %s GROUP BY shift_type',
				$g_date
			),
			ARRAY_A
		);
		$out = array(
			NCK_Shifts::MORNING => 0,
			NCK_Shifts::EVENING => 0,
		);
		foreach ( (array) $rows as $row ) {
			$out[ $row['shift_type'] ] = (int) $row['c'];
		}
		return $out;
	}

	public static function recent( $limit = 40 ) {
		global $wpdb;
		$limit = max( 1, min( 200, (int) $limit ) );
		$m     = NCK_Members::table();
		$a     = self::table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, m.full_name, m.phone FROM {$a} a LEFT JOIN {$m} m ON m.id = a.member_id ORDER BY a.id DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}

	public static function for_date( $g_date ) {
		global $wpdb;
		$m = NCK_Members::table();
		$a = self::table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, m.full_name, m.phone FROM {$a} a LEFT JOIN {$m} m ON m.id = a.member_id WHERE a.shift_date = %s ORDER BY a.shift_type ASC, a.checked_in_at ASC",
				$g_date
			),
			ARRAY_A
		);
	}

	/**
	 * @return array{ok:bool,message:string,id?:int}
	 */
	public static function check_in( $member_id, $shift, $g_date, $source = 'front' ) {
		$member = NCK_Members::get( $member_id );
		if ( ! $member ) {
			return array( 'ok' => false, 'message' => 'عضو پیدا نشد.' );
		}

		$shift = NCK_Shifts::normalize_type( $shift );
		if ( ! $shift ) {
			return array( 'ok' => false, 'message' => 'شیفت نامعتبر است.' );
		}

		$j = NCK_Jalali::from_g_date( $g_date );
		if ( ! $j ) {
			return array( 'ok' => false, 'message' => 'تاریخ نامعتبر است.' );
		}

		$sub  = NCK_Subscriptions::active_for_shift( (int) $member['id'], $shift );
		$used = self::used_in_month( (int) $member['id'], $shift, $j['y'], $j['m'] );
		$day  = NCK_Shifts::day_status( $shift, $j['y'], $j['m'], $j['d'], NCK_Settings::holiday_context( $j['y'], $j['m'], $j['d'] ) );

		$gate = NCK_Shifts::can_check_in(
			array(
				'quota'          => $sub ? (int) $sub['shifts_per_month'] : 0,
				'used'           => $used,
				'has_plan'       => (bool) $sub,
				'already'       => (bool) self::exists( (int) $member['id'], $g_date, $shift ),
				'day'            => $day,
				'member_active'  => ( 'active' === $member['status'] ),
			)
		);
		if ( ! $gate['ok'] ) {
			return $gate;
		}

		global $wpdb;
		$ok = $wpdb->insert(
			self::table(),
			array(
				'member_id'       => (int) $member['id'],
				'subscription_id' => $sub ? (int) $sub['id'] : 0,
				'shift_date'      => $g_date,
				'shift_type'      => $shift,
				'jalali_year'     => (int) $j['y'],
				'jalali_month'    => (int) $j['m'],
				'source'          => in_array( $source, array( 'front', 'admin' ), true ) ? $source : 'front',
				'checked_in_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s' )
		);
		if ( ! $ok ) {
			return array( 'ok' => false, 'message' => 'ثبت حضور انجام نشد.' );
		}

		$left = NCK_Shifts::remaining( (int) $sub['shifts_per_month'], $used + 1 );
		$lbl  = NCK_Shifts::labels();
		return array(
			'ok'      => true,
			'message' => 'حضور شیفت ' . $lbl[ $shift ] . ' ثبت شد. باقی‌مانده این ماه: ' . NCK_Jalali::fa_digits( $left ),
			'id'      => (int) $wpdb->insert_id,
			'left'    => $left,
		);
	}

	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	public static function month_report( $jy, $jm ) {
		global $wpdb;
		$m = NCK_Members::table();
		$a = self::table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.member_id, m.full_name, m.phone, a.shift_type, COUNT(*) AS used
				 FROM {$a} a LEFT JOIN {$m} m ON m.id = a.member_id
				 WHERE a.jalali_year = %d AND a.jalali_month = %d
				 GROUP BY a.member_id, a.shift_type
				 ORDER BY m.full_name ASC",
				(int) $jy,
				(int) $jm
			),
			ARRAY_A
		);
	}
}
