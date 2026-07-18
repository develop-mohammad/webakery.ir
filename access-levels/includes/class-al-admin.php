<?php
defined( 'ABSPATH' ) || exit;

class AL_Admin {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function menu() {
		add_menu_page(
			'Barbari',
			'Barbari',
			'manage_options',
			'access-levels',
			array( $this, 'render' ),
			'dashicons-shield-alt',
			58
		);
	}

	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'access-levels' ) ) {
			return;
		}
		wp_enqueue_style( 'al-admin', AL_URL . 'assets/admin.css', array(), AL_VERSION );
		wp_enqueue_script( 'al-admin', AL_URL . 'assets/admin.js', array(), AL_VERSION, true );
	}

	public function handle_save() {
		if ( empty( $_POST['al_save_rules'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'al_save_rules' );

		$raw   = isset( $_POST['denied'] ) ? (array) wp_unslash( $_POST['denied'] ) : array();
		$rules = array();
		foreach ( $raw as $role => $items ) {
			$rules[ sanitize_key( $role ) ] = array_map( 'sanitize_key', (array) $items );
		}
		AL_Access::save_rules( $rules );
		add_settings_error( 'al', 'saved', 'دسترسی‌ها ذخیره شد.', 'updated' );
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tab = sanitize_key( $_GET['tab'] ?? 'access' ); // phpcs:ignore
		$tabs = array(
			'access'  => 'دسترسی افزونه‌ها',
			'users'   => 'کاربران',
			'license' => 'لایسنس',
		);
		include AL_PATH . 'includes/views/layout.php';
	}
}
