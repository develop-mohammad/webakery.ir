<?php
defined( 'ABSPATH' ) || exit;

/**
 * موتور تشخیص و اختصاص خودکار «صفحه تخفیف».
 *
 * بهینه‌سازی‌ها:
 * - اختصاص تک‌محصول در پایان همان درخواست ذخیره
 * - بازبینی سراسری به‌صورت دسته‌ای در پس‌زمینه (بدون قفل کردن پیشخوان)
 * - رد کردن به‌روزرسانی وقتی ترم/متای تخفیف تغییری نکرده
 * - کش قوانین صفحه تخفیف در طول یک درخواست
 */
class WDP_Assigner {

	const META_PERCENT = '_wdp_discount_percent';
	const META_FIXED    = '_wdp_discount_fixed';

	const QUEUE_OPTION = 'wdp_recalc_queue';
	const BATCH_HOOK   = 'wdp_recalculate_batch';
	const BATCH_SIZE   = 50;

	/** @var array<int,true> */
	private static $pending = array();

	/** @var bool */
	private static $shutdown_hooked = false;

	/** @var bool */
	private static $assigning = false;

	/** @var array|null */
	private static $rules_cache = null;

	public static function register() {
		add_action( 'woocommerce_after_product_object_save', array( __CLASS__, 'on_product_object_save' ), 20, 1 );
		add_action( 'woocommerce_new_product', array( __CLASS__, 'queue_assign' ), 20 );
		add_action( 'woocommerce_update_product', array( __CLASS__, 'queue_assign' ), 20 );
		add_action( 'woocommerce_new_product_variation', array( __CLASS__, 'queue_assign_from_variation' ) );
		add_action( 'woocommerce_update_product_variation', array( __CLASS__, 'queue_assign_from_variation' ) );
		add_action( 'save_post_product', array( __CLASS__, 'on_save_post' ), 40, 3 );

		add_action( 'woocommerce_product_quick_edit_save', array( __CLASS__, 'on_quick_or_bulk_save' ) );
		add_action( 'woocommerce_product_bulk_edit_save', array( __CLASS__, 'on_quick_or_bulk_save' ) );

		// فقط شروع/پایان تخفیف زمان‌بندی‌شده — بدون هوک عمومی updated_post_meta (سنگین است).
		add_action( 'woocommerce_scheduled_sales', array( __CLASS__, 'schedule_recalculate' ), 20 );

		add_action( self::BATCH_HOOK, array( __CLASS__, 'process_batch' ) );

		add_filter( 'bulk_actions-edit-product', array( __CLASS__, 'register_bulk_action' ) );
		add_filter( 'handle_bulk_actions-edit-product', array( __CLASS__, 'handle_bulk_action' ), 10, 3 );

		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'set_object_terms', array( __CLASS__, 'on_terms_changed' ), 10, 4 );
	}

	/** پاک کردن کش قوانین (بعد از ذخیره صفحه تخفیف). */
	public static function clear_rules_cache() {
		self::$rules_cache = null;
	}

	/**
	 * قوانین صفحه تخفیف با کش درخواستی.
	 *
	 * @return array
	 */
	public static function rules() {
		if ( null === self::$rules_cache ) {
			self::$rules_cache = WDP_Taxonomy::all_rules();
		}
		return self::$rules_cache;
	}

	/**
	 * @param int|WC_Product $product_id_or_object
	 */
	public static function queue_assign( $product_id_or_object ) {
		$product_id = self::normalize_product_id( $product_id_or_object );
		if ( ! $product_id ) {
			return;
		}

		$type = get_post_type( $product_id );
		if ( 'product_variation' === $type ) {
			$parent = (int) wp_get_post_parent_id( $product_id );
			if ( ! $parent ) {
				return;
			}
			$product_id = $parent;
		} elseif ( 'product' !== $type ) {
			return;
		}

		self::$pending[ $product_id ] = true;
		if ( ! self::$shutdown_hooked ) {
			self::$shutdown_hooked = true;
			add_action( 'shutdown', array( __CLASS__, 'flush_pending' ), 5 );
		}
	}

	public static function flush_pending() {
		if ( ! self::$pending || self::$assigning ) {
			return;
		}
		$ids           = array_keys( self::$pending );
		self::$pending = array();
		foreach ( $ids as $id ) {
			self::assign( $id );
		}
	}

	public static function on_product_object_save( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		if ( $product->is_type( 'variation' ) ) {
			$parent = (int) $product->get_parent_id();
			if ( $parent ) {
				self::$pending[ $parent ] = true;
				if ( ! self::$shutdown_hooked ) {
					self::$shutdown_hooked = true;
					add_action( 'shutdown', array( __CLASS__, 'flush_pending' ), 5 );
				}
			}
			return;
		}
		$id = (int) $product->get_id();
		if ( $id ) {
			self::$pending[ $id ] = true;
			if ( ! self::$shutdown_hooked ) {
				self::$shutdown_hooked = true;
				add_action( 'shutdown', array( __CLASS__, 'flush_pending' ), 5 );
			}
		}
	}

	public static function on_terms_changed( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( self::$assigning || 'product_cat' !== $taxonomy ) {
			return;
		}
		if ( 'product' !== get_post_type( $object_id ) ) {
			return;
		}
		self::queue_assign( $object_id );
	}

	public static function on_save_post( $post_id, $post, $update ) {
		unset( $post, $update );
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		self::queue_assign( $post_id );
	}

	public static function on_quick_or_bulk_save( $product ) {
		if ( $product instanceof WC_Product ) {
			self::queue_assign( $product->get_id() );
		}
	}

	public static function queue_assign_from_variation( $variation_id ) {
		self::queue_assign( (int) $variation_id );
	}

	/**
	 * @param int|WC_Product $product_id_or_object
	 * @return int
	 */
	private static function normalize_product_id( $product_id_or_object ) {
		if ( $product_id_or_object instanceof WC_Product ) {
			return (int) $product_id_or_object->get_id();
		}
		return (int) $product_id_or_object;
	}

	/**
	 * @param int|WC_Product  $product_id_or_object
	 * @param WC_Product|null $product
	 * @param bool            $bust_cache قبل از خواندن، کش محصول را پاک کن (برای مسیرهای غیر CRUD)
	 */
	public static function assign( $product_id_or_object, $product = null, $bust_cache = false ) {
		unset( $product );
		if ( ! class_exists( 'WooCommerce' ) || self::$assigning ) {
			return;
		}

		$product_id = self::normalize_product_id( $product_id_or_object );
		if ( ! $product_id ) {
			return;
		}

		$type = get_post_type( $product_id );
		if ( 'product_variation' === $type ) {
			$parent = (int) wp_get_post_parent_id( $product_id );
			if ( $parent ) {
				self::assign( $parent, null, $bust_cache );
			}
			return;
		}
		if ( 'product' !== $type ) {
			return;
		}

		if ( $bust_cache ) {
			if ( function_exists( 'wc_delete_product_transients' ) ) {
				wc_delete_product_transients( $product_id );
			}
			clean_post_cache( $product_id );
			wp_cache_delete( 'product-' . $product_id, 'products' );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		self::$assigning = true;
		unset( self::$pending[ $product_id ] );
		try {
			$discount   = self::compute( $product );
			$categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
			$categories = is_wp_error( $categories ) ? array() : array_map( 'intval', $categories );
			$term_id    = $discount ? WDP_Util::find_best_match( self::rules(), $discount, $categories ) : null;
			$term_id    = $term_id ? (int) $term_id : null;

			$current = wp_get_object_terms( $product_id, WDP_Taxonomy::TAXONOMY, array( 'fields' => 'ids' ) );
			$current = is_wp_error( $current ) ? array() : array_map( 'intval', $current );
			$current_id = $current ? (int) $current[0] : null;

			$terms_changed = ( $current_id !== $term_id );
			if ( $terms_changed ) {
				if ( $term_id ) {
					wp_set_object_terms( $product_id, array( $term_id ), WDP_Taxonomy::TAXONOMY, false );
				} else {
					wp_set_object_terms( $product_id, array(), WDP_Taxonomy::TAXONOMY, false );
				}
			}

			$old_percent = get_post_meta( $product_id, self::META_PERCENT, true );
			$old_fixed   = get_post_meta( $product_id, self::META_FIXED, true );
			if ( $discount ) {
				if ( (string) $old_percent !== (string) $discount['percent'] ) {
					update_post_meta( $product_id, self::META_PERCENT, $discount['percent'] );
				}
				if ( (string) $old_fixed !== (string) $discount['fixed'] ) {
					update_post_meta( $product_id, self::META_FIXED, $discount['fixed'] );
				}
			} elseif ( '' !== $old_percent || '' !== $old_fixed ) {
				delete_post_meta( $product_id, self::META_PERCENT );
				delete_post_meta( $product_id, self::META_FIXED );
			}

			if ( $terms_changed ) {
				do_action( 'wdp_product_assigned', $product_id, $term_id, $discount );
			}
		} finally {
			self::$assigning = false;
		}
	}

	/**
	 * @return array{percent:float,fixed:float}|null
	 */
	public static function compute( WC_Product $product ) {
		if ( ! $product->is_on_sale() ) {
			return null;
		}

		if ( $product->is_type( 'variable' ) && method_exists( $product, 'get_variation_regular_price' ) ) {
			$regular = (float) $product->get_variation_regular_price( 'min', true );
			$sale    = (float) $product->get_variation_sale_price( 'min', true );
		} else {
			$regular = (float) $product->get_regular_price();
			$sale    = (float) $product->get_sale_price();
		}

		return WDP_Util::compute_discount( $regular, $sale );
	}

	/* ─── بازبینی دسته‌ای پس‌زمینه ─────────────────────────────── */

	/**
	 * شناسه محصولاتی که باید بازبینی شوند.
	 *
	 * @return int[]
	 */
	public static function collect_recalc_ids() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}

		$ids = function_exists( 'wc_get_product_ids_on_sale' ) ? (array) wc_get_product_ids_on_sale() : array();

		$terms = get_terms(
			array(
				'taxonomy'   => WDP_Taxonomy::TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term_id ) {
				$assigned = get_objects_in_term( $term_id, WDP_Taxonomy::TAXONOMY );
				if ( ! is_wp_error( $assigned ) ) {
					$ids = array_merge( $ids, array_map( 'intval', $assigned ) );
				}
			}
		}

		return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
	}

	/**
	 * صف بازبینی سراسری در پس‌زمینه (بدون قفل کردن صفحه).
	 *
	 * @return int تعداد محصول در صف
	 */
	public static function schedule_recalculate() {
		$ids = self::collect_recalc_ids();
		update_option(
			self::QUEUE_OPTION,
			array(
				'ids'     => $ids,
				'done'    => 0,
				'total'   => count( $ids ),
				'started' => time(),
			),
			false
		);

		self::clear_rules_cache();
		self::ensure_batch_scheduled( 1 );

		return count( $ids );
	}

	/**
	 * وضعیت صف بازبینی پس‌زمینه.
	 *
	 * @return array{running:bool,done:int,total:int}|null
	 */
	public static function queue_status() {
		$state = get_option( self::QUEUE_OPTION, null );
		if ( ! is_array( $state ) || empty( $state['total'] ) ) {
			return null;
		}
		return array(
			'running' => true,
			'done'    => (int) ( $state['done'] ?? 0 ),
			'total'   => (int) $state['total'],
		);
	}

	private static function ensure_batch_scheduled( $delay_seconds = 1 ) {
		if ( ! wp_next_scheduled( self::BATCH_HOOK ) ) {
			wp_schedule_single_event( time() + max( 1, (int) $delay_seconds ), self::BATCH_HOOK );
		}
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	/** یک دسته از صف را پردازش می‌کند و در صورت نیاز دسته بعد را زمان‌بندی می‌کند. */
	public static function process_batch() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			delete_option( self::QUEUE_OPTION );
			return;
		}

		$state = get_option( self::QUEUE_OPTION, null );
		if ( ! is_array( $state ) || empty( $state['ids'] ) || ! is_array( $state['ids'] ) ) {
			delete_option( self::QUEUE_OPTION );
			return;
		}

		$ids   = array_map( 'intval', $state['ids'] );
		$done  = max( 0, (int) ( $state['done'] ?? 0 ) );
		$total = (int) ( $state['total'] ?? count( $ids ) );
		$slice = array_slice( $ids, $done, self::BATCH_SIZE );

		// کش قوانین یک‌بار برای کل دسته.
		self::rules();

		foreach ( $slice as $id ) {
			self::assign( $id, null, false );
		}

		$done += count( $slice );

		if ( $done >= $total || ! $slice ) {
			delete_option( self::QUEUE_OPTION );

			$log = get_option( 'wdp_log', array() );
			$log = is_array( $log ) ? $log : array();
			array_unshift(
				$log,
				array(
					'time'  => time(),
					'count' => $total,
				)
			);
			update_option( 'wdp_log', array_slice( $log, 0, 20 ), false );
			return;
		}

		$state['done'] = $done;
		update_option( self::QUEUE_OPTION, $state, false );
		self::ensure_batch_scheduled( 2 );
	}

	/**
	 * سازگاری با کد قدیمی: بازبینی هم‌زمان همه (فقط برای تست/ابزار).
	 * در پیشخوان از schedule_recalculate استفاده شود.
	 *
	 * @return int
	 */
	public static function recalculate_all() {
		$ids = self::collect_recalc_ids();
		self::clear_rules_cache();
		self::rules();
		foreach ( $ids as $id ) {
			self::assign( $id, null, false );
		}
		return count( $ids );
	}

	/* ─── عیب‌یابی ─────────────────────────────────────────────── */

	public static function diagnose( $product_id ) {
		$product_id = (int) $product_id;
		$result     = array(
			'product_id' => $product_id,
			'exists'     => false,
			'licensed'   => WDP_Plugin::licensed(),
			'woo'        => class_exists( 'WooCommerce' ),
		);

		if ( ! $result['woo'] || ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			return $result;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return $result;
		}

		$result['exists']     = true;
		$result['name']       = $product->get_name();
		$result['is_on_sale'] = $product->is_on_sale();
		$result['edit_link']  = get_edit_post_link( $product_id, 'raw' );

		if ( $product->is_type( 'variable' ) && method_exists( $product, 'get_variation_regular_price' ) ) {
			$result['regular'] = (float) $product->get_variation_regular_price( 'min', true );
			$result['sale']    = (float) $product->get_variation_sale_price( 'min', true );
		} else {
			$result['regular'] = (float) $product->get_regular_price();
			$result['sale']    = (float) $product->get_sale_price();
		}

		$result['discount'] = self::compute( $product );

		$cat_ids                  = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
		$cat_ids                  = is_wp_error( $cat_ids ) ? array() : array_map( 'intval', $cat_ids );
		$result['category_ids']   = $cat_ids;
		$result['category_names'] = array();
		foreach ( $cat_ids as $cat_id ) {
			$cat_term = get_term( $cat_id, 'product_cat' );
			if ( $cat_term && ! is_wp_error( $cat_term ) ) {
				$result['category_names'][] = $cat_term->name;
			}
		}

		$rules  = self::rules();
		$checks = array();
		foreach ( $rules as $rule ) {
			$term      = get_term( $rule['term_id'], WDP_Taxonomy::TAXONOMY );
			$term_name = ( $term && ! is_wp_error( $term ) ) ? $term->name : ( '#' . $rule['term_id'] );

			$cat_ok = empty( $rule['categories'] ) || array_intersect( $rule['categories'], $cat_ids );

			$value_ok  = false;
			$value_now = null;
			if ( $result['discount'] ) {
				$value_now = $result['discount'][ $rule['type'] ];
				$min       = min( $rule['min'], $rule['max'] );
				$max       = max( $rule['min'], $rule['max'] );
				$value_ok  = ( $value_now >= $min - 0.001 && $value_now <= $max + 0.001 );
			}

			$checks[] = array(
				'term_id'    => $rule['term_id'],
				'name'       => $term_name,
				'type'       => $rule['type'],
				'min'        => $rule['min'],
				'max'        => $rule['max'],
				'categories' => $rule['categories'],
				'cat_ok'     => (bool) $cat_ok,
				'value_ok'   => $value_ok,
				'value_now'  => $value_now,
				'match'      => $cat_ok && $value_ok,
			);
		}
		$result['rule_checks'] = $checks;

		$result['matched_term_id'] = $result['discount'] ? WDP_Util::find_best_match( $rules, $result['discount'], $cat_ids ) : null;

		$current_terms           = wp_get_object_terms( $product_id, WDP_Taxonomy::TAXONOMY, array( 'fields' => 'ids' ) );
		$result['current_terms'] = is_wp_error( $current_terms ) ? array() : array_map( 'intval', $current_terms );

		return $result;
	}

	/* ─── اکشن دسته‌ای فهرست محصولات ───────────────────────────── */

	public static function register_bulk_action( $actions ) {
		$actions['wdp_recalculate'] = 'بازبینی صفحه تخفیف (Webakery)';
		return $actions;
	}

	public static function handle_bulk_action( $redirect_to, $action, $ids ) {
		if ( 'wdp_recalculate' !== $action ) {
			return $redirect_to;
		}
		foreach ( (array) $ids as $id ) {
			self::assign( (int) $id );
		}
		return add_query_arg( 'wdp_recalculated', count( (array) $ids ), $redirect_to );
	}

	/* ─── متاباکس ──────────────────────────────────────────────── */

	public static function add_meta_box() {
		add_meta_box(
			'wdp_status',
			'🏷️ صفحه تخفیف (Webakery)',
			array( __CLASS__, 'render_meta_box' ),
			'product',
			'side',
			'default'
		);
	}

	public static function render_meta_box( $post ) {
		$percent = get_post_meta( $post->ID, self::META_PERCENT, true );
		$fixed   = get_post_meta( $post->ID, self::META_FIXED, true );
		$terms   = get_the_terms( $post->ID, WDP_Taxonomy::TAXONOMY );

		echo '<div class="wdp-metabox">';

		if ( '' === $percent && '' === $fixed ) {
			echo '<p class="wdp-muted">این محصول الان تخفیف فعالی ندارد.</p>';
		} else {
			echo '<p>تخفیف فعلی: <strong>' . esc_html( WDP_Util::fa_digits( WDP_Util::trim_zeros( $percent ) ) ) . '٪</strong>'
				. ' (' . esc_html( WDP_Util::fa_digits( WDP_Util::trim_zeros( $fixed ) ) ) . ' ' . esc_html( WDP_Taxonomy::currency() ) . ')</p>';
		}

		if ( $terms && ! is_wp_error( $terms ) ) {
			echo '<p>صفحه تخفیف: ';
			foreach ( $terms as $term ) {
				$link = get_term_link( $term );
				if ( ! is_wp_error( $link ) ) {
					echo '<a href="' . esc_url( $link ) . '" target="_blank" rel="noopener">' . esc_html( $term->name ) . '</a> ';
				}
			}
			echo '</p>';
		} else {
			echo '<p class="wdp-muted">در هیچ صفحه تخفیفی قرار ندارد.</p>';
		}

		echo '<p class="wdp-hint">بعد از ذخیره محصول یا تغییر قیمت حراج، خودکار به‌روز می‌شود.</p>';
		echo '</div>';
	}
}
