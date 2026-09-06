<?php
defined( 'ABSPATH' ) || exit;

/**
 * گزارش تطبیق: خریدهای ووکامرس (زرین‌پال) + واریز شاپرک + کارمزد.
 */
class WAP_Zarinpal_Report {

	/**
	 * @return array{
	 *   filters:array,
	 *   summary:array,
	 *   orders:array<int,array>,
	 *   settles:array<int,array>,
	 *   error:string
	 * }
	 */
	public static function build( ?array $src = null ): array {
		$f = self::get_filters( $src );
		$out = array(
			'filters' => $f,
			'summary' => array(
				'wc_count'        => 0,
				'wc_gross'        => 0.0,
				'wc_fee'          => 0.0,
				'wc_net'          => 0.0,
				'settle_count'    => 0,
				'settle_total'    => 0.0,
				'diff_net_settle' => 0.0,
				'fee_source'      => 'formula',
			),
			'orders'  => array(),
			'settles' => array(),
			'error'   => '',
		);

		if ( ! class_exists( 'WooCommerce' ) ) {
			$out['error'] = 'ووکامرس فعال نیست.';
			return $out;
		}

		$orders_raw = self::get_zarinpal_orders( $f );
		$order_rows = array();
		$gross = 0.0;
		$fee_sum = 0.0;
		$net_sum = 0.0;
		$fee_source = 'formula';

		foreach ( $orders_raw as $order ) {
			$total = (float) $order->get_total();
			$fee_info = WAP_Zarinpal_Fee::resolve_fee( $total, false );
			$fee = (float) $fee_info['fee_toman'];
			$fee_type = $fee_info['fee_type'];
			$net = ( $fee_type === 'Payer' ) ? $total : WAP_Zarinpal_Fee::net_after_fee_toman( $total, (int) $fee );
			$fee_source = $fee_info['source'];

			$created = $order->get_date_created();
			$order_rows[] = array(
				'order_id'     => $order->get_id(),
				'order_number' => $order->get_order_number(),
				'date'         => $created ? $created->date_i18n( 'Y-m-d H:i' ) : '',
				'date_jalali'  => $created ? self::format_jalali( $created->getTimestamp() ) : '',
				'customer'     => trim( $order->get_formatted_billing_full_name() ),
				'status'       => $order->get_status(),
				'status_label' => wc_get_order_status_name( $order->get_status() ),
				'payment'      => $order->get_payment_method_title() ?: $order->get_payment_method(),
				'gross'        => $total,
				'fee'          => $fee,
				'fee_type'     => $fee_type,
				'net'          => $net,
				'transaction'  => (string) ( $order->get_transaction_id() ?: $order->get_meta( '_transaction_id' ) ),
			);
			$gross   += $total;
			$fee_sum += $fee;
			$net_sum += $net;
		}

		$settles = array();
		$settle_total = 0.0;
		$settle_err = '';
		$fetched = self::fetch_settles( $f );
		if ( is_wp_error( $fetched ) ) {
			$settle_err = $fetched->get_error_message();
		} else {
			foreach ( $fetched as $row ) {
				$amount_rial = (float) ( $row['amount'] ?? 0 );
				$amount_toman = $amount_rial >= 10 ? $amount_rial / 10 : $amount_rial;
				$settles[] = array(
					'id'            => (string) ( $row['id'] ?? '' ),
					'status'        => (string) ( $row['status'] ?? '' ),
					'amount_rial'   => $amount_rial,
					'amount'        => $amount_toman,
					'reference_id'  => (string) ( $row['reference_id'] ?? '' ),
					'reconciled_at' => (string) ( $row['reconciled_at'] ?? '' ),
					'payable_at'    => (string) ( $row['payable_at'] ?? '' ),
					'date_jalali'   => self::format_iso_jalali( (string) ( $row['reconciled_at'] ?? '' ) ),
				);
				$settle_total += $amount_toman;
			}
		}

		$out['orders']  = $order_rows;
		$out['settles'] = $settles;
		$out['error']   = $settle_err;
		$out['summary'] = array(
			'wc_count'        => count( $order_rows ),
			'wc_gross'        => $gross,
			'wc_fee'          => $fee_sum,
			'wc_net'          => $net_sum,
			'settle_count'    => count( $settles ),
			'settle_total'    => $settle_total,
			'diff_net_settle' => $settle_total - $net_sum,
			'fee_source'      => $fee_source,
		);
		return $out;
	}

