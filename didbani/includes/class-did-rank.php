<?php
defined( 'ABSPATH' ) || exit;

/**
 * تطبیق نتایج SERP با دامنه‌های پروژه و ذخیره رتبه.
 */
class DID_Rank {

	/**
	 * اولین موقعیت هر دامنه در لیست نتایج.
	 *
	 * @param array $results
	 * @param array $hosts host => domain_id
	 * @return array<int,array{domain_id:int,position:int,url:string,title:string}>
	 */
	public static function match( array $results, array $hosts ) {
		$found = array();
		foreach ( $results as $row ) {
			if ( ! is_array( $row ) || empty( $row['url'] ) ) {
				continue;
			}
			$result_host = DID_Url::host( $row['url'] );
			foreach ( $hosts as $tracked => $domain_id ) {
				$domain_id = (int) $domain_id;
				if ( isset( $found[ $domain_id ] ) ) {
					continue;
				}
				if ( DID_Url::host_matches( $result_host, $tracked ) ) {
					$found[ $domain_id ] = array(
						'domain_id' => $domain_id,
						'position'  => isset( $row['position'] ) ? (int) $row['position'] : 0,
						'url'       => (string) $row['url'],
						'title'     => isset( $row['title'] ) ? (string) $row['title'] : '',
					);
				}
			}
		}
		return $found;
	}

	public static function start( $project_id ) {
		$active = DID_Db::active_job( $project_id, 'rank' );
		if ( $active ) {
			return $active;
		}
		$keywords = DID_Db::keywords( $project_id );
		$ids      = array();
		foreach ( $keywords as $kw ) {
			$ids[] = (int) $kw['id'];
		}
		if ( ! $ids ) {
			return new WP_Error( 'no_keywords', 'کلیدواژه‌ای ثبت نشده. حداقل یک کلمه کلیدی در تب پروژه بگذارید.' );
		}
		if ( ! DID_Db::ensure_own_domain( $project_id ) ) {
			return new WP_Error( 'no_own', 'دامنهٔ سایت مشخص نیست. آدرس سایت را در تب پروژه بگذارید یا وردپرس را با دامنهٔ معتبر تنظیم کنید.' );
		}
		$engines = self::enabled_engines();
		if ( ! $engines ) {
			return new WP_Error( 'no_engine', 'هیچ ارائه‌دهندهٔ رتبه‌ای پیکربندی نشده. کلید Bing یا گوگل را در تنظیمات وارد کنید.' );
		}
		$device = DID_Settings::device();
		$id     = DID_Db::insert_job(
			$project_id,
			'rank',
			array(
				'keywords' => $ids,
				'engines'  => $engines,
				'device'   => $device,
				'index'    => 0,
			)
		);
		return DID_Db::job( $id );
	}

	/**
	 * @return string[] google, bing
	 */
	public static function enabled_engines() {
		$out = array();
		if ( trim( (string) DID_Settings::get( 'bing_api_key', '' ) ) ) {
			$out[] = 'bing';
		}
		$g = DID_Settings::get( 'google_serp_provider', 'none' );
		if ( 'serpapi' === $g && trim( (string) DID_Settings::get( 'serpapi_key', '' ) ) ) {
			$out[] = 'google';
		} elseif ( 'dataforseo' === $g && trim( (string) DID_Settings::get( 'dataforseo_login', '' ) ) && trim( (string) DID_Settings::get( 'dataforseo_password', '' ) ) ) {
			$out[] = 'google';
		} elseif ( 'cse' === $g && trim( (string) DID_Settings::get( 'google_cse_key', '' ) ) && trim( (string) DID_Settings::get( 'google_cse_cx', '' ) ) ) {
			$out[] = 'google';
		}
		return $out;
	}

