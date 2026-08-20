<?php
/**
 * enamad-order — ذخیره‌سازی سبک بر پایه فایل JSON (بدون نیاز به دیتابیس)
 */
class EO_Database {

	private static $data = null;
	private static $file = null;

	private static function file(): string {
		if ( self::$file ) {
			return self::$file;
		}
		$dir = __DIR__ . '/../data/';
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0755, true );
		}
		self::$file = $dir . 'orders.json';
		return self::$file;
	}

	private static function load(): array {
		if ( self::$data !== null ) {
			return self::$data;
		}
		$f = self::file();
		if ( ! file_exists( $f ) ) {
			self::$data = [ 'orders' => [] ];
		} else {
			$decoded    = json_decode( (string) file_get_contents( $f ), true );
			self::$data = is_array( $decoded ) && isset( $decoded['orders'] ) ? $decoded : [ 'orders' => [] ];
		}
		return self::$data;
	}

	private static function save(): void {
		$tmp = self::file() . '.tmp';
		file_put_contents( $tmp, json_encode( self::$data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ), LOCK_EX );
		rename( $tmp, self::file() );
	}

	/** مسیر فایل ذخیره‌سازی را برای تست عوض می‌کند. */
	public static function set_file( string $path ): void {
		self::$file = $path;
		self::$data = null;
	}

	public static function reset(): void {
		self::$file = null;
		self::$data = null;
	}

	public static function insert( array $order ): void {
		self::load();
		self::$data['orders'][] = $order;
		self::save();
	}

	public static function update_by_track( string $track_id, array $changes ): void {
		self::load();
		foreach ( self::$data['orders'] as &$o ) {
			if ( ( $o['track_id'] ?? '' ) === $track_id ) {
				$o = array_merge( $o, $changes );
				break;
			}
		}
		unset( $o );
		self::save();
	}

	public static function update_by_code( string $order_code, array $changes ): void {
		self::load();
		foreach ( self::$data['orders'] as &$o ) {
			if ( ( $o['order_code'] ?? '' ) === $order_code ) {
				$o = array_merge( $o, $changes );
				break;
			}
		}
		unset( $o );
		self::save();
	}

	public static function find_by_track( string $track_id ): ?array {
		foreach ( self::load()['orders'] as $o ) {
			if ( ( $o['track_id'] ?? '' ) === $track_id ) {
				return $o;
			}
		}
		return null;
	}

	public static function find_by_code( string $order_code ): ?array {
		foreach ( self::load()['orders'] as $o ) {
			if ( ( $o['order_code'] ?? '' ) === $order_code ) {
				return $o;
			}
		}
		return null;
	}

	public static function order_code_exists( string $order_code ): bool {
		return self::find_by_code( $order_code ) !== null;
	}

	public static function all( int $limit = 200, int $offset = 0 ): array {
		$all = array_reverse( self::load()['orders'] );
		return array_slice( $all, $offset, $limit );
	}

	public static function total(): int {
		return count( self::load()['orders'] );
	}

	public static function count_by_status( string $status ): int {
		return count( array_filter( self::load()['orders'], fn( $o ) => ( $o['status'] ?? '' ) === $status ) );
	}
}
