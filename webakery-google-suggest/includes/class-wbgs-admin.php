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
		add_action( 'wp_ajax_wbgs_volumes', array( $this, 'ajax_volumes' ) );
		add_action( 'wp_ajax_wbgs_trends', array( $this, 'ajax_trends' ) );
		add_action( 'wp_ajax_wbgs_xmind', array( $this, 'ajax_xmind' ) );
		add_action( 'wp_ajax_wbgs_report_save', array( $this, 'ajax_report_save' ) );
		add_action( 'wp_ajax_wbgs_report_list', array( $this, 'ajax_report_list' ) );
		add_action( 'wp_ajax_wbgs_report_get', array( $this, 'ajax_report_get' ) );
		add_action( 'wp_ajax_wbgs_report_delete', array( $this, 'ajax_report_delete' ) );
		add_action( 'wp_ajax_nopriv_wbgs_queries', array( $this, 'ajax_queries' ) );
		add_action( 'wp_ajax_nopriv_wbgs_fetch', array( $this, 'ajax_fetch' ) );
		add_action( 'wp_ajax_nopriv_wbgs_volumes', array( $this, 'ajax_volumes' ) );
		add_action( 'wp_ajax_nopriv_wbgs_trends', array( $this, 'ajax_trends' ) );
		add_action( 'wp_ajax_nopriv_wbgs_xmind', array( $this, 'ajax_xmind' ) );
		add_action( 'wp_ajax_nopriv_wbgs_report_save', array( $this, 'ajax_report_save' ) );
		add_action( 'wp_ajax_nopriv_wbgs_report_list', array( $this, 'ajax_report_list' ) );
		add_action( 'wp_ajax_nopriv_wbgs_report_get', array( $this, 'ajax_report_get' ) );
		add_action( 'wp_ajax_nopriv_wbgs_report_delete', array( $this, 'ajax_report_delete' ) );
		add_action( 'admin_post_wbgs_delete_report', array( $this, 'handle_delete_report' ) );
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

		wp_enqueue_style( 'wbgs-admin', WBGS_URL . 'assets/css/admin.css', array(), WBGS_VERSION );
		wp_enqueue_style( 'wbgs-google', WBGS_URL . 'assets/css/google.css', array( 'wbgs-admin' ), WBGS_VERSION );
		wp_enqueue_script( 'wbgs-admin', WBGS_URL . 'assets/js/admin.js', array(), WBGS_VERSION, true );
		wp_localize_script( 'wbgs-admin', 'wbgsAdmin', WBGS_Plugin::script_data( 'wbgs_admin' ) );
	}

	public static function tabs() {
		return array(
			'extract'  => 'استخراج',
			'reports'  => 'گزارش‌ها',
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

		$slug = isset( $_POST['front_slug'] ) ? wp_unslash( $_POST['front_slug'] ) : 'sajest';
		$slug = WBGS_Frontend::sanitize_slug( is_string( $slug ) ? $slug : 'sajest' );
		$prev = WBGS_Plugin::settings();

		$keep = function ( $key ) use ( $prev ) {
			$raw = isset( $_POST[ $key ] ) ? trim( (string) wp_unslash( $_POST[ $key ] ) ) : '';
			return $raw !== '' ? $raw : (string) $prev[ $key ];
		};

		update_option(
			WBGS_Plugin::OPTION,
			array(
				'hl'                    => $hl ? $hl : 'fa',
				'gl'                    => $gl ? $gl : 'ir',
				'delay_ms'              => $delay,
				'front_enabled'         => ! empty( $_POST['front_enabled'] ) ? 1 : 0,
				'front_public'          => ! empty( $_POST['front_public'] ) ? 1 : 0,
				'front_slug'            => $slug,
				'ads_developer_token'   => $keep( 'ads_developer_token' ),
				'ads_client_id'         => $keep( 'ads_client_id' ),
				'ads_client_secret'     => $keep( 'ads_client_secret' ),
				'ads_refresh_token'     => $keep( 'ads_refresh_token' ),
				'ads_customer_id'       => preg_replace( '/\D/', '', $keep( 'ads_customer_id' ) ),
				'ads_login_customer_id' => preg_replace( '/\D/', '', $keep( 'ads_login_customer_id' ) ),
				'guest_daily_cap'       => max( 50, min( 2000, isset( $_POST['guest_daily_cap'] ) ? (int) $_POST['guest_daily_cap'] : 400 ) ),
			),
			false
		);

		flush_rewrite_rules();

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
		$this->rate_limit_front();

		$query = isset( $_POST['q'] ) ? wp_unslash( $_POST['q'] ) : '';
		$query = is_string( $query ) ? $query : '';
		if ( $query === '' ) {
			wp_send_json_error( array( 'message' => 'کوئری خالی است.' ), 400 );
		}

		$source   = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : 'google';
		$settings = WBGS_Plugin::settings();
		$result   = WBGS_Suggest::fetch( $query, $settings['hl'], $settings['gl'], $source );

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

		$used = WBGS_Reports::usage_bump();
		$snap = WBGS_Reports::usage_snapshot();
		$snap['used'] = $used;

		$items = array();
		foreach ( $result['items'] as $row ) {
			$text            = isset( $row['text'] ) ? (string) $row['text'] : '';
			$row['source']   = WBGS_Suggest::normalize_source( $source );
			$row['intent']   = WBGS_Intent::classify( $text );
			$row['intent_fa']= WBGS_Intent::label( $text );
			$row['entity']   = WBGS_Entity::classify( $text );
			$row['entity_fa']= WBGS_Entity::label( $text );
			$items[]         = $row;
		}

		wp_send_json_success(
			array(
				'q'      => $query,
				'source' => WBGS_Suggest::normalize_source( $source ),
				'items'  => $items,
				'usage'  => $snap,
			)
		);
	}

	public function ajax_xmind() {
		$this->ajax_guard();
		$seed = isset( $_POST['seed'] ) ? wp_unslash( $_POST['seed'] ) : '';
		$seed = is_string( $seed ) ? $seed : '';
		$raw  = isset( $_POST['rows'] ) ? wp_unslash( $_POST['rows'] ) : '';
		$rows = array();
		if ( is_string( $raw ) && $raw !== '' ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$rows = $decoded;
			}
		}
		$out = WBGS_Export::xmind( $seed, $rows );
		if ( empty( $out['ok'] ) ) {
			wp_send_json_error( array( 'message' => 'ساخت فایل XMind نشد.' ), 500 );
		}
		wp_send_json_success(
			array(
				'name' => $out['name'],
				'b64'  => base64_encode( $out['bin'] ),
			)
		);
	}

	public function ajax_volumes() {
		$this->ajax_guard();
		$this->rate_limit_front();

		$raw = isset( $_POST['keywords'] ) ? wp_unslash( $_POST['keywords'] ) : array();
		if ( is_string( $raw ) ) {
			$raw = explode( "\n", $raw );
		}
		$keywords = array();
		foreach ( (array) $raw as $kw ) {
			if ( is_string( $kw ) && trim( $kw ) !== '' ) {
				$keywords[] = $kw;
			}
		}
		$settings = WBGS_Plugin::settings();
		$out      = WBGS_Ads::volumes( $keywords, $settings['hl'], $settings['gl'] );
		if ( ! $out['ok'] ) {
			wp_send_json_error(
				array(
					'message'     => $out['message'],
					'configured'  => $out['configured'],
					'volumes'     => $out['volumes'],
				),
				$out['configured'] ? 502 : 400
			);
		}
		wp_send_json_success( $out );
	}

	public function ajax_trends() {
		$this->ajax_guard();
		$this->rate_limit_front();

		$settings = WBGS_Plugin::settings();
		$seed     = isset( $_POST['seed'] ) ? wp_unslash( $_POST['seed'] ) : '';
		$seed     = is_string( $seed ) ? $seed : '';
		$out      = WBGS_Trends::payload( $settings['gl'], $settings['hl'], $seed );
		if ( ! $out['ok'] ) {
			wp_send_json_error(
				array(
					'message'  => 'خواندن ترند ایران ناموفق بود. لینک رسمی گوگل ترند را باز کنید.',
					'explore'  => $out['explore'],
					'trending' => $out['trending'],
					'geo_fa'   => $out['geo_fa'],
					'note'     => $out['note'],
				),
				502
			);
		}
		wp_send_json_success( $out );
	}

	public function ajax_report_save() {
		$this->ajax_guard();

		$seed = isset( $_POST['seed'] ) ? wp_unslash( $_POST['seed'] ) : '';
		$seed = is_string( $seed ) ? $seed : '';
		$raw  = isset( $_POST['rows'] ) ? wp_unslash( $_POST['rows'] ) : '';
		$rows = array();
		if ( is_string( $raw ) && $raw !== '' ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$rows = $decoded;
			}
		} elseif ( is_array( $raw ) ) {
			$rows = $raw;
		}
		$out = WBGS_Reports::save( $seed, $rows );
		if ( ! $out['ok'] ) {
			wp_send_json_error( array( 'message' => $out['message'] ), 400 );
		}
		wp_send_json_success(
			array(
				'report' => $out['report'],
				'list'   => WBGS_Reports::list_for(),
			)
		);
	}

	public function ajax_report_list() {
		$this->ajax_guard();
		wp_send_json_success( array( 'list' => WBGS_Reports::list_for() ) );
	}

	public function ajax_report_get() {
		$this->ajax_guard();
		$id     = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$report = WBGS_Reports::get( $id );
		if ( ! $report ) {
			wp_send_json_error( array( 'message' => 'گزارش پیدا نشد.' ), 404 );
		}
		wp_send_json_success( array( 'report' => $report ) );
	}

	public function ajax_report_delete() {
		$this->ajax_guard();
		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		if ( ! WBGS_Reports::delete( $id ) ) {
			wp_send_json_error( array( 'message' => 'حذف نشد.' ), 404 );
		}
		wp_send_json_success( array( 'list' => WBGS_Reports::list_for() ) );
	}

	public function handle_delete_report() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		check_admin_referer( 'wbgs_delete_report' );
		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		WBGS_Reports::delete( $id );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => WBGS_MENU,
					'tab'     => 'reports',
					'deleted' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function action_links( $links ) {
		$url   = admin_url( 'admin.php?page=' . WBGS_MENU );
		$front = '<a href="' . esc_url( $url ) . '">استخراج</a>';
		array_unshift( $links, $front );
		$public = WBGS_Frontend::url();
		if ( $public ) {
			array_unshift( $links, '<a href="' . esc_url( $public ) . '" target="_blank" rel="noopener">صفحه سایت</a>' );
		}
		return $links;
	}

	private function ajax_guard() {
		if ( ! WBGS_Plugin::licensed() ) {
			wp_send_json_error( array( 'message' => 'لایسنس یا دوره آزمایشی فعال نیست.' ), 402 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		$admin = $nonce && wp_verify_nonce( $nonce, 'wbgs_admin' );
		$front = $nonce && wp_verify_nonce( $nonce, 'wbgs_front' );
		if ( ! $admin && ! $front ) {
			wp_send_json_error( array( 'message' => 'نشست نامعتبر است. صفحه را تازه کنید.' ), 403 );
		}
		if ( $admin && ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز' ), 403 );
		}
		$public = ! empty( WBGS_Plugin::settings()['front_public'] );
		if ( ! is_user_logged_in() && ! ( $front && $public ) ) {
			wp_send_json_error( array( 'message' => 'ابتدا وارد شوید.' ), 403 );
		}
	}

	private function rate_limit_front() {
		if ( current_user_can( self::CAP ) ) {
			return;
		}
		$snap = WBGS_Reports::usage_snapshot();
		if ( $snap['cap'] > 0 && $snap['used'] >= $snap['cap'] ) {
			wp_send_json_error(
				array(
					'message' => 'سقف استفادهٔ امروز پر شد. فردا دوباره تلاش کنید.',
					'code'    => 'limited',
					'usage'   => $snap,
				),
				429
			);
		}
		$who = is_user_logged_in() ? ( 'u' . get_current_user_id() ) : ( 'ip' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'x' ) );
		$key = 'wbgs_rl_' . $who;
		$n   = (int) get_transient( $key );
		$cap = is_user_logged_in() ? 400 : 300;
		if ( $n >= $cap ) {
			wp_send_json_error(
				array(
					'message' => 'تعداد درخواست در این دقیقه زیاد بود. کمی صبر کنید.',
					'code'    => 'limited',
				),
				429
			);
		}
		set_transient( $key, $n + 1, MINUTE_IN_SECONDS );
	}
}
