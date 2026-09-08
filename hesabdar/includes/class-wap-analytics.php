<?php
defined( 'ABSPATH' ) || exit;

/**
 * داده‌های داشبورد تحلیلی: ناخالص/خالص، درگاه‌ها، منبع ورود، مشتریان ثابت، پرفروش، پیک خرید.
 */
class WAP_Analytics {

	/**
	 * @return array<string,mixed>
	 */
	public static function build( ?array $src = null ): array {
		$f = class_exists( 'WAP_Data' ) ? WAP_Data::get_filters( $src ) : array( 'date_from' => '', 'date_to' => '' );
		$orders_all = WAP_Data::get_orders( $f );
		$paid = WAP_Data::filter_paid_orders( $orders_all );
		$gn = WAP_Data::gross_vs_net( $orders_all );

		return array(
			'filters'   => $f,
			'gross_net' => $gn,
			'gateways'  => self::gateway_breakdown( $paid ),
			'traffic'   => self::traffic_breakdown( $paid ),
			'loyal'     => self::loyal_customers( $paid, 3 ),
			'top_products' => array_slice( WAP_Data::get_product_sales( $paid ), 0, 10 ),
			'peak_hours'=> self::peak_hours( $paid ),
			'peak_days' => self::peak_days( $paid ),
			'fees'      => self::fee_breakdown( $paid ),
		);
	}

	/** @param array<int,\WC_Order> $orders */
	public static function gateway_breakdown( array $orders ): array {
		$out = array();
		foreach ( $orders as $order ) {
			$method = (string) $order->get_payment_method();
			$fam    = WAP_Gateway::family( $method );
			if ( ! isset( $out[ $fam ] ) ) {
				$out[ $fam ] = array(
					'id'     => $fam,
					'label'  => $fam === WAP_Gateway::FAMILY_OTHER ? 'سایر' : WAP_Gateway::families()[ $fam ]['label'],
					'count'  => 0,
					'total'  => 0.0,
					'fee'    => 0.0,
					'note'   => WAP_Gateway::fee_note( $fam ),
				);
			}
			$total = (float) $order->get_total();
			$out[ $fam ]['count']++;
			$out[ $fam ]['total'] += $total;
			$out[ $fam ]['fee']   += WAP_Gateway::estimate_fee_toman( $method, $total );
		}
		uasort( $out, function( $a, $b ) { return $b['total'] <=> $a['total']; } );
		return array_values( $out );
	}

	/** @param array<int,\WC_Order> $orders */
	public static function traffic_breakdown( array $orders ): array {
		$out = array();
		foreach ( $orders as $order ) {
			$src = WAP_Traffic::order_source( $order );
			if ( ! isset( $out[ $src ] ) ) {
				$out[ $src ] = array(
					'id'    => $src,
					'label' => WAP_Traffic::source_label( $src ),
					'count' => 0,
					'total' => 0.0,
				);
			}
			$out[ $src ]['count']++;
			$out[ $src ]['total'] += (float) $order->get_total();
		}
		uasort( $out, function( $a, $b ) { return $b['total'] <=> $a['total']; } );
		return array_values( $out );
	}

	/**
	 * مشتریان ثابت: حداقل N خرید در بازه.
	 *
	 * @param array<int,\WC_Order> $orders
	 * @return array{threshold:int,count:int,rows:array<int,array>}
	 */
	public static function loyal_customers( array $orders, int $threshold = 3 ): array {
		$bucket = array();
		foreach ( $orders as $order ) {
			$email = strtolower( trim( (string) $order->get_billing_email() ) );
			$phone = preg_replace( '/\D+/', '', (string) $order->get_billing_phone() );
			$key   = $email !== '' ? 'e:' . $email : ( $phone !== '' ? 'p:' . $phone : 'id:' . $order->get_customer_id() );
			if ( $key === 'id:0' || $key === 'e:' || $key === 'p:' ) {
				$key = 'o:' . $order->get_id();
			}
			if ( ! isset( $bucket[ $key ] ) ) {
				$bucket[ $key ] = array(
					'name'  => trim( $order->get_formatted_billing_full_name() ),
					'phone' => (string) $order->get_billing_phone(),
					'email' => (string) $order->get_billing_email(),
					'count' => 0,
					'total' => 0.0,
				);
			}
			$bucket[ $key ]['count']++;
			$bucket[ $key ]['total'] += (float) $order->get_total();
		}
		$rows = array();
		foreach ( $bucket as $row ) {
			if ( $row['count'] >= $threshold ) {
				$rows[] = $row;
			}
		}
		usort( $rows, function( $a, $b ) {
			return $b['count'] <=> $a['count'] ?: $b['total'] <=> $a['total'];
		} );
		return array(
			'threshold' => $threshold,
			'count'     => count( $rows ),
			'rows'      => array_slice( $rows, 0, 50 ),
		);
	}

	/** @param array<int,\WC_Order> $orders @return array<int,array{hour:int,count:int,total:float}> */
	public static function peak_hours( array $orders ): array {
		$hours = array();
		for ( $h = 0; $h < 24; $h++ ) {
			$hours[ $h ] = array( 'hour' => $h, 'count' => 0, 'total' => 0.0 );
		}
		foreach ( $orders as $order ) {
			$created = $order->get_date_created();
			if ( ! $created ) {
				continue;
			}
			$h = (int) $created->date( 'G' );
			$hours[ $h ]['count']++;
			$hours[ $h ]['total'] += (float) $order->get_total();
		}
		return array_values( $hours );
	}

	/** @param array<int,\WC_Order> $orders */
	public static function peak_days( array $orders ): array {
		$names = array( 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه' );
		// PHP date('w'): 0=Sun ... 6=Sat — map to FA week starting شنبه
		$days = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$days[ $i ] = array( 'dow' => $i, 'label' => $names[ $i ], 'count' => 0, 'total' => 0.0 );
		}
		foreach ( $orders as $order ) {
			$created = $order->get_date_created();
			if ( ! $created ) {
				continue;
			}
			$w = (int) $created->date( 'w' ); // 0 Sun
			$days[ $w ]['count']++;
			$days[ $w ]['total'] += (float) $order->get_total();
		}
		// reorder to Saturday-first for IR
		$order_idx = array( 6, 0, 1, 2, 3, 4, 5 );
		$out = array();
		foreach ( $order_idx as $i ) {
			$out[] = $days[ $i ];
		}
		return $out;
	}

	/** @param array<int,\WC_Order> $orders */
	public static function fee_breakdown( array $orders ): array {
		$fee_sum = 0.0;
		$net_sum = 0.0;
		$gross   = 0.0;
		foreach ( $orders as $order ) {
			$total = (float) $order->get_total();
			$fee   = WAP_Gateway::estimate_fee_toman( (string) $order->get_payment_method(), $total );
			$gross += $total;
			$fee_sum += $fee;
			$net_sum += max( 0, $total - $fee );
		}
		return array(
			'gross' => $gross,
			'fee'   => $fee_sum,
			'net'   => $net_sum,
		);
	}
}
