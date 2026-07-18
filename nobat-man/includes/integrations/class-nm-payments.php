<?php
defined( 'ABSPATH' ) || exit;

/**
 * انتخاب و اولویت‌بندی درگاه پرداخت رزرو.
 */
class NM_Payments {

	/**
	 * لینک پرداخت برای رزرو بر اساس تنظیمات.
	 */
	public static function pay_url_for_booking( $booking ) {
		if ( ! $booking || empty( $booking->id ) ) {
			return '';
		}

		// اصلاح خودکار مرچنت اشتباه (UUID زرین‌پال در فیلد زیبال)
		if ( class_exists( 'NM_Settings' ) && method_exists( 'NM_Settings', 'heal_payment_merchants' ) ) {
			NM_Settings::heal_payment_merchants();
		}

		$gw = sanitize_key( (string) NM_Settings::get( 'payment_gateway', 'auto' ) );

		if ( 'zarinpal' === $gw ) {
			if ( NM_Zarinpal::enabled() ) {
				return NM_Zarinpal::pay_url_for_booking( $booking );
			}
			return self::fallback_url( $booking, 'zarinpal' );
		}

		if ( 'zibal' === $gw ) {
			if ( NM_Zibal::enabled() ) {
				return NM_Zibal::pay_url_for_booking( $booking );
			}
			return self::fallback_url( $booking, 'zibal' );
		}

		if ( 'woocommerce' === $gw ) {
			if ( class_exists( 'WooCommerce' ) ) {
				$url = NM_WooCommerce::create_checkout_for_booking( $booking );
				if ( $url ) {
					return $url;
				}
			}
			return self::fallback_url( $booking, 'woocommerce' );
		}

		// auto:
		// 1) زرین‌پال مستقیم اگر مرچنت معتبر
		// 2) ووکامرس (زرین‌پال/زیبال نصب‌شده روی ووکامرس)
		// 3) زیبال مستقیم اگر مرچنت معتبر
		if ( NM_Zarinpal::enabled() ) {
			return NM_Zarinpal::pay_url_for_booking( $booking );
		}
		if ( class_exists( 'WooCommerce' ) ) {
			$url = NM_WooCommerce::create_checkout_for_booking( $booking );
			if ( $url ) {
				return $url;
			}
		}
		if ( NM_Zibal::enabled() ) {
			return NM_Zibal::pay_url_for_booking( $booking );
		}
		return '';
	}

	/**
	 * وقتی درگاه انتخابی کار نکرد، جایگزین منطقی بده.
	 */
	public static function fallback_url( $booking, $failed = '' ) {
		$failed = sanitize_key( (string) $failed );

		if ( 'zarinpal' !== $failed && NM_Zarinpal::enabled() ) {
			return NM_Zarinpal::pay_url_for_booking( $booking );
		}
		if ( 'woocommerce' !== $failed && class_exists( 'WooCommerce' ) ) {
			$url = NM_WooCommerce::create_checkout_for_booking( $booking );
			if ( $url ) {
				return $url;
			}
		}
		if ( 'zibal' !== $failed && NM_Zibal::enabled() ) {
			return NM_Zibal::pay_url_for_booking( $booking );
		}
		return '';
	}

	/**
	 * ریدایرکت به لینک پرداخت جایگزین؛ در صورت نبودن، false.
	 */
	public static function redirect_fallback( $booking, $failed = '' ) {
		$url = self::fallback_url( $booking, $failed );
		if ( ! $url ) {
			return false;
		}
		// لینک‌های داخلی (admin-post / checkout)
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * آیا رشته شبیه مرچنت‌کد زرین‌پال (UUID) است؟
	 */
	public static function looks_like_zarinpal_merchant( $merchant ) {
		$merchant = trim( (string) $merchant );
		return (bool) preg_match(
			'/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/',
			$merchant
		);
	}

	/**
	 * صفحه خطای پرداخت با راهنما و دکمه تلاش مجدد.
	 */
	public static function die_payment_error( $message, $booking = null ) {
		$retry = '';
		if ( $booking && ! empty( $booking->id ) ) {
			$url = self::pay_url_for_booking( $booking );
			if ( $url ) {
				$retry = '<p style="margin-top:18px"><a class="btn" href="' . esc_url( $url ) . '">تلاش مجدد پرداخت</a></p>';
			}
		}
		$settings_hint = '';
		if ( current_user_can( 'manage_options' ) ) {
			$settings_hint = '<p style="margin-top:12px;color:#64748b;font-size:13px">مدیر: در «نوبت من ← تنظیمات ← پرداخت» مرچنت زرین‌پال را در فیلد مخصوص زرین‌پال بگذارید و فیلد زیبال را خالی کنید، یا درگاه را «ووکامرس» انتخاب کنید.</p>';
		}

		nocache_headers();
		status_header( 400 );
		echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
		echo '<title>خطای پرداخت</title><style>
			body{font-family:Tahoma,Arial,sans-serif;background:#f8fafc;margin:0;padding:24px;color:#0f172a}
			.card{max-width:560px;margin:40px auto;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:28px;box-shadow:0 10px 30px rgba(15,23,42,.06)}
			h1{font-size:18px;margin:0 0 12px;color:#b91c1c}
			.btn{display:inline-block;background:#6d28d9;color:#fff;text-decoration:none;padding:10px 18px;border-radius:10px}
			a.home{color:#6d28d9}
		</style></head><body><div class="card">';
		echo '<h1>پرداخت انجام نشد</h1>';
		echo '<p>' . wp_kses_post( $message ) . '</p>';
		echo $retry; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $settings_hint; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<p style="margin-top:16px"><a class="home" href="' . esc_url( home_url( '/' ) ) . '">بازگشت به سایت</a></p>';
		echo '</div></body></html>';
		exit;
	}
}
