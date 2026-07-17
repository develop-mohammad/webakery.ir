<?php
defined( 'ABSPATH' ) || exit;

/**
 * موتور اسلات‌های قابل رزرو — سبک و بدون کوئری‌های سنگین تکراری.
 */
class NM_Availability {

	public static function month_status( $jy, $jm, $specialist_id = 0 ) {
		$grid     = NM_Jalali::month_grid( $jy, $jm );
		$closed   = array_map( 'intval', (array) NM_Settings::get( 'closed_weekdays', array( 6 ) ) );
		$block_h  = (int) NM_Settings::get( 'block_holidays', 1 );
		$holidays = NM_Holidays::all_for_year( $jy );
		$today    = NM_Jalali::today();
		$today_ts = strtotime( NM_Jalali::to_g_date( $today['y'], $today['m'], $today['d'] ) );

		$out = array();
		foreach ( $grid as $cell ) {
			if ( null === $cell ) {
				$out[] = null;
				continue;
			}
			$reason = '';
			$ok     = true;
			if ( in_array( (int) $cell['weekday'], $closed, true ) ) {
				$ok = false; $reason = 'تعطیل هفتگی';
			}
			if ( $ok && $block_h && isset( $holidays[ $cell['jalali'] ] ) ) {
				$ok = false; $reason = $holidays[ $cell['jalali'] ];
			}
			if ( $ok && strtotime( $cell['g_date'] ) < $today_ts ) {
				$ok = false; $reason = 'گذشته';
			}
			if ( $ok && self::is_exception_closed( $specialist_id, $cell['g_date'] ) ) {
				$ok = false; $reason = 'تعطیل ویژه';
			}
			$slots = $ok ? count( self::slots_for_date( $cell['jalali'], $specialist_id, true ) ) : 0;
			if ( $ok && 0 === $slots ) {
				$ok = false; $reason = 'پر شده';
			}
			$out[] = array(
				'd'       => $cell['d'],
				'jalali'  => $cell['jalali'],
				'g_date'  => $cell['g_date'],
				'weekday' => $cell['weekday'],
				'available'=> $ok,
				'slots'   => $slots,
				'reason'  => $reason,
				'holiday' => $holidays[ $cell['jalali'] ] ?? '',
			);
		}
		return array(
			'year'  => (int) $jy,
			'month' => (int) $jm,
			'label' => NM_Jalali::month_names()[ $jm - 1 ] . ' ' . $jy,
			'weekdays' => NM_Jalali::weekday_names(),
			'days'  => $out,
		);
	}

	public static function slots_for_date( $jalali_date, $specialist_id = 0, $count_only = false ) {
		$parsed = NM_Jalali::parse( $jalali_date );
		if ( ! $parsed ) {
			return array();
		}
		$wd     = NM_Jalali::weekday( $parsed['y'], $parsed['m'], $parsed['d'] );
		$g_date = NM_Jalali::to_g_date( $parsed['y'], $parsed['m'], $parsed['d'] );

		$duration = self::duration_for( $specialist_id );
		$buffer   = self::buffer_for( $specialist_id );
		$step     = max( 5, (int) NM_Settings::get( 'slot_step', 15 ) );
		$windows  = self::windows_for_weekday( $specialist_id, $wd );

		if ( empty( $windows ) ) {
			return array();
		}

		$booked = self::booked_ranges( $specialist_id, $g_date );
		$slots  = array();

		foreach ( $windows as $win ) {
			$start = self::time_to_min( $win['start_time'] );
			$end   = self::time_to_min( $win['end_time'] );
			for ( $t = $start; $t + $duration <= $end; $t += $step ) {
				$slot_end = $t + $duration;
				$block_end = $slot_end + $buffer;
				if ( self::overlaps_any( $t, $block_end, $booked ) ) {
					continue;
				}
				if ( $count_only ) {
					$slots[] = 1;
					continue;
				}
				$price = NM_Pricing::price_for( $specialist_id, $wd, $jalali_date, self::min_to_time( $t ) );
				$slots[] = array(
					'start'    => self::min_to_time( $t ),
					'end'      => self::min_to_time( $slot_end ),
					'duration' => $duration,
					'price'    => $price,
					'price_fa' => NM_Settings::format_price( $price ),
				);
			}
		}

		return $slots;
	}

	public static function duration_for( $specialist_id ) {
		$min = max( 5, (int) NM_Settings::get( 'min_duration', 5 ) );
		$max = min( 300, (int) NM_Settings::get( 'max_duration', 300 ) );
		$d   = (int) NM_Settings::get( 'default_duration', 60 );
		if ( $specialist_id ) {
			$sp = NM_Specialist::get( $specialist_id );
			if ( $sp && ! empty( $sp->duration ) ) {
				$d = (int) $sp->duration;
			}
		}
		return max( $min, min( $max, $d ) );
	}

	public static function buffer_for( $specialist_id ) {
		$b = (int) NM_Settings::get( 'buffer_minutes', 10 );
		if ( $specialist_id ) {
			$sp = NM_Specialist::get( $specialist_id );
			if ( $sp && isset( $sp->buffer_minutes ) ) {
				$b = (int) $sp->buffer_minutes;
			}
		}
		return max( 0, $b );
	}

	public static function windows_for_weekday( $specialist_id, $weekday ) {
		global $wpdb;
		$table = $wpdb->prefix . 'nm_schedules';

		$has_specific = (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE specialist_id = %d AND weekday = %d AND is_active = 1 LIMIT 1",
			$specialist_id, $weekday
		) );

		if ( $has_specific ) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT start_time, end_time FROM {$table} WHERE specialist_id = %d AND weekday = %d AND is_active = 1 ORDER BY start_time",
				$specialist_id, $weekday
			), ARRAY_A );
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT start_time, end_time FROM {$table} WHERE specialist_id = 0 AND weekday = %d AND is_active = 1 ORDER BY start_time",
				$weekday
			), ARRAY_A );
		}
		return $rows ?: array();
	}

	public static function is_exception_closed( $specialist_id, $g_date ) {
		global $wpdb;
		$table = $wpdb->prefix . 'nm_exceptions';
		$id = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE g_date = %s AND type = 'closed' AND specialist_id IN (0, %d) LIMIT 1",
			$g_date, $specialist_id
		) );
		return (bool) $id;
	}

	public static function booked_ranges( $specialist_id, $g_date ) {
		global $wpdb;
		$table = $wpdb->prefix . 'nm_bookings';
		$rows  = $wpdb->get_results( $wpdb->prepare(
			"SELECT start_time, end_time, duration FROM {$table}
			 WHERE g_date = %s AND specialist_id = %d
			 AND status IN ('pending','paid','confirmed','completed')",
			$g_date, $specialist_id
		), ARRAY_A );
		$buffer = self::buffer_for( $specialist_id );
		$ranges = array();
		foreach ( $rows as $r ) {
			$s = self::time_to_min( $r['start_time'] );
			$e = self::time_to_min( $r['end_time'] ) + $buffer;
			$ranges[] = array( $s, $e );
		}
		return $ranges;
	}

	public static function overlaps_any( $start, $end, array $ranges ) {
		foreach ( $ranges as $r ) {
			if ( $start < $r[1] && $end > $r[0] ) {
				return true;
			}
		}
		return false;
	}

	public static function time_to_min( $time ) {
		$parts = explode( ':', (string) $time );
		return ( (int) $parts[0] * 60 ) + (int) ( $parts[1] ?? 0 );
	}

	public static function min_to_time( $min ) {
		$h = (int) floor( $min / 60 );
		$m = $min % 60;
		return sprintf( '%02d:%02d', $h, $m );
	}
}
