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
			'peak_calendar' => self::peak_calendar( $paid, $f ),
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

	/**
	 * تقویم شمسی پیک خرید — روزبه‌روز در ماه‌های بازه.
	 *
	 * @param array<int,\WC_Order> $orders
	 * @param array                $f      فیلتر با date_from / date_to
	 * @return array{months:array<int,array>,max_count:int,total_orders:int}
	 */
	public static function peak_calendar( array $orders, array $f = array() ): array {
		$by_day = array();
		foreach ( $orders as $order ) {
			$created = $order->get_date_created();
			if ( ! $created ) {
				continue;
			}
			list( $jy, $jm, $jd ) = WAP_Jalali::to_jalali(
				(int) $created->date( 'Y' ),
				(int) $created->date( 'n' ),
				(int) $created->date( 'j' )
			);
			$key = sprintf( '%04d-%02d-%02d', $jy, $jm, $jd );
			if ( ! isset( $by_day[ $key ] ) ) {
				$by_day[ $key ] = array(
					'y'     => $jy,
					'm'     => $jm,
					'd'     => $jd,
					'count' => 0,
					'total' => 0.0,
				);
			}
			$by_day[ $key ]['count']++;
			$by_day[ $key ]['total'] += (float) $order->get_total();
		}

		$today = WAP_Jalali::today();
		$from  = ! empty( $f['date_from'] ) ? WAP_Jalali::parse( (string) $f['date_from'] ) : null;
		$to    = ! empty( $f['date_to'] ) ? WAP_Jalali::parse( (string) $f['date_to'] ) : null;
		if ( ! $from && ! empty( $by_day ) ) {
			$first = reset( $by_day );
			$from  = array( 'y' => $first['y'], 'm' => $first['m'], 'd' => 1 );
		}
		if ( ! $to && ! empty( $by_day ) ) {
			$last = end( $by_day );
			$to   = array( 'y' => $last['y'], 'm' => $last['m'], 'd' => $last['d'] );
		}
		if ( ! $from ) {
			$from = array( 'y' => $today['y'], 'm' => $today['m'], 'd' => 1 );
		}
		if ( ! $to ) {
			$to = array( 'y' => $today['y'], 'm' => $today['m'], 'd' => $today['d'] );
		}

		$names  = WAP_Jalali::month_names();
		$months = array();
		$y      = (int) $from['y'];
		$m      = (int) $from['m'];
		$end_y  = (int) $to['y'];
		$end_m  = (int) $to['m'];
		$max_count = 0;
		$guard  = 0;
		while ( ( $y < $end_y || ( $y === $end_y && $m <= $end_m ) ) && $guard < 6 ) {
			$guard++;
			$len = WAP_Jalali::month_length( $y, $m );
			// weekday of 1st: convert to Sat=0..Fri=6
			$g1  = WAP_Jalali::to_gregorian( $y, $m, 1 );
			$ts  = mktime( 12, 0, 0, $g1[1], $g1[2], $g1[0] );
			$w   = (int) date( 'w', $ts ); // 0=Sun
			$sat0 = ( $w + 1 ) % 7; // Sat=0
			$days = array();
			for ( $i = 0; $i < $sat0; $i++ ) {
				$days[] = array( 'blank' => true );
			}
			for ( $d = 1; $d <= $len; $d++ ) {
				$key = sprintf( '%04d-%02d-%02d', $y, $m, $d );
				$cell = isset( $by_day[ $key ] ) ? $by_day[ $key ] : array(
					'y'     => $y,
					'm'     => $m,
					'd'     => $d,
					'count' => 0,
					'total' => 0.0,
				);
				$cell['blank']   = false;
				$cell['is_today'] = ( $y === $today['y'] && $m === $today['m'] && $d === $today['d'] );
				$in_range = true;
				if ( $from ) {
					$a = $y * 10000 + $m * 100 + $d;
					$b = $from['y'] * 10000 + $from['m'] * 100 + $from['d'];
					if ( $a < $b ) {
						$in_range = false;
					}
				}
				if ( $to ) {
					$a = $y * 10000 + $m * 100 + $d;
					$b = $to['y'] * 10000 + $to['m'] * 100 + $to['d'];
					if ( $a > $b ) {
						$in_range = false;
					}
				}
				$cell['in_range'] = $in_range;
				if ( $cell['count'] > $max_count ) {
					$max_count = $cell['count'];
				}
				$days[] = $cell;
			}
			$months[] = array(
				'y'     => $y,
				'm'     => $m,
				'label' => $names[ $m - 1 ] . ' ' . $y,
				'days'  => $days,
			);
			$m++;
			if ( $m > 12 ) {
				$m = 1;
				$y++;
			}
		}

		return array(
			'months'       => $months,
			'max_count'    => max( 1, $max_count ),
			'total_orders' => count( $orders ),
			'weekdays'     => array( 'ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج' ),
		);
	}

	/**
	 * رنگ پله‌ای با کنتراست بالا برای heatmap پیک خرید.
	 *
	 * @return array{bg:string,fg:string,level:int,border:string}
	 */
	public static function heat_tone( int $count, int $max_count ): array {
		if ( $count <= 0 ) {
			return array(
				'bg'     => '#ffffff',
				'fg'     => '#5f6368',
				'border' => '#dadce0',
				'level'  => 0,
			);
		}
		$ratio = $count / max( 1, $max_count );
		if ( $ratio <= 0.2 ) {
			return array( 'bg' => '#d2e3fc', 'fg' => '#0842a0', 'border' => '#8ab4f8', 'level' => 1 );
		}
		if ( $ratio <= 0.4 ) {
			return array( 'bg' => '#8ab4f8', 'fg' => '#041e49', 'border' => '#4c8bf5', 'level' => 2 );
		}
		if ( $ratio <= 0.6 ) {
			return array( 'bg' => '#4285f4', 'fg' => '#ffffff', 'border' => '#1a73e8', 'level' => 3 );
		}
		if ( $ratio <= 0.8 ) {
			return array( 'bg' => '#1a73e8', 'fg' => '#ffffff', 'border' => '#174ea6', 'level' => 4 );
		}
		return array( 'bg' => '#0b57d0', 'fg' => '#ffffff', 'border' => '#0842a0', 'level' => 5 );
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
