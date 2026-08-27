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
	}

	public function sync_after_save( $product_id ) {
		WBE_Product::sync_wc( (int) $product_id );
	}
}
