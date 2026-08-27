<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WBE_Admin {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 58 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_wbe_export', array( $this, 'handle_export' ) );
	}

	public function menu() {
		$cap   = 'manage_woocommerce';
		$count = ( class_exists( 'WBE_Alerts' ) && WBE_Plugin::licensed() ) ? WBE_Alerts::count() : 0;
		$badge = $count ? ' <span class="awaiting-mod">' . (int) $count . '</span>' : '';
		add_menu_page(
			'انقضای کالا',
			'انقضای کالا' . $badge,
			$cap,
			'webakery-expiry',
			array( $this, 'render_reports' ),
			'dashicons-calendar-alt',
			56
		);
		add_submenu_page( 'webakery-expiry', 'گزارش انقضا', 'گزارش', $cap, 'webakery-expiry', array( $this, 'render_reports' ) );
		add_submenu_page( 'webakery-expiry', 'تنظیمات انقضای کالا', 'تنظیمات', $cap, 'webakery-expiry-settings', array( $this, 'render_settings' ) );
		if ( defined( 'WBE_EDITION' ) && 'pro' === WBE_EDITION ) {
			add_submenu_page( 'webakery-expiry', 'لایسنس انقضای کالا', 'لایسنس', 'manage_options', 'webakery-expiry-license', array( $this, 'render_license' ) );
		}
	}

	public function register_settings() {
		register_setting(
			'wbe_settings_group',
			WBE_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'WBE_Settings', 'sanitize' ),
				'default'           => WBE_Settings::defaults(),
			)
		);
	}

	public function assets( $hook ) {
		wp_enqueue_style( 'wbe-admin', WBE_URL . 'assets/admin.css', array(), WBE_VERSION );
		$ok = ( false !== strpos( (string) $hook, 'webakery-expiry' ) );
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && in_array( $screen->id, array( 'product', 'edit-product' ), true ) ) {
			$ok = true;
		}
		if ( ! $ok ) {
			return;
		}
		wp_enqueue_script( 'wbe-admin', WBE_URL . 'assets/admin.js', array( 'jquery' ), WBE_VERSION, true );
	}

	public function render_reports() {
		if ( ! WBE_Plugin::licensed() ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>برای دیدن گزارش، لایسنس را فعال کنید.</p></div></div>';
			return;
		}
		$rows    = WBE_Reports::rows( WBE_Reports::filters_from_request() );
		$filters = WBE_Reports::filters_from_request();
		$sort    = isset( $_GET['wbe_sort'] ) ? sanitize_key( wp_unslash( $_GET['wbe_sort'] ) ) : 'expiry';
		$dir     = ( isset( $_GET['wbe_dir'] ) && 'asc' === $_GET['wbe_dir'] ) ? 'asc' : 'desc';
		include WBE_PATH . 'includes/views/reports.php';
	}

	public function render_settings() {
		$s = WBE_Settings::get();
		include WBE_PATH . 'includes/views/settings.php';
	}

	public function render_license() {
		include WBE_PATH . 'includes/views/license.php';
	}

	public function handle_export() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		check_admin_referer( 'wbe_export' );
		if ( ! WBE_Plugin::licensed() ) {
			wp_die( 'لایسنس نامعتبر است.' );
		}
		$filters = WBE_Reports::filters_from_request();
		$rows    = WBE_Reports::rows( $filters );
		$format  = isset( $_REQUEST['format'] ) ? sanitize_key( wp_unslash( $_REQUEST['format'] ) ) : 'xls';
		if ( 'csv' === $format ) {
			WBE_Export::csv( $rows );
		}
		WBE_Export::xls( $rows );
	}
}
