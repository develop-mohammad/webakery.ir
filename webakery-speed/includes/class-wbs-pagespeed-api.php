<?php
/**
 * Google PageSpeed Insights API client.
 *
 * @package WebakerySpeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches PSI results.
 */
class WBS_PageSpeed_API {

	/**
	 * Run scan.
	 *
	 * @param string $url      URL to scan.
	 * @param string $api_key  API key.
	 * @param string $strategy mobile|desktop.
	 * @return array|WP_Error
	 */
	public static function scan( $url, $api_key, $strategy = 'mobile' ) {
		$url = esc_url_raw( $url );
		if ( empty( $url ) ) {
			return new WP_Error( 'wbs_no_url', __( 'آدرس سایت خالی است.', 'webakery-speed' ) );
		}

		if ( empty( $api_key ) ) {
			return new WP_Error( 'wbs_no_api_key', __( 'کلید API گوگل PageSpeed لازم است.', 'webakery-speed' ) );
		}

		$endpoint = add_query_arg(
			array(
				'url'      => $url,
				'key'      => $api_key,
				'strategy' => in_array( $strategy, array( 'mobile', 'desktop' ), true ) ? $strategy : 'mobile',
				'category' => 'performance',
				'locale'   => 'fa',
			),
			'https://www.googleapis.com/pagespeedonline/v5/runPagespeed'
		);

		$response = wp_remote_get(
			$endpoint,
			array(
				'timeout' => 60,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$message = isset( $body['error']['message'] )
				? $body['error']['message']
				: __( 'خطا در دریافت گزارش PageSpeed.', 'webakery-speed' );
			return new WP_Error( 'wbs_api_error', $message, array( 'status' => $code ) );
		}

		return self::normalize_scan( $body, $url, $strategy );
	}

	/**
	 * Normalize PSI or Lighthouse payload into scan result.
	 *
	 * @param array  $body     Raw body with lighthouseResult key or audits at root.
	 * @param string $url      URL.
	 * @param string $strategy Strategy.
	 * @return array
	 */
	public static function normalize_scan( $body, $url, $strategy ) {
		return self::normalize_response( $body, $url, $strategy );
	}

	/**
	 * Parse pasted Lighthouse JSON.
	 *
	 * @param string $json Raw JSON.
	 * @param string $url  Scanned URL.
	 * @return array|WP_Error
	 */
	public static function parse_json_report( $json, $url = '' ) {
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'wbs_invalid_json', __( 'فایل JSON معتبر نیست.', 'webakery-speed' ) );
		}

		// Lighthouse export or PSI wrapper.
		if ( isset( $data['lighthouseResult'] ) ) {
			return self::normalize_response( $data, $url, 'imported' );
		}

		// Chrome extension export (DOM or PageSpeed).
		if ( isset( $data['source'] ) && 'webakery-speed-chrome' === $data['source'] && ! empty( $data['issues'] ) ) {
			return self::normalize_chrome_export( $data );
		}

		if ( isset( $data['audits'] ) ) {
			$wrapped = array(
				'lighthouseResult' => $data,
			);
			return self::normalize_response( $wrapped, $url, 'imported' );
		}

		return new WP_Error( 'wbs_unknown_json', __( 'ساختار گزارش PageSpeed شناخته نشد.', 'webakery-speed' ) );
	}

	/**
	 * Normalize API response.
	 *
	 * @param array  $body     Raw body.
	 * @param string $url      URL.
	 * @param string $strategy Strategy.
	 * @return array
	 */
	private static function normalize_response( $body, $url, $strategy ) {
		$lh       = $body['lighthouseResult'] ?? array();
		$audits   = $lh['audits'] ?? array();
		$categories = $lh['categories'] ?? array();
		$perf     = isset( $categories['performance']['score'] ) ? (float) $categories['performance']['score'] : null;

		$issues = array();
		foreach ( $audits as $audit_id => $audit ) {
			$score = isset( $audit['score'] ) ? $audit['score'] : null;
			$mode  = $audit['scoreDisplayMode'] ?? '';

			if ( in_array( $mode, array( 'notApplicable', 'manual', 'informative' ), true ) ) {
				continue;
			}

			if ( null === $score || $score >= 0.9 ) {
				continue;
			}

			$suggested = WBS_Fix_Registry::fixes_for_audit( $audit_id );
			$issues[]  = array(
				'id'          => sanitize_key( $audit_id ),
				'title'       => sanitize_text_field( $audit['title'] ?? $audit_id ),
				'description' => sanitize_textarea_field( $audit['description'] ?? '' ),
				'score'       => $score,
				'display'     => isset( $audit['displayValue'] ) ? sanitize_text_field( $audit['displayValue'] ) : '',
				'suggested'   => $suggested,
			);
		}

		usort(
			$issues,
			function ( $a, $b ) {
				return $a['score'] <=> $b['score'];
			}
		);

		return array(
			'url'             => $url ? esc_url_raw( $url ) : ( $lh['finalUrl'] ?? '' ),
			'strategy'        => $strategy,
			'scanned_at'      => current_time( 'mysql' ),
			'performance'     => null !== $perf ? (int) round( $perf * 100 ) : null,
			'issues'          => $issues,
			'suggested_fixes' => self::collect_suggested_fixes( $issues ),
			'report_url'      => '',
			'report_id'       => '',
			'source'          => 'api',
		);
	}

	/**
	 * Normalize Chrome extension JSON export.
	 *
	 * @param array $data Chrome export payload.
	 * @return array
	 */
	private static function normalize_chrome_export( $data ) {
		$issues = array();

		foreach ( (array) $data['issues'] as $issue ) {
			if ( ! is_array( $issue ) ) {
				continue;
			}

			$issues[] = array(
				'id'          => sanitize_key( $issue['id'] ?? 'chrome-issue' ),
				'title'       => sanitize_text_field( $issue['title'] ?? '' ),
				'description' => sanitize_textarea_field( $issue['detail'] ?? '' ),
				'score'       => isset( $issue['score'] ) ? (float) $issue['score'] : 0.5,
				'display'     => '',
				'suggested'   => isset( $issue['suggested'] ) && is_array( $issue['suggested'] )
					? array_map( 'sanitize_key', $issue['suggested'] )
					: array(),
			);
		}

		$suggested = isset( $data['suggestedFixes'] ) && is_array( $data['suggestedFixes'] )
			? array_map( 'sanitize_key', $data['suggestedFixes'] )
			: self::collect_suggested_fixes( $issues );

		return array(
			'url'             => esc_url_raw( $data['url'] ?? '' ),
			'strategy'        => 'chrome',
			'scanned_at'      => sanitize_text_field( $data['exportedAt'] ?? current_time( 'mysql' ) ),
			'performance'     => isset( $data['performance'] ) ? (int) $data['performance'] : null,
			'issues'          => $issues,
			'suggested_fixes' => $suggested,
		);
	}

	/**
	 * Unique suggested fixes from issues.
	 *
	 * @param array $issues Issues list.
	 * @return array
	 */
	private static function collect_suggested_fixes( $issues ) {
		$fixes = array();
		foreach ( $issues as $issue ) {
			foreach ( $issue['suggested'] as $slug ) {
				$fixes[ $slug ] = true;
			}
		}
		return array_keys( $fixes );
	}
}
