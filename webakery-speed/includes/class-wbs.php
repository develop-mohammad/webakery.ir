<?php
/**
 * Main plugin class.
 *
 * @package WebakerySpeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core bootstrap.
 */
class WBS {

	/**
	 * Singleton.
	 *
	 * @var WBS|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return WBS
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		WBS_Fix_Manager::init();

		if ( is_admin() ) {
			WBS_Admin::init();
		}
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'webakery-speed', false, dirname( WBS_BASENAME ) . '/languages' );
	}

	/**
	 * Activation.
	 */
	public static function activate() {
		if ( false === get_option( WBS_Settings::OPTION_KEY ) ) {
			update_option( WBS_Settings::OPTION_KEY, WBS_Settings::defaults() );
		}
	}

	/**
	 * Deactivation — optimizations stop because hooks are not registered.
	 */
	public static function deactivate() {
		// Intentionally empty: disabling plugin restores original behavior.
	}
}
