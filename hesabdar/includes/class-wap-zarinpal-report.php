<?php
defined( 'ABSPATH' ) || exit;

/**
 * گزارش تطبیق: خریدهای ووکامرس (زرین‌پال) + واریز شاپرک + کارمزد.
 *
 * واریز شاپرک دقیقاً از API Reconciliation زرین‌پال خوانده می‌شود
 * (همان فیلدهای پنل: id, amount ریال, reference_id, reconciled_at, status).
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
				'wc_count'          => 0,
				'wc_gross'          => 0.0,
				'wc_fee'            => 0.0,
				'wc_net'            => 0.0,
				'settle_count'      => 0,
				'settle_total'      => 0.0,
				'settle_total_rial' => 0.0,
				'diff_net_settle'   => 0.0,
				'fee_source'        => 'formula',
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
		$settle_rial  = 0.0;
		$settle_paid_count = 0;
		$settle_pending_count = 0;
		$settle_err = '';
		$fetched = self::fetch_settles( $f );
		if ( is_wp_error( $fetched ) ) {
			$settle_err = $fetched->get_error_message();
		} else {
			foreach ( $fetched as $row ) {
				$amount_rial = (float) ( $row['amount'] ?? 0 );
				$amount_toman = $amount_rial / 10;
				$status = (string) ( $row['status'] ?? '' );
				$status_u = strtoupper( $status );
				$settles[] = array(
					'id'               => (string) ( $row['id'] ?? '' ),
					'status'           => $status,
					'status_label'     => self::status_label( $status ),
					'amount_rial'      => $amount_rial,
					'amount'           => $amount_toman,
					'reference_id'     => (string) ( $row['reference_id'] ?? '' ),
					'reconciled_at'    => (string) ( $row['reconciled_at'] ?? '' ),
					'payable_at'       => (string) ( $row['payable_at'] ?? '' ),
					'date_jalali'      => self::format_iso_jalali( (string) ( $row['reconciled_at'] ?? '' ) ),
					'payable_jalali'   => self::format_iso_jalali( (string) ( $row['payable_at'] ?? '' ) ),
					'payable_display'  => self::format_iso_display( (string) ( $row['payable_at'] ?? '' ) ),
					'reconciled_display' => self::format_iso_display( (string) ( $row['reconciled_at'] ?? '' ) ),
				);
				if ( $status_u === 'PAID' ) {
					$settle_total += $amount_toman;
					$settle_rial  += $amount_rial;
					$settle_paid_count++;
				} elseif ( $status_u === 'IN_PROGRESS' ) {
					$settle_pending_count++;
				}
			}
			usort( $settles, function( $a, $b ) {
				$ka = (string) ( $a['payable_at'] ?: $a['reconciled_at'] );
				$kb = (string) ( $b['payable_at'] ?: $b['reconciled_at'] );
				return strcmp( $kb, $ka );
			} );
		}

		$out['orders']  = $order_rows;
		$out['settles'] = $settles;
		$out['settles_paid'] = array_values( array_filter( $settles, function( $r ) {
			return strtoupper( (string) ( $r['status'] ?? '' ) ) === 'PAID';
		} ) );
		$out['settles_pending'] = array_values( array_filter( $settles, function( $r ) {
			return strtoupper( (string) ( $r['status'] ?? '' ) ) === 'IN_PROGRESS';
		} ) );
		$out['error']   = $settle_err;
		$out['summary'] = array(
			'wc_count'             => count( $order_rows ),
			'wc_gross'             => $gross,
			'wc_fee'               => $fee_sum,
			'wc_net'               => $net_sum,
			'settle_count'         => $settle_paid_count,
			'settle_pending_count' => $settle_pending_count,
			'settle_total'         => $settle_total,
			'settle_total_rial'    => $settle_rial,
			'diff_net_settle'      => $settle_total - $net_sum,
			'fee_source'           => $fee_source,
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
			$title  = (string) $order->get_payment_method_title();
			$check  = $method . ' ' . $title;
			if ( class_exists( 'WAP_Gateway' ) ) {
				if ( ! WAP_Gateway::is_family( $check, WAP_Gateway::FAMILY_ZARINPAL ) ) {
					continue;
				}
			} elseif ( ! preg_match( '/zarin|zpal|زرین/ui', $check ) ) {
				continue;
			}
			$out[] = $order;
		}
		return $out;
	}

	/**
	 * تبدیل بازه شمسی فیلتر به Y-m-d میلادی برای API زرین‌پال.
	 *
	 * @return array{0:string,1:string} [from, to]
	 */
	public static function gregorian_range( array $f ): array {
		$from = '';
		$to   = '';
		if ( ! empty( $f['date_from'] ) ) {
			$ts = WAP_Jalali::str_to_timestamp( $f['date_from'], false );
			if ( $ts ) {
				$from = gmdate( 'Y-m-d', $ts );
			}
		}
		if ( ! empty( $f['date_to'] ) ) {
			$ts = WAP_Jalali::str_to_timestamp( $f['date_to'], false );
			if ( $ts ) {
				$to = gmdate( 'Y-m-d', $ts );
			}
		}
		return array( $from, $to );
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
            return new WP_Error( 'wap_zp_cfg', 'Access Token و Terminal ID را در «پیامک واریز خالص» وارد کنید.' );
		}

		list( $from, $to ) = self::gregorian_range( $f );
		// همه وضعیت‌ها تا مسیر خرید→شاپرک→واریز در پنل دیده شود
		$opts = array( 'filter' => 'ALL' );
		if ( $from !== '' ) {
			$opts['created_from_date'] = $from;
		}
		if ( $to !== '' ) {
			$opts['created_to_date'] = $to;
		}

		$items = WAP_Zarinpal_Reconcile::fetch_reconciles( $token, $tid, $opts );
		if ( is_wp_error( $items ) ) {
			return $items;
		}

		$ts_from = ! empty( $f['date_from'] ) ? WAP_Jalali::str_to_timestamp( $f['date_from'], false ) : 0;
		$ts_to   = ! empty( $f['date_to'] ) ? WAP_Jalali::str_to_timestamp( $f['date_to'], true ) : 0;
		if ( ! $ts_from && ! $ts_to ) {
			return $items;
		}

		$out = array();
		foreach ( $items as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$status = strtoupper( (string) ( $row['status'] ?? '' ) );
			if ( ! in_array( $status, array( 'PAID', 'IN_PROGRESS', 'REVERSED' ), true ) ) {
				continue;
			}
			$iso = (string) ( $row['payable_at'] ?? $row['reconciled_at'] ?? '' );
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

	/** برچسب فارسی وضعیت تسویه (مثل پنل زرین‌پال). */
	public static function status_label( string $status ): string {
		$map = array(
			'PAID'        => 'تسویه شده',
			'IN_PROGRESS' => 'در حال انجام',
			'REVERSED'    => 'برگشت‌خورده',
			'ALL'         => 'همه',
		);
		$key = strtoupper( trim( $status ) );
		return $map[ $key ] ?? ( $status !== '' ? $status : '—' );
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

	/**
	 * نمایش تاریخ مثل پنل زرین‌پال: «امروز ۰۴:۰۰» یا «۲۸ مرداد ۰۴:۰۰».
	 */
	public static function format_iso_display( string $iso ): string {
		if ( $iso === '' ) {
			return '—';
		}
		$ts = strtotime( $iso );
		if ( ! $ts ) {
			return $iso;
		}
		$g = getdate( $ts );
		list( $jy, $jm, $jd ) = WAP_Jalali::to_jalali( (int) $g['year'], (int) $g['mon'], (int) $g['mday'] );
		$names = WAP_Jalali::month_names();
		$month = $names[ $jm - 1 ] ?? (string) $jm;
		$time  = sprintf( '%02d:%02d', (int) $g['hours'], (int) $g['minutes'] );

		$today = WAP_Jalali::today();
		if ( (int) $today['y'] === $jy && (int) $today['m'] === $jm && (int) $today['d'] === $jd ) {
			return 'امروز ' . $time;
		}
		$now_mid  = strtotime( date( 'Y-m-d' ) . ' 12:00:00' );
		$yest_mid = $now_mid ? strtotime( '-1 day', $now_mid ) : false;
		if ( $yest_mid ) {
			$yg = getdate( $yest_mid );
			list( $yy, $ym, $yd ) = WAP_Jalali::to_jalali( (int) $yg['year'], (int) $yg['mon'], (int) $yg['mday'] );
			if ( $yy === $jy && $ym === $jm && $yd === $jd ) {
				return 'دیروز ' . $time;
			}
		}
		return $jd . ' ' . $month . ' ' . $time;
	}
}
