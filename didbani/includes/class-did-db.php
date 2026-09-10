<?php
defined( 'ABSPATH' ) || exit;

/**
 * جداول سفارشی دیدبانی.
 */
class DID_Db {

	const PROJECTS = 'did_projects';
	const DOMAINS  = 'did_domains';
	const KEYWORDS = 'did_keywords';
	const PAGES    = 'did_pages';
	const RANKS    = 'did_ranks';
	const JOBS     = 'did_jobs';
	const HISTORY   = 'did_rank_history';
	const BACKLINKS = 'did_backlinks';
	const SCHEMA    = 3;

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . $name;
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		$projects = self::table( self::PROJECTS );
		$domains  = self::table( self::DOMAINS );
		$keywords = self::table( self::KEYWORDS );
		$pages    = self::table( self::PAGES );
		$ranks    = self::table( self::RANKS );
		$jobs      = self::table( self::JOBS );
		$history   = self::table( self::HISTORY );
		$backlinks = self::table( self::BACKLINKS );

		dbDelta(
			"CREATE TABLE {$projects} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(190) NOT NULL DEFAULT '',
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id)
			) {$charset};\n"
		);

		dbDelta(
			"CREATE TABLE {$domains} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				project_id bigint(20) unsigned NOT NULL,
				host varchar(190) NOT NULL DEFAULT '',
				kind varchar(20) NOT NULL DEFAULT 'competitor',
				last_crawled_at datetime DEFAULT NULL,
				crawl_status varchar(20) NOT NULL DEFAULT '',
				crawl_error text NULL,
				page_count int unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				KEY project_id (project_id),
				KEY host (host)
			) {$charset};\n"
		);

		dbDelta(
			"CREATE TABLE {$keywords} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				project_id bigint(20) unsigned NOT NULL,
				keyword varchar(190) NOT NULL DEFAULT '',
				keyword_norm varchar(190) NOT NULL DEFAULT '',
				PRIMARY KEY  (id),
				KEY project_id (project_id)
			) {$charset};\n"
		);

		dbDelta(
			"CREATE TABLE {$pages} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				project_id bigint(20) unsigned NOT NULL,
				domain_id bigint(20) unsigned NOT NULL,
				url text NOT NULL,
				status_code smallint NOT NULL DEFAULT 0,
				title text NULL,
				meta_description text NULL,
				h1 text NULL,
				canonical text NULL,
				word_count int unsigned NOT NULL DEFAULT 0,
				keyword_hits longtext NULL,
				fetched_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY project_domain (project_id, domain_id)
			) {$charset};\n"
		);

		dbDelta(
			"CREATE TABLE {$ranks} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				project_id bigint(20) unsigned NOT NULL,
				keyword_id bigint(20) unsigned NOT NULL,
				domain_id bigint(20) unsigned NOT NULL,
				engine varchar(20) NOT NULL DEFAULT '',
				provider varchar(30) NOT NULL DEFAULT '',
				device varchar(16) NOT NULL DEFAULT 'desktop',
				city varchar(40) NOT NULL DEFAULT '',
				position smallint NOT NULL DEFAULT 0,
				prev_position smallint NOT NULL DEFAULT 0,
				result_url text NULL,
				result_title text NULL,
				approximate tinyint(1) NOT NULL DEFAULT 0,
				checked_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY lookup (project_id, keyword_id, domain_id, engine),
				KEY lookup_geo (project_id, keyword_id, domain_id, engine, device, city)
			) {$charset};\n"
		);

		dbDelta(
			"CREATE TABLE {$history} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				project_id bigint(20) unsigned NOT NULL,
				keyword_id bigint(20) unsigned NOT NULL,
				domain_id bigint(20) unsigned NOT NULL,
				engine varchar(20) NOT NULL DEFAULT '',
				device varchar(16) NOT NULL DEFAULT 'desktop',
				city varchar(40) NOT NULL DEFAULT '',
				position smallint NOT NULL DEFAULT 0,
				checked_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY slice (project_id, keyword_id, domain_id, engine, device, city, id)
			) {$charset};\n"
		);

		dbDelta(
			"CREATE TABLE {$jobs} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				project_id bigint(20) unsigned NOT NULL,
				type varchar(20) NOT NULL DEFAULT '',
				status varchar(20) NOT NULL DEFAULT 'pending',
				message text NULL,
				payload longtext NULL,
				created_at datetime NOT NULL,
				finished_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY project_status (project_id, status)
			) {$charset};\n"
		);

		dbDelta(
			"CREATE TABLE {$backlinks} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				project_id bigint(20) unsigned NOT NULL,
				domain_id bigint(20) unsigned NOT NULL,
				backlinks int unsigned NOT NULL DEFAULT 0,
				referring_domains int unsigned NOT NULL DEFAULT 0,
				referring_pages int unsigned NOT NULL DEFAULT 0,
				domain_rank int unsigned NOT NULL DEFAULT 0,
				anchors longtext NULL,
				error text NULL,
				checked_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY lookup (project_id, domain_id)
			) {$charset};\n"
		);
	}

	public static function maybe_upgrade() {
		if ( ! function_exists( 'get_option' ) ) {
			return;
		}
		$v = (int) get_option( 'did_db_version', 0 );
		if ( $v < self::SCHEMA ) {
			self::install();
			update_option( 'did_db_version', self::SCHEMA, false );
		}
	}

	public static function now() {
		return current_time( 'mysql' );
	}

	/* ─── پروژه ─── */

	public static function projects() {
		global $wpdb;
		$t = self::table( self::PROJECTS );
		return $wpdb->get_results( "SELECT * FROM {$t} ORDER BY id DESC", ARRAY_A ); // phpcs:ignore
	}

	public static function project( $id ) {
		global $wpdb;
		$t = self::table( self::PROJECTS );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore
	}

	public static function save_project( $id, $name ) {
		global $wpdb;
		$t    = self::table( self::PROJECTS );
		$name = sanitize_text_field( $name );
		if ( '' === $name ) {
			$name = 'پروژه بدون نام';
		}
		$now = self::now();
		if ( $id ) {
			$wpdb->update( $t, array( 'name' => $name, 'updated_at' => $now ), array( 'id' => (int) $id ) );
			return (int) $id;
		}
		$wpdb->insert(
			$t,
			array(
				'name'       => $name,
				'created_at' => $now,
				'updated_at' => $now,
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function delete_project( $id ) {
		global $wpdb;
		$id = (int) $id;
		$wpdb->delete( self::table( self::PROJECTS ), array( 'id' => $id ) );
		$wpdb->delete( self::table( self::DOMAINS ), array( 'project_id' => $id ) );
		$wpdb->delete( self::table( self::KEYWORDS ), array( 'project_id' => $id ) );
		$wpdb->delete( self::table( self::PAGES ), array( 'project_id' => $id ) );
		$wpdb->delete( self::table( self::RANKS ), array( 'project_id' => $id ) );
		$wpdb->delete( self::table( self::HISTORY ), array( 'project_id' => $id ) );
		$wpdb->delete( self::table( self::JOBS ), array( 'project_id' => $id ) );
		$wpdb->delete( self::table( self::BACKLINKS ), array( 'project_id' => $id ) );
	}

	/**
	 * اگر دامنهٔ خود سایت خالی باشد، از آدرس وردپرس پر می‌شود.
	 *
	 * @param int    $project_id
	 * @param string $fallback_host
	 * @return int domain_id یا ۰
	 */
	public static function ensure_own_domain( $project_id, $fallback_host = '' ) {
		$project_id = (int) $project_id;
		$domains    = self::domains( $project_id );
		foreach ( $domains as $d ) {
			if ( 'own' === $d['kind'] && '' !== $d['host'] ) {
				return (int) $d['id'];
			}
		}
		$host = DID_Url::host( $fallback_host );
		if ( '' === $host && function_exists( 'home_url' ) ) {
			$host = DID_Url::host( home_url() );
		}
		if ( '' === $host && function_exists( 'site_url' ) ) {
			$host = DID_Url::host( site_url() );
		}
		if ( '' === $host ) {
			return 0;
		}
		$comps = array();
		foreach ( $domains as $d ) {
			if ( 'competitor' === $d['kind'] && '' !== $d['host'] ) {
				$comps[] = $d['host'];
			}
		}
		self::upsert_domains( $project_id, $host, $comps );
		foreach ( self::domains( $project_id ) as $d ) {
			if ( 'own' === $d['kind'] ) {
				return (int) $d['id'];
			}
		}
		return 0;
	}

	/* ─── دامنه ─── */

	public static function domains( $project_id ) {
		global $wpdb;
		$t = self::table( self::DOMAINS );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE project_id = %d ORDER BY kind ASC, id ASC", (int) $project_id ), ARRAY_A ); // phpcs:ignore
	}

	public static function domain( $id ) {
		global $wpdb;
		$t = self::table( self::DOMAINS );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore
	}

	public static function upsert_domains( $project_id, $own_host, array $competitors ) {
		global $wpdb;
		$t     = self::table( self::DOMAINS );
		$keep  = array();
		$own   = DID_Url::host( $own_host );
		if ( $own ) {
			$keep[] = self::upsert_domain( $project_id, $own, 'own' );
		}
		$seen = array( $own => true );
		foreach ( $competitors as $c ) {
			$host = DID_Url::host( $c );
			if ( '' === $host || isset( $seen[ $host ] ) ) {
				continue;
			}
			$seen[ $host ] = true;
			$keep[]        = self::upsert_domain( $project_id, $host, 'competitor' );
		}
		$keep = array_filter( array_map( 'intval', $keep ) );
		if ( $keep ) {
			$in = implode( ',', $keep );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE project_id = %d AND id NOT IN ({$in})", (int) $project_id ) ); // phpcs:ignore
		} else {
			$wpdb->delete( $t, array( 'project_id' => (int) $project_id ) );
		}
	}

	private static function upsert_domain( $project_id, $host, $kind ) {
		global $wpdb;
		$t   = self::table( self::DOMAINS );
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT id FROM {$t} WHERE project_id = %d AND host = %s", (int) $project_id, $host ),
			ARRAY_A
		); // phpcs:ignore
		if ( $row ) {
			$wpdb->update( $t, array( 'kind' => $kind ), array( 'id' => (int) $row['id'] ) );
			return (int) $row['id'];
		}
		$wpdb->insert(
			$t,
			array(
				'project_id' => (int) $project_id,
				'host'       => $host,
				'kind'       => $kind,
			)
		);
		return (int) $wpdb->insert_id;
	}

	/* ─── کلیدواژه ─── */

	public static function keywords( $project_id ) {
		global $wpdb;
		$t = self::table( self::KEYWORDS );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE project_id = %d ORDER BY id ASC", (int) $project_id ), ARRAY_A ); // phpcs:ignore
	}

	public static function upsert_keywords( $project_id, array $keywords ) {
		global $wpdb;
		$t    = self::table( self::KEYWORDS );
		$keep = array();
		$seen = array();
		foreach ( $keywords as $kw ) {
			$display = sanitize_text_field( $kw );
			$norm    = DID_Text::normalize( $display );
			if ( '' === $norm || isset( $seen[ $norm ] ) ) {
				continue;
			}
			$seen[ $norm ] = true;
			$row           = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id FROM {$t} WHERE project_id = %d AND keyword_norm = %s",
					(int) $project_id,
					$norm
				),
				ARRAY_A
			); // phpcs:ignore
			if ( $row ) {
				$wpdb->update( $t, array( 'keyword' => $display ), array( 'id' => (int) $row['id'] ) );
				$keep[] = (int) $row['id'];
			} else {
				$wpdb->insert(
					$t,
					array(
						'project_id'   => (int) $project_id,
						'keyword'      => $display,
						'keyword_norm' => $norm,
					)
				);
				$keep[] = (int) $wpdb->insert_id;
			}
		}
		if ( $keep ) {
			$in = implode( ',', array_map( 'intval', $keep ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE project_id = %d AND id NOT IN ({$in})", (int) $project_id ) ); // phpcs:ignore
		} else {
			$wpdb->delete( $t, array( 'project_id' => (int) $project_id ) );
		}
	}

	/* ─── صفحات ─── */

	public static function pages( $project_id, $domain_id = 0, $limit = 200 ) {
		global $wpdb;
		$t = self::table( self::PAGES );
		if ( $domain_id ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$t} WHERE project_id = %d AND domain_id = %d ORDER BY id DESC LIMIT %d",
					(int) $project_id,
					(int) $domain_id,
					(int) $limit
				),
				ARRAY_A
			); // phpcs:ignore
		}
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$t} WHERE project_id = %d ORDER BY id DESC LIMIT %d",
				(int) $project_id,
				(int) $limit
			),
			ARRAY_A
		); // phpcs:ignore
	}

	public static function clear_pages( $project_id, $domain_id ) {
		global $wpdb;
		$wpdb->delete(
			self::table( self::PAGES ),
			array(
				'project_id' => (int) $project_id,
				'domain_id'  => (int) $domain_id,
			)
		);
	}

	public static function insert_page( array $row ) {
		global $wpdb;
		$wpdb->insert( self::table( self::PAGES ), $row );
		return (int) $wpdb->insert_id;
	}

	public static function update_domain_crawl( $domain_id, $status, $error, $page_count ) {
		global $wpdb;
		$wpdb->update(
			self::table( self::DOMAINS ),
			array(
				'last_crawled_at' => self::now(),
				'crawl_status'    => $status,
				'crawl_error'     => $error,
				'page_count'      => (int) $page_count,
			),
			array( 'id' => (int) $domain_id )
		);
	}

	/* ─── رتبه ─── */

	public static function ranks( $project_id, $city = '', $device = '' ) {
		global $wpdb;
		$t = self::table( self::RANKS );
		if ( '' !== $city && '' !== $device ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$t} WHERE project_id = %d AND city = %s AND device = %s",
					(int) $project_id,
					$city,
					$device
				),
				ARRAY_A
			); // phpcs:ignore
		}
		if ( '' !== $device ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$t} WHERE project_id = %d AND device = %s",
					(int) $project_id,
					$device
				),
				ARRAY_A
			); // phpcs:ignore
		}
		if ( '' !== $city ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$t} WHERE project_id = %d AND city = %s",
					(int) $project_id,
					$city
				),
				ARRAY_A
			); // phpcs:ignore
		}
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE project_id = %d", (int) $project_id ), ARRAY_A ); // phpcs:ignore
	}

	public static function upsert_rank( array $row ) {
		global $wpdb;
		$t      = self::table( self::RANKS );
		$device = isset( $row['device'] ) ? DID_Geo::sanitize_device( $row['device'] ) : 'desktop';
		$city   = isset( $row['city'] ) ? strtolower( preg_replace( '/[^a-z0-9_]/', '', (string) $row['city'] ) ) : '';
		$row['device'] = $device;
		$row['city']   = $city;

		$ex = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, position FROM {$t} WHERE project_id = %d AND keyword_id = %d AND domain_id = %d AND engine = %s AND device = %s AND city = %s",
				(int) $row['project_id'],
				(int) $row['keyword_id'],
				(int) $row['domain_id'],
				$row['engine'],
				$device,
				$city
			),
			ARRAY_A
		); // phpcs:ignore

		$prev = $ex ? (int) $ex['position'] : 0;
		$row['prev_position'] = $prev;
		$row['checked_at']    = self::now();

		if ( $ex ) {
			$wpdb->update( $t, $row, array( 'id' => (int) $ex['id'] ) );
			$id = (int) $ex['id'];
		} else {
			$wpdb->insert( $t, $row );
			$id = (int) $wpdb->insert_id;
		}

		self::append_history( $row );
		return $id;
	}

	private static function append_history( array $row ) {
		global $wpdb;
		$h = self::table( self::HISTORY );
		$wpdb->insert(
			$h,
			array(
				'project_id' => (int) $row['project_id'],
				'keyword_id' => (int) $row['keyword_id'],
				'domain_id'  => (int) $row['domain_id'],
				'engine'     => $row['engine'],
				'device'     => $row['device'],
				'city'       => $row['city'],
				'position'   => (int) $row['position'],
				'checked_at' => self::now(),
			)
		);
		$keep = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$h} WHERE project_id = %d AND keyword_id = %d AND domain_id = %d AND engine = %s AND device = %s AND city = %s ORDER BY id DESC LIMIT 40",
				(int) $row['project_id'],
				(int) $row['keyword_id'],
				(int) $row['domain_id'],
				$row['engine'],
				$row['device'],
				$row['city']
			)
		); // phpcs:ignore
		if ( $keep ) {
			$in = implode( ',', array_map( 'intval', $keep ) );
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$h} WHERE project_id = %d AND keyword_id = %d AND domain_id = %d AND engine = %s AND device = %s AND city = %s AND id NOT IN ({$in})",
					(int) $row['project_id'],
					(int) $row['keyword_id'],
					(int) $row['domain_id'],
					$row['engine'],
					$row['device'],
					$row['city']
				)
			); // phpcs:ignore
		}
	}

	/* ─── صف ─── */

	public static function insert_job( $project_id, $type, array $payload ) {
		global $wpdb;
		$wpdb->insert(
			self::table( self::JOBS ),
			array(
				'project_id' => (int) $project_id,
				'type'       => $type,
				'status'     => 'pending',
				'message'    => '',
				'payload'    => wp_json_encode( $payload ),
				'created_at' => self::now(),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function job( $id ) {
		global $wpdb;
		$t = self::table( self::JOBS );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore
	}

	public static function latest_job( $project_id, $type ) {
		global $wpdb;
		$t = self::table( self::JOBS );
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$t} WHERE project_id = %d AND type = %s ORDER BY id DESC LIMIT 1",
				(int) $project_id,
				$type
			),
			ARRAY_A
		); // phpcs:ignore
	}

	public static function active_job( $project_id, $type ) {
		global $wpdb;
		$t = self::table( self::JOBS );
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$t} WHERE project_id = %d AND type = %s AND status IN ('pending','running') ORDER BY id DESC LIMIT 1",
				(int) $project_id,
				$type
			),
			ARRAY_A
		); // phpcs:ignore
	}

	public static function update_job( $id, array $data ) {
		global $wpdb;
		$wpdb->update( self::table( self::JOBS ), $data, array( 'id' => (int) $id ) );
	}

	/* ─── بک‌لینک ─── */

	public static function backlinks( $project_id ) {
		global $wpdb;
		$t = self::table( self::BACKLINKS );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE project_id = %d", (int) $project_id ), ARRAY_A ); // phpcs:ignore
	}

	public static function upsert_backlink( array $row ) {
		global $wpdb;
		$t  = self::table( self::BACKLINKS );
		$ex = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$t} WHERE project_id = %d AND domain_id = %d",
				(int) $row['project_id'],
				(int) $row['domain_id']
			),
			ARRAY_A
		); // phpcs:ignore
		$row['checked_at'] = self::now();
		if ( $ex ) {
			$wpdb->update( $t, $row, array( 'id' => (int) $ex['id'] ) );
			return (int) $ex['id'];
		}
		$wpdb->insert( $t, $row );
		return (int) $wpdb->insert_id;
	}

	public static function pending_jobs() {
		global $wpdb;
		$t = self::table( self::JOBS );
		return $wpdb->get_results( "SELECT * FROM {$t} WHERE status IN ('pending','running') ORDER BY id ASC LIMIT 5", ARRAY_A ); // phpcs:ignore
	}
}
