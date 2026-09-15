<?php
defined( 'ABSPATH' ) || exit;

class WBSS_Install {

	const DB_VERSION    = '1.0.0';
	const VERSION_OPTION = 'wbss_db_version';

	public static function activate() {
		self::create_tables();
		update_option( self::VERSION_OPTION, self::DB_VERSION, false );

		if ( ! get_option( 'wbss_settings' ) ) {
			add_option(
				'wbss_settings',
				array(
					'default_project' => 0,
					'seed_demo'       => 1,
				),
				'',
				false
			);
		}

		WBSS_Seed::maybe_seed();
	}

	public static function maybe_upgrade() {
		$cur = get_option( self::VERSION_OPTION, '' );
		if ( self::DB_VERSION === $cur ) {
			return;
		}
		self::create_tables();
		update_option( self::VERSION_OPTION, self::DB_VERSION, false );
		WBSS_Seed::maybe_seed();
	}

	public static function create_tables() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix;

		$sql = array();

		$sql[] = "CREATE TABLE {$p}wbss_projects (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			domain VARCHAR(190) NULL,
			notes TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}wbss_keywords (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			project_id BIGINT UNSIGNED NOT NULL,
			keyword VARCHAR(255) NOT NULL,
			intent VARCHAR(20) NOT NULL DEFAULT 'informational',
			volume INT UNSIGNED NOT NULL DEFAULT 0,
			difficulty TINYINT UNSIGNED NOT NULL DEFAULT 0,
			page_url TEXT NULL,
			notes TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY project_id (project_id),
			KEY status (status)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}wbss_ranks (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			keyword_id BIGINT UNSIGNED NOT NULL,
			checked_at DATE NOT NULL,
			position SMALLINT UNSIGNED NOT NULL DEFAULT 101,
			page_url TEXT NULL,
			engine VARCHAR(20) NOT NULL DEFAULT 'google',
			device VARCHAR(20) NOT NULL DEFAULT 'desktop',
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY keyword_id (keyword_id),
			KEY checked_at (checked_at)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}wbss_content (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			project_id BIGINT UNSIGNED NOT NULL,
			title VARCHAR(255) NOT NULL,
			url TEXT NULL,
			keyword_id BIGINT UNSIGNED NULL,
			word_count INT UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			published_at DATE NULL,
			notes TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY project_id (project_id),
			KEY status (status)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}wbss_technical (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			project_id BIGINT UNSIGNED NOT NULL,
			title VARCHAR(255) NOT NULL,
			category VARCHAR(40) NOT NULL DEFAULT 'other',
			severity VARCHAR(20) NOT NULL DEFAULT 'medium',
			status VARCHAR(20) NOT NULL DEFAULT 'open',
			page_url TEXT NULL,
			notes TEXT NULL,
			created_at DATETIME NOT NULL,
			done_at DATETIME NULL,
			PRIMARY KEY (id),
			KEY project_id (project_id),
			KEY status (status)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}wbss_backlinks (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			project_id BIGINT UNSIGNED NOT NULL,
			source_url TEXT NOT NULL,
			target_url TEXT NULL,
			anchor VARCHAR(255) NULL,
			rel_type VARCHAR(20) NOT NULL DEFAULT 'dofollow',
			da TINYINT UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'live',
			acquired_at DATE NULL,
			notes TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY project_id (project_id),
			KEY status (status)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}wbss_press (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			project_id BIGINT UNSIGNED NOT NULL,
			outlet VARCHAR(190) NOT NULL,
			article_url TEXT NULL,
			target_url TEXT NULL,
			topic VARCHAR(255) NULL,
			cost INT UNSIGNED NOT NULL DEFAULT 0,
			publish_date DATE NULL,
			follow_type VARCHAR(20) NOT NULL DEFAULT 'dofollow',
			status VARCHAR(20) NOT NULL DEFAULT 'planned',
			notes TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY project_id (project_id),
			KEY status (status)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}wbss_activity (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			module VARCHAR(40) NOT NULL,
			action_type VARCHAR(20) NOT NULL,
			entity_id BIGINT UNSIGNED NULL,
			title VARCHAR(255) NOT NULL,
			meta LONGTEXT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY project_id (project_id),
			KEY module (module),
			KEY created_at (created_at)
		) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}
}
