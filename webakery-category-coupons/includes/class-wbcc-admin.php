<?php
defined( 'ABSPATH' ) || exit;

/**
 * پیشخوان افزونه: کمپین‌ها، کدهای ساخته‌شده، تنظیمات و لایسنس.
 */
class WBCC_Admin {

	/** @var self|null */
	private static $instance = null;

	const CAP = 'manage_woocommerce';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );

		add_action( 'admin_post_wbcc_save_campaign', array( $this, 'handle_save_campaign' ) );
		add_action( 'admin_post_wbcc_delete_campaign', array( $this, 'handle_delete_campaign' ) );
		add_action( 'admin_post_wbcc_toggle_campaign', array( $this, 'handle_toggle_campaign' ) );
		add_action( 'admin_post_wbcc_generate', array( $this, 'handle_generate' ) );
		add_action( 'admin_post_wbcc_delete_coupon', array( $this, 'handle_delete_coupon' ) );
		add_action( 'admin_post_wbcc_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_wbcc_cleanup', array( $this, 'handle_cleanup' ) );
		add_action( 'admin_post_wbcc_export', array( $this, 'handle_export' ) );
	}

	public function menu() {
		add_menu_page(
			'کد تخفیف دسته‌بندی',
			'کد تخفیف دسته‌بندی',
			self::CAP,
			WBCC_MENU,
			array( $this, 'render' ),
			'dashicons-tickets-alt',
			57
		);
	}

	public function assets( $hook ) {
		if ( 'toplevel_page_' . WBCC_MENU !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wbcc-admin', WBCC_URL . 'assets/admin.css', array(), WBCC_VERSION );
		wp_enqueue_script( 'wbcc-admin', WBCC_URL . 'assets/admin.js', array(), WBCC_VERSION, true );
	}

	public static function tabs() {
		return array(
			'campaigns' => 'کمپین‌های تخفیف',
			'coupons'   => 'کدهای ساخته‌شده',
			'settings'  => 'تنظیمات',
			'license'   => 'لایسنس',
		);
	}

	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$tabs = self::tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'campaigns';
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'campaigns';
		}
		include WBCC_PATH . 'includes/views/layout.php';
	}

	/* ─── ذخیره کمپین ─────────────────────────────────────────── */

	public function handle_save_campaign() {
		$this->guard( 'wbcc_save_campaign' );

		$input = wp_unslash( $_POST );
		$id    = WBCC_Campaigns::save( $input );

		$msg = 'کمپین ذخیره شد.';
		if ( ! empty( $_POST['generate_now'] ) ) {
			$campaign = WBCC_Campaigns::get( $id );
			$res      = WBCC_Generator::generate( $campaign, (int) $campaign['batch_count'], 'manual' );
			$msg     .= ' ' . $res['message'];
			$this->redirect( array( 'tab' => 'coupons', 'campaign' => $id ), $msg, ! empty( $res['ok'] ) );
		}

		$this->redirect( array( 'tab' => 'campaigns', 'edit' => $id ), $msg, true );
	}

	public function handle_delete_campaign() {
		$this->guard( 'wbcc_campaign_action' );
		$id = (int) ( $_REQUEST['id'] ?? 0 );
		WBCC_Campaigns::delete( $id );
		$this->redirect( array( 'tab' => 'campaigns' ), 'کمپین حذف شد. (کدهای ساخته‌شده حذف نشدند)', true );
	}

	public function handle_toggle_campaign() {
		$this->guard( 'wbcc_campaign_action' );
		$id      = (int) ( $_REQUEST['id'] ?? 0 );
		$enabled = ! empty( $_REQUEST['enabled'] );
		WBCC_Campaigns::toggle( $id, $enabled );
		$this->redirect( array( 'tab' => 'campaigns' ), $enabled ? 'کمپین فعال شد.' : 'کمپین غیرفعال شد.', true );
	}

	/* ─── ساخت کد ─────────────────────────────────────────────── */

	public function handle_generate() {
		$this->guard( 'wbcc_campaign_action' );
		$id       = (int) ( $_REQUEST['id'] ?? 0 );
		$count    = (int) ( $_REQUEST['count'] ?? 0 );
		$campaign = WBCC_Campaigns::get( $id );

		if ( ! $campaign ) {
			$this->redirect( array( 'tab' => 'campaigns' ), 'کمپین پیدا نشد.', false );
		}
		$count = $count > 0 ? $count : (int) $campaign['batch_count'];
		$res   = WBCC_Generator::generate( $campaign, $count, 'manual' );

		$this->redirect(
			array( 'tab' => 'coupons', 'campaign' => $id ),
			$res['message'],
			! empty( $res['ok'] )
		);
	}

	public function handle_delete_coupon() {
		$this->guard( 'wbcc_coupon_action' );
		$id = (int) ( $_REQUEST['id'] ?? 0 );
		$ok = WBCC_Generator::delete_coupon( $id );
		$this->redirect(
			array( 'tab' => 'coupons', 'campaign' => (int) ( $_REQUEST['campaign'] ?? 0 ) ),
			$ok ? 'کد تخفیف حذف شد.' : 'حذف انجام نشد.',
			$ok
		);
	}

	/* ─── تنظیمات ─────────────────────────────────────────────── */

	public function handle_save_settings() {
		$this->guard( 'wbcc_save_settings' );

		$prefix = strtoupper( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $_POST['default_prefix'] ?? '' ) ) );
		$days   = WBCC_Campaigns::digits( (string) ( $_POST['cleanup_days'] ?? 7 ) );

		update_option( 'wbcc_settings', array(
			'default_prefix'  => substr( $prefix, 0, 12 ) ?: 'OFF',
			'cleanup_expired' => empty( $_POST['cleanup_expired'] ) ? 0 : 1,
			'cleanup_days'    => max( 0, min( 365, (int) $days ) ),
			'delete_data'     => empty( $_POST['delete_data'] ) ? 0 : 1,
		), false );

		$this->redirect( array( 'tab' => 'settings' ), 'تنظیمات ذخیره شد.', true );
	}

	public function handle_cleanup() {
		$this->guard( 'wbcc_settings_action' );
		$settings = get_option( 'wbcc_settings', array() );
		$deleted  = WBCC_Generator::cleanup_expired( (int) ( $settings['cleanup_days'] ?? 0 ) );
		$this->redirect( array( 'tab' => 'settings' ), $deleted . ' کد منقضی‌شده پاک شد.', true );
	}

	/* ─── خروجی CSV ───────────────────────────────────────────── */

	public function handle_export() {
		$this->guard( 'wbcc_coupon_action' );

		$campaign_id = (int) ( $_REQUEST['campaign'] ?? 0 );
		$data        = WBCC_Generator::list_coupons( array( 'campaign' => $campaign_id, 'limit' => 5000 ) );
		$campaigns   = WBCC_Campaigns::all();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=webakery-coupons-' . gmdate( 'Ymd-His' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM تا اکسل فارسی درست باز کند
		fputcsv( $out, array( 'کد تخفیف', 'کمپین', 'نوع', 'مقدار', 'دسته‌بندی‌ها', 'انقضا', 'سقف مصرف', 'مصرف‌شده' ) );

		foreach ( $data['items'] as $item ) {
			$campaign = $campaigns[ $item['campaign'] ] ?? null;
			fputcsv( $out, array(
				$item['code'],
				$campaign ? $campaign['name'] : '—',
				'percent' === $item['type'] ? 'درصدی' : 'مبلغ ثابت',
				WBCC_Campaigns::trim_zeros( $item['amount'] ),
				implode( ' | ', WBCC_Campaigns::category_names( $item['categories'] ) ),
				$item['expires'] ? WBCC_Date::format( $item['expires'] ) : 'بدون انقضا',
				$item['limit'] ?: 'نامحدود',
				$item['usage'],
			) );
		}
		fclose( $out );
		exit;
	}

	/* ─── ابزارها ─────────────────────────────────────────────── */

	protected function guard( $nonce_action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		check_admin_referer( $nonce_action );
	}

	protected function redirect( array $args, $message, $success = true ) {
		set_transient( 'wbcc_notice_' . get_current_user_id(), array(
			'message' => $message,
			'success' => (bool) $success,
		), 60 );

		$args = array_merge( array( 'page' => WBCC_MENU ), $args );
		wp_safe_redirect( admin_url( 'admin.php?' . http_build_query( $args ) ) );
		exit;
	}

	public static function notice() {
		$key    = 'wbcc_notice_' . get_current_user_id();
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

	/**
	 * فهرست دسته‌بندی‌های محصولات به‌صورت سلسله‌مراتبی (برای چک‌باکس‌ها).
	 *
	 * @return array<int,array{id:int,name:string,depth:int,count:int}>
	 */
	public static function category_tree() {
		$terms = get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		) );
		if ( is_wp_error( $terms ) || ! $terms ) {
			return array();
		}

		$by_parent = array();
		foreach ( $terms as $term ) {
			$by_parent[ (int) $term->parent ][] = $term;
		}

		$flat = array();
		$walk = function ( $parent, $depth ) use ( &$walk, &$flat, $by_parent ) {
			if ( empty( $by_parent[ $parent ] ) ) {
				return;
			}
			foreach ( $by_parent[ $parent ] as $term ) {
				$flat[] = array(
					'id'    => (int) $term->term_id,
					'name'  => $term->name,
					'depth' => $depth,
					'count' => (int) $term->count,
				);
				$walk( (int) $term->term_id, $depth + 1 );
			}
		};
		$walk( 0, 0 );

		return $flat;
	}
}
