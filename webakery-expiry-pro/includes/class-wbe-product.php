<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ذخیره و خواندن بچ‌های محصول + همگام‌سازی قیمت/موجودی ووکامرس.
 */
class WBE_Product {

	const META_BATCHES         = '_wbe_batches';
	const META_CALENDAR        = '_wbe_calendar';
	const META_ACTIVE_EXPIRY   = '_wbe_active_expiry';
	const META_HIDE_COUNTDOWN  = '_wbe_hide_countdown';

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
	 * آیا تایمر تا پایان کمپین برای این محصول مجاز است؟
	 */
	public static function countdown_enabled( $product_id ) {
		$global = ! empty( WBE_Settings::get()['show_sale_countdown'] );
		$hidden = (string) get_post_meta( (int) $product_id, self::META_HIDE_COUNTDOWN, true ) === '1';
		return WBE_Engine::countdown_allowed( $global, $hidden );
	}

	public static function save_hide_countdown( $product_id, $hide ) {
		$product_id = (int) $product_id;
		if ( $hide ) {
			update_post_meta( $product_id, self::META_HIDE_COUNTDOWN, '1' );
		} else {
			delete_post_meta( $product_id, self::META_HIDE_COUNTDOWN );
		}
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
				self::ensure_sale_dates( $product, $active['expiry'] );
			} else {
				$product->set_sale_price( '' );
				$product->set_price( $regular );
				$product->set_date_on_sale_from( '' );
				$product->set_date_on_sale_to( '' );
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
	 * اگر بازه فروش فوق‌العاده خالی یا گذشته باشد، پایان فروش = تاریخ انقضای بچ فعال.
	 *
	 * @param WC_Product $product
	 * @param string     $expiry_ymd
	 */
	public static function ensure_sale_dates( $product, $expiry_ymd ) {
		if ( ! is_object( $product ) || ! method_exists( $product, 'set_date_on_sale_to' ) ) {
			return;
		}
		$expiry_ymd = (string) $expiry_ymd;
		if ( $expiry_ymd === '' ) {
			return;
		}
		$today   = class_exists( 'WBE_Jalali' ) ? WBE_Jalali::today_ymd() : gmdate( 'Y-m-d' );
		$to_ymd  = method_exists( $product, 'get_date_on_sale_to' ) ? WBE_Jalali::datetime_to_ymd( $product->get_date_on_sale_to( 'edit' ) ) : '';
		$from_ymd = method_exists( $product, 'get_date_on_sale_from' ) ? WBE_Jalali::datetime_to_ymd( $product->get_date_on_sale_from( 'edit' ) ) : '';
		if ( $to_ymd === '' || $to_ymd < $today ) {
			$product->set_date_on_sale_to( $expiry_ymd . ' 23:59:59' );
			$to_ymd = $expiry_ymd;
		}
		if ( $from_ymd !== '' && $from_ymd > $to_ymd ) {
			$product->set_date_on_sale_from( '' );
		}
	}

	/**
	 * بازه فروش فوق‌العاده ذخیره‌شده روی محصول (بدون فیلتر نمایش).
	 *
	 * @param WC_Product|int $product
	 * @return array{0:string,1:string} from, to به Y-m-d
	 */
	public static function sale_ymd_pair( $product ) {
		if ( is_numeric( $product ) && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( (int) $product );
		}
		if ( ! is_object( $product ) ) {
			return array( '', '' );
		}
		$from = method_exists( $product, 'get_date_on_sale_from' ) ? WBE_Jalali::datetime_to_ymd( $product->get_date_on_sale_from( 'edit' ) ) : '';
		$to   = method_exists( $product, 'get_date_on_sale_to' ) ? WBE_Jalali::datetime_to_ymd( $product->get_date_on_sale_to( 'edit' ) ) : '';
		return array( $from, $to );
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

	public static function brand_taxonomies() {
		$out = array();
		$slugs = class_exists( 'WBE_Engine' ) ? WBE_Engine::brand_taxonomy_slugs() : array( 'product_brand', 'pwb-brand', 'product_brands', 'pa_brand', 'pa_brands' );
		foreach ( $slugs as $tax ) {
			if ( function_exists( 'taxonomy_exists' ) && taxonomy_exists( $tax ) ) {
				$out[] = $tax;
			}
		}
		return $out;
	}

	/**
	 * برندها برای دراپ‌داون فیلتر.
	 *
	 * @return array<int,object>
	 */
	public static function brand_terms() {
		$out  = array();
		$seen = array();
		if ( ! function_exists( 'get_terms' ) ) {
			return $out;
		}
		foreach ( self::brand_taxonomies() as $tax ) {
			$list = get_terms(
				array(
					'taxonomy'   => $tax,
					'hide_empty' => false,
				)
			);
			if ( is_wp_error( $list ) || ! $list ) {
				continue;
			}
			foreach ( $list as $term ) {
				$id = (int) $term->term_id;
				if ( isset( $seen[ $id ] ) ) {
					continue;
				}
				$seen[ $id ] = true;
				$out[]       = $term;
			}
		}
		usort(
			$out,
			function ( $a, $b ) {
				return strcasecmp( (string) $a->name, (string) $b->name );
			}
		);
		return $out;
	}

	/**
	 * شناسه ترم‌های برند برای فیلتر (خود ترم + فرزندها).
	 *
	 * @param string $filter شناسه، نام یا اسلاگ.
	 * @return array<int,int>
	 */
	public static function brand_term_ids_for_filter( $filter ) {
		$filter = trim( (string) $filter );
		if ( $filter === '' ) {
			return array();
		}
		$ids = array();
		if ( ctype_digit( $filter ) ) {
			$id = (int) $filter;
			if ( $id > 0 ) {
				$ids[] = $id;
				if ( function_exists( 'get_term' ) ) {
					$term = get_term( $id );
					if ( $term && ! is_wp_error( $term ) && ! empty( $term->taxonomy ) && function_exists( 'get_term_children' ) ) {
						$kids = get_term_children( $id, $term->taxonomy );
						if ( ! is_wp_error( $kids ) && $kids ) {
							$ids = array_merge( $ids, array_map( 'intval', $kids ) );
						}
					}
				}
			}
			return array_values( array_unique( array_filter( $ids ) ) );
		}
		if ( ! function_exists( 'get_term_by' ) ) {
			return array();
		}
		foreach ( self::brand_taxonomies() as $tax ) {
			foreach ( array( 'name', 'slug' ) as $field ) {
				$term = get_term_by( $field, $filter, $tax );
				if ( ! $term || is_wp_error( $term ) ) {
					continue;
				}
				$ids[] = (int) $term->term_id;
				if ( function_exists( 'get_term_children' ) ) {
					$kids = get_term_children( (int) $term->term_id, $tax );
					if ( ! is_wp_error( $kids ) && $kids ) {
						$ids = array_merge( $ids, array_map( 'intval', $kids ) );
					}
				}
			}
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	public static function brand_label( $product_id ) {
		foreach ( self::brand_taxonomies() as $tax ) {
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

	/**
	 * ویرایش گروهی بچ فعال — بدون WC_Product::save تا روی تعداد بالا کند نشود.
	 *
	 * @param int   $product_id شناسه محصول.
	 * @param array $ops        عملیات موتور + sale_from / sale_to (Y-m-d).
	 * @param bool  $flush_alerts بعد از هر محصول هشدار را تازه نکن (در ذخیرهٔ تکه‌ای false بگذار).
	 * @return bool اگر چیزی اعمال شد.
	 */
	public static function apply_bulk( $product_id, array $ops, $flush_alerts = true ) {
		$product_id = (int) $product_id;
		$did        = self::apply_identity( $product_id, $ops );
		$batches    = self::batches( $product_id );

		if ( ! $batches ) {
			if ( ! empty( $ops['expiry'] ) && self::seed_batch_and_apply( $product_id, $ops, false ) ) {
				if ( $flush_alerts && class_exists( 'WBE_Alerts' ) ) {
					WBE_Alerts::flush();
				}
				return true;
			}
			$plain = self::apply_wc_plain( $product_id, $ops, false );
			if ( $flush_alerts && class_exists( 'WBE_Alerts' ) && ( $plain || $did ) ) {
				WBE_Alerts::flush();
			}
			return $plain || $did;
		}

		$today           = class_exists( 'WBE_Jalali' ) ? WBE_Jalali::today_ymd() : gmdate( 'Y-m-d' );
		$next            = WBE_Engine::apply_bulk_to_active( $batches, $ops, $today );
		$batches_changed = $next !== $batches;
		$touch_dates     = ! empty( $ops['clear_sale'] )
			|| ( isset( $ops['sale_from'] ) && '' !== $ops['sale_from'] )
			|| ( isset( $ops['sale_to'] ) && '' !== $ops['sale_to'] );

		if ( ! $batches_changed && ! $touch_dates ) {
			return true;
		}

		self::$syncing = true;
		if ( $batches_changed ) {
			update_post_meta( $product_id, self::META_BATCHES, $next );
			self::push_wc_price_meta( $product_id, $next );
		}
		if ( $touch_dates ) {
			self::push_wc_sale_dates( $product_id, $ops );
		} elseif ( $batches_changed ) {
			self::ensure_sale_date_meta( $product_id, $next );
		}
		self::refresh_wc_index( $product_id );
		self::$syncing = false;

		if ( $flush_alerts && class_exists( 'WBE_Alerts' ) ) {
			WBE_Alerts::flush();
		}
		return true;
	}

	/**
	 * نام، SKU و وضعیت نوشته.
	 *
	 * @param int   $product_id
	 * @param array $ops
	 * @return bool
	 */
	public static function apply_identity( $product_id, array $ops ) {
		$product_id = (int) $product_id;
		$ok         = false;
		if ( isset( $ops['name'] ) && '' !== trim( (string) $ops['name'] ) && function_exists( 'wp_update_post' ) ) {
			$title = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $ops['name'] ) : trim( (string) $ops['name'] );
			wp_update_post(
				array(
					'ID'         => $product_id,
					'post_title' => $title,
				)
			);
			$ok = true;
		}
		if ( array_key_exists( 'sku', $ops ) ) {
			$sku = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $ops['sku'] ) : (string) $ops['sku'];
			update_post_meta( $product_id, '_sku', $sku );
			$ok = true;
		}
		$allowed = array( 'publish', 'draft', 'private', 'pending' );
		if ( isset( $ops['status'] ) && in_array( (string) $ops['status'], $allowed, true ) && function_exists( 'wp_update_post' ) ) {
			wp_update_post(
				array(
					'ID'          => $product_id,
					'post_status' => (string) $ops['status'],
				)
			);
			$ok = true;
		}
		return $ok;
	}

	/**
	 * محصول بدون بچ: با پر کردن انقضا، اولین بچ ساخته می‌شود.
	 *
	 * @param int   $product_id
	 * @param array $ops
	 * @param bool  $flush_alerts
	 * @return bool
	 */
	public static function seed_batch_and_apply( $product_id, array $ops, $flush_alerts = true ) {
		$product_id = (int) $product_id;
		$state      = self::read_wc_plain( $product_id );
		$next_state = WBE_Engine::apply_plain_state( $state['regular'], $state['sale'], $state['stock'], $ops );
		$batches    = WBE_Engine::sanitize_batches(
			array(
				array(
					'price'    => $next_state['regular'],
					'stock'    => $next_state['stock'],
					'expiry'   => $ops['expiry'],
					'discount' => $next_state['discount'],
				),
			),
			'gregorian'
		);
		if ( ! $batches ) {
			return false;
		}
		self::$syncing = true;
		update_post_meta( $product_id, self::META_BATCHES, $batches );
		self::push_wc_price_meta( $product_id, $batches );
		$touch_dates = ! empty( $ops['clear_sale'] )
			|| ( isset( $ops['sale_from'] ) && '' !== $ops['sale_from'] )
			|| ( isset( $ops['sale_to'] ) && '' !== $ops['sale_to'] );
		if ( $touch_dates ) {
			self::push_wc_sale_dates( $product_id, $ops );
		} else {
			self::ensure_sale_date_meta( $product_id, $batches );
		}
		self::refresh_wc_index( $product_id );
		self::$syncing = false;
		if ( $flush_alerts && class_exists( 'WBE_Alerts' ) ) {
			WBE_Alerts::flush();
		}
		return true;
	}

	/**
	 * @param int $product_id
	 * @return array{regular:string,sale:string,stock:string}
	 */
	public static function read_wc_plain( $product_id ) {
		$product_id = (int) $product_id;
		return array(
			'regular' => (string) get_post_meta( $product_id, '_regular_price', true ),
			'sale'    => (string) get_post_meta( $product_id, '_sale_price', true ),
			'stock'   => (string) get_post_meta( $product_id, '_stock', true ),
		);
	}

	/**
	 * ویرایش قیمت ووکامرس وقتی هنوز بچی نیست.
	 *
	 * @param int   $product_id
	 * @param array $ops
	 * @param bool  $flush_alerts
	 * @return bool
	 */
	public static function apply_wc_plain( $product_id, array $ops, $flush_alerts = true ) {
		$product_id  = (int) $product_id;
		$touch_dates = ! empty( $ops['clear_sale'] )
			|| ( isset( $ops['sale_from'] ) && '' !== $ops['sale_from'] )
			|| ( isset( $ops['sale_to'] ) && '' !== $ops['sale_to'] );
		$touch_price = WBE_Engine::has_price_ops( $ops )
			|| ( array_key_exists( 'stock', $ops ) && null !== $ops['stock'] && '' !== $ops['stock'] );
		if ( ! $touch_price && ! $touch_dates ) {
			return false;
		}

		self::$syncing = true;
		if ( $touch_price ) {
			$state = self::read_wc_plain( $product_id );
			$next  = WBE_Engine::apply_plain_state( $state['regular'], $state['sale'], $state['stock'], $ops );
			update_post_meta( $product_id, '_regular_price', $next['regular'] );
			if ( '' !== $next['sale'] ) {
				update_post_meta( $product_id, '_sale_price', $next['sale'] );
				update_post_meta( $product_id, '_price', $next['sale'] );
			} else {
				update_post_meta( $product_id, '_sale_price', '' );
				update_post_meta( $product_id, '_price', $next['regular'] );
			}
			if ( array_key_exists( 'stock', $ops ) && null !== $ops['stock'] && '' !== $ops['stock'] ) {
				update_post_meta( $product_id, '_manage_stock', 'yes' );
				update_post_meta( $product_id, '_stock', $next['stock'] );
				update_post_meta( $product_id, '_stock_status', $next['stock'] > 0 ? 'instock' : 'outofstock' );
			}
		}
		if ( $touch_dates ) {
			self::push_wc_sale_dates( $product_id, $ops );
		}
		self::refresh_wc_index( $product_id );
		self::$syncing = false;
		if ( $flush_alerts && class_exists( 'WBE_Alerts' ) ) {
			WBE_Alerts::flush();
		}
		return true;
	}

	/**
	 * قیمت و موجودی ووکامرس را مستقیم روی متا می‌نویسد (بدون بارگذاری شیء محصول).
	 *
	 * @param int   $product_id
	 * @param array $batches
	 */
	public static function push_wc_price_meta( $product_id, array $batches ) {
		$product_id = (int) $product_id;
		$today      = class_exists( 'WBE_Jalali' ) ? WBE_Jalali::today_ymd() : gmdate( 'Y-m-d' );
		$idx        = WBE_Engine::active_index( $batches, $today );
		if ( null === $idx ) {
			update_post_meta( $product_id, '_stock', 0 );
			update_post_meta( $product_id, '_stock_status', 'outofstock' );
			delete_post_meta( $product_id, self::META_ACTIVE_EXPIRY );
			return;
		}
		$active   = $batches[ $idx ];
		$regular  = (string) $active['price'];
		$discount = WBE_Engine::discount_of( $active );
		update_post_meta( $product_id, '_manage_stock', 'yes' );
		update_post_meta( $product_id, '_backorders', 'no' );
		update_post_meta( $product_id, '_regular_price', $regular );
		if ( $discount > 0 ) {
			$sale = (string) WBE_Engine::sale_price( $regular, $discount );
			update_post_meta( $product_id, '_sale_price', $sale );
			update_post_meta( $product_id, '_price', $sale );
		} else {
			update_post_meta( $product_id, '_sale_price', '' );
			update_post_meta( $product_id, '_price', $regular );
			delete_post_meta( $product_id, '_sale_price_dates_from' );
			delete_post_meta( $product_id, '_sale_price_dates_to' );
		}
		$stock = (int) $active['stock'];
		update_post_meta( $product_id, '_stock', $stock );
		update_post_meta( $product_id, '_stock_status', $stock > 0 ? 'instock' : 'outofstock' );
		update_post_meta( $product_id, self::META_ACTIVE_EXPIRY, $active['expiry'] );
	}

	/**
	 * @param int   $product_id
	 * @param array $ops
	 */
	public static function push_wc_sale_dates( $product_id, array $ops ) {
		$product_id = (int) $product_id;
		if ( ! empty( $ops['clear_sale'] ) ) {
			delete_post_meta( $product_id, '_sale_price_dates_from' );
			delete_post_meta( $product_id, '_sale_price_dates_to' );
			return;
		}
		if ( isset( $ops['sale_from'] ) && '' !== $ops['sale_from'] ) {
			$ts = self::ymd_to_ts( $ops['sale_from'], false );
			if ( $ts ) {
				update_post_meta( $product_id, '_sale_price_dates_from', $ts );
			}
		}
		if ( isset( $ops['sale_to'] ) && '' !== $ops['sale_to'] ) {
			$ts = self::ymd_to_ts( $ops['sale_to'], true );
			if ( $ts ) {
				update_post_meta( $product_id, '_sale_price_dates_to', $ts );
			}
		}
	}

	/**
	 * اگر تخفیف هست و بازه جشنواره خالی/گذشته است، پایان = انقضای بچ.
	 *
	 * @param int   $product_id
	 * @param array $batches
	 */
	public static function ensure_sale_date_meta( $product_id, array $batches ) {
		$today = class_exists( 'WBE_Jalali' ) ? WBE_Jalali::today_ymd() : gmdate( 'Y-m-d' );
		$idx   = WBE_Engine::active_index( $batches, $today );
		if ( null === $idx ) {
			return;
		}
		$active = $batches[ $idx ];
		if ( WBE_Engine::discount_of( $active ) <= 0 ) {
			return;
		}
		$to_raw = get_post_meta( $product_id, '_sale_price_dates_to', true );
		$to_ymd = class_exists( 'WBE_Jalali' ) ? WBE_Jalali::datetime_to_ymd( $to_raw ) : '';
		if ( '' === $to_ymd || $to_ymd < $today ) {
			$ts = self::ymd_to_ts( $active['expiry'], true );
			if ( $ts ) {
				update_post_meta( $product_id, '_sale_price_dates_to', $ts );
			}
		}
	}

	/**
	 * @param string $ymd
	 * @param bool   $end_of_day
	 * @return int
	 */
	public static function ymd_to_ts( $ymd, $end_of_day = false ) {
		$ymd = (string) $ymd;
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ymd ) ) {
			return 0;
		}
		$time = $end_of_day ? '23:59:59' : '00:00:00';
		if ( function_exists( 'wp_timezone' ) ) {
			$dt = date_create( $ymd . ' ' . $time, wp_timezone() );
			return $dt ? $dt->getTimestamp() : 0;
		}
		$ts = strtotime( $ymd . ' ' . $time );
		return $ts ? (int) $ts : 0;
	}

	/**
	 * کش و جدول جستجوی ووکامرس را برای یک محصول تازه می‌کند.
	 *
	 * @param int $product_id
	 */
	public static function refresh_wc_index( $product_id ) {
		$product_id = (int) $product_id;
		if ( function_exists( 'clean_post_cache' ) ) {
			clean_post_cache( $product_id );
		}
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $product_id );
		}
		if ( ! class_exists( 'WC_Data_Store' ) ) {
			return;
		}
		try {
			$store = WC_Data_Store::load( 'product' );
			if ( $store && method_exists( $store, 'update_lookup_table' ) ) {
				$store->update_lookup_table( $product_id, 'wc_product_meta_lookup' );
			}
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		}
	}
}
