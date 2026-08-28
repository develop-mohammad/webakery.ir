<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * فیلدهای بچ کنار قیمت محصول در پیشخوان.
 */
class WBE_Admin_Product {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'woocommerce_product_options_pricing', array( $this, 'render' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'sync_after_save' ), 40 );
		add_filter( 'manage_edit-product_columns', array( $this, 'columns' ) );
		add_action( 'manage_product_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_filter( 'manage_edit-product_sortable_columns', array( $this, 'sortable' ) );
	}

	public function render() {
		global $post;
		if ( ! $post ) {
			return;
		}
		$pid       = (int) $post->ID;
		$batches   = WBE_Product::batches( $pid );
		$override  = get_post_meta( $pid, WBE_Product::META_CALENDAR, true );
		$effective = WBE_Product::calendar( $pid );
		$global    = WBE_Settings::calendar();
		$hide_cd   = (string) get_post_meta( $pid, WBE_Product::META_HIDE_COUNTDOWN, true ) === '1';
		$wc_price  = '';
		$wc_disc   = '';
		if ( function_exists( 'wc_get_product' ) ) {
			$wc_product = wc_get_product( $pid );
			if ( $wc_product ) {
				$wc_price = $wc_product->get_regular_price( 'edit' );
				$wc_disc  = (string) WBE_Engine::discount_from_prices( $wc_price, $wc_product->get_sale_price( 'edit' ) );
				if ( '0' === $wc_disc ) {
					$wc_disc = '';
				}
			}
		}
		include WBE_PATH . 'includes/views/product-batches.php';
	}

	public function save( $product ) {
		if ( ! $product || ! current_user_can( 'edit_products' ) ) {
			return;
		}
		if ( ! isset( $_POST['wbe_batches_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbe_batches_nonce'] ) ), 'wbe_save_batches' ) ) {
			return;
		}
		$pid      = (int) $product->get_id();
		$override = isset( $_POST['wbe_calendar'] ) ? sanitize_key( wp_unslash( $_POST['wbe_calendar'] ) ) : '';
		$cal      = in_array( $override, array( 'jalali', 'gregorian' ), true ) ? $override : WBE_Settings::calendar();
		$rows     = isset( $_POST['wbe_batches'] ) && is_array( $_POST['wbe_batches'] ) ? wp_unslash( $_POST['wbe_batches'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$batches  = WBE_Engine::sanitize_batches( $rows, $cal );
		WBE_Product::save_batches( $pid, $batches, $override, false );
		WBE_Product::save_hide_countdown( $pid, ! empty( $_POST['wbe_hide_countdown'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	public function sync_after_save( $product_id ) {
		WBE_Product::sync_wc( (int) $product_id );
	}

	public function columns( $cols ) {
		$out = array();
		foreach ( $cols as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'price' === $key ) {
				$out['wbe_expiry'] = 'تاریخ انقضا';
			}
		}
		if ( ! isset( $out['wbe_expiry'] ) ) {
			$out['wbe_expiry'] = 'تاریخ انقضا';
		}
		return $out;
	}

	public function column( $column, $post_id ) {
		if ( 'wbe_expiry' !== $column ) {
			return;
		}
		$post_id = (int) $post_id;
		$exp     = get_post_meta( $post_id, WBE_Product::META_ACTIVE_EXPIRY, true );
		if ( ! $exp ) {
			$active = WBE_Product::active( $post_id );
			$exp    = $active ? $active['expiry'] : '';
		}
		if ( ! $exp ) {
			echo '—';
			return;
		}
		echo esc_html( WBE_Jalali::format_ymd( $exp, WBE_Product::calendar( $post_id ), true ) );
	}

	public function sortable( $cols ) {
		$cols['wbe_expiry'] = 'wbe_expiry';
		return $cols;
	}
}
