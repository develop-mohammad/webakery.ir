<?php
defined( 'ABSPATH' ) || exit;

class NCK_Install {

	const DB_VERSION     = '1.1.0';
	const VERSION_OPTION = 'nck_db_version';

	public static function activate() {
		self::create_tables();
		update_option( self::VERSION_OPTION, self::DB_VERSION, false );
		if ( ! get_option( NCK_Settings::OPTION ) ) {
			add_option( self::option_name(), NCK_Settings::defaults(), '', false );
		}
		$opt = 'wbl_' . NCK_PRODUCT . '_install_time';
		if ( ! get_option( $opt ) ) {
			add_option( $opt, time(), '', false );
		}
	}

	private static function option_name() {
		return NCK_Settings::OPTION;
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'nck_daily_maintenance' );
	}

	public static function maybe_upgrade() {
		$cur = get_option( self::VERSION_OPTION, '' );
		if ( self::DB_VERSION !== $cur ) {
			self::create_tables();
			update_option( self::VERSION_OPTION, self::DB_VERSION, false );
		}
		NCK_Settings::fix_legacy_intro();
	}

	public static function create_tables() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix;

		$sql = array();

		$sql[] = "CREATE TABLE {$p}nck_members (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			full_name VARCHAR(190) NOT NULL,
			honorific VARCHAR(10) NOT NULL DEFAULT 'mr',
			phone VARCHAR(20) NOT NULL,
			national_id VARCHAR(20) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			notes TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY phone (phone),
			KEY status (status),
			KEY full_name (full_name)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}nck_contracts (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			member_id BIGINT UNSIGNED NOT NULL,
			kind VARCHAR(20) NOT NULL DEFAULT 'cowork',
			plan VARCHAR(20) NOT NULL DEFAULT 'morning',
			full_name VARCHAR(190) NOT NULL,
			honorific VARCHAR(10) NOT NULL DEFAULT 'mr',
			phone VARCHAR(20) NOT NULL,
			national_id VARCHAR(20) NULL,
			print_token VARCHAR(64) NOT NULL,
			signature_png LONGTEXT NULL,
			payload LONGTEXT NULL,
			signed_at DATETIME NULL,
			signed_ip VARCHAR(45) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'signed',
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY print_token (print_token),
			KEY member_id (member_id),
			KEY kind (kind),
			KEY status (status),
			KEY signed_at (signed_at)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}nck_subscriptions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			member_id BIGINT UNSIGNED NOT NULL,
			contract_id BIGINT UNSIGNED NULL,
			shift_type VARCHAR(20) NOT NULL,
			shifts_per_month SMALLINT UNSIGNED NOT NULL DEFAULT 26,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY member_id (member_id),
			KEY shift_type (shift_type),
			KEY status (status)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}nck_attendances (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			member_id BIGINT UNSIGNED NOT NULL,
			subscription_id BIGINT UNSIGNED NULL,
			shift_date DATE NOT NULL,
			shift_type VARCHAR(20) NOT NULL,
			jalali_year SMALLINT UNSIGNED NOT NULL,
			jalali_month TINYINT UNSIGNED NOT NULL,
			source VARCHAR(20) NOT NULL DEFAULT 'front',
			checked_in_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY member_day_shift (member_id, shift_date, shift_type),
			KEY shift_date (shift_date),
			KEY jalali_month (jalali_year, jalali_month),
			KEY member_month (member_id, jalali_year, jalali_month, shift_type)
		) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}
}
