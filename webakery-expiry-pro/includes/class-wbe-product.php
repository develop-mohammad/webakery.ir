<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ذخیره و خواندن بچ‌های محصول + همگام‌سازی قیمت/موجودی ووکامرس.
 */
class WBE_Product {

	const META_BATCHES  = '_wbe_batches';
	const META_CALENDAR = '_wbe_calendar';

	/** @var bool */
	private static $syncing = false;

	public static function batches( $product_id ) {
		$raw = get_post_meta( (int) $product_id, self::META_BATCHES, true );
		return is_array( $raw ) ? $raw : array();
	}

	public static function configured( $product_id ) {
		return WBE_Engine::is_configured( self::batches( $product_id ) );
	}

	/**
	 * تقویم این محصول: jalali|gregorian
	 */
	public static function calendar( $product_id ) {
		$override = get_post_meta( (int) $product_id, self::META_CALENDAR, true );
		if ( 'jalali' === $override || 'gregorian' === $override ) {
			return $override;
		}
		return WBE_Settings::calendar();
	}

	public static function save_batches( $product_id, array $batches, $calendar_override = null, $sync = true ) {
		$product_id = (int) $product_id;
		if ( empty( $batches ) ) {
			delete_post_meta( $product_id, self::META_BATCHES );
		} else {
			update_post_meta( $product_id, self::META_BATCHES, $batches );
		}
		if ( null !== $calendar_override ) {
			$calendar_override = sanitize_key( (string) $calendar_override );
			if ( in_array( $calendar_override, array( 'jalali', 'gregorian' ), true ) ) {
				update_post_meta( $product_id, self::META_CALENDAR, $calendar_override );
			} else {
				delete_post_meta( $product_id, self::META_CALENDAR );
			}
		}
		if ( $sync ) {
			self::sync_wc( $product_id );
		}
	}

	public static function active( $product_id ) {
		$batches = self::batches( $product_id );
		$idx     = WBE_Engine::active_index( $batches, WBE_Jalali::today_ymd() );
		if ( null === $idx ) {
			return null;
		}
		$batch          = $batches[ $idx ];
		$batch['_index'] = $idx;
		return $batch;
	}

	public static function consume( $product_id, $qty ) {
		$product_id = (int) $product_id;
		$batches    = self::batches( $product_id );
		$result     = WBE_Engine::consume( $batches, $qty, WBE_Jalali::today_ymd() );
		update_post_meta( $product_id, self::META_BATCHES, $result['batches'] );
		self::sync_wc( $product_id );
		return $result['batch_id'];
	}

	public static function restore( $product_id, $qty, $batch_id ) {
		$product_id = (int) $product_id;
		$batches    = WBE_Engine::restore( self::batches( $product_id ), $qty, $batch_id );
		update_post_meta( $product_id, self::META_BATCHES, $batches );
		self::sync_wc( $product_id );
	}

	/**
	 * قیمت و موجودی ووکامرس = بچ فعال. رزرو دیده نمی‌شود.
	 */
	public static function sync_wc( $product_id ) {
		if ( self::$syncing || ! function_exists( 'wc_get_product' ) ) {
			return;
		}
		if ( ! self::configured( $product_id ) ) {
			return;
		}
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}
		self::$syncing = true;
		$active        = self::active( $product_id );
		$product->set_manage_stock( true );
		$product->set_backorders( 'no' );
		if ( $active ) {
			$product->set_regular_price( (string) $active['price'] );
			$product->set_price( (string) $active['price'] );
			$product->set_stock_quantity( (int) $active['stock'] );
			$product->set_stock_status( 'instock' );
		} else {
			$product->set_stock_quantity( 0 );
			$product->set_stock_status( 'outofstock' );
		}
		$product->save();
		self::$syncing = false;
	}

	public static function is_syncing() {
		return self::$syncing;
	}

	public static function brand_label( $product_id ) {
		$taxes = array( 'product_brand', 'pwb-brand', 'product_brands' );
		foreach ( $taxes as $tax ) {
			if ( ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$terms = get_the_terms( $product_id, $tax );
			if ( $terms && ! is_wp_error( $terms ) ) {
				return implode( '، ', wp_list_pluck( $terms, 'name' ) );
			}
		}
		if ( ! function_exists( 'wc_get_product' ) ) {
			return '';
		}
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return '';
		}
		foreach ( array( 'brand', 'pa_brand', 'brands' ) as $attr ) {
			$val = $product->get_attribute( $attr );
			if ( $val ) {
				return wp_strip_all_tags( $val );
			}
		}
		return '';
	}

	public static function category_label( $product_id ) {
		$terms = get_the_terms( $product_id, 'product_cat' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}
		return implode( '، ', wp_list_pluck( $terms, 'name' ) );
	}

	public static function configured_ids() {
		$q = new WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => array( 'publish', 'private', 'draft' ),
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'     => self::META_BATCHES,
						'compare' => 'EXISTS',
					),
				),
			)
		);
		return array_map( 'intval', $q->posts );
	}
}
