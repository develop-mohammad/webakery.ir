<?php
/**
 * Applies enabled fixes on frontend only.
 *
 * @package WebakerySpeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fix loader and runner.
 */
class WBS_Fix_Manager {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'boot_fixes' ), 20 );
	}

	/**
	 * Load and boot enabled fix classes.
	 */
	public static function boot_fixes() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		if ( ! WBS_Settings::is_active() ) {
			return;
		}

		foreach ( WBS_Fix_Registry::all() as $slug => $meta ) {
			if ( ! WBS_Settings::is_fix_enabled( $slug ) ) {
				continue;
			}

			$path = WBS_PATH . 'includes/fixes/' . $meta['file'];
			if ( ! file_exists( $path ) ) {
				continue;
			}

			require_once $path;
			if ( class_exists( $meta['class'] ) && method_exists( $meta['class'], 'boot' ) ) {
				call_user_func( array( $meta['class'], 'boot' ) );
			}
		}
	}

	/**
	 * Enable fixes by slug list.
	 *
	 * @param array $slugs Fix slugs.
	 * @param bool  $merge Merge with existing.
	 */
	public static function enable_fixes( $slugs, $merge = true ) {
		$settings = WBS_Settings::get();
		$valid    = array_keys( WBS_Fix_Registry::all() );
		$slugs    = array_values( array_intersect( array_map( 'sanitize_key', $slugs ), $valid ) );

		if ( $merge && ! empty( $settings['enabled_fixes'] ) ) {
			$slugs = array_values( array_unique( array_merge( $settings['enabled_fixes'], $slugs ) ) );
		}

		$settings['enabled_fixes'] = $slugs;
		$settings['last_applied']  = array(
			'time'  => current_time( 'mysql' ),
			'fixes' => $slugs,
		);
		update_option( WBS_Settings::OPTION_KEY, $settings );
	}

	/**
	 * Disable all fixes instantly (emergency).
	 */
	public static function disable_all_fixes() {
		$settings = WBS_Settings::get();
		$settings['enabled']       = false;
		$settings['enabled_fixes'] = array();
		update_option( WBS_Settings::OPTION_KEY, $settings );
	}
}
