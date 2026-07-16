<?php
/**
 * Plugin settings.
 *
 * @package WebakerySpeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings storage.
 */
class WBS_Settings {

	const OPTION_KEY       = 'webakery_speed_settings';
	const SCAN_OPTION_KEY  = 'webakery_speed_last_scan';
	const STATUS_OPTION_KEY = 'webakery_speed_fix_status';

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'           => true,
			'safe_mode'         => true,
			'psi_api_key'       => '',
			'scan_url'          => home_url( '/' ),
			'strategy'          => 'mobile',
			'exclude_scripts'   => 'jquery-core,jquery-migrate,wp-polyfill',
			'exclude_styles'    => '',
			'last_applied'      => array(),
		);
	}

	/**
	 * Get settings.
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Get one value.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public static function get_one( $key, $default = '' ) {
		$settings = self::get();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Update settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function update( $input ) {
		$current = self::get();
		if ( ! empty( $input['_update_fixes'] ) ) {
			$enabled_fixes = isset( $input['enabled_fixes'] ) && is_array( $input['enabled_fixes'] )
				? array_map( 'sanitize_key', $input['enabled_fixes'] )
				: array();
		} else {
			$enabled_fixes = isset( $current['enabled_fixes'] ) ? $current['enabled_fixes'] : array();
		}

		$settings = array(
			'enabled'         => ! empty( $input['enabled'] ),
			'safe_mode'       => ! empty( $input['safe_mode'] ),
			'psi_api_key'     => sanitize_text_field( $input['psi_api_key'] ?? '' ),
			'scan_url'        => esc_url_raw( $input['scan_url'] ?? home_url( '/' ) ),
			'strategy'        => in_array( $input['strategy'] ?? 'mobile', array( 'mobile', 'desktop' ), true )
				? $input['strategy']
				: 'mobile',
			'exclude_scripts' => sanitize_textarea_field( $input['exclude_scripts'] ?? '' ),
			'exclude_styles'  => sanitize_textarea_field( $input['exclude_styles'] ?? '' ),
			'enabled_fixes'   => $enabled_fixes,
			'last_applied'    => isset( $current['last_applied'] ) ? $current['last_applied'] : array(),
		);

		update_option( self::OPTION_KEY, $settings );
		return $settings;
	}

	/**
	 * Parse comma/newline list.
	 *
	 * @param string $raw Raw string.
	 * @return array
	 */
	public static function parse_list( $raw ) {
		$parts = preg_split( '/[\s,]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY );
		return array_filter( array_map( 'sanitize_key', $parts ) );
	}

	/**
	 * Is master switch on.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return (bool) self::get_one( 'enabled', true );
	}

	/**
	 * Is fix enabled.
	 *
	 * @param string $slug Fix slug.
	 * @return bool
	 */
	public static function is_fix_enabled( $slug ) {
		if ( ! self::is_active() ) {
			return false;
		}
		$enabled = self::get_one( 'enabled_fixes', array() );
		return is_array( $enabled ) && in_array( $slug, $enabled, true );
	}

	/**
	 * Save last scan.
	 *
	 * @param array $scan Scan payload.
	 */
	public static function save_scan( $scan ) {
		update_option( self::SCAN_OPTION_KEY, $scan, false );
	}

	/**
	 * Get last scan.
	 *
	 * @return array|null
	 */
	public static function get_last_scan() {
		$scan = get_option( self::SCAN_OPTION_KEY );
		return is_array( $scan ) ? $scan : null;
	}
}
