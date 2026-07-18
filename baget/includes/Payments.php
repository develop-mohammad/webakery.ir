<?php
namespace WCCP;

defined( 'ABSPATH' ) || exit;

/**
 * پرداخت لینک مستقیم — زرین‌پال (+ fallback ووکامرس).
 */
class Payments {

	const OPTION = 'wccp_payment_settings';

	/** @return array{zarinpal_merchant:string,sandbox:int} */
	public static function settings() {
		$raw = get_option( self::OPTION, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		return array(
			'zarinpal_merchant' => self::normalize_merchant( $raw['zarinpal_merchant'] ?? '' ),
			'sandbox'           => ! empty( $raw['sandbox'] ) ? 1 : 0,
		);
	}

	/** @param array $data */
	public static function save_settings( array $data ) {
		$clean = array(
			'zarinpal_merchant' => self::normalize_merchant( $data['zarinpal_merchant'] ?? '' ),
			'sandbox'           => ! empty( $data['sandbox'] ) ? 1 : 0,
		);
		update_option( self::OPTION, $clean, false );
		return $clean;
	}

	public static function normalize_merchant( $merchant ) {
		$merchant = strtolower( trim( (string) $merchant ) );
		$merchant = preg_replace( '/\s+/', '', $merchant );
		$merchant = str_replace( array( '{', '}', '"' ), '', (string) $merchant );
		if ( preg_match( '/^[0-9a-f]{32}$/', $merchant ) ) {
			$merchant = substr( $merchant, 0, 8 ) . '-' .
				substr( $merchant, 8, 4 ) . '-' .
				substr( $merchant, 12, 4 ) . '-' .
				substr( $merchant, 16, 4 ) . '-' .
				substr( $merchant, 20, 12 );
		}
		return $merchant;
	}

	public static function looks_like_merchant( $merchant ) {
		$merchant = self::normalize_merchant( $merchant );
		return (bool) preg_match(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
			$merchant
		);
	}

	public static function merchant() {
		$s = self::settings();
		return $s['zarinpal_merchant'];
	}

	public static function zarinpal_enabled() {
		return self::looks_like_merchant( self::merchant() );
	}

	/** مبلغ به ریال برای API زرین‌پال (قیمت پلاگین به تومان است). */
	public static function amount_rial( $toman ) {
		return max( 0, (int) $toman ) * 10;
	}

	/**
	 * @param array $payload
	 * @return array
	 */
	public static function zarinpal_api( $url, array $payload ) {
		$resp = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return array( 'errors' => array( 'message' => $resp->get_error_message() ) );
		}
		$decoded = json_decode( wp_remote_retrieve_body( $resp ), true );
		return is_array( $decoded ) ? $decoded : array( 'errors' => array( 'message' => 'پاسخ نامعتبر درگاه' ) );
	}

	public static function request_url() {
		$s = self::settings();
		return ! empty( $s['sandbox'] )
			? 'https://sandbox.zarinpal.com/pg/v4/payment/request.json'
			: 'https://api.zarinpal.com/pg/v4/payment/request.json';
	}

	public static function verify_url() {
		$s = self::settings();
		return ! empty( $s['sandbox'] )
			? 'https://sandbox.zarinpal.com/pg/v4/payment/verify.json'
			: 'https://api.zarinpal.com/pg/v4/payment/verify.json';
	}

	public static function startpay_url( $authority ) {
		$s = self::settings();
		$base = ! empty( $s['sandbox'] )
			? 'https://sandbox.zarinpal.com/pg/StartPay/'
			: 'https://www.zarinpal.com/pg/StartPay/';
		return $base . rawurlencode( (string) $authority );
	}

	/**
	 * ذخیره پرداخت معلق.
	 *
	 * @param array $data
	 * @return string token
	 */
	public static function store_pending( array $data ) {
		$token = 'wccp_' . strtolower( wp_generate_password( 16, false, false ) );
		set_transient( 'wccp_pay_' . $token, $data, DAY_IN_SECONDS );
		return $token;
	}

	/** @return array|null */
	public static function get_pending( $token ) {
		$token = sanitize_key( (string) $token );
		if ( ! $token ) {
			return null;
		}
		$data = get_transient( 'wccp_pay_' . $token );
		return is_array( $data ) ? $data : null;
	}

