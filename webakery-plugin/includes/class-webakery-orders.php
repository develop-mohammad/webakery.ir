<?php
/**
 * Order / inquiry handling.
 *
 * @package Webakery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores and emails customer orders.
 */
class Webakery_Orders {

	/**
	 * Hook registrations.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'wp_ajax_webakery_submit_order', array( __CLASS__, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_webakery_submit_order', array( __CLASS__, 'handle_submit' ) );
	}

	/**
	 * Private CPT for stored orders.
	 */
	public static function register_post_type() {
		register_post_type(
			'wbk_order',
			array(
				'labels'              => array(
					'name'          => __( 'سفارش‌ها', 'webakery' ),
					'singular_name' => __( 'سفارش', 'webakery' ),
					'menu_name'     => __( 'سفارش‌ها', 'webakery' ),
					'edit_item'     => __( 'مشاهده سفارش', 'webakery' ),
					'search_items'  => __( 'جستجوی سفارش', 'webakery' ),
					'not_found'     => __( 'سفارشی یافت نشد.', 'webakery' ),
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
	 * AJAX order submit handler.
	 */
	public static function handle_submit() {
		check_ajax_referer( 'webakery_order', 'nonce' );

		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$product = isset( $_POST['product'] ) ? sanitize_text_field( wp_unslash( $_POST['product'] ) ) : '';
		$qty     = isset( $_POST['qty'] ) ? absint( $_POST['qty'] ) : 1;
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( empty( $name ) || empty( $phone ) ) {
			wp_send_json_error(
				array( 'message' => __( 'نام و شماره تماس الزامی است.', 'webakery' ) ),
				400
			);
		}

		$qty = max( 1, $qty );

		$order_id = wp_insert_post(
			array(
				'post_type'   => 'wbk_order',
				'post_status' => 'publish',
				'post_title'  => sprintf(
					/* translators: 1: customer name, 2: product */
					__( 'سفارش %1$s — %2$s', 'webakery' ),
					$name,
					$product ? $product : __( 'عمومی', 'webakery' )
				),
			),
			true
		);

		if ( is_wp_error( $order_id ) ) {
			wp_send_json_error(
				array( 'message' => __( 'ثبت سفارش ناموفق بود.', 'webakery' ) ),
				500
			);
		}

		update_post_meta( $order_id, '_wbk_customer_name', $name );
		update_post_meta( $order_id, '_wbk_customer_phone', $phone );
		update_post_meta( $order_id, '_wbk_product', $product );
		update_post_meta( $order_id, '_wbk_qty', $qty );
		update_post_meta( $order_id, '_wbk_message', $message );

		$settings = Webakery_Settings::get();
		$to       = ! empty( $settings['order_email'] ) ? $settings['order_email'] : get_option( 'admin_email' );

		$subject = sprintf(
			/* translators: %s: store name */
			__( '[%s] سفارش جدید', 'webakery' ),
			$settings['store_name']
		);

		$body = sprintf(
			"%s\n%s\n%s\n%s\n%s\n%s",
			__( 'نام:', 'webakery' ) . ' ' . $name,
			__( 'تلفن:', 'webakery' ) . ' ' . $phone,
			__( 'محصول:', 'webakery' ) . ' ' . ( $product ? $product : '—' ),
			__( 'تعداد:', 'webakery' ) . ' ' . $qty,
			__( 'پیام:', 'webakery' ) . ' ' . ( $message ? $message : '—' ),
			home_url( '/wp-admin/post.php?post=' . $order_id . '&action=edit' )
		);

		wp_mail( $to, $subject, $body );

		wp_send_json_success(
			array( 'message' => __( 'سفارش شما ثبت شد. به زودی تماس می‌گیریم.', 'webakery' ) )
		);
	}
}
