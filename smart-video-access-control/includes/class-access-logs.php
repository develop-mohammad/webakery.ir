<?php
defined( 'ABSPATH' ) || exit;

final class SVAC_Access_Logs {
	public static function activate(): void {
		global $wpdb;
		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			"CREATE TABLE {$table_name} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				video_id bigint(20) unsigned NOT NULL,
				access_time datetime NOT NULL,
				ip_address varchar(45) NOT NULL DEFAULT '',
				status varchar(20) NOT NULL,
				PRIMARY KEY  (id),
				KEY video_id (video_id),
				KEY user_id (user_id),
				KEY access_time (access_time)
			) {$charset_collate};"
		);
	}

	public static function log( int $video_id, bool $allowed ): void {
		global $wpdb;
		$wpdb->insert(
			self::table_name(),
			array(
				'user_id'     => get_current_user_id(),
				'video_id'    => $video_id,
				'access_time' => current_time( 'mysql', true ),
				'ip_address'  => self::get_ip(),
				'status'      => $allowed ? 'allowed' : 'denied',
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);
	}

	public static function get_logs( int $limit = 50 ): array {
		global $wpdb;
		$limit = min( max( 1, $limit ), 500 );
		return (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}video_access_logs ORDER BY access_time DESC LIMIT %d", $limit ),
			ARRAY_A
		);
	}

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'video_access_logs';
	}

	private static function get_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}
