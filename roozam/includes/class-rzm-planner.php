<?php
defined( 'ABSPATH' ) || exit;

/**
 * ذخیره و زمان‌بندی هوشمند برنامه روزانه.
 */
class RZM_Planner {

	const META_DAYS     = 'rzm_days';
	const META_ROUTINES = 'rzm_routines';

	public static function get_day( $user_id, $date ) {
		$date = self::sanitize_date( $date );
		$days = self::all_days( $user_id );
		if ( isset( $days[ $date ] ) && is_array( $days[ $date ] ) ) {
			return self::normalize_day( $days[ $date ], $date );
		}
		return self::normalize_day(
			array(
				'date'  => $date,
				'tasks' => array(),
				'note'  => '',
			),
			$date
		);
	}

	public static function save_day( $user_id, $date, array $payload ) {
		$user_id = absint( $user_id );
		$date    = self::sanitize_date( $date );
		if ( ! $user_id || ! $date ) {
			return new WP_Error( 'rzm_invalid', 'تاریخ یا کاربر نامعتبر است.' );
		}

		$day = self::normalize_day(
			array(
				'date'  => $date,
				'tasks' => isset( $payload['tasks'] ) && is_array( $payload['tasks'] ) ? $payload['tasks'] : array(),
				'note'  => isset( $payload['note'] ) ? sanitize_textarea_field( $payload['note'] ) : '',
			),
			$date
		);

		$days          = self::all_days( $user_id );
		$days[ $date ] = $day;
		$days          = self::prune_days( $days );
		update_user_meta( $user_id, self::META_DAYS, $days );
		return $day;
	}

	public static function get_routines( $user_id ) {
		$user_id  = absint( $user_id );
		$routines = get_user_meta( $user_id, self::META_ROUTINES, true );
		if ( ! is_array( $routines ) ) {
			$routines = self::default_routines();
		}
		$out = array();
		foreach ( $routines as $item ) {
			$clean = self::normalize_task( $item, true );
			if ( $clean ) {
				$out[] = $clean;
			}
		}
		return $out;
	}

