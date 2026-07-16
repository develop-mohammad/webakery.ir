<?php
/**
 * Order / inquiry handling.
 *
 * @package Hesabdar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores and emails customer orders.
 */
class Hesabdar_Orders {

	/**
	 * Hook registrations.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'wp_ajax_hesabdar_submit_order', array( __CLASS__, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_hesabdar_submit_order', array( __CLASS__, 'handle_submit' ) );
	}

	/**
	 * Private CPT for stored orders.
	 */
	public static function register_post_type() {
		register_post_type(
			'hsb_order',
			array(
				'labels'              => array(
					'name'          => __( 'سفارش‌ها', 'hesabdar' ),
					'singular_name' => __( 'سفارش', 'hesabdar' ),
					'menu_name'     => __( 'سفارش‌ها', 'hesabdar' ),
					'edit_item'     => __( 'مشاهده سفارش', 'hesabdar' ),
					'search_items'  => __( 'جستجوی سفارش', 'hesabdar' ),
					'not_found'     => __( 'سفارشی یافت نشد.', 'hesabdar' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=hsb_product',
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
		check_ajax_referer( 'hesabdar_order', 'nonce' );

		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$product = isset( $_POST['product'] ) ? sanitize_text_field( wp_unslash( $_POST['product'] ) ) : '';
		$qty     = isset( $_POST['qty'] ) ? absint( $_POST['qty'] ) : 1;
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( empty( $name ) || empty( $phone ) ) {
			wp_send_json_error(
				array( 'message' => __( 'نام و شماره تماس الزامی است.', 'hesabdar' ) ),
				400
			);
		}

		$qty = max( 1, $qty );

		$order_id = wp_insert_post(
			array(
				'post_type'   => 'hsb_order',
				'post_status' => 'publish',
				'post_title'  => sprintf(
					/* translators: 1: customer name, 2: product */
					__( 'سفارش %1$s — %2$s', 'hesabdar' ),
					$name,
					$product ? $product : __( 'عمومی', 'hesabdar' )
				),
			),
			true
		);

		if ( is_wp_error( $order_id ) ) {
			wp_send_json_error(
				array( 'message' => __( 'ثبت سفارش ناموفق بود.', 'hesabdar' ) ),
				500
			);
		}

		update_post_meta( $order_id, '_hsb_customer_name', $name );
		update_post_meta( $order_id, '_hsb_customer_phone', $phone );
		update_post_meta( $order_id, '_hsb_product', $product );
		update_post_meta( $order_id, '_hsb_qty', $qty );
		update_post_meta( $order_id, '_hsb_message', $message );
		update_post_meta( $order_id, '_hsb_unit_price', Hesabdar_Invoice::lookup_product_price( $product ) );

		$settings = Hesabdar_Settings::get();
		$to       = ! empty( $settings['order_email'] ) ? $settings['order_email'] : get_option( 'admin_email' );

		$subject = sprintf(
			/* translators: %s: store name */
			__( '[%s] سفارش جدید', 'hesabdar' ),
			$settings['store_name']
		);

		$body = sprintf(
			"%s\n%s\n%s\n%s\n%s\n%s",
			__( 'نام:', 'hesabdar' ) . ' ' . $name,
			__( 'تلفن:', 'hesabdar' ) . ' ' . $phone,
			__( 'محصول:', 'hesabdar' ) . ' ' . ( $product ? $product : '—' ),
			__( 'تعداد:', 'hesabdar' ) . ' ' . $qty,
			__( 'پیام:', 'hesabdar' ) . ' ' . ( $message ? $message : '—' ),
			home_url( '/wp-admin/post.php?post=' . $order_id . '&action=edit' )
		);

		wp_mail( $to, $subject, $body );

		wp_send_json_success(
			array( 'message' => __( 'سفارش شما ثبت شد. به زودی تماس می‌گیریم.', 'hesabdar' ) )
		);
	}
}
