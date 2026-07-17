<?php
defined( 'ABSPATH' ) || exit;

class NM_WooCommerce {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'woocommerce_order_status_processing', array( $this, 'on_paid' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_paid' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'thankyou' ), 5 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'item_data' ), 10, 2 );
	}

	public static function ensure_product() {
		$pid = (int) NM_Settings::get( 'wc_product_id', 0 );
		if ( $pid && get_post( $pid ) ) {
			return $pid;
		}
		$pid = wp_insert_post( array(
			'post_title'  => 'رزرو نوبت مشاوره',
			'post_status' => 'publish',
			'post_type'   => 'product',
			'post_content'=> 'محصول سیستمی افزونه نوبت من — حذف نکنید.',
		) );
		if ( is_wp_error( $pid ) || ! $pid ) {
			return 0;
		}
		update_post_meta( $pid, '_virtual', 'yes' );
		update_post_meta( $pid, '_sold_individually', 'yes' );
		update_post_meta( $pid, '_manage_stock', 'no' );
		update_post_meta( $pid, '_price', '0' );
		update_post_meta( $pid, '_regular_price', '0' );
		wp_set_object_terms( $pid, 'simple', 'product_type' );
		NM_Settings::update( array( 'wc_product_id' => (int) $pid ) );
		return (int) $pid;
	}

	public static function create_checkout_for_booking( $booking ) {
		if ( ! function_exists( 'WC' ) ) {
			return '';
		}
		$product_id = self::ensure_product();
		if ( ! $product_id ) {
			return '';
		}

		if ( null === WC()->cart ) {
			if ( function_exists( 'wc_load_cart' ) ) {
				wc_load_cart();
			}
		}
		if ( WC()->cart ) {
			WC()->cart->empty_cart();
			WC()->cart->add_to_cart( $product_id, 1, 0, array(), array(
				'nm_booking_id'   => (int) $booking->id,
				'nm_booking_code' => $booking->booking_code,
				'nm_jalali_date'  => $booking->jalali_date,
				'nm_start_time'   => substr( $booking->start_time, 0, 5 ),
				'nm_price'        => (int) $booking->price,
			) );
		}

		// قیمت سفارشی از طریق فیلتر
		add_action( 'woocommerce_before_calculate_totals', function ( $cart ) use ( $booking ) {
			if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
			foreach ( $cart->get_cart() as $item ) {
				if ( ! empty( $item['nm_booking_id'] ) && (int) $item['nm_booking_id'] === (int) $booking->id ) {
					$item['data']->set_price( (float) $booking->price );
				}
			}
		}, 20 );

		return wc_get_checkout_url();
	}

	public function item_data( $data, $cart_item ) {
		if ( empty( $cart_item['nm_booking_code'] ) ) {
			return $data;
		}
		$data[] = array( 'name' => 'کد رزرو', 'value' => $cart_item['nm_booking_code'] );
		$data[] = array( 'name' => 'تاریخ', 'value' => $cart_item['nm_jalali_date'] . ' — ' . $cart_item['nm_start_time'] );
		return $data;
	}

	public function on_paid( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;
		foreach ( $order->get_items() as $item ) {
			$bid = (int) $item->get_meta( 'nm_booking_id' );
			if ( ! $bid && isset( $item['nm_booking_id'] ) ) {
				$bid = (int) $item['nm_booking_id'];
			}
			// از cart item data ذخیره شده در order item
			if ( ! $bid ) {
				foreach ( $item->get_meta_data() as $meta ) {
					if ( 'nm_booking_id' === $meta->key ) {
						$bid = (int) $meta->value;
					}
				}
			}
			// fallback: meta از line item در زمان ایجاد سفارش
			if ( ! $bid ) {
				$bid = (int) $order->get_meta( '_nm_booking_id' );
			}
			if ( $bid ) {
				NM_Booking::mark_paid( $bid, $order_id );
			}
		}

		// ذخیره از session cart meta هنگام ایجاد سفارش
		$bid = (int) $order->get_meta( '_nm_booking_id' );
		if ( $bid ) {
			NM_Booking::mark_paid( $bid, $order_id );
		}
	}

	public function thankyou( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;
		$bid = (int) $order->get_meta( '_nm_booking_id' );
		if ( ! $bid ) {
			foreach ( $order->get_items() as $item ) {
				$meta = $item->get_meta( 'nm_booking_id' );
				if ( $meta ) { $bid = (int) $meta; break; }
			}
		}
		if ( ! $bid ) return;
		$booking = NM_Booking::get( $bid );
		if ( ! $booking ) return;
		echo '<div class="nm-wc-thanks nm-card" style="margin:20px 0;padding:20px;border-radius:16px;background:#f8f5ff;border:1px solid #e9d5ff">';
		echo NM_Booking::thank_you_html( $booking );
		echo '</div>';
	}
}

// ذخیره booking id روی سفارش هنگام checkout
add_action( 'woocommerce_checkout_create_order_line_item', function ( $item, $cart_item_key, $values, $order ) {
	if ( empty( $values['nm_booking_id'] ) ) return;
	$item->add_meta_data( 'nm_booking_id', (int) $values['nm_booking_id'], true );
	$item->add_meta_data( 'nm_booking_code', $values['nm_booking_code'], true );
	$item->add_meta_data( 'کد رزرو', $values['nm_booking_code'], true );
	$item->add_meta_data( 'تاریخ شمسی', $values['nm_jalali_date'] . ' ' . $values['nm_start_time'], true );
	$order->update_meta_data( '_nm_booking_id', (int) $values['nm_booking_id'] );
	$order->update_meta_data( '_nm_booking_code', $values['nm_booking_code'] );
}, 10, 4 );

add_action( 'woocommerce_before_calculate_totals', function ( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
	if ( empty( $cart->cart_contents ) ) return;
	foreach ( $cart->get_cart() as $item ) {
		if ( ! empty( $item['nm_price'] ) ) {
			$item['data']->set_price( (float) $item['nm_price'] );
		}
	}
}, 20 );