	public static function step( array $job ) {
		$payload = json_decode( (string) $job['payload'], true );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		$project_id = (int) $job['project_id'];
		$keywords   = isset( $payload['keywords'] ) ? $payload['keywords'] : array();
		$engines    = isset( $payload['engines'] ) ? $payload['engines'] : array();
		$device     = isset( $payload['device'] ) ? DID_Geo::sanitize_device( $payload['device'] ) : DID_Settings::device();
		$index      = isset( $payload['index'] ) ? (int) $payload['index'] : 0;
		$combos     = array();
		foreach ( $keywords as $kw_id ) {
			foreach ( $engines as $engine ) {
				$combos[] = array( (int) $kw_id, (string) $engine );
			}
		}
		$total = count( $combos );

		if ( $index >= $total || ! $combos ) {
			DID_Db::update_job(
				(int) $job['id'],
				array(
					'status'      => 'done',
					'message'     => 'بررسی رتبه تمام شد.',
					'finished_at' => DID_Db::now(),
					'payload'     => wp_json_encode( $payload ),
				)
			);
			return array(
				'done'    => true,
				'index'   => $index,
				'total'   => $total,
				'percent' => 100,
				'message' => 'بررسی رتبه تمام شد.',
			);
		}

		$kw_id  = $combos[ $index ][0];
		$engine = $combos[ $index ][1];

		global $wpdb;
		$kt  = DID_Db::table( DID_Db::KEYWORDS );
		$kw  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$kt} WHERE id = %d", $kw_id ), ARRAY_A ); // phpcs:ignore
		$msg = 'در حال بررسی…';
		if ( $kw ) {
			$err = self::check_one( $project_id, $kw, $engine, $device );
			$msg = $err ? $err : sprintf(
				'«%s» در %s بررسی شد.',
				$kw['keyword'],
				'google' === $engine ? 'گوگل' : 'بینگ'
			);
		}

		$payload['index'] = $index + 1;
		$done             = $payload['index'] >= $total;
		DID_Db::update_job(
			(int) $job['id'],
			array(
				'status'      => $done ? 'done' : 'running',
				'message'     => $done ? 'بررسی رتبه تمام شد.' : $msg,
				'payload'     => wp_json_encode( $payload ),
				'finished_at' => $done ? DID_Db::now() : null,
			)
		);

