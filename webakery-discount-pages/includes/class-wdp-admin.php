<?php
defined( 'ABSPATH' ) || exit;

/**
 * پیشخوان افزونه: فهرست صفحه‌های تخفیف، تنظیمات و لایسنس.
 */
class WDP_Admin {

	const CAP = 'manage_woocommerce';

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
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_notices', array( $this, 'bulk_notice' ) );

		add_action( 'admin_post_wdp_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_wdp_recalculate', array( $this, 'handle_recalculate' ) );
	}

	public function menu() {
		add_menu_page(
			'صفحه‌های تخفیف',
			'صفحه‌های تخفیف',
			self::CAP,
			WDP_MENU,
			array( $this, 'render' ),
			'dashicons-tag',
			57
		);
		add_submenu_page( WDP_MENU, 'پیشخوان', 'پیشخوان', self::CAP, WDP_MENU, array( $this, 'render' ) );
		add_submenu_page(
			WDP_MENU,
			'مدیریت صفحات تخفیف',
			'افزودن / ویرایش صفحات',
			self::CAP,
			'edit-tags.php?taxonomy=' . WDP_Taxonomy::TAXONOMY . '&post_type=product'
		);
	}

	public function assets( $hook ) {
		$screen        = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_tax_screen = $screen && ! empty( $screen->taxonomy ) && WDP_Taxonomy::TAXONOMY === $screen->taxonomy;
		$is_product    = $screen && 'product' === $screen->post_type && 'post' === $screen->base;

		if ( 'toplevel_page_' . WDP_MENU !== $hook && ! $is_tax_screen && ! $is_product ) {
			return;
		}
		wp_enqueue_style( 'wdp-admin', WDP_URL . 'assets/admin.css', array(), WDP_VERSION );
		wp_enqueue_script( 'wdp-admin', WDP_URL . 'assets/admin.js', array(), WDP_VERSION, true );
	}

	public static function tabs() {
		return array(
			'overview' => 'پیشخوان',
			'settings' => 'تنظیمات',
			'license'  => 'لایسنس',
		);
	}

	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$tabs = self::tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview';
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'overview';
		}
		include WDP_PATH . 'includes/views/layout.php';
	}

	/* ─── تنظیمات ──────────────────────────────────────────────── */

	public function handle_save_settings() {
		$this->guard( 'wdp_save_settings' );

		$prev     = get_option( 'wdp_settings', array() );
		$url_base = isset( $_POST['url_base'] ) ? sanitize_text_field( wp_unslash( $_POST['url_base'] ) ) : '';
		$url_base = $url_base ? sanitize_title( $url_base ) : 'discount';

		update_option(
			'wdp_settings',
			array(
				'url_base'    => $url_base,
				'delete_data' => empty( $_POST['delete_data'] ) ? 0 : 1,
			),
			false
		);

		if ( empty( $prev['url_base'] ) || $prev['url_base'] !== $url_base ) {
			flush_rewrite_rules();
		}

		$this->redirect( array( 'tab' => 'settings' ), 'تنظیمات ذخیره شد.', true );
	}

	public function handle_recalculate() {
		$this->guard( 'wdp_recalculate' );

		if ( ! WDP_Plugin::licensed() ) {
			$this->redirect( array( 'tab' => 'overview' ), 'دوره آزمایشی به پایان رسیده؛ برای بازبینی خودکار محصولات، لایسنس را فعال کنید.', false );
		}

		$count = WDP_Assigner::recalculate_all();
		$this->redirect( array( 'tab' => 'overview' ), $count . ' محصول بررسی و در صورت نیاز جابه‌جا شد.', true );
	}

	/* ─── ابزارها ──────────────────────────────────────────────── */

	protected function guard( $nonce_action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		check_admin_referer( $nonce_action );
	}

	protected function redirect( array $args, $message, $success = true ) {
		set_transient(
			'wdp_notice_' . get_current_user_id(),
			array(
				'message' => $message,
				'success' => (bool) $success,
			),
			60
		);
		$args = array_merge( array( 'page' => WDP_MENU ), $args );
		wp_safe_redirect( admin_url( 'admin.php?' . http_build_query( $args ) ) );
		exit;
	}

	public static function notice() {
		$key    = 'wdp_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! $notice ) {
			return;
		}
		delete_transient( $key );
		printf(
			'<div class="notice %s is-dismissible"><p>%s</p></div>',
			empty( $notice['success'] ) ? 'notice-error' : 'notice-success',
			esc_html( $notice['message'] )
		);
	}

	public function bulk_notice() {
		if ( ! empty( $_REQUEST['wdp_recalculated'] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%d محصول بررسی و صفحه تخفیفشان به‌روزرسانی شد.</p></div>',
				(int) $_REQUEST['wdp_recalculated']
			);
		}
	}
}