	public static function bind_authority( $authority, $token ) {
		$authority = sanitize_text_field( (string) $authority );
		if ( ! $authority ) {
			return;
		}
		update_option( 'wccp_zarinpal_auth_' . $authority, sanitize_key( $token ), false );
	}

	/** @return string */
	public static function token_by_authority( $authority ) {
		$authority = sanitize_text_field( (string) $authority );
		if ( ! $authority ) {
			return '';
		}
		return sanitize_key( (string) get_option( 'wccp_zarinpal_auth_' . $authority, '' ) );
	}

	public static function clear_authority( $authority ) {
		$authority = sanitize_text_field( (string) $authority );
		if ( $authority ) {
			delete_option( 'wccp_zarinpal_auth_' . $authority );
		}
	}

	/**
	 * درخواست زرین‌پال و برگرداندن URL درگاه یا WP_Error.
	 *
	 * @param array  $pending
	 * @param string $token
	 * @return string|\WP_Error
	 */
	public static function create_zarinpal_url( array $pending, $token ) {
		if ( ! self::zarinpal_enabled() ) {
			return new \WP_Error( 'merchant', 'مرچنت‌کد زرین‌پال تنظیم نشده یا نامعتبر است.' );
		}
		$amount = self::amount_rial( $pending['amount'] ?? 0 );
		if ( $amount < 10000 ) {
			return new \WP_Error( 'amount', 'مبلغ پرداخت باید حداقل ۱٬۰۰۰ تومان باشد.' );
		}

		$callback = admin_url( 'admin-post.php?action=wccp_pay_verify' );
		$meta     = array();
		if ( ! empty( $pending['phone'] ) ) {
			$meta['mobile'] = (string) $pending['phone'];
		}
		if ( ! empty( $pending['email'] ) ) {
			$meta['email'] = (string) $pending['email'];
		}

		$payload = array(
			'merchant_id'  => self::merchant(),
			'amount'       => $amount,
			'callback_url' => $callback,
			'description'  => (string) ( $pending['description'] ?? 'پرداخت لینک مستقیم Baget' ),
			'metadata'     => $meta,
		);

		$resp = self::zarinpal_api( self::request_url(), $payload );
		$code = (int) ( $resp['data']['code'] ?? 0 );
		$auth = (string) ( $resp['data']['authority'] ?? '' );
		if ( ( 100 !== $code && 101 !== $code ) || ! $auth ) {
			$msg = $resp['errors']['message'] ?? ( $resp['data']['message'] ?? 'خطای ناشناخته درگاه' );
			return new \WP_Error( 'gateway', 'زرین‌پال: ' . (string) $msg );
		}

		self::bind_authority( $auth, $token );
		$pending['authority'] = $auth;
		set_transient( 'wccp_pay_' . $token, $pending, DAY_IN_SECONDS );

		return self::startpay_url( $auth );
	}

