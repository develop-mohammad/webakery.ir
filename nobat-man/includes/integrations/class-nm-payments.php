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
				return NM_WooCommerce::create_checkout_for_booking( $booking );
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
			return NM_WooCommerce::create_checkout_for_booking( $booking );
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
}
