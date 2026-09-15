<?php
defined( 'ABSPATH' ) || exit;

/**
 * دسترسی به جداول سئو استودیو و گزارش‌های تجمیعی.
 */
class WBSS_DB {

	public static function table( $name ) {
		global $wpdb;
		$map = array(
			'projects'   => 'wbss_projects',
			'keywords'   => 'wbss_keywords',
			'ranks'      => 'wbss_ranks',
			'content'    => 'wbss_content',
			'technical'  => 'wbss_technical',
			'backlinks'  => 'wbss_backlinks',
			'press'      => 'wbss_press',
			'activity'   => 'wbss_activity',
		);
		$key = isset( $map[ $name ] ) ? $map[ $name ] : '';
		return $key ? $wpdb->prefix . $key : '';
	}

	public static function now() {
		return current_time( 'mysql' );
	}

	public static function log( $project_id, $module, $action, $title, $entity_id = 0, $meta = array() ) {
		global $wpdb;
		$wpdb->insert(
			self::table( 'activity' ),
			array(
				'project_id'  => (int) $project_id,
				'module'      => sanitize_key( $module ),
				'action_type' => sanitize_key( $action ),
				'entity_id'   => (int) $entity_id,
				'title'       => sanitize_text_field( $title ),
				'meta'        => wp_json_encode( $meta, JSON_UNESCAPED_UNICODE ),
				'user_id'     => get_current_user_id(),
				'created_at'  => self::now(),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	public static function projects() {
		global $wpdb;
		$t = self::table( 'projects' );
		return $wpdb->get_results( "SELECT * FROM {$t} ORDER BY id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function project( $id ) {
		global $wpdb;
		$t = self::table( 'projects' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function save_project( $data ) {
		global $wpdb;
		$id   = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$row  = array(
			'name'   => sanitize_text_field( $data['name'] ?? '' ),
			'domain' => esc_url_raw( $data['domain'] ?? '' ),
			'notes'  => sanitize_textarea_field( $data['notes'] ?? '' ),
		);
		if ( '' === $row['name'] ) {
			return new WP_Error( 'wbss_name', 'نام پروژه لازم است.' );
		}
		$t = self::table( 'projects' );
		if ( $id ) {
			$wpdb->update( $t, $row, array( 'id' => $id ) );
			self::log( $id, 'project', 'updated', 'ویرایش پروژه: ' . $row['name'], $id );
			return $id;
		}
		$row['created_at'] = self::now();
		$wpdb->insert( $t, $row );
		$id = (int) $wpdb->insert_id;
		self::log( $id, 'project', 'created', 'پروژه جدید: ' . $row['name'], $id );
		return $id;
	}

	public static function delete_project( $id ) {
		global $wpdb;
		$id = (int) $id;
		$p  = self::project( $id );
		if ( ! $p ) {
			return false;
		}
		$kw_t = self::table( 'keywords' );
		$ids  = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$kw_t} WHERE project_id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $ids ) {
			$in = implode( ',', array_map( 'intval', $ids ) );
			$wpdb->query( "DELETE FROM " . self::table( 'ranks' ) . " WHERE keyword_id IN ({$in})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		foreach ( array( 'keywords', 'content', 'technical', 'backlinks', 'press', 'activity' ) as $mod ) {
			$wpdb->delete( self::table( $mod ), array( 'project_id' => $id ), array( '%d' ) );
		}
		$wpdb->delete( self::table( 'projects' ), array( 'id' => $id ), array( '%d' ) );
		self::log( 0, 'project', 'deleted', 'حذف پروژه: ' . $p->name, $id );
		return true;
	}

	public static function list_rows( $module, $project_id, $args = array() ) {
		$project_id = (int) $project_id;
		switch ( $module ) {
			case 'keywords':
				return self::list_keywords( $project_id, $args );
			case 'content':
				return self::simple_list( 'content', $project_id, 'created_at DESC' );
			case 'technical':
				return self::simple_list( 'technical', $project_id, 'FIELD(status,"open","in_progress","done"), FIELD(severity,"critical","high","medium","low"), id DESC' );
			case 'backlinks':
				return self::simple_list( 'backlinks', $project_id, 'acquired_at DESC, id DESC' );
			case 'press':
				return self::simple_list( 'press', $project_id, 'publish_date DESC, id DESC' );
			case 'activity':
				return self::list_activity( $project_id, $args );
			default:
				return array();
		}
	}

	private static function simple_list( $module, $project_id, $order ) {
		global $wpdb;
		$t = self::table( $module );
		if ( ! $t ) {
			return array();
		}
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE project_id = %d ORDER BY {$order}", $project_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function list_keywords( $project_id, $args = array() ) {
		global $wpdb;
		$kt = self::table( 'keywords' );
		$rt = self::table( 'ranks' );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT k.*,
					(SELECT r.position FROM {$rt} r WHERE r.keyword_id = k.id ORDER BY r.checked_at DESC, r.id DESC LIMIT 1) AS current_rank,
					(SELECT r.checked_at FROM {$rt} r WHERE r.keyword_id = k.id ORDER BY r.checked_at DESC, r.id DESC LIMIT 1) AS last_checked,
					(SELECT r.position FROM {$rt} r WHERE r.keyword_id = k.id ORDER BY r.checked_at DESC, r.id DESC LIMIT 1 OFFSET 1) AS previous_rank
				FROM {$kt} k
				WHERE k.project_id = %d
				ORDER BY k.id DESC",
				(int) $project_id
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( $rows as $row ) {
			$cur  = $row->current_rank ? (int) $row->current_rank : 0;
			$prev = $row->previous_rank ? (int) $row->previous_rank : 0;
			$row->change = ( $cur && $prev ) ? ( $prev - $cur ) : 0;
		}
		return $rows;
	}

	public static function keyword_ranks( $keyword_id, $limit = 90 ) {
		global $wpdb;
		$t = self::table( 'ranks' );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$t} WHERE keyword_id = %d ORDER BY checked_at ASC, id ASC LIMIT %d",
				(int) $keyword_id,
				(int) $limit
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function get_row( $module, $id ) {
		global $wpdb;
		$t = self::table( $module );
		if ( ! $t ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function save_row( $module, $data ) {
		$map = array(
			'keywords'  => array( 'save_keyword', 'کیورد' ),
			'content'   => array( 'save_content', 'محتوا' ),
			'technical' => array( 'save_technical', 'سئو تکنیکال' ),
			'backlinks' => array( 'save_backlink', 'بک‌لینک' ),
			'press'     => array( 'save_press', 'رپورتاژ' ),
		);
		if ( ! isset( $map[ $module ] ) ) {
			return new WP_Error( 'wbss_mod', 'ماژول نامعتبر است.' );
		}
		$method = $map[ $module ][0];
		return self::$method( $data );
	}

	public static function delete_row( $module, $id ) {
		global $wpdb;
		$id  = (int) $id;
		$row = self::get_row( $module, $id );
		if ( ! $row ) {
			return false;
		}
		if ( 'keywords' === $module ) {
			$wpdb->delete( self::table( 'ranks' ), array( 'keyword_id' => $id ), array( '%d' ) );
		}
		$t = self::table( $module );
		$wpdb->delete( $t, array( 'id' => $id ), array( '%d' ) );
		$title = self::row_title( $module, $row );
		self::log( (int) ( $row->project_id ?? 0 ), $module, 'deleted', 'حذف ' . $title, $id );
		return true;
	}

	private static function row_title( $module, $row ) {
		if ( isset( $row->keyword ) ) {
			return $row->keyword;
		}
		if ( isset( $row->title ) ) {
			return $row->title;
		}
		if ( isset( $row->outlet ) ) {
			return $row->outlet;
		}
		if ( isset( $row->source_url ) ) {
			return $row->source_url;
		}
		return $module . ' #' . (int) $row->id;
	}

	public static function save_keyword( $data ) {
		global $wpdb;
		$id  = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$pid = (int) ( $data['project_id'] ?? 0 );
		$row = array(
			'project_id' => $pid,
			'keyword'    => sanitize_text_field( $data['keyword'] ?? '' ),
			'intent'     => self::pick( $data['intent'] ?? 'informational', array( 'informational', 'transactional', 'commercial', 'navigational' ) ),
			'volume'     => max( 0, (int) ( $data['volume'] ?? 0 ) ),
			'difficulty' => min( 100, max( 0, (int) ( $data['difficulty'] ?? 0 ) ) ),
			'page_url'   => esc_url_raw( $data['page_url'] ?? '' ),
			'notes'      => sanitize_textarea_field( $data['notes'] ?? '' ),
			'status'     => self::pick( $data['status'] ?? 'active', array( 'active', 'paused', 'archived' ) ),
			'updated_at' => self::now(),
		);
		if ( '' === $row['keyword'] || ! $pid ) {
			return new WP_Error( 'wbss_kw', 'کیورد و پروژه لازم است.' );
		}
		$t = self::table( 'keywords' );
		if ( $id ) {
			$wpdb->update( $t, $row, array( 'id' => $id ) );
			self::log( $pid, 'keywords', 'updated', 'ویرایش کیورد: ' . $row['keyword'], $id );
			return $id;
		}
		$row['created_at'] = self::now();
		$wpdb->insert( $t, $row );
		$id = (int) $wpdb->insert_id;
		self::log( $pid, 'keywords', 'created', 'کیورد ریسرچ: ' . $row['keyword'], $id, array( 'volume' => $row['volume'] ) );
		$pos = isset( $data['position'] ) ? (int) $data['position'] : 0;
		if ( $pos > 0 ) {
			self::save_rank(
				array(
					'keyword_id' => $id,
					'position'   => $pos,
					'checked_at' => $data['checked_at'] ?? WBSS_Jalali::today_g(),
					'page_url'   => $row['page_url'],
				)
			);
		}
		return $id;
	}

	public static function save_rank( $data ) {
		global $wpdb;
		$kid = (int) ( $data['keyword_id'] ?? 0 );
		$kw  = self::get_row( 'keywords', $kid );
		if ( ! $kw ) {
			return new WP_Error( 'wbss_rank', 'کیورد پیدا نشد.' );
		}
		$pos  = (int) ( $data['position'] ?? 101 );
		$pos  = $pos < 1 ? 101 : min( 200, $pos );
		$date = WBSS_Jalali::parse( $data['checked_at'] ?? '' );
		if ( ! $date ) {
			$date = WBSS_Jalali::today_g();
		}
		$row = array(
			'keyword_id' => $kid,
			'checked_at' => $date,
			'position'   => $pos,
			'page_url'   => esc_url_raw( $data['page_url'] ?? ( $kw->page_url ?? '' ) ),
			'engine'     => self::pick( $data['engine'] ?? 'google', array( 'google', 'bing' ) ),
			'device'     => self::pick( $data['device'] ?? 'desktop', array( 'desktop', 'mobile' ) ),
			'created_at' => self::now(),
		);
		$wpdb->insert( self::table( 'ranks' ), $row );
		$id = (int) $wpdb->insert_id;
		self::log(
			(int) $kw->project_id,
			'rank',
			'checked',
			'ثبت رتبه «' . $kw->keyword . '»: جایگاه ' . $pos,
			$kid,
			array( 'position' => $pos, 'date' => $date )
		);
		return $id;
	}

	public static function save_content( $data ) {
		global $wpdb;
		$id  = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$pid = (int) ( $data['project_id'] ?? 0 );
		$row = array(
			'project_id'   => $pid,
			'title'        => sanitize_text_field( $data['title'] ?? '' ),
			'url'          => esc_url_raw( $data['url'] ?? '' ),
			'keyword_id'   => (int) ( $data['keyword_id'] ?? 0 ) ?: null,
			'word_count'   => max( 0, (int) ( $data['word_count'] ?? 0 ) ),
			'status'       => self::pick( $data['status'] ?? 'draft', array( 'draft', 'published', 'updated' ) ),
			'published_at' => WBSS_Jalali::parse( $data['published_at'] ?? '' ) ?: null,
			'notes'        => sanitize_textarea_field( $data['notes'] ?? '' ),
			'updated_at'   => self::now(),
		);
		if ( '' === $row['title'] || ! $pid ) {
			return new WP_Error( 'wbss_content', 'عنوان محتوا و پروژه لازم است.' );
		}
		$t = self::table( 'content' );
		if ( $id ) {
			$wpdb->update( $t, $row, array( 'id' => $id ) );
			self::log( $pid, 'content', 'updated', 'ویرایش محتوا: ' . $row['title'], $id );
			return $id;
		}
		$row['created_at'] = self::now();
		$wpdb->insert( $t, $row );
		$id = (int) $wpdb->insert_id;
		self::log( $pid, 'content', 'created', 'تولید محتوا: ' . $row['title'], $id, array( 'status' => $row['status'] ) );
		return $id;
	}

	public static function save_technical( $data ) {
		global $wpdb;
		$id  = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$pid = (int) ( $data['project_id'] ?? 0 );
		$row = array(
			'project_id' => $pid,
			'title'      => sanitize_text_field( $data['title'] ?? '' ),
			'category'   => self::pick( $data['category'] ?? 'other', array( 'speed', 'index', 'schema', 'mobile', 'security', 'crawl', 'other' ) ),
			'severity'   => self::pick( $data['severity'] ?? 'medium', array( 'low', 'medium', 'high', 'critical' ) ),
			'status'     => self::pick( $data['status'] ?? 'open', array( 'open', 'in_progress', 'done' ) ),
			'page_url'   => esc_url_raw( $data['page_url'] ?? '' ),
			'notes'      => sanitize_textarea_field( $data['notes'] ?? '' ),
		);
		if ( '' === $row['title'] || ! $pid ) {
			return new WP_Error( 'wbss_tech', 'عنوان آیتم تکنیکال لازم است.' );
		}
		if ( 'done' === $row['status'] ) {
			$row['done_at'] = self::now();
		}
		$t = self::table( 'technical' );
		if ( $id ) {
			$wpdb->update( $t, $row, array( 'id' => $id ) );
			self::log( $pid, 'technical', 'updated', 'سئو تکنیکال: ' . $row['title'], $id, array( 'status' => $row['status'] ) );
			return $id;
		}
		$row['created_at'] = self::now();
		$wpdb->insert( $t, $row );
		$id = (int) $wpdb->insert_id;
		self::log( $pid, 'technical', 'created', 'ثبت تکنیکال: ' . $row['title'], $id );
		return $id;
	}

	public static function save_backlink( $data ) {
		global $wpdb;
		$id  = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$pid = (int) ( $data['project_id'] ?? 0 );
		$row = array(
			'project_id'  => $pid,
			'source_url'  => esc_url_raw( $data['source_url'] ?? '' ),
			'target_url'  => esc_url_raw( $data['target_url'] ?? '' ),
			'anchor'      => sanitize_text_field( $data['anchor'] ?? '' ),
			'rel_type'    => self::pick( $data['rel_type'] ?? 'dofollow', array( 'dofollow', 'nofollow', 'ugc', 'sponsored' ) ),
			'da'          => min( 100, max( 0, (int) ( $data['da'] ?? 0 ) ) ),
			'status'      => self::pick( $data['status'] ?? 'live', array( 'pending', 'live', 'lost' ) ),
			'acquired_at' => WBSS_Jalali::parse( $data['acquired_at'] ?? '' ) ?: null,
			'notes'       => sanitize_textarea_field( $data['notes'] ?? '' ),
		);
		if ( '' === $row['source_url'] || ! $pid ) {
			return new WP_Error( 'wbss_bl', 'آدرس منبع بک‌لینک لازم است.' );
		}
		$t = self::table( 'backlinks' );
		if ( $id ) {
			$wpdb->update( $t, $row, array( 'id' => $id ) );
			self::log( $pid, 'backlinks', 'updated', 'ویرایش بک‌لینک: ' . $row['source_url'], $id );
			return $id;
		}
		$row['created_at'] = self::now();
		$wpdb->insert( $t, $row );
		$id = (int) $wpdb->insert_id;
		self::log( $pid, 'backlinks', 'created', 'بک‌لینک جدید: ' . $row['source_url'], $id );
		return $id;
	}

	public static function save_press( $data ) {
		global $wpdb;
		$id  = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$pid = (int) ( $data['project_id'] ?? 0 );
		$row = array(
			'project_id'   => $pid,
			'outlet'       => sanitize_text_field( $data['outlet'] ?? '' ),
			'article_url'  => esc_url_raw( $data['article_url'] ?? '' ),
			'target_url'   => esc_url_raw( $data['target_url'] ?? '' ),
			'topic'        => sanitize_text_field( $data['topic'] ?? '' ),
			'cost'         => max( 0, (int) ( $data['cost'] ?? 0 ) ),
			'publish_date' => WBSS_Jalali::parse( $data['publish_date'] ?? '' ) ?: null,
			'follow_type'  => self::pick( $data['follow_type'] ?? 'dofollow', array( 'dofollow', 'nofollow' ) ),
			'status'       => self::pick( $data['status'] ?? 'planned', array( 'planned', 'published', 'lost' ) ),
			'notes'        => sanitize_textarea_field( $data['notes'] ?? '' ),
		);
		if ( '' === $row['outlet'] || ! $pid ) {
			return new WP_Error( 'wbss_pr', 'نام رسانه رپورتاژ لازم است.' );
		}
		$t = self::table( 'press' );
		if ( $id ) {
			$wpdb->update( $t, $row, array( 'id' => $id ) );
			self::log( $pid, 'press', 'updated', 'ویرایش رپورتاژ: ' . $row['outlet'], $id );
			return $id;
		}
		$row['created_at'] = self::now();
		$wpdb->insert( $t, $row );
		$id = (int) $wpdb->insert_id;
		self::log( $pid, 'press', 'created', 'رپورتاژ: ' . $row['outlet'] . ( $row['topic'] ? ' — ' . $row['topic'] : '' ), $id );
		return $id;
	}

	public static function list_activity( $project_id, $args = array() ) {
		global $wpdb;
		$t      = self::table( 'activity' );
		$limit  = isset( $args['limit'] ) ? min( 200, max( 1, (int) $args['limit'] ) ) : 80;
		$module = isset( $args['module'] ) ? sanitize_key( $args['module'] ) : '';
		$days   = isset( $args['days'] ) ? max( 1, (int) $args['days'] ) : 90;
		$since  = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days', current_time( 'timestamp' ) ) );

		$sql    = "SELECT * FROM {$t} WHERE created_at >= %s";
		$params = array( $since );
		if ( $project_id ) {
			$sql     .= ' AND project_id = %d';
			$params[] = $project_id;
		}
		if ( $module && 'all' !== $module ) {
			$sql     .= ' AND module = %s';
			$params[] = $module;
		}
		$sql     .= ' ORDER BY id DESC LIMIT %d';
		$params[] = $limit;
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function dashboard( $project_id, $days = 30 ) {
		global $wpdb;
		$pid   = (int) $project_id;
		$days  = max( 7, min( 365, (int) $days ) );
		$since = gmdate( 'Y-m-d', strtotime( '-' . $days . ' days', current_time( 'timestamp' ) ) );
		$month = gmdate( 'Y-m-01', current_time( 'timestamp' ) );

		$kw     = self::list_keywords( $pid );
		$ranked = array_values(
			array_filter(
				$kw,
				static function ( $r ) {
					return ! empty( $r->current_rank ) && (int) $r->current_rank < 101;
				}
			)
		);
		$avg = 0;
		if ( $ranked ) {
			$sum = 0;
			foreach ( $ranked as $r ) {
				$sum += (int) $r->current_rank;
			}
			$avg = round( $sum / count( $ranked ), 1 );
		}

		$up = 0;
		$dn = 0;
		foreach ( $kw as $r ) {
			if ( (int) $r->change > 0 ) {
				$up++;
			} elseif ( (int) $r->change < 0 ) {
				$dn++;
			}
		}

		$ct = self::table( 'content' );
		$tt = self::table( 'technical' );
		$bt = self::table( 'backlinks' );
		$pt = self::table( 'press' );
		$at = self::table( 'activity' );
		$rt = self::table( 'ranks' );
		$kt = self::table( 'keywords' );

		$content_pub = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$ct} WHERE project_id = %d AND status IN ('published','updated') AND (published_at IS NULL OR published_at >= %s)", $pid, $since ) ); // phpcs:ignore
		$content_all = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$ct} WHERE project_id = %d", $pid ) ); // phpcs:ignore
		$tech_open   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tt} WHERE project_id = %d AND status != 'done'", $pid ) ); // phpcs:ignore
		$tech_done   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tt} WHERE project_id = %d AND status = 'done'", $pid ) ); // phpcs:ignore
		$tech_crit   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tt} WHERE project_id = %d AND status != 'done' AND severity = 'critical'", $pid ) ); // phpcs:ignore
		$bl_live     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$bt} WHERE project_id = %d AND status = 'live'", $pid ) ); // phpcs:ignore
		$bl_lost     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$bt} WHERE project_id = %d AND status = 'lost'", $pid ) ); // phpcs:ignore
		$pr_month    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pt} WHERE project_id = %d AND status = 'published' AND publish_date >= %s", $pid, $month ) ); // phpcs:ignore
		$pr_cost     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(cost),0) FROM {$pt} WHERE project_id = %d AND status = 'published' AND publish_date >= %s", $pid, $since ) ); // phpcs:ignore
		$actions     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$at} WHERE project_id = %d AND created_at >= %s", $pid, $since . ' 00:00:00' ) ); // phpcs:ignore

		$buckets = array( 'top3' => 0, 'top10' => 0, 'top20' => 0, 'top50' => 0, 'other' => 0 );
		foreach ( $ranked as $r ) {
			$p = (int) $r->current_rank;
			if ( $p <= 3 ) {
				$buckets['top3']++;
			} elseif ( $p <= 10 ) {
				$buckets['top10']++;
			} elseif ( $p <= 20 ) {
				$buckets['top20']++;
			} elseif ( $p <= 50 ) {
				$buckets['top50']++;
			} else {
				$buckets['other']++;
			}
		}

		$series = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.checked_at AS d, AVG(r.position) AS avg_pos, COUNT(*) AS n
				FROM {$rt} r
				INNER JOIN {$kt} k ON k.id = r.keyword_id
				WHERE k.project_id = %d AND r.checked_at >= %s AND r.position < 101
				GROUP BY r.checked_at
				ORDER BY r.checked_at ASC",
				$pid,
				$since
			)
		); // phpcs:ignore

		$by_mod = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT module, COUNT(*) AS n FROM {$at} WHERE project_id = %d AND created_at >= %s GROUP BY module",
				$pid,
				$since . ' 00:00:00'
			)
		); // phpcs:ignore

		$movers = $kw;
		usort(
			$movers,
			static function ( $a, $b ) {
				return abs( (int) $b->change ) <=> abs( (int) $a->change );
			}
		);
		$movers = array_slice( $movers, 0, 8 );

		$score = self::score(
			array(
				'avg'        => $avg,
				'ranked'     => count( $ranked ),
				'kw'         => count( $kw ),
				'top10'      => $buckets['top3'] + $buckets['top10'],
				'content'    => $content_all,
				'tech_open'  => $tech_open,
				'tech_done'  => $tech_done,
				'tech_crit'  => $tech_crit,
				'bl_live'    => $bl_live,
				'bl_lost'    => $bl_lost,
				'press'      => $pr_month,
			)
		);

		return array(
			'kpis'     => array(
				'score'       => $score,
				'avg_rank'    => $avg,
				'keywords'    => count( $kw ),
				'ranked'      => count( $ranked ),
				'improved'    => $up,
				'dropped'     => $dn,
				'content_pub' => $content_pub,
				'content_all' => $content_all,
				'tech_open'   => $tech_open,
				'tech_done'   => $tech_done,
				'backlinks'   => $bl_live,
				'press'       => $pr_month,
				'press_cost'  => $pr_cost,
				'actions'     => $actions,
			),
			'buckets'  => $buckets,
			'series'   => $series,
			'by_mod'   => $by_mod,
			'movers'   => $movers,
			'activity' => self::list_activity( $pid, array( 'days' => $days, 'limit' => 12 ) ),
			'days'     => $days,
			'since'    => $since,
		);
	}

	private static function score( $s ) {
		$score = 42;
		if ( $s['ranked'] ) {
			$score += max( 0, 20 - min( 20, (int) round( $s['avg'] / 2 ) ) );
			$score += min( 16, $s['top10'] * 2 );
		}
		$score += min( 10, (int) $s['content'] );
		$score += min( 10, (int) $s['bl_live'] );
		$score += min( 8, (int) $s['press'] * 2 );
		$score += min( 8, (int) $s['tech_done'] );
		$score -= min( 12, (int) $s['tech_open'] );
		$score -= min( 10, (int) $s['tech_crit'] * 4 );
		$score -= min( 8, (int) $s['bl_lost'] * 2 );
		return max( 0, min( 100, $score ) );
	}

	public static function export_project( $project_id ) {
		$pid = (int) $project_id;
		$p   = self::project( $pid );
		if ( ! $p ) {
			return new WP_Error( 'wbss_exp', 'پروژه پیدا نشد.' );
		}
		$kw    = self::list_keywords( $pid );
		$ranks = array();
		foreach ( $kw as $k ) {
			$ranks[ (int) $k->id ] = self::keyword_ranks( (int) $k->id, 365 );
		}
		return array(
			'exported_at' => self::now(),
			'project'     => $p,
			'keywords'    => $kw,
			'ranks'       => $ranks,
			'content'     => self::simple_list( 'content', $pid, 'id ASC' ),
			'technical'   => self::simple_list( 'technical', $pid, 'id ASC' ),
			'backlinks'   => self::simple_list( 'backlinks', $pid, 'id ASC' ),
			'press'       => self::simple_list( 'press', $pid, 'id ASC' ),
			'activity'    => self::list_activity( $pid, array( 'days' => 365, 'limit' => 200 ) ),
		);
	}

	private static function pick( $val, $allowed ) {
		$val = sanitize_key( (string) $val );
		return in_array( $val, $allowed, true ) ? $val : $allowed[0];
	}
}
