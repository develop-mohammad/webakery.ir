<?php
namespace WCCP;

defined( 'ABSPATH' ) || exit;

class Plugin {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		Admin::instance();
		Ajax::instance();
		Checkout::instance();
		OnlineProducts::instance();

		if ( is_readable( WCCP_PATH . 'includes/License.php' ) ) {
			require_once WCCP_PATH . 'includes/License.php';
			if ( class_exists( '\\WCCP\\License' ) ) {
				License::init();
			}
		}
	}

	public static function activate() {
		if ( false === get_option( Fields::ACTIVE_OPTION, false ) ) {
			update_option( Fields::ACTIVE_OPTION, Fields::default_active(), false );
		}
		OnlineProducts::register_cpt();
		flush_rewrite_rules();
	}
}
