<?php
defined( 'ABSPATH' ) || exit;

/**
 * کرول مؤدب صفحات عمومی یک دامنه (robots + sitemap + لینک داخلی).
 */
class DID_Crawler {

	/**
	 * یک قدم از صف کرول.
	 *
	 * @param array $job
	 * @param int   $budget تعداد صفحه در این درخواست
	 * @return array
	 */
	public static function step( array $job, $budget = 3 ) {
		$payload = json_decode( (string) $job['payload'], true );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		$project_id = (int) $job['project_id'];
		$domains    = isset( $payload['domains'] ) ? $payload['domains'] : array();
		$index      = isset( $payload['index'] ) ? (int) $payload['index'] : 0;
		$state      = isset( $payload['state'] ) && is_array( $payload['state'] ) ? $payload['state'] : null;

		if ( $index >= count( $domains ) ) {
			return self::finish( $job, $payload, 'کرول تمام شد.' );
		}

		$domain_id = (int) $domains[ $index ];
		$domain    = DID_Db::domain( $domain_id );
		if ( ! $domain ) {
			$payload['index'] = $index + 1;
			$payload['state'] = null;
			DID_Db::update_job(
				(int) $job['id'],
				array(
					'status'  => 'running',
					'payload' => wp_json_encode( $payload ),
					'message' => 'دامنه پیدا نشد؛ ادامه…',
				)
			);
			return self::progress( $payload, 'دامنه پیدا نشد.' );
		}

		if ( null === $state ) {
			$state = self::begin_domain( $project_id, $domain );
		}

		$processed = 0;
		while ( $processed < $budget && ! empty( $state['queue'] ) && (int) $state['saved'] < (int) $state['max'] ) {
			$url = array_shift( $state['queue'] );
			if ( isset( $state['visited'][ $url ] ) ) {
				continue;
			}
			$state['visited'][ $url ] = 1;
			if ( ! DID_Robots::allowed( $url, $state['robots'] ) ) {
				continue;
			}
			self::maybe_delay( $state );
			$page = self::fetch_page( $project_id, $domain, $url, $state );
			if ( $page ) {
				$state['saved']++;
				foreach ( $page['links'] as $link ) {
					if ( isset( $state['visited'][ $link ] ) ) {
						continue;
					}
					if ( ! DID_Robots::allowed( $link, $state['robots'] ) ) {
						continue;
					}
					$state['queue'][] = $link;
				}
			}
			$processed++;
		}

		$done_domain = empty( $state['queue'] ) || (int) $state['saved'] >= (int) $state['max'];
		if ( $done_domain ) {
			$status = $state['saved'] > 0 ? 'ok' : 'empty';
			$error  = $state['error'];
			DID_Db::update_domain_crawl( $domain_id, $status, $error, (int) $state['saved'] );
			$payload['index'] = $index + 1;
			$payload['state'] = null;
			$msg              = sprintf( 'دامنه %s: %d صفحه.', $domain['host'], (int) $state['saved'] );
		} else {
			$payload['index'] = $index;
			$payload['state'] = $state;
			$msg              = sprintf( 'کرول %s: %d صفحه…', $domain['host'], (int) $state['saved'] );
		}

		$finished = $payload['index'] >= count( $domains );
		DID_Db::update_job(
			(int) $job['id'],
			array(
				'status'      => $finished ? 'done' : 'running',
				'payload'     => wp_json_encode( self::compact_state( $payload ) ),
				'message'     => $finished ? 'کرول تمام شد.' : $msg,
				'finished_at' => $finished ? DID_Db::now() : null,
			)
		);

		return self::progress( $payload, $finished ? 'کرول تمام شد.' : $msg, $finished );
	}