	public static function get_filters( ?array $src = null ): array {
		if ( $src === null ) {
			$src = $_GET;
		}
		$base = class_exists( 'WAP_Data' ) ? WAP_Data::get_filters( $src ) : array(
			'date_from' => '',
			'date_to'   => '',
		);
		return array(
			'date_from' => $base['date_from'] ?? '',
			'date_to'   => $base['date_to'] ?? '',
			'only_paid' => ! isset( $src['only_paid'] ) || (string) $src['only_paid'] !== '0',
		);
	}

	/** @return array<int,\WC_Order> */
	public static function get_zarinpal_orders( array $f ): array {
		$orders = WAP_Data::get_orders( array(
			'date_from'    => $f['date_from'],
			'date_to'      => $f['date_to'],
			'order_status' => '',
			'period'       => 'day',
		) );
		$out = array();
		foreach ( $orders as $order ) {
			if ( ! empty( $f['only_paid'] ) && ! WAP_Data::is_paid_order( $order ) ) {
				continue;
			}
			$method = (string) $order->get_payment_method();
			if ( ! WAP_Payment_Notify::is_zarinpal_method( $method ) ) {
				continue;
			}
			$out[] = $order;
		}
		return $out;
	}

	/**
	 * @return array<int,array>|WP_Error
	 */
	public static function fetch_settles( array $f ) {
		if ( ! class_exists( 'WAP_Zarinpal_Reconcile' ) || ! class_exists( 'WAP_SMS' ) ) {
			return new WP_Error( 'wap_zp', 'ماژول تسویه زرین‌پال موجود نیست.' );
		}
		$token = trim( (string) WAP_SMS::get( 'zp_access_token', '' ) );
		$tid   = trim( (string) WAP_SMS::get( 'zp_terminal_id', '' ) );
		if ( $token === '' || $tid === '' ) {
			return new WP_Error( 'wap_zp_cfg', 'Access Token و Terminal ID را در «اطلاع‌رسانی پیامک» وارد کنید.' );
		}
		$items = WAP_Zarinpal_Reconcile::fetch_reconciles( $token, $tid );
		if ( is_wp_error( $items ) ) {
			return $items;
		}

		$ts_from = ! empty( $f['date_from'] ) ? WAP_Jalali::str_to_timestamp( $f['date_from'], false ) : 0;
		$ts_to   = ! empty( $f['date_to'] ) ? WAP_Jalali::str_to_timestamp( $f['date_to'], true ) : 0;

		$out = array();
		foreach ( $items as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$status = strtoupper( (string) ( $row['status'] ?? '' ) );
			if ( $status !== 'PAID' ) {
				continue;
			}
			$iso = (string) ( $row['reconciled_at'] ?? $row['payable_at'] ?? '' );
			$ts  = $iso !== '' ? strtotime( $iso ) : 0;
			if ( $ts_from && $ts && $ts < $ts_from ) {
				continue;
			}
			if ( $ts_to && $ts && $ts > $ts_to ) {
				continue;
			}
			$out[] = $row;
		}
		return $out;
	}

	private static function format_jalali( int $ts ): string {
		$g = getdate( $ts );
		list( $jy, $jm, $jd ) = WAP_Jalali::to_jalali( (int) $g['year'], (int) $g['mon'], (int) $g['mday'] );
		return sprintf( '%04d/%02d/%02d', $jy, $jm, $jd );
	}

	private static function format_iso_jalali( string $iso ): string {
		if ( $iso === '' ) {
			return '';
		}
		$ts = strtotime( $iso );
		return $ts ? self::format_jalali( $ts ) : $iso;
	}
}
