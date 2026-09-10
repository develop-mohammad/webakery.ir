<?php
defined( 'ABSPATH' ) || exit;

/**
 * صف رصد بک‌لینک برای دامنه‌های پروژه.
 */
class DID_Backlinks {

	public static function start( $project_id ) {
		$active = DID_Db::active_job( $project_id, 'backlinks' );
		if ( $active ) {
			return $active;
		}
		if ( ! DID_Settings::has_dataforseo() ) {
			return new WP_Error( 'no_dfs', 'برای بک‌لینک، ورود DataForSEO را در تنظیمات وارد کنید.' );
		}
		DID_Db::ensure_own_domain( $project_id );
		$domains = DID_Db::domains( $project_id );
		$ids     = array();
		foreach ( $domains as $d ) {
			$ids[] = (int) $d['id'];
		}
		if ( ! $ids ) {
			return new WP_Error( 'no_domains', 'دامنه‌ای برای رصد بک‌لینک نیست. سایت خود یا رقبا را در تب پروژه بگذارید.' );
		}
		$id = DID_Db::insert_job(
			$project_id,
			'backlinks',
			array(
				'domains' => $ids,
				'index'   => 0,
			)
		);
		return DID_Db::job( $id );
	}

	public static function step( array $job ) {
		$payload = json_decode( (string) $job['payload'], true );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		$project_id = (int) $job['project_id'];
		$ids        = isset( $payload['domains'] ) ? $payload['domains'] : array();
		$index      = isset( $payload['index'] ) ? (int) $payload['index'] : 0;
		$total      = count( $ids );

		if ( $index >= $total || ! $ids ) {
			DID_Db::update_job(
				(int) $job['id'],
				array(
					'status'      => 'done',
					'message'     => 'بررسی بک‌لینک تمام شد.',
					'finished_at' => DID_Db::now(),
					'payload'     => wp_json_encode( $payload ),
				)
			);
			return array(
				'done'    => true,
				'index'   => $index,
				'total'   => $total,
				'percent' => 100,
				'message' => 'بررسی بک‌لینک تمام شد.',
			);
		}

		$domain = DID_Db::domain( (int) $ids[ $index ] );
		$msg    = 'در حال بررسی بک‌لینک…';
		if ( $domain ) {
			$pack = DID_Provider_Backlinks::fetch(
				$domain['host'],
				DID_Settings::get( 'dataforseo_login', '' ),
				DID_Settings::get( 'dataforseo_password', '' )
			);
			$err  = empty( $pack['ok'] ) ? ( isset( $pack['error'] ) ? $pack['error'] : 'خطای بک‌لینک' ) : '';
			DID_Db::upsert_backlink(
				array(
					'project_id'        => $project_id,
					'domain_id'         => (int) $domain['id'],
					'backlinks'         => (int) $pack['backlinks'],
					'referring_domains' => (int) $pack['referring_domains'],
					'referring_pages'   => (int) $pack['referring_pages'],
					'domain_rank'       => (int) $pack['domain_rank'],
					'anchors'           => function_exists( 'wp_json_encode' ) ? wp_json_encode( $pack['anchors'] ) : json_encode( $pack['anchors'] ),
					'error'             => $err,
				)
			);
				$msg = $err
				? ( $domain['host'] . ': ' . $err )
				: sprintf( '%s: %s بک‌لینک.', $domain['host'], DID_Admin::format_int( (int) $pack['backlinks'] ) );
		}

		$payload['index'] = $index + 1;
		$done             = $payload['index'] >= $total;
		DID_Db::update_job(
			(int) $job['id'],
			array(
				'status'      => $done ? 'done' : 'running',
				'message'     => $done ? 'بررسی بک‌لینک تمام شد.' : $msg,
				'payload'     => wp_json_encode( $payload ),
				'finished_at' => $done ? DID_Db::now() : null,
			)
		);

		return array(
			'done'    => $done,
			'index'   => $payload['index'],
			'total'   => $total,
			'percent' => $total ? min( 100, (int) floor( ( $payload['index'] / $total ) * 100 ) ) : 100,
			'message' => $done ? 'بررسی بک‌لینک تمام شد.' : $msg,
		);
	}

	/**
	 * @param int $project_id
	 * @return array<int,array> domain_id => row
	 */
	public static function map( $project_id ) {
		$out  = array();
		$rows = DID_Db::backlinks( $project_id );
		foreach ( $rows as $r ) {
			$out[ (int) $r['domain_id'] ] = $r;
		}
		return $out;
	}
}
