<?php
defined( 'ABSPATH' ) || exit;

/**
 * همگام‌سازی سبک با افزونه حسابدار (در صورت نصب).
 */
class NM_Hesabdar {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'nm_booking_paid', array( $this, 'push_sale' ) );
	}

	public function push_sale( $booking ) {
		if ( ! $booking ) return;

		// اگر حسابدار هوک عمومی داشته باشد
		do_action( 'hesabdar_external_sale', array(
			'source'       => 'nobat-man',
			'ref'          => $booking->booking_code,
			'amount'       => (int) $booking->price,
			'customer'     => $booking->customer_name,
			'phone'        => $booking->customer_phone,
			'email'        => $booking->customer_email,
			'order_id'     => (int) $booking->order_id,
			'description'  => 'رزرو نوبت ' . $booking->jalali_date . ' ' . substr( $booking->start_time, 0, 5 ),
			'jalali_date'  => $booking->jalali_date,
		) );

		// متا روی سفارش ووکامرس برای گزارش حسابدار
		if ( $booking->order_id && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $booking->order_id );
			if ( $order ) {
				$order->update_meta_data( '_nm_synced_hesabdar', 1 );
				$order->update_meta_data( '_nm_booking_code', $booking->booking_code );
				$order->save();
			}
		}
	}
}
