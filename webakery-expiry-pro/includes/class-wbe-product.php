<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ذخیره و خواندن بچ‌های محصول + همگام‌سازی قیمت/موجودی ووکامرس.
 */
class WBE_Product {

	const META_BATCHES        = '_wbe_batches';
	const META_CALENDAR       = '_wbe_calendar';
	const META_ACTIVE_EXPIRY  = '_wbe_active_expiry';

	/** @var bool */
	private static $syncing = false;

	/** @var bool */
	private static $pulling = false;

	/** @var array<int,bool> product_id => update_discount */
	private static $queued = array();

	public static function register() {
		add_action( 'woocommerce_product_object_updated_props', array( __CLASS__, 'on_props_updated' ), 20, 2 );
		add_action( 'woocommerce_product_bulk_edit_save', array( __CLASS__, 'on_wc_price_save' ), 20 );
		add_action( 'woocommerce_product_quick_edit_save', array( __CLASS__, 'on_wc_price_save' ), 20 );
		add_action( 'woocommerce_rest_insert_product_object', array( __CLASS__, 'on_wc_price_save' ), 20, 1 );
		add_action( 'woocommerce_product_import_inserted_product_object', array( __CLASS__, 'on_wc_price_save' ), 20, 1 );
		add_action( 'woocommerce_new_product', array( __CLASS__, 'on_new_product' ), 20 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_price_meta' ), 20, 4 );
		add_action( 'added_post_meta', array( __CLASS__, 'on_price_meta' ), 20, 4 );
	}

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
		if ( class_exists( 'WBE_Alerts' ) ) {
			WBE_Alerts::flush();
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
		$product_id = (int) $product_id;
		if ( ! self::configured( $product_id ) ) {
			delete_post_meta( $product_id, self::META_ACTIVE_EXPIRY );
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
			$regular  = (string) $active['price'];
			$discount = WBE_Engine::discount_of( $active );
			$product->set_regular_price( $regular );
			if ( $discount > 0 ) {
				$sale = (string) WBE_Engine::sale_price( $regular, $discount );
				$product->set_sale_price( $sale );
				$product->set_price( $sale );
			} else {
				$product->set_sale_price( '' );
				$product->set_price( $regular );
			}
			$product->set_stock_quantity( (int) $active['stock'] );
			$product->set_stock_status( 'instock' );
			update_post_meta( $product_id, self::META_ACTIVE_EXPIRY, $active['expiry'] );
		} else {
			$product->set_stock_quantity( 0 );
			$product->set_stock_status( 'outofstock' );
			delete_post_meta( $product_id, self::META_ACTIVE_EXPIRY );
		}
		$product->save();
		self::$syncing = false;
	}

	public static function is_syncing() {
		return self::$syncing;
	}

	/**
	 * قیمت نوشته‌شده در ووکامرس (گروهی، سریع، API، ایمپورت، متای مستقیم) روی بچ فعال می‌آید.
	 *
	 * @param WC_Product|int $product
	 * @param bool           $update_discount
	 */
	public static function pull_wc_price( $product, $update_discount = true ) {
		if ( self::$syncing || self::$pulling ) {
			return;
		}
		if ( ! empty( $_POST['wbe_batches_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		if ( class_exists( 'WBE_Plugin' ) && ! WBE_Plugin::licensed() ) {
			return;
		}
		if ( is_numeric( $product ) ) {
			if ( ! function_exists( 'wc_get_product' ) ) {
				return;
			}
			$product = wc_get_product( (int) $product );
		}
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return;
		}
		$product_id = (int) $product->get_id();
		if ( ! $product_id || ! self::configured( $product_id ) ) {
			return;
		}
		$regular = method_exists( $product, 'get_regular_price' ) ? $product->get_regular_price( 'edit' ) : '';
		if ( ( $regular === '' || (float) $regular <= 0 ) && function_exists( 'get_post_meta' ) ) {
			$regular = get_post_meta( $product_id, '_regular_price', true );
		}
		if ( ( $regular === '' || (float) $regular <= 0 ) && function_exists( 'get_post_meta' ) ) {
			$regular = get_post_meta( $product_id, '_price', true );
		}
		$sale = method_exists( $product, 'get_sale_price' ) ? $product->get_sale_price( 'edit' ) : '';
		if ( $sale === '' && $update_discount && function_exists( 'get_post_meta' ) ) {
			$sale = get_post_meta( $product_id, '_sale_price', true );
		}
		$today   = class_exists( 'WBE_Jalali' ) ? WBE_Jalali::today_ymd() : gmdate( 'Y-m-d' );
		$batches = self::batches( $product_id );
		$next    = WBE_Engine::apply_wc_price_to_active( $batches, $regular, $sale, $today, $update_discount );
		if ( $next === $batches ) {
			return;
		}
		self::$pulling = true;
		try {
			self::save_batches( $product_id, $next, null, true );
		} finally {
			self::$pulling = false;
		}
	}

	public static function on_props_updated( $product, $props ) {
		if ( ! is_array( $props ) ) {
			return;
		}
		$watch = array( 'regular_price', 'sale_price', 'price' );
		if ( ! array_intersect( $watch, $props ) ) {
			return;
		}
		$sale_updated = in_array( 'sale_price', $props, true );
		self::pull_wc_price( $product, $sale_updated );
	}

	public static function on_wc_price_save( $product ) {
		self::pull_wc_price( $product, self::request_updates_sale() );
	}

	public static function on_new_product( $product_id ) {
		self::pull_wc_price( (int) $product_id, true );
	}

	/**
	 * افزونه‌هایی که مستقیم `_regular_price` می‌نویسند (ویرایش گروهی، ساخت از دسته).
	 *
	 * @param int    $meta_id
	 * @param int    $object_id
	 * @param string $meta_key
	 * @param mixed  $meta_value
	 */
	public static function on_price_meta( $meta_id, $object_id, $meta_key, $meta_value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( self::$syncing || self::$pulling ) {
			return;
		}
		if ( ! in_array( $meta_key, array( '_regular_price', '_sale_price', '_price' ), true ) ) {
			return;
		}
		$object_id = (int) $object_id;
		if ( $object_id <= 0 ) {
			return;
		}
		if ( function_exists( 'get_post_type' ) && 'product' !== get_post_type( $object_id ) ) {
			return;
		}
		if ( ! isset( self::$queued[ $object_id ] ) ) {
			self::$queued[ $object_id ] = false;
		}
		if ( '_sale_price' === $meta_key ) {
			self::$queued[ $object_id ] = true;
		}
		if ( function_exists( 'has_action' ) && function_exists( 'add_action' ) && ! has_action( 'shutdown', array( __CLASS__, 'flush_queued' ) ) ) {
			add_action( 'shutdown', array( __CLASS__, 'flush_queued' ) );
		}
	}

	public static function flush_queued() {
		if ( empty( self::$queued ) ) {
			return;
		}
		$jobs         = self::$queued;
		self::$queued = array();
		foreach ( $jobs as $id => $update_discount ) {
			self::pull_wc_price( (int) $id, (bool) $update_discount );
		}
	}

	/**
	 * در ویرایش گروهی ووکامرس اگر فیلد قیمت فروش خالی باشد، درصد تخفیف بچ حفظ می‌شود.
	 *
	 * @return bool
	 */
	private static function request_updates_sale() {
		if ( ! isset( $_REQUEST['change_sale_price'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return true;
		}
		$raw = $_REQUEST['change_sale_price']; // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
		if ( function_exists( 'wp_unslash' ) ) {
			$raw = wp_unslash( $raw );
		}
		return (string) $raw !== '';
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
