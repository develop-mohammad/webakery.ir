<?php
defined( 'ABSPATH' ) || exit;

/**
 * گزارش‌های ذخیره‌شدهٔ استخراج — برای بازگشایی عبارت پایه و مقایسه.
 */
class WBGS_Reports {

	const OPTION    = 'wbgs_reports';
	const MAX_USER  = 40;
	const MAX_GUEST = 8;
	const ROW_CAP   = 400;

	/**
	 * @return string
	 */
	public static function actor_key() {
		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
			return 'u' . get_current_user_id();
		}
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'x';
		return 'g' . md5( $ip );
	}

	/**
	 * @return array<string,array>
	 */
	public static function all_store() {
		if ( function_exists( 'get_option' ) ) {
			$raw = get_option( self::OPTION, array() );
			return is_array( $raw ) ? $raw : array();
		}
		$raw = isset( $GLOBALS['wbgs_test_options'][ self::OPTION ] ) ? $GLOBALS['wbgs_test_options'][ self::OPTION ] : array();
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * @param array<string,array> $store
	 */
	public static function write_store( $store ) {
		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION, $store, false );
			return;
		}
		$GLOBALS['wbgs_test_options'][ self::OPTION ] = $store;
	}

	/**
	 * @param array<int,array> $rows
	 * @return array<int,array>
	 */
	public static function compact_rows( $rows ) {
		$out = array();
		$n   = 0;
		foreach ( (array) $rows as $row ) {
			if ( $n >= self::ROW_CAP ) {
				break;
			}
			$text = isset( $row['text'] ) ? WBGS_Suggest::normalize_seed( $row['text'] ) : '';
			if ( $text === '' ) {
				continue;
			}
			$searches = null;
			if ( array_key_exists( 'searches', $row ) && null !== $row['searches'] && '' !== $row['searches'] ) {
				$searches = (int) $row['searches'];
			}
			$out[] = array(
				'text'      => $text,
				'relevance' => isset( $row['relevance'] ) ? (int) $row['relevance'] : 0,
				'rank'      => isset( $row['rank'] ) ? (int) $row['rank'] : 0,
				'count'     => isset( $row['count'] ) ? max( 1, (int) $row['count'] ) : 1,
				'intent'    => isset( $row['intent'] ) ? (string) $row['intent'] : WBGS_Intent::classify( $text ),
				'searches'  => $searches,
			);
			$n++;
		}
		return $out;
	}

	/**
	 * @param array $report
	 * @return array{id:string,seed:string,created:int,count:int}
	 */
	public static function summary( $report ) {
		$report = is_array( $report ) ? $report : array();
		return array(
			'id'      => isset( $report['id'] ) ? (string) $report['id'] : '',
			'seed'    => isset( $report['seed'] ) ? (string) $report['seed'] : '',
			'created' => isset( $report['created'] ) ? (int) $report['created'] : 0,
			'count'   => isset( $report['count'] ) ? (int) $report['count'] : 0,
		);
	}

	/**
	 * @param string           $seed
	 * @param array<int,array> $rows
	 * @param string           $actor
	 * @return array{ok:bool,message:string,report:?array}
	 */
	public static function save( $seed, $rows, $actor = '' ) {
		$seed = WBGS_Suggest::normalize_seed( $seed );
		$rows = self::compact_rows( $rows );
		if ( $seed === '' || ! $rows ) {
			return array(
				'ok'      => false,
				'message' => 'گزارش خالی است.',
				'report'  => null,
			);
		}
		$actor = $actor !== '' ? $actor : self::actor_key();
		$store = self::all_store();
		if ( ! isset( $store[ $actor ] ) || ! is_array( $store[ $actor ] ) ) {
			$store[ $actor ] = array();
		}

		$id     = 'r' . substr( md5( $seed . microtime( true ) . mt_rand() ), 0, 12 );
		$report = array(
			'id'      => $id,
			'seed'    => $seed,
			'created' => time(),
			'count'   => count( $rows ),
			'rows'    => $rows,
		);
		array_unshift( $store[ $actor ], $report );
		$cap                 = 0 === strpos( $actor, 'u' ) ? self::MAX_USER : self::MAX_GUEST;
		$store[ $actor ]     = array_slice( $store[ $actor ], 0, $cap );
		self::write_store( $store );

		return array(
			'ok'      => true,
			'message' => '',
			'report'  => self::summary( $report ),
		);
	}

	/**
	 * @param string $actor
	 * @return array<int,array>
	 */
	public static function list_for( $actor = '' ) {
		$actor = $actor !== '' ? $actor : self::actor_key();
		$store = self::all_store();
		$list  = isset( $store[ $actor ] ) && is_array( $store[ $actor ] ) ? $store[ $actor ] : array();
		$out   = array();
		foreach ( $list as $report ) {
			$out[] = self::summary( $report );
		}
		return $out;
	}

	/**
	 * @param string $id
	 * @param string $actor
	 * @return array|null
	 */
	public static function get( $id, $actor = '' ) {
		$id    = is_string( $id ) ? $id : '';
		$actor = $actor !== '' ? $actor : self::actor_key();
		if ( $id === '' ) {
			return null;
		}
		$store = self::all_store();
		if ( isset( $store[ $actor ] ) && is_array( $store[ $actor ] ) ) {
			foreach ( $store[ $actor ] as $report ) {
				if ( isset( $report['id'] ) && $report['id'] === $id ) {
					return $report;
				}
			}
		}
		if ( function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) ) {
			foreach ( $store as $bucket ) {
				foreach ( (array) $bucket as $report ) {
					if ( isset( $report['id'] ) && $report['id'] === $id ) {
						return $report;
					}
				}
			}
		}
		return null;
	}

	/**
	 * @param string $id
	 * @param string $actor
	 * @return bool
	 */
	public static function delete( $id, $actor = '' ) {
		$id    = is_string( $id ) ? $id : '';
		$actor = $actor !== '' ? $actor : self::actor_key();
		if ( $id === '' ) {
			return false;
		}
		$store   = self::all_store();
		$changed = false;
		$targets = array( $actor );
		if ( function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) ) {
			$targets = array_keys( $store );
		}
		foreach ( $targets as $key ) {
			if ( ! isset( $store[ $key ] ) || ! is_array( $store[ $key ] ) ) {
				continue;
			}
			$keep = array();
			foreach ( $store[ $key ] as $report ) {
				if ( isset( $report['id'] ) && $report['id'] === $id ) {
					$changed = true;
					continue;
				}
				$keep[] = $report;
			}
			$store[ $key ] = $keep;
		}
		if ( $changed ) {
			self::write_store( $store );
		}
		return $changed;
	}

	/**
	 * فهرست تخت برای زبانهٔ پیشخوان.
	 *
	 * @return array<int,array>
	 */
	public static function admin_flat() {
		$out = array();
		foreach ( self::all_store() as $actor => $list ) {
			foreach ( (array) $list as $report ) {
				$row          = self::summary( $report );
				$row['actor'] = (string) $actor;
				$out[]        = $row;
			}
		}
		usort(
			$out,
			function ( $a, $b ) {
				return (int) $b['created'] - (int) $a['created'];
			}
		);
		return $out;
	}

	/**
	 * @return string
	 */
	public static function usage_key() {
		$who = function_exists( 'is_user_logged_in' ) && is_user_logged_in()
			? ( 'u' . get_current_user_id() )
			: ( 'ip' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'x' ) );
		return 'wbgs_use_' . gmdate( 'Ymd' ) . '_' . $who;
	}

	/**
	 * @return int
	 */
	public static function usage_get() {
		if ( ! function_exists( 'get_transient' ) ) {
			return 0;
		}
		return (int) get_transient( self::usage_key() );
	}

	/**
	 * @return int
	 */
	public static function usage_bump() {
		if ( ! function_exists( 'get_transient' ) || ! function_exists( 'set_transient' ) ) {
			return 1;
		}
		$key = self::usage_key();
		$n   = (int) get_transient( $key ) + 1;
		$ttl = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
		set_transient( $key, $n, $ttl );
		return $n;
	}

	/**
	 * @return array{used:int,cap:int,admin:bool}
	 */
	public static function usage_snapshot() {
		$cap   = 400;
		$admin = function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
		if ( class_exists( 'WBGS_Plugin' ) ) {
			$settings = WBGS_Plugin::settings();
			if ( isset( $settings['guest_daily_cap'] ) ) {
				$cap = max( 50, min( 2000, (int) $settings['guest_daily_cap'] ) );
			}
		}
		return array(
			'used'  => self::usage_get(),
			'cap'   => $admin ? 0 : $cap,
			'admin' => $admin ? true : false,
		);
	}
}
