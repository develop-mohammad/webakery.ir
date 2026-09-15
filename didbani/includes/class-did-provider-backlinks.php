<?php
defined( 'ABSPATH' ) || exit;

/**
 * خلاصه بک‌لینک و انکر از DataForSEO Backlinks API.
 */
class DID_Provider_Backlinks {

	const SUMMARY = 'https://api.dataforseo.com/v3/backlinks/summary/live';
	const ANCHORS = 'https://api.dataforseo.com/v3/backlinks/anchors/live';

	/**
	 * @param string $host
	 * @param string $login
	 * @param string $password
	 * @return array{ok:bool,error:string,backlinks:int,referring_domains:int,referring_pages:int,domain_rank:int,anchors:array}
	 */
	public static function fetch( $host, $login, $password ) {
		$host = DID_Url::host( $host );
		if ( '' === $host ) {
			return self::fail( 'دامنه نامعتبر است.' );
		}

		$summary = DID_Provider_DataForSeo::request(
			self::SUMMARY,
			array(
				array(
					'target'                    => $host,
					'include_subdomains'        => true,
					'exclude_internal_backlinks' => true,
				),
			),
			$login,
			$password,
			45
		);
		if ( empty( $summary['ok'] ) ) {
			return self::fail( $summary['error'] );
		}
		$counts = self::parse_summary( isset( $summary['data'] ) ? $summary['data'] : array() );

		$anchors_pack = DID_Provider_DataForSeo::request(
			self::ANCHORS,
			array(
				array(
					'target'             => $host,
					'limit'              => 20,
					'order_by'           => array( 'backlinks,desc' ),
					'include_subdomains' => true,
				),
			),
			$login,
			$password,
			45
		);
		$anchors = array();
		if ( ! empty( $anchors_pack['ok'] ) ) {
			$anchors = self::parse_anchors( isset( $anchors_pack['data'] ) ? $anchors_pack['data'] : array() );
		}

		return array(
			'ok'                 => true,
			'error'              => '',
			'backlinks'          => $counts['backlinks'],
			'referring_domains'  => $counts['referring_domains'],
			'referring_pages'    => $counts['referring_pages'],
			'domain_rank'        => $counts['domain_rank'],
			'anchors'            => $anchors,
		);
	}

	/**
	 * @param array $data
	 * @return array{backlinks:int,referring_domains:int,referring_pages:int,domain_rank:int}
	 */
	public static function parse_summary( array $data ) {
		$row = array();
		if ( isset( $data['tasks'][0]['result'][0] ) && is_array( $data['tasks'][0]['result'][0] ) ) {
			$row = $data['tasks'][0]['result'][0];
		}
		return array(
			'backlinks'         => isset( $row['backlinks'] ) ? (int) $row['backlinks'] : 0,
			'referring_domains' => isset( $row['referring_domains'] ) ? (int) $row['referring_domains'] : 0,
			'referring_pages'   => isset( $row['referring_pages'] ) ? (int) $row['referring_pages'] : 0,
			'domain_rank'       => isset( $row['rank'] ) ? (int) $row['rank'] : 0,
		);
	}

	/**
	 * @param array $data
	 * @return array<int,array{anchor:string,backlinks:int,referring_domains:int}>
	 */
	public static function parse_anchors( array $data ) {
		$items = array();
		if ( isset( $data['tasks'][0]['result'][0]['items'] ) && is_array( $data['tasks'][0]['result'][0]['items'] ) ) {
			$items = $data['tasks'][0]['result'][0]['items'];
		}
		$out = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$anchor = isset( $item['anchor'] ) ? trim( (string) $item['anchor'] ) : '';
			if ( '' === $anchor ) {
				$anchor = '(بدون متن)';
			}
			$out[] = array(
				'anchor'            => $anchor,
				'backlinks'         => isset( $item['backlinks'] ) ? (int) $item['backlinks'] : 0,
				'referring_domains' => isset( $item['referring_domains'] ) ? (int) $item['referring_domains'] : 0,
			);
		}
		return $out;
	}

	private static function fail( $message ) {
		return array(
			'ok'                => false,
			'error'             => $message,
			'backlinks'         => 0,
			'referring_domains' => 0,
			'referring_pages'   => 0,
			'domain_rank'       => 0,
			'anchors'           => array(),
		);
	}
}
