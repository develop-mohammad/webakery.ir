<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * کاهش/بازگشت موجودی سفارش و فیلتر قیمت فعال (با تخفیف بچ).
 */
class WBE_Stock {

	public static function register() {
		add_action( 'woocommerce_reduce_order_stock', array( __CLASS__, 'on_reduce' ) );
		add_action( 'woocommerce_restore_order_stock', array( __CLASS__, 'on_restore' ) );
		add_filter( 'woocommerce_product_get_price', array( __CLASS__, 'filter_price' ), 99, 2 );
		add_filter( 'woocommerce_product_get_regular_price', array( __CLASS__, 'filter_regular_price' ), 99, 2 );
		add_filter( 'woocommerce_product_get_sale_price', array( __CLASS__, 'filter_sale_price' ), 99, 2 );
		add_filter( 'woocommerce_product_get_stock_quantity', array( __CLASS__, 'filter_stock' ), 99, 2 );
		add_filter( 'woocommerce_product_is_on_sale', array( __CLASS__, 'filter_on_sale' ), 99, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_sync_viewed' ) );
	}

	public static function on_reduce( $order ) {
		if ( ! WBE_Plugin::licensed() || ! is_a( $order, 'WC_Order' ) ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {
			if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
				continue;
			}
			$pid = (int) $item->get_product_id();
			if ( ! WBE_Product::configured( $pid ) ) {
				continue;
			}
			$qty      = (int) $item->get_quantity();
			$batch_id = WBE_Product::consume( $pid, $qty );
			if ( $batch_id ) {
				$item->add_meta_data( '_wbe_batch_id', $batch_id, true );
				$item->save();
			}
		}
	}

	public static function on_restore( $order ) {
		if ( ! WBE_Plugin::licensed() || ! is_a( $order, 'WC_Order' ) ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {
			if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
				continue;
			}
			$pid = (int) $item->get_product_id();
			if ( ! WBE_Product::configured( $pid ) ) {
				continue;
			}
			$batch_id = (string) $item->get_meta( '_wbe_batch_id' );
			WBE_Product::restore( $pid, (int) $item->get_quantity(), $batch_id );
		}
	}

	public static function filter_regular_price( $price, $product ) {
		if ( WBE_Product::is_syncing() || ! self::ok_product( $product ) ) {
			return $price;
		}
		$active = WBE_Product::active( $product->get_id() );
		return $active ? (string) $active['price'] : $price;
	}

	public static function filter_sale_price( $price, $product ) {
		if ( WBE_Product::is_syncing() || ! self::ok_product( $product ) ) {
			return $price;
		}
		$active = WBE_Product::active( $product->get_id() );
		if ( ! $active || WBE_Engine::discount_of( $active ) <= 0 ) {
			return '';
		}
		return (string) WBE_Engine::sale_price( $active['price'], WBE_Engine::discount_of( $active ) );
	}

	public static function filter_price( $price, $product ) {
		if ( WBE_Product::is_syncing() || ! self::ok_product( $product ) ) {
			return $price;
		}
		$active = WBE_Product::active( $product->get_id() );
		if ( ! $active ) {
			return $price;
		}
		return (string) WBE_Engine::sale_price( $active['price'], WBE_Engine::discount_of( $active ) );
	}

	public static function filter_stock( $qty, $product ) {
		if ( WBE_Product::is_syncing() || ! self::ok_product( $product ) ) {
			return $qty;
		}
		$active = WBE_Product::active( $product->get_id() );
		return $active ? (int) $active['stock'] : 0;
	}

	public static function filter_on_sale( $on_sale, $product ) {
		if ( ! self::ok_product( $product ) ) {
			return $on_sale;
		}
		$active = WBE_Product::active( $product->get_id() );
		return $active && WBE_Engine::discount_of( $active ) > 0;
	}

	public static function maybe_sync_viewed() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		$id = get_queried_object_id();
		if ( $id && WBE_Product::configured( $id ) && WBE_Plugin::licensed() ) {
			WBE_Product::sync_wc( $id );
		}
	}

	private static function ok_product( $product ) {
		if ( ! WBE_Plugin::licensed() || ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return false;
		}
		return WBE_Product::configured( $product->get_id() );
	}
}