	public static function save_routines( $user_id, array $routines ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return new WP_Error( 'rzm_invalid', 'کاربر نامعتبر است.' );
		}
		$out = array();
		foreach ( $routines as $item ) {
			$clean = self::normalize_task( $item, true );
			if ( $clean ) {
				$out[] = $clean;
			}
		}
		update_user_meta( $user_id, self::META_ROUTINES, $out );
		return $out;
	}

	/**
	 * برنامه روز را با عادت‌ها و کارهای بدون زمان، زمان‌بندی می‌کند.
	 *
	 * @param array $day
	 * @param array $routines
	 * @param array $prefs wake_time, sleep_time, break_minutes
	 * @return array
	 */
	public static function auto_plan( array $day, array $routines, array $prefs ) {
		$wake  = self::time_to_minutes( isset( $prefs['wake_time'] ) ? $prefs['wake_time'] : '07:00' );
		$sleep = self::time_to_minutes( isset( $prefs['sleep_time'] ) ? $prefs['sleep_time'] : '23:00' );
		$break = isset( $prefs['break_minutes'] ) ? max( 0, min( 60, (int) $prefs['break_minutes'] ) ) : 10;

		if ( $sleep <= $wake ) {
			$sleep = $wake + 12 * 60;
		}

		$fixed   = array();
		$flex    = array();
		$seen    = array();

		foreach ( $day['tasks'] as $task ) {
			$task = self::normalize_task( $task, false );
			if ( ! $task ) {
				continue;
			}
			$seen[ $task['title'] . '|' . $task['duration'] ] = true;
			if ( $task['start'] !== '' ) {
				$fixed[] = $task;
			} else {
				$flex[] = $task;
			}
		}

		// عادت‌های فعال که هنوز در روز نیستند.
		foreach ( $routines as $routine ) {
			$routine = self::normalize_task( $routine, true );
			if ( ! $routine || empty( $routine['enabled'] ) ) {
				continue;
			}
			$key = $routine['title'] . '|' . $routine['duration'];
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$item = $routine;
			$item['id']        = self::uid();
			$item['done']      = false;
			$item['from_routine'] = true;
			if ( $item['start'] !== '' ) {
				$fixed[] = $item;
			} else {
				$flex[] = $item;
			}
		}

		usort(
			$fixed,
			static function ( $a, $b ) {
				return self::time_to_minutes( $a['start'] ) - self::time_to_minutes( $b['start'] );
			}
		);

		// اولویت: high > medium > low سپس مدت کوتاه‌تر.
		usort(
			$flex,
			static function ( $a, $b ) {
				$rank = array( 'high' => 0, 'medium' => 1, 'low' => 2 );
				$ra   = isset( $rank[ $a['priority'] ] ) ? $rank[ $a['priority'] ] : 1;
				$rb   = isset( $rank[ $b['priority'] ] ) ? $rank[ $b['priority'] ] : 1;
				if ( $ra !== $rb ) {
					return $ra - $rb;
				}
				return (int) $a['duration'] - (int) $b['duration'];
			}
		);

		$busy = array();
		foreach ( $fixed as $task ) {
			$start = self::time_to_minutes( $task['start'] );
			$end   = $start + max( 5, (int) $task['duration'] );
			$busy[] = array( $start, $end );
		}
		usort(
			$busy,
			static function ( $a, $b ) {
				return $a[0] - $b[0];
			}
		);

		$cursor = $wake;
		foreach ( $flex as &$task ) {
			$dur = max( 5, (int) $task['duration'] );
			$placed = false;
			$scan   = max( $cursor, $wake );
			while ( $scan + $dur <= $sleep ) {
				$end = $scan + $dur;
				if ( ! self::overlaps_any( $scan, $end, $busy ) ) {
					$task['start'] = self::minutes_to_time( $scan );
					$busy[]        = array( $scan, $end );
					usort(
						$busy,
						static function ( $a, $b ) {
							return $a[0] - $b[0];
						}
					);
					$cursor = $end + $break;
					$placed = true;
					break;
				}
				// به انتهای نزدیک‌ترین تداخل برو.
				$next = $scan + 5;
				foreach ( $busy as $block ) {
					if ( $scan < $block[1] && $end > $block[0] ) {
						$next = max( $next, $block[1] + $break );
					}
				}
				$scan = $next;
			}
			if ( ! $placed ) {
				$task['start'] = '';
			}
		}
		unset( $task );

		$all = array_merge( $fixed, $flex );
		usort(
			$all,
			static function ( $a, $b ) {
				if ( $a['start'] === '' && $b['start'] === '' ) {
					return 0;
				}
				if ( $a['start'] === '' ) {
					return 1;
				}
				if ( $b['start'] === '' ) {
					return -1;
				}
				return self::time_to_minutes( $a['start'] ) - self::time_to_minutes( $b['start'] );
			}
		);

		$day['tasks'] = $all;
		return $day;
	}

	public static function default_routines() {
		return array(
			array(
				'id'       => 'r1',
				'title'    => 'ورزش سبک',
				'duration' => 30,
				'priority' => 'medium',
				'start'    => '07:30',
				'enabled'  => true,
				'category' => 'سلامت',
			),
			array(
				'id'       => 'r2',
				'title'    => 'مرور اهداف روز',
				'duration' => 15,
				'priority' => 'high',
				'start'    => '08:15',
				'enabled'  => true,
				'category' => 'تمرکز',
			),
			array(
				'id'       => 'r3',
				'title'    => 'مطالعه',
				'duration' => 45,
				'priority' => 'medium',
				'start'    => '',
				'enabled'  => true,
				'category' => 'رشد',
			),
		);
	}

	private static function all_days( $user_id ) {
		$days = get_user_meta( absint( $user_id ), self::META_DAYS, true );
		return is_array( $days ) ? $days : array();
	}

	private static function prune_days( array $days ) {
		if ( count( $days ) <= 90 ) {
			return $days;
		}
		krsort( $days );
		return array_slice( $days, 0, 90, true );
	}

	public static function normalize_public_day( array $day, $date ) {
		return self::normalize_day( $day, self::sanitize_date( $date ) );
	}

	private static function normalize_day( array $day, $date ) {
		$tasks = array();
		if ( ! empty( $day['tasks'] ) && is_array( $day['tasks'] ) ) {
			foreach ( $day['tasks'] as $task ) {
				$clean = self::normalize_task( $task, false );
				if ( $clean ) {
					$tasks[] = $clean;
				}
			}
		}
		return array(
			'date'  => $date,
			'tasks' => $tasks,
			'note'  => isset( $day['note'] ) ? sanitize_textarea_field( $day['note'] ) : '',
		);
	}

	private static function normalize_task( $task, $is_routine ) {
		if ( ! is_array( $task ) ) {
			return null;
		}
		$title = isset( $task['title'] ) ? sanitize_text_field( $task['title'] ) : '';
		if ( $title === '' ) {
			return null;
		}
		$priority = isset( $task['priority'] ) ? sanitize_key( $task['priority'] ) : 'medium';
		if ( ! in_array( $priority, array( 'high', 'medium', 'low' ), true ) ) {
			$priority = 'medium';
		}
		$start = '';
		if ( ! empty( $task['start'] ) ) {
			$start = RZM_Settings::sanitize_time( $task['start'], '' );
		}
		$out = array(
			'id'       => ! empty( $task['id'] ) ? sanitize_text_field( $task['id'] ) : self::uid(),
			'title'    => $title,
			'duration' => max( 5, min( 480, absint( isset( $task['duration'] ) ? $task['duration'] : 30 ) ) ),
			'priority' => $priority,
			'start'    => $start,
			'category' => isset( $task['category'] ) ? sanitize_text_field( $task['category'] ) : '',
		);
		if ( $is_routine ) {
			$out['enabled'] = ! isset( $task['enabled'] ) || ! empty( $task['enabled'] );
		} else {
			$out['done']          = ! empty( $task['done'] );
			$out['from_routine']  = ! empty( $task['from_routine'] );
		}
		return $out;
	}

	public static function sanitize_date( $date ) {
		$date = is_string( $date ) ? trim( $date ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return gmdate( 'Y-m-d', current_time( 'timestamp' ) );
		}
		return $date;
	}

	public static function time_to_minutes( $time ) {
		$time = RZM_Settings::sanitize_time( $time, '00:00' );
		list( $h, $m ) = array_map( 'intval', explode( ':', $time ) );
		return ( $h * 60 ) + $m;
	}

	public static function minutes_to_time( $minutes ) {
		$minutes = max( 0, min( 24 * 60 - 1, (int) $minutes ) );
		$h       = (int) floor( $minutes / 60 );
		$m       = $minutes % 60;
		return sprintf( '%02d:%02d', $h, $m );
	}

	private static function overlaps_any( $start, $end, array $busy ) {
		foreach ( $busy as $block ) {
			if ( $start < $block[1] && $end > $block[0] ) {
				return true;
			}
		}
		return false;
	}

	private static function uid() {
		return 't' . substr( md5( uniqid( (string) mt_rand(), true ) ), 0, 10 );
	}
}
