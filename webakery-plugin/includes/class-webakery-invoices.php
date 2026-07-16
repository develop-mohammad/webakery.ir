<?php
/**
 * Invoice handling.
 *
 * @package Webakery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores invoices linked to bakery orders.
 */
class Webakery_Invoices {

	const COUNTER_OPTION = 'webakery_invoice_counter';

	/**
	 * Hook registrations.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
	}

	/**
	 * Private CPT for invoices, beside orders.
	 */
	public static function register_post_type() {
		register_post_type(
			'wbk_invoice',
			array(
				'labels'              => array(
					'name'          => __( 'فاکتورها', 'webakery' ),
					'singular_name' => __( 'فاکتور', 'webakery' ),
					'menu_name'     => __( 'فاکتورها', 'webakery' ),
					'add_new'       => __( 'افزودن فاکتور', 'webakery' ),
					'add_new_item'  => __( 'افزودن فاکتور جدید', 'webakery' ),
					'edit_item'     => __( 'ویرایش فاکتور', 'webakery' ),
					'search_items'  => __( 'جستجوی فاکتور', 'webakery' ),
					'not_found'     => __( 'فاکتوری یافت نشد.', 'webakery' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=wbk_product',
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title' ),
				'exclude_from_search' => true,
			)
		);
	}

	/**
	 * Available invoice statuses.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			'draft'     => __( 'پیش‌نویس', 'webakery' ),
			'issued'    => __( 'صادر شده', 'webakery' ),
			'paid'      => __( 'پرداخت شده', 'webakery' ),
			'cancelled' => __( 'لغو شده', 'webakery' ),
		);
	}

	/**
	 * Next sequential invoice number (WBK-0001).
	 *
	 * @return string
	 */
	public static function next_number() {
		$counter = absint( get_option( self::COUNTER_OPTION, 0 ) ) + 1;
		update_option( self::COUNTER_OPTION, $counter, false );
		return 'WBK-' . str_pad( (string) $counter, 4, '0', STR_PAD_LEFT );
	}

	/**
	 * Guess unit price from matching product title.
	 *
	 * @param string $product_name Product name from order.
	 * @return int
	 */
	public static function lookup_unit_price( $product_name ) {
		$product_name = trim( (string) $product_name );
		if ( '' === $product_name ) {
			return 0;
		}

		global $wpdb;
		$product_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_title = %s LIMIT 1",
				'wbk_product',
				'publish',
				$product_name
			)
		);

		if ( ! $product_id ) {
			$products = get_posts(
				array(
					'post_type'      => 'wbk_product',
					'post_status'    => 'publish',
					's'              => $product_name,
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);
			$product_id = empty( $products ) ? 0 : $products[0];
		}

		if ( ! $product_id ) {
			return 0;
		}

		return absint( get_post_meta( $product_id, '_wbk_price', true ) );
	}

	/**
	 * Find existing invoice for an order.
	 *
	 * @param int $order_id Order post ID.
	 * @return int Invoice ID or 0.
	 */
	public static function find_for_order( $order_id ) {
		$order_id = absint( $order_id );
		if ( ! $order_id ) {
			return 0;
		}

		$found = get_posts(
			array(
				'post_type'      => 'wbk_invoice',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_wbk_order_id',
				'meta_value'     => $order_id,
			)
		);

		return empty( $found ) ? 0 : absint( $found[0] );
	}

	/**
	 * Create an invoice from an order.
	 *
	 * @param int $order_id Order post ID.
	 * @return int|WP_Error Invoice ID or error.
	 */
	public static function create_from_order( $order_id ) {
		$order_id = absint( $order_id );
		$order    = get_post( $order_id );

		if ( ! $order || 'wbk_order' !== $order->post_type ) {
			return new WP_Error( 'invalid_order', __( 'سفارش معتبر نیست.', 'webakery' ) );
		}

		$existing = self::find_for_order( $order_id );
		if ( $existing ) {
			return $existing;
		}

		$name    = (string) get_post_meta( $order_id, '_wbk_customer_name', true );
		$phone   = (string) get_post_meta( $order_id, '_wbk_customer_phone', true );
		$product = (string) get_post_meta( $order_id, '_wbk_product', true );
		$qty     = max( 1, absint( get_post_meta( $order_id, '_wbk_qty', true ) ) );
		$message = (string) get_post_meta( $order_id, '_wbk_message', true );
		$price   = self::lookup_unit_price( $product );
		$total   = $price * $qty;
		$number  = self::next_number();

		$invoice_id = wp_insert_post(
			array(
				'post_type'   => 'wbk_invoice',
				'post_status' => 'publish',
				'post_title'  => sprintf(
					/* translators: 1: invoice number, 2: customer name */
					__( 'فاکتور %1$s — %2$s', 'webakery' ),
					$number,
					$name ? $name : __( 'مشتری', 'webakery' )
				),
			),
			true
		);

		if ( is_wp_error( $invoice_id ) ) {
			return $invoice_id;
		}

		update_post_meta( $invoice_id, '_wbk_invoice_number', $number );
		update_post_meta( $invoice_id, '_wbk_order_id', $order_id );
		update_post_meta( $invoice_id, '_wbk_customer_name', $name );
		update_post_meta( $invoice_id, '_wbk_customer_phone', $phone );
		update_post_meta( $invoice_id, '_wbk_product', $product );
		update_post_meta( $invoice_id, '_wbk_qty', $qty );
		update_post_meta( $invoice_id, '_wbk_unit_price', $price );
		update_post_meta( $invoice_id, '_wbk_total', $total );
		update_post_meta( $invoice_id, '_wbk_status', 'issued' );
		update_post_meta( $invoice_id, '_wbk_notes', $message );
		update_post_meta( $invoice_id, '_wbk_issue_date', current_time( 'Y-m-d' ) );

		update_post_meta( $order_id, '_wbk_invoice_id', $invoice_id );

		return $invoice_id;
	}

	/**
	 * Recalculate total from qty and unit price.
	 *
	 * @param int $invoice_id Invoice post ID.
	 */
	public static function recalculate_total( $invoice_id ) {
		$qty   = max( 1, absint( get_post_meta( $invoice_id, '_wbk_qty', true ) ) );
		$price = absint( get_post_meta( $invoice_id, '_wbk_unit_price', true ) );
		update_post_meta( $invoice_id, '_wbk_total', $qty * $price );
	}

	/**
	 * Get invoice meta bundle.
	 *
	 * @param int $invoice_id Invoice post ID.
	 * @return array
	 */
	public static function get_meta( $invoice_id ) {
		return array(
			'number'         => get_post_meta( $invoice_id, '_wbk_invoice_number', true ),
			'order_id'       => absint( get_post_meta( $invoice_id, '_wbk_order_id', true ) ),
			'customer_name'  => get_post_meta( $invoice_id, '_wbk_customer_name', true ),
			'customer_phone' => get_post_meta( $invoice_id, '_wbk_customer_phone', true ),
			'product'        => get_post_meta( $invoice_id, '_wbk_product', true ),
			'qty'            => absint( get_post_meta( $invoice_id, '_wbk_qty', true ) ),
			'unit_price'     => absint( get_post_meta( $invoice_id, '_wbk_unit_price', true ) ),
			'total'          => absint( get_post_meta( $invoice_id, '_wbk_total', true ) ),
			'status'         => get_post_meta( $invoice_id, '_wbk_status', true ),
			'notes'          => get_post_meta( $invoice_id, '_wbk_notes', true ),
			'issue_date'     => get_post_meta( $invoice_id, '_wbk_issue_date', true ),
		);
	}
}