	/**
	 * ساخت سفارش ووکامرس و لینک پرداخت (fallback).
	 *
	 * @return string|\WP_Error
	 */
	public static function create_wc_pay_url( array $pending ) {
		if ( ! function_exists( 'wc_create_order' ) || ! class_exists( 'WooCommerce' ) ) {
			return new \WP_Error( 'wc', 'ووکامرس فعال نیست.' );
		}

		try {
			$order = wc_create_order();
			if ( is_wp_error( $order ) || ! $order ) {
				return new \WP_Error( 'wc', 'ساخت سفارش ووکامرس ناموفق بود.' );
			}

			$item = new \WC_Order_Item_Fee();
			$item->set_name( (string) ( $pending['description'] ?? 'پرداخت لینک مستقیم' ) );
			$item->set_total( (float) ( $pending['amount'] ?? 0 ) );
			$order->add_item( $item );

			$order->set_billing_first_name( (string) ( $pending['first_name'] ?? '' ) );
			$order->set_billing_last_name( (string) ( $pending['last_name'] ?? '' ) );
			$order->set_billing_email( (string) ( $pending['email'] ?? '' ) );
			$order->set_billing_phone( (string) ( $pending['phone'] ?? '' ) );
			$order->set_created_via( 'baget-pay-link' );
			$order->update_meta_data( '_wccp_pay_token', (string) ( $pending['token'] ?? '' ) );
			$order->update_meta_data( '_wccp_product_id', (int) ( $pending['product_id'] ?? 0 ) );
			foreach ( (array) ( $pending['fields'] ?? array() ) as $k => $v ) {
				$order->update_meta_data( (string) $k, is_array( $v ) ? implode( '، ', $v ) : (string) $v );
			}
			$order->calculate_totals();
			$order->save();

			$url = $order->get_checkout_payment_url( false );
			return $url ? $url : new \WP_Error( 'wc', 'لینک پرداخت ووکامرس ساخته نشد.' );
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'wc', $e->getMessage() );
		}
	}

	public static function redirect_external( $url ) {
		$url = esc_url_raw( (string) $url );
		if ( ! $url ) {
			return;
		}
		$host      = wp_parse_url( $url, PHP_URL_HOST );
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( $host && $home_host && strtolower( (string) $host ) === strtolower( (string) $home_host ) ) {
			wp_safe_redirect( $url );
		} else {
			wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		}
		exit;
	}

	/** صفحه خطای خوانا به‌جای صفحه خالی admin-post */
	public static function die_error( $message, $back_url = '' ) {
		$back_url = $back_url ? $back_url : home_url( '/' );
		nocache_headers();
		status_header( 200 );
		echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
		echo '<title>خطای پرداخت</title><style>
			body{font-family:Tahoma,Arial,sans-serif;background:#f8fafc;margin:0;padding:24px;color:#0f172a}
			.card{max-width:560px;margin:40px auto;background:#fff;border:1px solid #fecaca;border-radius:16px;padding:28px;box-shadow:0 10px 30px rgba(15,23,42,.06)}
			h1{font-size:18px;margin:0 0 12px;color:#b91c1c}
			.btn{display:inline-block;background:#6d28d9;color:#fff;text-decoration:none;padding:10px 18px;border-radius:10px;margin-top:16px}
			.hint{margin-top:14px;padding:12px;background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;font-size:13px;color:#9a3412}
		</style></head><body><div class="card">';
		echo '<h1>پرداخت انجام نشد</h1>';
		echo '<p>' . wp_kses_post( $message ) . '</p>';
		if ( current_user_can( 'manage_options' ) ) {
			echo '<div class="hint">مدیر: در <a href="' . esc_url( admin_url( 'admin.php?page=wccp&tab=payments' ) ) . '">Baget ← پرداخت</a> مرچنت‌کد ۳۶ کاراکتری زرین‌پال را وارد کنید. اگر ووکامرس و درگاه فعال دارید، به‌عنوان جایگزین استفاده می‌شود.</div>';
		}
		echo '<a class="btn" href="' . esc_url( $back_url ) . '">بازگشت</a>';
		echo '</div></body></html>';
		exit;
	}

	public static function die_success( array $pending, $ref_id = '' ) {
		nocache_headers();
		status_header( 200 );
		echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
		echo '<title>پرداخت موفق</title><style>
			body{font-family:Tahoma,Arial,sans-serif;background:#ecfdf5;margin:0;padding:24px;color:#0f172a}
			.card{max-width:560px;margin:40px auto;background:#fff;border:1px solid #bbf7d0;border-radius:16px;padding:28px;box-shadow:0 10px 30px rgba(15,23,42,.06)}
			h1{font-size:20px;margin:0 0 12px;color:#15803d}
			.meta{color:#64748b;font-size:13px;margin-top:10px}
			.btn{display:inline-block;background:#16a34a;color:#fff;text-decoration:none;padding:10px 18px;border-radius:10px;margin-top:18px}
		</style></head><body><div class="card">';
		echo '<h1>✓ پرداخت با موفقیت انجام شد</h1>';
		echo '<p>' . esc_html( (string) ( $pending['description'] ?? 'سفارش' ) ) . '</p>';
		echo '<p><strong>مبلغ:</strong> ' . esc_html( number_format_i18n( (int) ( $pending['amount'] ?? 0 ) ) ) . ' تومان</p>';
		if ( $ref_id ) {
			echo '<p class="meta">کد پیگیری: <code dir="ltr">' . esc_html( (string) $ref_id ) . '</code></p>';
		}
		echo '<a class="btn" href="' . esc_url( home_url( '/' ) ) . '">بازگشت به سایت</a>';
		echo '</div></body></html>';
		exit;
	}
}
