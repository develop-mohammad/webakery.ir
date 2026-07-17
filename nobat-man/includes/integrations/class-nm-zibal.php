<?php
defined( 'ABSPATH' ) || exit;

/**
 * پرداخت زیبال برای رزرو نوبت.
 */
class NM_Zibal {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_post_nm_zibal_start', array( __CLASS__, 'start' ) );
		add_action( 'admin_post_nopriv_nm_zibal_start', array( __CLASS__, 'start' ) );
		add_action( 'admin_post_nm_zibal_cb', array( __CLASS__, 'callback' ) );
		add_action( 'admin_post_nopriv_nm_zibal_cb', array( __CLASS__, 'callback' ) );
	}

	public static function merchant() {
		return trim( (string) NM_Settings::get( 'zibal_merchant', '' ) );
	}

	/** آیا زیبال واقعاً قابل استفاده است؟ (مرچنت شبیه UUID معتبر) */
	public static function enabled() {
		$m = self::merchant();
		if ( '' === $m || in_array( strtolower( $m ), array( 'zibal', 'test', 'xxx' ), true ) ) {
			return false;
		}
		// مرچنت واقعی زیبال معمولاً UUID است؛ مقدار کوتاه/نامعتبر را فعال نکن
		return (bool) preg_match( '/^[0-9a-fA-F\-]{8,64}$/', $m );
	}

	/** ساخت لینک شروع پرداخت برای یک رزرو */
	public static function pay_url_for_booking( $booking ) {
		if ( ! $booking || empty( $booking->id ) ) {
			return '';
		}
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=nm_zibal_start&booking_id=' . (int) $booking->id ),
			'nm_zibal_start_' . (int) $booking->id
		);
	}

	public static function start() {
		$booking_id = (int) ( $_GET['booking_id'] ?? 0 );
		if ( ! $booking_id || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'nm_zibal_start_' . $booking_id ) ) {
			wp_die( 'درخواست نامعتبر است.' );
		}

		$booking = NM_Booking::get( $booking_id );
		if ( ! $booking ) {
			wp_die( 'رزرو یافت نشد.' );
		}
		if ( 'paid' === $booking->payment_status ) {
			wp_safe_redirect( add_query_arg( 'nm_code', $booking->booking_code, home_url( '/' ) ) );
			exit;
		}

		if ( ! self::enabled() ) {
			$fallback = NM_Payments::fallback_url( $booking, 'zibal' );
			if ( $fallback ) {
				wp_safe_redirect( $fallback );
				exit;
			}
			wp_die( 'مرچنت‌کد زیبال معتبر نیست. مرچنت خودتان را از پنل زیبال بگذارید، یا زرین‌پال/ووکامرس را انتخاب کنید.' );
		}

		$amount = (int) $booking->price * 10; // تومان → ریال
		if ( $amount < 1000 ) {
			wp_die( 'مبلغ نامعتبر است.' );
		}

		$callback = admin_url( 'admin-post.php?action=nm_zibal_cb' );
		$resp     = self::api(
			'https://gateway.zibal.ir/v1/request',
			array(
				'merchant'    => self::merchant(),
				'amount'      => $amount,
				'callbackUrl' => $callback,
				'description' => 'رزرو نوبت ' . $booking->booking_code,
				'orderId'     => (string) $booking->id,
			)
		);

		if ( empty( $resp['result'] ) || 100 !== (int) $resp['result'] || empty( $resp['trackId'] ) ) {
			$msg = (string) ( $resp['message'] ?? (string) ( $resp['result'] ?? '' ) );
			// invalid merchant و مشابه → سراغ درگاه جایگزین
			if ( false !== stripos( $msg, 'merchant' ) || false !== stripos( $msg, 'مرچنت' ) ) {
				$fallback = NM_Payments::fallback_url( $booking, 'zibal' );
				if ( $fallback ) {
					wp_safe_redirect( $fallback );
					exit;
				}
			}
			wp_die(
				'خطا در اتصال به درگاه زیبال: ' . esc_html( $msg )
				. '<br><br>اگر زرین‌پال دارید: در تنظیمات نوبت من، درگاه را «زرین‌پال» یا «ووکامرس» بگذارید و مرچنت زرین‌پال را وارد کنید.'
			);
		}

		$track = (string) $resp['trackId'];
		update_option( 'nm_zibal_track_' . $track, (int) $booking->id, false );

		wp_redirect( 'https://gateway.zibal.ir/start/' . rawurlencode( $track ) );
		exit;
	}

	public static function callback() {
		$track = isset( $_GET['trackId'] ) ? sanitize_text_field( wp_unslash( $_GET['trackId'] ) ) : '';
		$success = isset( $_GET['success'] ) ? (int) $_GET['success'] : 0;

		if ( ! $track ) {
			wp_die( 'trackId نامعتبر' );
		}

		$booking_id = (int) get_option( 'nm_zibal_track_' . $track, 0 );
		if ( ! $booking_id ) {
			wp_die( 'رزرو مرتبط یافت نشد.' );
		}

		$booking = NM_Booking::get( $booking_id );
		if ( ! $booking ) {
			wp_die( 'رزرو یافت نشد.' );
		}

		if ( 1 !== $success ) {
			NM_Booking::update_status( $booking_id, 'pending', 'failed' );
			wp_die( 'پرداخت لغو شد یا ناموفق بود. <a href="' . esc_url( home_url( '/' ) ) . '">بازگشت</a>' );
		}

		$verify = self::api(
			'https://gateway.zibal.ir/v1/verify',
			array(
				'merchant' => self::merchant(),
				'trackId'  => (int) $track,
			)
		);

		$code = (int) ( $verify['result'] ?? 0 );
		if ( 100 !== $code && 201 !== $code ) {
			wp_die( 'تأیید پرداخت ناموفق. کد: ' . esc_html( (string) $code ) );
		}

		NM_Booking::mark_paid( $booking_id, 0 );
		delete_option( 'nm_zibal_track_' . $track );

		$booking = NM_Booking::get( $booking_id );
		$html    = NM_Booking::thank_you_html( $booking );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
		echo '<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">';
		echo '<title>پرداخت موفق</title><style>body{font-family:Vazirmatn,Tahoma,sans-serif;background:#f5f3ff;margin:0;padding:24px}.card{max-width:640px;margin:40px auto;background:#fff;border-radius:20px;padding:28px;box-shadow:0 16px 40px rgba(15,23,42,.08)}</style></head><body><div class="card">';
		echo '<h1 style="color:#16a34a">✓ پرداخت موفق</h1>';
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<p><a href="' . esc_url( home_url( '/' ) ) . '">بازگشت به سایت</a></p></div></body></html>';
		exit;
	}

	private static function api( $url, array $data ) {
		$resp = wp_remote_post(
			$url,
			array(
				'timeout' => 25,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $data ),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return array( 'result' => -1, 'message' => $resp->get_error_message() );
		}
		$decoded = json_decode( wp_remote_retrieve_body( $resp ), true );
		return is_array( $decoded ) ? $decoded : array( 'result' => -1, 'message' => 'پاسخ نامعتبر درگاه' );
	}
}
