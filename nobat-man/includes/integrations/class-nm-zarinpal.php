<?php
defined( 'ABSPATH' ) || exit;

/**
 * پرداخت مستقیم زرین‌پال (API v4) برای رزرو نوبت.
 */
class NM_Zarinpal {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_post_nm_zarinpal_start', array( __CLASS__, 'start' ) );
		add_action( 'admin_post_nopriv_nm_zarinpal_start', array( __CLASS__, 'start' ) );
		add_action( 'admin_post_nm_zarinpal_cb', array( __CLASS__, 'callback' ) );
		add_action( 'admin_post_nopriv_nm_zarinpal_cb', array( __CLASS__, 'callback' ) );
	}

	public static function merchant() {
		return trim( (string) NM_Settings::get( 'zarinpal_merchant', '' ) );
	}

	/** مرچنت زرین‌پال معمولاً UUID ۳۶ کاراکتری است */
	public static function enabled() {
		$m = self::merchant();
		return (bool) preg_match( '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $m );
	}

	public static function pay_url_for_booking( $booking ) {
		if ( ! $booking || empty( $booking->id ) ) {
			return '';
		}
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=nm_zarinpal_start&booking_id=' . (int) $booking->id ),
			'nm_zarinpal_start_' . (int) $booking->id
		);
	}

	public static function start() {
		$booking_id = (int) ( $_GET['booking_id'] ?? 0 );
		if ( ! $booking_id || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'nm_zarinpal_start_' . $booking_id ) ) {
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
			$fallback = NM_Payments::fallback_url( $booking, 'zarinpal' );
			if ( $fallback ) {
				wp_safe_redirect( $fallback );
				exit;
			}
			wp_die( 'مرچنت‌کد زرین‌پال معتبر نیست. از تنظیمات نوبت من وارد کنید یا درگاه ووکامرس را انتخاب کنید.' );
		}

		$amount = (int) $booking->price * 10; // تومان → ریال
		if ( $amount < 10000 ) {
			wp_die( 'حداقل مبلغ پرداخت زرین‌پال ۱۰٬۰۰۰ ریال است.' );
		}

		$callback = admin_url( 'admin-post.php?action=nm_zarinpal_cb' );
		$payload  = array(
			'merchant_id'  => self::merchant(),
			'amount'       => $amount,
			'callback_url' => $callback,
			'description'  => 'رزرو نوبت ' . $booking->booking_code,
			'metadata'     => array(
				'mobile' => (string) ( $booking->customer_phone ?? '' ),
				'email'  => (string) ( $booking->customer_email ?? '' ),
			),
		);

		$resp = self::api( 'https://api.zarinpal.com/pg/v4/payment/request.json', $payload );
		$code = (int) ( $resp['data']['code'] ?? 0 );
		$auth = (string) ( $resp['data']['authority'] ?? '' );

		if ( 100 !== $code || ! $auth ) {
			$msg = $resp['errors']['message'] ?? ( $resp['data']['message'] ?? 'خطای ناشناخته' );
			$fallback = NM_Payments::fallback_url( $booking, 'zarinpal' );
			if ( $fallback ) {
				wp_safe_redirect( $fallback );
				exit;
			}
			wp_die( 'خطا در اتصال به زرین‌پال: ' . esc_html( (string) $msg ) );
		}

		update_option( 'nm_zarinpal_auth_' . $auth, (int) $booking->id, false );
		wp_redirect( 'https://www.zarinpal.com/pg/StartPay/' . rawurlencode( $auth ) );
		exit;
	}

	public static function callback() {
		$authority = isset( $_GET['Authority'] ) ? sanitize_text_field( wp_unslash( $_GET['Authority'] ) ) : '';
		$status    = isset( $_GET['Status'] ) ? sanitize_text_field( wp_unslash( $_GET['Status'] ) ) : '';

		if ( ! $authority ) {
			wp_die( 'Authority نامعتبر' );
		}

		$booking_id = (int) get_option( 'nm_zarinpal_auth_' . $authority, 0 );
		if ( ! $booking_id ) {
			wp_die( 'رزرو مرتبط یافت نشد.' );
		}

		$booking = NM_Booking::get( $booking_id );
		if ( ! $booking ) {
			wp_die( 'رزرو یافت نشد.' );
		}

		if ( 'OK' !== strtoupper( $status ) ) {
			NM_Booking::update_status( $booking_id, 'pending', 'failed' );
			wp_die( 'پرداخت لغو شد یا ناموفق بود. <a href="' . esc_url( home_url( '/' ) ) . '">بازگشت</a>' );
		}

		$amount = (int) $booking->price * 10;
		$verify = self::api(
			'https://api.zarinpal.com/pg/v4/payment/verify.json',
			array(
				'merchant_id' => self::merchant(),
				'amount'      => $amount,
				'authority'   => $authority,
			)
		);

		$code = (int) ( $verify['data']['code'] ?? 0 );
		if ( 100 !== $code && 101 !== $code ) {
			$msg = $verify['errors']['message'] ?? (string) $code;
			wp_die( 'تأیید پرداخت زرین‌پال ناموفق: ' . esc_html( (string) $msg ) );
		}

		NM_Booking::mark_paid( $booking_id, 0 );
		delete_option( 'nm_zarinpal_auth_' . $authority );

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
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode( $data ),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return array( 'errors' => array( 'message' => $resp->get_error_message() ) );
		}
		$decoded = json_decode( wp_remote_retrieve_body( $resp ), true );
		return is_array( $decoded ) ? $decoded : array( 'errors' => array( 'message' => 'پاسخ نامعتبر درگاه' ) );
	}
}