		return array(
			'done'    => $done,
			'index'   => $payload['index'],
			'total'   => $total,
			'percent' => $total ? min( 100, (int) floor( ( $payload['index'] / $total ) * 100 ) ) : 100,
			'message' => $done ? 'بررسی رتبه تمام شد.' : $msg,
		);
	}

	/**
	 * @param int    $project_id
	 * @param array  $keyword_row
	 * @param string $engine
	 * @param string $device
	 * @return string خطا یا خالی
	 */
	public static function check_one( $project_id, array $keyword_row, $engine, $device = 'desktop' ) {
		$depth   = (int) DID_Settings::get( 'rank_depth', 20 );
		$market  = (string) DID_Settings::get( 'market', 'fa-IR' );
		$country = (string) DID_Settings::get( 'country', 'ir' );
		$hl      = strtolower( substr( $market, 0, 2 ) );
		if ( strlen( $hl ) !== 2 ) {
			$hl = 'fa';
		}
		$device = DID_Geo::sanitize_device( $device );

		$opts = array(
			'device'        => $device,
			'location_name' => 'Iran',
		);

		$provider_id = '';
		$approx      = 0;
		if ( 'bing' === $engine ) {
			$pack        = DID_Provider_Bing::search( $keyword_row['keyword'], $depth, DID_Settings::get( 'bing_api_key', '' ), $market, $opts );
			$provider_id = 'bing';
		} else {
			$g = DID_Settings::get( 'google_serp_provider', 'none' );
			if ( 'serpapi' === $g ) {
				$pack        = DID_Provider_Serp::search( $keyword_row['keyword'], $depth, DID_Settings::get( 'serpapi_key', '' ), $hl, $country, $opts );
				$provider_id = 'serpapi';
			} elseif ( 'dataforseo' === $g ) {
				$pack        = DID_Provider_DataForSeo::search(
					$keyword_row['keyword'],
					$depth,
					DID_Settings::get( 'dataforseo_login', '' ),
					DID_Settings::get( 'dataforseo_password', '' ),
					$opts
				);
				$provider_id = 'dataforseo';
			} elseif ( 'cse' === $g ) {
				$pack        = DID_Provider_Cse::search(
					$keyword_row['keyword'],
					$depth,
					DID_Settings::get( 'google_cse_key', '' ),
					DID_Settings::get( 'google_cse_cx', '' ),
					$hl,
					$country
				);
				$provider_id = 'cse';
				$approx      = 1;
			} else {
				return 'ارائه‌دهندهٔ گوگل انتخاب نشده.';
			}
		}

		if ( empty( $pack['ok'] ) ) {
			return isset( $pack['error'] ) ? $pack['error'] : 'خطای رتبه.';
		}

		$domains = DID_Db::domains( $project_id );
		$hosts   = array();
		foreach ( $domains as $d ) {
			$hosts[ $d['host'] ] = (int) $d['id'];
		}
		$found = self::match( $pack['results'], $hosts );

		foreach ( $domains as $d ) {
			$did = (int) $d['id'];
			$hit = isset( $found[ $did ] ) ? $found[ $did ] : null;
			DID_Db::upsert_rank(
				array(
					'project_id'   => (int) $project_id,
					'keyword_id'   => (int) $keyword_row['id'],
					'domain_id'    => $did,
					'engine'       => $engine,
					'provider'     => $provider_id,
					'device'       => $device,
					'city'         => '',
					'position'     => $hit ? (int) $hit['position'] : 0,
					'result_url'   => $hit ? $hit['url'] : '',
					'result_title' => $hit ? $hit['title'] : '',
					'approximate'  => $approx,
				)
			);
		}

		return '';
	}

	/**
	 * @param int    $prev
	 * @param int    $now
	 * @return array{kind:string,steps:int,label:string}
	 */
	public static function delta( $prev, $now ) {
		$prev = (int) $prev;
		$now  = (int) $now;
		if ( $prev < 1 && $now < 1 ) {
			return array( 'kind' => 'none', 'steps' => 0, 'label' => '' );
		}
		if ( $prev < 1 && $now >= 1 ) {
			return array( 'kind' => 'enter', 'steps' => 0, 'label' => 'ورود' );
		}
		if ( $prev >= 1 && $now < 1 ) {
			return array( 'kind' => 'exit', 'steps' => 0, 'label' => 'خروج' );
		}
		$d = $prev - $now;
		if ( $d > 0 ) {
			return array( 'kind' => 'up', 'steps' => $d, 'label' => 'رشد ' . $d );
		}
		if ( $d < 0 ) {
			return array( 'kind' => 'down', 'steps' => -$d, 'label' => 'افت ' . ( -$d ) );
		}
		return array( 'kind' => 'same', 'steps' => 0, 'label' => 'ثابت' );
	}

	/**
	 * ماتریس: keyword_id × domain_id × engine
	 *
	 * @param int $project_id
	 * @return array
	 */
	public static function matrix( $project_id ) {
		$device = DID_Settings::device();
		$rows   = DID_Db::ranks( $project_id, '', $device );
		$out    = array();
		foreach ( $rows as $r ) {
			if ( '' !== (string) $r['city'] ) {
				continue;
			}
			$k = (int) $r['keyword_id'];
			$d = (int) $r['domain_id'];
			$e = $r['engine'];
			if ( ! isset( $out[ $k ] ) ) {
				$out[ $k ] = array();
			}
			if ( ! isset( $out[ $k ][ $d ] ) ) {
				$out[ $k ][ $d ] = array();
			}
			$out[ $k ][ $d ][ $e ] = $r;
		}
		if ( $out ) {
			return $out;
		}
		foreach ( $rows as $r ) {
			$k = (int) $r['keyword_id'];
			$d = (int) $r['domain_id'];
			$e = $r['engine'];
			if ( ! isset( $out[ $k ] ) ) {
				$out[ $k ] = array();
			}
			if ( ! isset( $out[ $k ][ $d ] ) ) {
				$out[ $k ][ $d ] = array();
			}
			$out[ $k ][ $d ][ $e ] = $r;
		}
		return $out;
	}

	/**
	 * لیست رشد/افت مرتب‌شده.
	 *
	 * @param int $project_id
	 * @return array
	 */
	public static function movers( $project_id ) {
		$matrix_rows = array();
		$mx          = self::matrix( $project_id );
		foreach ( $mx as $domains ) {
			foreach ( $domains as $engines ) {
				foreach ( $engines as $r ) {
					$matrix_rows[] = $r;
				}
			}
		}
		$out = array();
		foreach ( $matrix_rows as $r ) {
			$chg = self::delta( isset( $r['prev_position'] ) ? $r['prev_position'] : 0, $r['position'] );
			if ( 'none' === $chg['kind'] || 'same' === $chg['kind'] ) {
				continue;
			}
			$r['change'] = $chg;
			$out[]       = $r;
		}
		return $out;
	}
}
