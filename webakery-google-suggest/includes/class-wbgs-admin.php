<?php
defined( 'ABSPATH' ) || exit;

class WBGS_Admin {

	/** @var self|null */
	private static $instance = null;

	const CAP = 'manage_options';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_wbgs_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'wp_ajax_wbgs_queries', array( $this, 'ajax_queries' ) );
		add_action( 'wp_ajax_wbgs_fetch', array( $this, 'ajax_fetch' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WBGS_FILE ), array( $this, 'action_links' ) );
	}

	public function menu() {
		add_menu_page(
			'سجست‌یاب گوگل',
			'سجست‌یاب گوگل',
			self::CAP,
			WBGS_MENU,
			array( $this, 'render' ),
			'dashicons-search',
			58
		);
	}

	public function assets( $hook ) {
		if ( 'toplevel_page_' . WBGS_MENU !== $hook ) {
			return;
		}

		$settings = WBGS_Plugin::settings();
		wp_enqueue_style( 'wbgs-admin', WBGS_URL . 'assets/css/admin.css', array(), WBGS_VERSION );
		wp_enqueue_script( 'wbgs-admin', WBGS_URL . 'assets/js/admin.js', array(), WBGS_VERSION, true );
		wp_localize_script(
			'wbgs-admin',
			'wbgsAdmin',
			array(
				'ajax'     => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wbgs_admin' ),
				'delay'    => (int) $settings['delay_ms'],
				'licensed' => WBGS_Plugin::licensed(),
				'i18n'     => array(
					'empty'    => 'عبارت پایه را بنویسید.',
					'locked'   => 'برای استخراج، لایسنس را فعال کنید یا دوره آزمایشی را استفاده کنید.',
					'limited'  => 'گوگل درخواست‌ها را محدود کرد. کمی صبر کنید و دوباره تلاش کنید.',
					'network'  => 'ارتباط با سرور برقرار نشد.',
					'none'     => 'گوگل برای این عبارت پیشنهادی برنگرداند.',
					'done'     => 'استخراج تمام شد.',
					'copy_ok'  => 'کپی شد.',
					'copy_err' => 'کپی نشد؛ دستی انتخاب کنید.',
					'stopped'  => 'استخراج متوقف شد.',
				),
			)
		);
	}

	public static function tabs() {
		return array(
			'extract'  => 'استخراج',
			'settings' => 'تنظیمات',
			'license'  => 'لایسنس',
		);
	}

	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}

		$tabs = self::tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'extract';
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'extract';
		}

		$settings = WBGS_Plugin::settings();
		$licensed = WBGS_Plugin::licensed();
		include WBGS_PATH . 'templates/admin-page.php';
	}

	public function handle_save_settings() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		check_admin_referer( 'wbgs_save_settings' );

		$hl = isset( $_POST['hl'] ) ? sanitize_text_field( wp_unslash( $_POST['hl'] ) ) : 'fa';
		$gl = isset( $_POST['gl'] ) ? sanitize_text_field( wp_unslash( $_POST['gl'] ) ) : 'ir';
		$hl = preg_replace( '/[^a-zA-Z\-]/', '', $hl );
		$gl = preg_replace( '/[^a-zA-Z]/', '', $gl );

		$delay = isset( $_POST['delay_ms'] ) ? (int) $_POST['delay_ms'] : 300;
		$delay = max( 150, min( 2000, $delay ) );

		update_option(
			WBGS_Plugin::OPTION,
			array(
				'hl'       => $hl ? $hl : 'fa',
				'gl'       => $gl ? $gl : 'ir',
				'delay_ms' => $delay,
			),
			false
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => WBGS_MENU,
					'tab'     => 'settings',
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function ajax_queries() {
		$this->ajax_guard();

		$seed  = isset( $_POST['seed'] ) ? wp_unslash( $_POST['seed'] ) : '';
		$modes = isset( $_POST['modes'] ) && is_array( $_POST['modes'] ) ? wp_unslash( $_POST['modes'] ) : array();
		$seed  = is_string( $seed ) ? $seed : '';

		$queries = WBGS_Suggest::build_queries( $seed, $modes );
		if ( ! $queries ) {
			wp_send_json_error( array( 'message' => 'عبارت پایه را بنویسید.' ), 400 );
		}

		wp_send_json_success(
			array(
				'queries' => $queries,
				'total'   => count( $queries ),
			)
		);
	}

	public function ajax_fetch() {
		$this->ajax_guard();

		$query = isset( $_POST['q'] ) ? wp_unslash( $_POST['q'] ) : '';
		$query = is_string( $query ) ? $query : '';
		if ( $query === '' ) {
			wp_send_json_error( array( 'message' => 'کوئری خالی است.' ), 400 );
		}

		$settings = WBGS_Plugin::settings();
		$result   = WBGS_Suggest::fetch( $query, $settings['hl'], $settings['gl'] );

		if ( ! $result['ok'] ) {
			$message = 'limited' === $result['error']
				? 'گوگل درخواست‌ها را محدود کرد. کمی صبر کنید و دوباره تلاش کنید.'
				: 'خواندن سجست گوگل ناموفق بود.';
			wp_send_json_error(
				array(
					'message' => $message,
					'code'    => $result['error'],
					'status'  => $result['status'],
				),
				429 === (int) $result['status'] ? 429 : 502
			);
		}

		wp_send_json_success(
			array(
				'q'     => $query,
				'items' => $result['items'],
			)
		);
	}

	public function action_links( $links ) {
		$url   = admin_url( 'admin.php?page=' . WBGS_MENU );
		$front = '<a href="' . esc_url( $url ) . '">استخراج</a>';
		array_unshift( $links, $front );
		return $links;
	}

	private function ajax_guard() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز' ), 403 );
		}
		check_ajax_referer( 'wbgs_admin', 'nonce' );
		if ( ! WBGS_Plugin::licensed() ) {
			wp_send_json_error( array( 'message' => 'لایسنس یا دوره آزمایشی فعال نیست.' ), 402 );
		}
	}
}
