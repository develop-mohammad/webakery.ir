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

		$settings = WBS_Settings::get();
		$settings['enabled'] = true;
		update_option( WBS_Settings::OPTION_KEY, $settings );

		return $slugs;
	}
}
