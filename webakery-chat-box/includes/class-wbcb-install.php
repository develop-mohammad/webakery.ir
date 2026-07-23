<?php
defined( 'ABSPATH' ) || exit;

class WBCB_Install {

	const DB_VERSION = '1.0.0';
	const VERSION_OPTION = 'wbcb_db_version';

	public static function activate() {
		self::create_tables();
		update_option( self::VERSION_OPTION, self::DB_VERSION, false );
		if ( ! get_option( WBCB_Settings::OPTION ) ) {
			add_option( WBCB_Settings::OPTION, WBCB_Settings::defaults(), '', false );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'wbcb_cleanup_old_conversations' );
	}

	public static function create_tables() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix;

		$sql = array();

		$sql[] = "CREATE TABLE {$p}wbcb_conversations (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			visitor_token VARCHAR(64) NOT NULL,
			visitor_name VARCHAR(190) NULL,
			visitor_email VARCHAR(190) NULL,
			page_url TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'open',
			unread_admin TINYINT(1) NOT NULL DEFAULT 1,
			last_message_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY visitor_token (visitor_token),
			KEY status (status),
			KEY unread_admin (unread_admin),
			KEY last_message_at (last_message_at)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}wbcb_messages (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			conversation_id BIGINT UNSIGNED NOT NULL,
			sender VARCHAR(20) NOT NULL,
			body LONGTEXT NOT NULL,
			meta LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY conversation_id (conversation_id),
			KEY created_at (created_at)
		) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	public static function maybe_upgrade() {
		$cur = get_option( self::VERSION_OPTION, '' );
		if ( self::DB_VERSION === $cur ) {
			return;
		}
		self::create_tables();
		update_option( self::VERSION_OPTION, self::DB_VERSION, false );
	}
}
