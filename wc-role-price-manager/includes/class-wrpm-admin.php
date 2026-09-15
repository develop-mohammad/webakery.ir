<?php
defined( 'ABSPATH' ) || exit;

/**
 * پنل مدیریت افزونه
 */
class WRPM_Admin {

	/** @var self|null */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function menu() {
		add_menu_page(
			'نقش‌قیمت',
			'نقش‌قیمت',
			'manage_woocommerce',
			'wc-role-price-manager',
			array( $this, 'render' ),
			'dashicons-groups',
			56
		);
	}

	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'wc-role-price-manager' ) ) {
			return;
		}
		wp_enqueue_style( 'wrpm-admin', WRPM_URL . 'assets/admin.css', array(), WRPM_VERSION );
		wp_enqueue_script( 'wrpm-admin', WRPM_URL . 'assets/admin.js', array(), WRPM_VERSION, true );
	}

	public function handle_actions() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// افزودن نقش
		if ( ! empty( $_POST['wrpm_add_role'] ) ) {
			check_admin_referer( 'wrpm_add_role' );
			$slug  = sanitize_key( wp_unslash( $_POST['role_slug'] ?? '' ) );
			$label = sanitize_text_field( wp_unslash( $_POST['role_label'] ?? '' ) );
			$base  = sanitize_key( wp_unslash( $_POST['role_base'] ?? 'customer' ) );
			$result = WRPM_Roles::add_role( $slug, $label, $base );
			if ( is_wp_error( $result ) ) {
				add_settings_error( 'wrpm', 'role_err', $result->get_error_message(), 'error' );
			} else {
				add_settings_error( 'wrpm', 'role_ok', 'نقش جدید افزوده شد.', 'updated' );
			}
		}

		// حذف نقش
		if ( ! empty( $_GET['wrpm_delete_role'] ) && ! empty( $_GET['_wpnonce'] ) ) {
			$slug = sanitize_key( wp_unslash( $_GET['wrpm_delete_role'] ) );
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wrpm_delete_role_' . $slug ) ) {
				if ( WRPM_Roles::delete_role( $slug ) ) {
					add_settings_error( 'wrpm', 'role_del', 'نقش حذف شد.', 'updated' );
				} else {
					add_settings_error( 'wrpm', 'role_del_err', 'حذف نقش ممکن نشد (فقط نقش‌های ساخته‌شده با این افزونه قابل حذف‌اند).', 'error' );
				}
			}
		}

		// ذخیره تنظیمات قیمت
		if ( ! empty( $_POST['wrpm_save_settings'] ) ) {
			check_admin_referer( 'wrpm_save_settings' );
			$discounts = array();
			if ( ! empty( $_POST['global_discounts'] ) && is_array( $_POST['global_discounts'] ) ) {
				foreach ( wp_unslash( $_POST['global_discounts'] ) as $role => $pct ) { // phpcs:ignore
					$discounts[ sanitize_key( $role ) ] = floatval( $pct );
				}
			}
			$hide_roles = isset( $_POST['hide_price_roles'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['hide_price_roles'] ) ) : array(); // phpcs:ignore
			WRPM_Pricing::save_settings(
				array(
					'global_discounts'   => $discounts,
					'hide_price_roles'   => $hide_roles,
					'hide_price_guests'  => ! empty( $_POST['hide_price_guests'] ) ? 1 : 0,
					'hide_price_message' => sanitize_text_field( wp_unslash( $_POST['hide_price_message'] ?? '' ) ),
				)
			);
			add_settings_error( 'wrpm', 'settings_ok', 'تنظیمات ذخیره شد.', 'updated' );
		}
	}

	public function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$tab  = sanitize_key( $_GET['tab'] ?? 'roles' ); // phpcs:ignore
		$tabs = array(
			'roles'    => 'نقش‌ها',
			'settings' => 'قیمت و نمایش',
			'help'     => 'راهنما',
			'license'  => 'لایسنس',
		);
		include WRPM_PATH . 'includes/views/layout.php';
	}
}
