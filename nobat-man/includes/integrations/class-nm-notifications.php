<?php
defined( 'ABSPATH' ) || exit;

class NM_Notifications {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'nm_booking_paid', array( $this, 'on_paid' ) );
		add_action( 'nm_booking_created', array( $this, 'on_created' ) );
	}

	public function on_created( $booking ) {
		// فقط لاگ سبک — ایمیل اصلی بعد از پرداخت
	}

	public function on_paid( $booking ) {
		if ( ! $booking ) return;

		if ( (int) NM_Settings::get( 'notify_email', 1 ) ) {
			$this->send_email( $booking );
		}

		if ( NM_Pro::is_active() && (int) NM_Settings::get( 'notify_sms', 0 ) ) {
			NM_SMS::send_booking( $booking );
		}

		if ( NM_Pro::is_active() ) {
			NM_Google_Calendar::sync_booking( $booking );
		}
	}

	private function send_email( $booking ) {
		$admin = NM_Settings::get( 'admin_email' ) ?: get_option( 'admin_email' );
		$subject = 'تایید رزرو نوبت — ' . $booking->booking_code;
		$body = "رزرو شما تایید شد.\n\n"
			. 'کد: ' . $booking->booking_code . "\n"
			. 'تاریخ: ' . $booking->jalali_date . ' ساعت ' . substr( $booking->start_time, 0, 5 ) . "\n"
			. 'مبلغ: ' . NM_Settings::format_price( $booking->price ) . "\n\n"
			. "سایت مشاوره آنلاین\n" . home_url( '/' );

		if ( $booking->customer_email ) {
			wp_mail( $booking->customer_email, $subject, $body );
		}
		wp_mail( $admin, '[نوبت من] ' . $subject, $body . "\nمشتری: {$booking->customer_name} / {$booking->customer_phone}" );
	}
}
