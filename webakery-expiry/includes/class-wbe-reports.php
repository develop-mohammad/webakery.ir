<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WBE_Reports {

	public static function register() {
		// no-op; admin calls this class.
	}

	public static function filters_from_request() {
		$src = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification
		return array(
			'category'     => isset( $src['wbe_cat'] ) ? (int) $src['wbe_cat'] : 0,
			'brand'        => isset( $src['wbe_brand'] ) ? sanitize_text_field( wp_unslash( $src['wbe_brand'] ) ) : '',
			'near'         => empty( $src['wbe_near'] ) ? 0 : 1,
			'q'            => isset( $src['s'] ) ? sanitize_text_field( wp_unslash( $src['s'] ) ) : '',
			'sort'         => isset( $src['wbe_sort'] ) ? sanitize_key( $src['wbe_sort'] ) : 'expiry',
			'dir'          => ( isset( $src['wbe_dir'] ) && 'asc' === $src['wbe_dir'] ) ? 'asc' : 'desc',
		);
	}

	public static function rows( $filters = array() ) {
		$filters = wp_parse_args(
			$filters,
			array(
				'category' => 0,
				'brand'    => '',
				'near'     => 0,
				'q'        => '',
				'sort'     => 'expiry',
				'dir'      => 'desc',
			)
		);
		$near_days = (int) WBE_Settings::get()['near_expiry_days'];
		$today     = WBE_Jalali::today_ymd();
		$sales     = self::sales_map();
		$out       = array();

		foreach ( WBE_Product::configured_ids() as $id ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
			if ( ! $product ) {
				continue;
			}
			if ( $filters['category'] ) {
				if ( ! has_term( $filters['category'], 'product_cat', $id ) ) {
					continue;
				}
			}
			$brand = WBE_Product::brand_label( $id );
			if ( $filters['brand'] !== '' && ! self::has( $brand, $filters['brand'] ) ) {
				continue;
			}
			$name = $product->get_name();
			if ( $filters['q'] !== '' && ! self::has( $name, $filters['q'] ) && ! self::has( (string) $product->get_sku(), $filters['q'] ) ) {
				continue;
			}

			$batches = WBE_Product::batches( $id );
			$active  = WBE_Product::active( $id );
			$cal     = WBE_Product::calendar( $id );
			$exp     = $active ? $active['expiry'] : '';
			$days    = ( $exp && $today ) ? (int) floor( ( strtotime( $exp . ' UTC' ) - strtotime( $today . ' UTC' ) ) / DAY_IN_SECONDS ) : null;
			if ( $filters['near'] && ( null === $days || $days > $near_days || $days < 0 ) ) {
				continue;
			}

			$sold_qty = isset( $sales[ $id ] ) ? $sales[ $id ]['qty'] : 0;
			$sold_amt = isset( $sales[ $id ] ) ? $sales[ $id ]['amount'] : 0.0;

			$out[] = array(
				'id'         => $id,
				'name'       => $name,
				'sku'        => (string) $product->get_sku(),
				'category'   => WBE_Product::category_label( $id ),
				'brand'      => $brand !== '' ? $brand : '—',
				'expiry'     => $exp,
				'expiry_fa'  => $exp ? WBE_Jalali::format_ymd( $exp, $cal, true ) : '—',
				'days'       => $days,
				'price'      => $active ? (float) WBE_Engine::sale_price( $active['price'], WBE_Engine::discount_of( $active ) ) : 0,
				'regular'    => $active ? (float) $active['price'] : 0,
				'discount'   => $active ? WBE_Engine::discount_of( $active ) : 0,
				'stock'      => $active ? (int) $active['stock'] : 0,
				'reserves'   => max( 0, count( $batches ) - ( $active ? 1 : 0 ) ),
				'batches'    => count( $batches ),
				'sold_qty'   => $sold_qty,
				'sold_amt'   => $sold_amt,
				'calendar'   => $cal,
				'status'     => $active ? ( ( null !== $days && $days <= $near_days ) ? 'near' : 'ok' ) : 'empty',
			);
		}

		$sort = $filters['sort'];
		$dir  = ( 'asc' === $filters['dir'] ) ? 1 : -1;
		usort(
			$out,
			function ( $a, $b ) use ( $sort, $dir ) {
				$va = isset( $a[ $sort ] ) ? $a[ $sort ] : '';
				$vb = isset( $b[ $sort ] ) ? $b[ $sort ] : '';
				if ( is_numeric( $va ) && is_numeric( $vb ) ) {
					$cmp = ( $va < $vb ) ? -1 : ( ( $va > $vb ) ? 1 : 0 );
				} else {
					$cmp = strcasecmp( (string) $va, (string) $vb );
				}
				return $cmp * $dir;
			}
		);
		return $out;
	}

	/**
	 * فروش هر محصول از سفارش‌های تکمیل/در حال انجام.
	 *
	 * @return array<int,array{qty:int,amount:float}>
	 */
	public static function sales_map() {
		$cached = get_transient( 'wbe_sales_map' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$map = array();
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return $map;
		}
		$orders = wc_get_orders(
			array(
				'status' => array( 'wc-completed', 'wc-processing' ),
				'limit'  => 400,
				'orderby' => 'date',
				'order'  => 'DESC',
				'return' => 'objects',
			)
		);
		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
					continue;
				}
				$pid = (int) $item->get_product_id();
				if ( $pid <= 0 ) {
					continue;
				}
				if ( ! isset( $map[ $pid ] ) ) {
					$map[ $pid ] = array( 'qty' => 0, 'amount' => 0.0 );
				}
				$map[ $pid ]['qty']    += (int) $item->get_quantity();
				$map[ $pid ]['amount'] += (float) $item->get_total();
			}
		}
		set_transient( 'wbe_sales_map', $map, 5 * MINUTE_IN_SECONDS );
		return $map;
	}

	private static function has( $haystack, $needle ) {
		$haystack = (string) $haystack;
		$needle   = (string) $needle;
		if ( function_exists( 'mb_stripos' ) ) {
			return false !== mb_stripos( $haystack, $needle );
		}
		return false !== stripos( $haystack, $needle );
	}
}
