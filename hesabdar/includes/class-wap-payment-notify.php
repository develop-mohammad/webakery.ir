<?php
defined( 'ABSPATH' ) || exit;

/**
 * اطلاع‌رسانی پیامکی پس از پرداخت موفق (زرین‌پال / درگاه‌های مشابه).
 *
 * توجه: «واریز شاپرک به حساب بانکی» رویداد مستقیمی در وردپرس ندارد؛
 * این کلاس روی «پرداخت موفق سفارش» در ووکامرس (معمولاً همان لحظهٔ تأیید زرین‌پال) عمل می‌کند.
 */
class WAP_Payment_Notify {

	const META_SENT = '_wap_payment_sms_sent';

	public static function init(): void {
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'on_payment_complete' ), 20, 1 );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'on_status_paid' ), 20, 2 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_status_paid' ), 20, 2 );
		add_action( 'admin_post_wap_test_payment_sms', array( __CLASS__, 'handle_test_sms' ) );
	}

	public static function on_payment_complete( $order_id ): void {
		self::maybe_notify( absint( $order_id ), 'payment_complete' );
	}

	public static function on_status_paid( $order_id, $order = null ): void {
		self::maybe_notify( absint( $order_id ), 'status' );
	}

	public static function maybe_notify( int $order_id, string $source = '' ): void {
		if ( $order_id <= 0 || ! class_exists( 'WooCommerce' ) || ! class_exists( 'WAP_SMS' ) ) {
			return;
		}
		if ( ! (int) WAP_SMS::get( 'enabled' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		if ( $order->get_meta( self::META_SENT ) ) {
			return;
		}

		$method = (string) $order->get_payment_method();
		if ( (int) WAP_SMS::get( 'only_zarinpal', 1 ) && ! self::is_zarinpal_method( $method ) ) {
			return;
		}

		// فقط سفارش‌های واقعاً پرداخت‌شده / در جریان موفق
		$status = $order->get_status();
		if ( in_array( $status, array( 'pending', 'failed', 'cancelled', 'refunded', 'checkout-draft' ), true ) ) {
			return;
		}

		$recipients = WAP_SMS::recipient_list();
		if ( empty( $recipients ) ) {
			return;
		}

		$total = (float) $order->get_total();
		$vars  = array(
			'order_id' => (string) $order->get_order_number(),
			'total'    => number_format( $total ),
			'customer' => trim( $order->get_formatted_billing_full_name() ),
			'phone'    => (string) $order->get_billing_phone(),
			'payment'  => $order->get_payment_method_title() ?: $method,
			'status'   => wc_get_order_status_name( $status ),
			'source'   => $source,
		);
		$message = WAP_SMS::render_message( $vars );

		$ok_any = false;
		$errors = array();
		foreach ( $recipients as $to ) {
			$result = WAP_SMS::send( $to, $message );
			if ( is_wp_error( $result ) ) {
				$errors[] = $to . ': ' . $result->get_error_message();
				continue;
			}
			$ok_any = true;
		}

		if ( $ok_any ) {
			$order->update_meta_data( self::META_SENT, current_time( 'mysql' ) );
			$order->add_order_note( 'Hesabdar: پیامک اطلاع‌رسانی پرداخت برای حسابدار(ها) ارسال شد.' );
			$order->save();
		} elseif ( $errors ) {
			$order->add_order_note( 'Hesabdar: خطای پیامک پرداخت — ' . implode( ' | ', $errors ) );
			$order->save();
			error_log( 'Hesabdar payment SMS errors: ' . implode( ' | ', $errors ) );
		}
	}

	public static function is_zarinpal_method( string $method ): bool {
		$method = strtolower( $method );
		return (bool) preg_match( '/zarin|zpal|زرین/', $method );
	}

	public static function handle_test_sms(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}
		check_admin_referer( 'wap_test_payment_sms' );

		$to = WAP_SMS::normalize_phone( (string) ( $_POST['wap_test_phone'] ?? '' ) );
		if ( $to === '' ) {
			$list = WAP_SMS::recipient_list();
			$to   = $list[0] ?? '';
		}
		$redirect = add_query_arg( array( 'page' => 'wap-payment-sms' ), admin_url( 'admin.php' ) );
		if ( $to === '' ) {
			wp_safe_redirect( add_query_arg( 'wap_sms_msg', 'no_phone', $redirect ) );
			exit;
		}

		$msg = WAP_SMS::render_message( array(
			'order_id' => 'TEST',
			'total'    => number_format( 150000 ),
			'customer' => 'تست',
			'phone'    => $to,
			'payment'  => 'زرین‌پال',
			'status'   => 'در حال انجام',
			'source'   => 'test',
		) );
		$result = WAP_SMS::send( $to, $msg );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'wap_sms_msg', rawurlencode( $result->get_error_message() ), $redirect ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( 'wap_sms_msg', 'ok', $redirect ) );
		exit;
	}
}