	/**
	 * @param int   $project_id
	 * @param array $domain
	 * @return array
	 */
	public static function begin_domain( $project_id, array $domain ) {
		$max = (int) DID_Settings::get( 'max_pages', 50 );
		DID_Db::clear_pages( $project_id, (int) $domain['id'] );
		$origin = 'https://' . $domain['host'] . '/';
		$robots = array(
			'disallow' => array(),
			'allow'    => array(),
			'sitemaps' => array(),
		);
		$error  = '';

		$rb = DID_Http::get( $origin . 'robots.txt', array( 'timeout' => 10, 'max_bytes' => 64000 ) );
		if ( $rb['ok'] && false !== stripos( $rb['content_type'] . $rb['body'], 'html' ) && strlen( $rb['body'] ) > 20 && false !== stripos( $rb['body'], '<html' ) ) {
			// صفحه HTML به‌جای robots — نادیده.
		} elseif ( $rb['ok'] ) {
			$robots = DID_Robots::parse( $rb['body'] );
		}

		$queue   = array();
		$visited = array();
		$sitemaps = $robots['sitemaps'];
		$sitemaps[] = $origin . 'sitemap.xml';
		$sitemaps[] = $origin . 'sitemap_index.xml';
		$sitemaps   = array_unique( $sitemaps );

		$sitemap_budget = 0;
		foreach ( $sitemaps as $sm ) {
			if ( $sitemap_budget >= 3 ) {
				break;
			}
			if ( ! DID_Url::host_matches( DID_Url::host( $sm ), $domain['host'] ) && DID_Url::host( $sm ) !== $domain['host'] ) {
				continue;
			}
			$sx = DID_Http::get( $sm, array( 'timeout' => 12, 'max_bytes' => 256000 ) );
			$sitemap_budget++;
			if ( ! $sx['ok'] ) {
				continue;
			}
			$locs = DID_Robots::sitemap_locs( $sx['body'] );
			$child_indexes = 0;
			foreach ( $locs as $loc ) {
				$loc = DID_Url::canonical( $loc );
				if ( '' === $loc ) {
					continue;
				}
				if ( ! DID_Url::host_matches( DID_Url::host( $loc ), $domain['host'] ) ) {
					continue;
				}
				if ( preg_match( '/sitemap/i', $loc ) && $child_indexes < 2 ) {
					$child_indexes++;
					$cx = DID_Http::get( $loc, array( 'timeout' => 12, 'max_bytes' => 256000 ) );
					if ( $cx['ok'] ) {
						foreach ( DID_Robots::sitemap_locs( $cx['body'] ) as $u2 ) {
							$u2 = DID_Url::canonical( $u2 );
							if ( $u2 && DID_Url::is_htmlish( $u2 ) && DID_Url::host_matches( DID_Url::host( $u2 ), $domain['host'] ) ) {
								$queue[] = $u2;
							}
						}
					}
					continue;
				}
				if ( DID_Url::is_htmlish( $loc ) ) {
					$queue[] = $loc;
				}
			}
		}

		array_unshift( $queue, DID_Url::canonical( $origin ) );
		$queue = array_values( array_unique( $queue ) );
		$queue = array_slice( $queue, 0, $max );

		if ( empty( $queue ) ) {
			$error = 'صفحهٔ شروع پیدا نشد.';
		}

		return array(
			'queue'    => $queue,
			'visited'  => $visited,
			'saved'    => 0,
			'max'      => $max,
			'robots'   => $robots,
			'error'    => $error,
			'last_at'  => 0,
		);
	}

