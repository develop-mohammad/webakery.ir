<?php
/**
 * Scan orchestration.
 *
 * @package WebakerySpeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scanner service.
 */
class WBS_Scanner {

	/**
	 * Run scan with current settings.
	 *
	 * @return array|WP_Error
	 */
	public static function run() {
		$settings = WBS_Settings::get();
		$result   = WBS_PageSpeed_API::scan(
			$settings['scan_url'],
			$settings['psi_api_key'],
			$settings['strategy']
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		WBS_Settings::save_scan( $result );
		return $result;
	}

	/**
	 * Import JSON report.
	 *
	 * @param string $json JSON string.
	 * @return array|WP_Error
	 */
	public static function import_json( $json ) {
		$settings = WBS_Settings::get();
		$result   = WBS_PageSpeed_API::parse_json_report( $json, $settings['scan_url'] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		WBS_Settings::save_scan( $result );
		return $result;
	}

	/**
	 * Import a pagespeed.web.dev report URL, run Lighthouse scan, optionally apply fixes.
	 *
	 * @param string $report_url Report URL from PageSpeed web.
	 * @param bool   $auto_apply Apply suggested fixes after scan.
	 * @return array|WP_Error
	 */
	public static function import_report_url( $report_url, $auto_apply = false ) {
		$parsed = WBS_Report_URL::parse( $report_url );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$settings = WBS_Settings::get();
		$settings['scan_url'] = $parsed['url'];
		$settings['strategy'] = $parsed['strategy'];
		update_option( WBS_Settings::OPTION_KEY, $settings );

		$result = null;
		if ( 'pagespeed-web' === ( $parsed['source'] ?? '' ) && ! empty( $parsed['report_url'] ) ) {
			$result = WBS_Report_Fetcher::fetch( $parsed['report_url'], $parsed['strategy'] );
		}

		if ( is_wp_error( $result ) || null === $result ) {
			if ( empty( $settings['psi_api_key'] ) ) {
				$message = is_wp_error( $result )
					? $result->get_error_message()
					: __( 'دریافت گزارش از pagespeed.web.dev ناموفق بود.', 'webakery-speed' );

				return new WP_Error(
					'wbs_no_api_key',
					$message . ' ' . __( 'کلید API PageSpeed را در تنظیمات وارد کنید یا دوباره تلاش کنید.', 'webakery-speed' )
				);
			}

			$result = WBS_PageSpeed_API::scan(
				$parsed['url'],
				$settings['psi_api_key'],
				$parsed['strategy']
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$result['fetch_method'] = 'api';
		}

		$result['report_url'] = $parsed['report_url'] ?? ( $result['report_url'] ?? '' );
		$result['report_id']  = $parsed['report_id'] ?? ( $result['report_id'] ?? '' );
		$result['source']     = $parsed['source'] ?? 'pagespeed-web';

		WBS_Settings::save_scan( $result );

		if ( $auto_apply ) {
			$safe_only               = (bool) WBS_Settings::get_one( 'safe_mode', true );
			$result['applied_fixes'] = self::apply_suggested( $safe_only );
		}

		return $result;
	}

	/**
	 * Apply safe suggested fixes from last scan.
	 *
	 * @param bool $safe_only Only low-risk fixes.
	 * @return array Enabled slugs.
	 */
	public static function apply_suggested( $safe_only = true ) {
		$scan = WBS_Settings::get_last_scan();
		if ( ! $scan || empty( $scan['suggested_fixes'] ) ) {
			return array();
		}

		$slugs = $scan['suggested_fixes'];
		if ( $safe_only ) {
			$slugs = array_values( array_intersect( $slugs, WBS_Fix_Registry::safe_slugs() ) );
		}

		WBS_Fix_Manager::enable_fixes( $slugs, true );

		$settings            = WBS_Settings::get();
		$settings['enabled'] = true;
		update_option( WBS_Settings::OPTION_KEY, $settings );

		return $slugs;
	}
}