	/**
	 * @param int    $project_id
	 * @param array  $domain
	 * @param string $url
	 * @param array  $state
	 * @return array|null
	 */
	public static function fetch_page( $project_id, array $domain, $url, array &$state ) {
		$res = DID_Http::get( $url, array( 'timeout' => 15, 'max_bytes' => 512000 ) );
		if ( ! $res['ok'] ) {
			if ( '' === $state['error'] ) {
				$state['error'] = $res['error'];
			}
			return null;
		}
		$ct = strtolower( $res['content_type'] );
		if ( $ct && false === strpos( $ct, 'html' ) && false === strpos( $ct, 'xml' ) && false === strpos( $ct, 'text/plain' ) ) {
			return null;
		}

		$parsed = DID_Html::parse( $res['body'], $res['final_url'] ? $res['final_url'] : $url );
		$kws    = DID_Db::keywords( $project_id );
		$hits   = array();
		foreach ( $kws as $kw ) {
			$hits[ (string) $kw['id'] ] = DID_Text::keyword_hits(
				$kw['keyword'],
				$parsed['title'],
				$parsed['h1'],
				$parsed['body_text'] . ' ' . $parsed['meta_description']
			);
		}

		DID_Db::insert_page(
			array(
				'project_id'       => (int) $project_id,
				'domain_id'        => (int) $domain['id'],
				'url'              => $url,
				'status_code'      => (int) $res['status'],
				'title'            => $parsed['title'],
				'meta_description' => $parsed['meta_description'],
				'h1'               => $parsed['h1'],
				'canonical'        => $parsed['canonical'],
				'word_count'       => (int) $parsed['word_count'],
				'keyword_hits'     => wp_json_encode( $hits ),
				'fetched_at'       => DID_Db::now(),
			)
		);

		$links = array();
		foreach ( $parsed['links'] as $link ) {
			if ( DID_Url::host_matches( DID_Url::host( $link ), $domain['host'] ) ) {
				$links[] = $link;
			}
		}

		return array( 'links' => $links );
	}

	private static function maybe_delay( array &$state ) {
		$ms = (int) DID_Settings::get( 'crawl_delay_ms', 800 );
		if ( $ms < 200 ) {
			$ms = 200;
		}
		$now = microtime( true );
		if ( $state['last_at'] > 0 ) {
			$wait = ( $ms / 1000 ) - ( $now - $state['last_at'] );
			if ( $wait > 0 && $wait < 5 ) {
				usleep( (int) ( $wait * 1000000 ) );
			}
		}
		$state['last_at'] = microtime( true );
	}

	private static function compact_state( array $payload ) {
		if ( isset( $payload['state']['visited'] ) && is_array( $payload['state']['visited'] ) ) {
			$keys = array_keys( $payload['state']['visited'] );
			$payload['state']['visited'] = array_fill_keys( array_slice( $keys, -400 ), 1 );
		}
		if ( isset( $payload['state']['queue'] ) && is_array( $payload['state']['queue'] ) ) {
			$payload['state']['queue'] = array_slice( $payload['state']['queue'], 0, 200 );
		}
		return $payload;
	}

	private static function finish( array $job, array $payload, $message ) {
		DID_Db::update_job(
			(int) $job['id'],
			array(
				'status'      => 'done',
				'payload'     => wp_json_encode( $payload ),
				'message'     => $message,
				'finished_at' => DID_Db::now(),
			)
		);
		return self::progress( $payload, $message, true );
	}

	private static function progress( array $payload, $message, $done = false ) {
		$total = isset( $payload['domains'] ) ? count( $payload['domains'] ) : 0;
		$index = isset( $payload['index'] ) ? (int) $payload['index'] : 0;
		return array(
			'done'     => $done,
			'index'    => $index,
			'total'    => $total,
			'message'  => $message,
			'percent'  => $total ? min( 100, (int) floor( ( $index / $total ) * 100 ) ) : 100,
		);
	}

	/**
	 * شروع کرول پروژه.
	 *
	 * @param int $project_id
	 * @return array|WP_Error
	 */
	public static function start( $project_id ) {
		$active = DID_Db::active_job( $project_id, 'crawl' );
		if ( $active ) {
			return $active;
		}
		$domains = DID_Db::domains( $project_id );
		$ids     = array();
		foreach ( $domains as $d ) {
			$ids[] = (int) $d['id'];
		}
		if ( ! $ids ) {
			return new WP_Error( 'no_domains', 'دامنه‌ای برای کرول ثبت نشده.' );
		}
		$id = DID_Db::insert_job(
			$project_id,
			'crawl',
			array(
				'domains' => $ids,
				'index'   => 0,
				'state'   => null,
			)
		);
		return DID_Db::job( $id );
	}
}
