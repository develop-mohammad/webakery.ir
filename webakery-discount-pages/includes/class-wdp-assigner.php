<?php
defined( 'ABSPATH' ) || exit;

/**
 * موتور تشخیص و اختصاص خودکار «صفحه تخفیف»:
 * برای هر محصول، درصد/مبلغ تخفیف فعلی را از قیمت اصلی و قیمت فروش ووکامرس
 * حساب می‌کند و محصول را به صفحه تخفیف منطبق می‌فرستد. با تغییر تخفیف محصول
 * (مثلاً از ۲۰٪ به ۵۰٪)، محصول خودکار از صفحه قبلی خارج و به صفحه درست منتقل می‌شود.
 */
class WDP_Assigner {

	const META_PERCENT = '_wdp_discount_percent';
	const META_FIXED    = '_wdp_discount_fixed';

	public static function register() {
		add_action( 'woocommerce_new_product', array( __CLASS__, 'assign' ), 20, 2 );
		add_action( 'woocommerce_update_product', array( __CLASS__, 'assign' ), 20, 2 );
		add_action( 'woocommerce_new_product_variation', array( __CLASS__, 'assign_from_variation' ) );
		add_action( 'woocommerce_update_product_variation', array( __CLASS__, 'assign_from_variation' ) );
		add_action( 'save_post_product', array( __CLASS__, 'on_save_post' ), 30, 3 );

		add_filter( 'bulk_actions-edit-product', array( __CLASS__, 'register_bulk_action' ) );
		add_filter( 'handle_bulk_actions-edit-product', array( __CLASS__, 'handle_bulk_action' ), 10, 3 );

		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );

		// اگر دسته‌بندی محصول (product_cat) عوض شود -- حتی بدون ذخیره کامل محصول
		// (مثلاً ویرایش سریع یا REST API) -- صفحه تخفیف را دوباره بررسی کن، چون
		// بعضی صفحه‌های تخفیف ممکن است به دسته‌بندی محدود شده باشند.
		add_action( 'set_object_terms', array( __CLASS__, 'on_terms_changed' ), 10, 4 );
	}

	public static function on_terms_changed( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( 'product_cat' !== $taxonomy || 'product' !== get_post_type( $object_id ) ) {
			return;
		}
		self::assign( $object_id );
	}

	/* ─── هوک‌های ووکامرس ──────────────────────────────────────── */

	public static function on_save_post( $post_id, $post, $update ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		self::assign( $post_id );
	}

	public static function assign_from_variation( $variation_id ) {
		$parent_id = wp_get_post_parent_id( $variation_id );
		if ( $parent_id ) {
			self::assign( $parent_id );
		}
	}

	/**
	 * محصول را بررسی و به صفحه تخفیف درست می‌فرستد (یا از همه صفحه‌ها حذف می‌کند).
	 * هم با شناسه عددی و هم با شیء WC_Product قابل فراخوانی است (سازگار با
	 * امضای هوک‌های مختلف نسخه‌های ووکامرس).
	 *
	 * @param int|WC_Product $product_id_or_object
	 * @param WC_Product|null $product
	 */
	public static function assign( $product_id_or_object, $product = null ) {
		if ( ! class_exists( 'WooCommerce' ) || ! WDP_Plugin::licensed() ) {
			return;
		}

		if ( $product instanceof WC_Product ) {
			$product_id = $product->get_id();
		} elseif ( $product_id_or_object instanceof WC_Product ) {
			$product    = $product_id_or_object;
			$product_id = $product->get_id();
		} else {
			$product_id = (int) $product_id_or_object;
			$product    = null;
		}

		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			return;
		}
		if ( ! $product ) {
			$product = wc_get_product( $product_id );
		}
		if ( ! $product ) {
			return;
		}

		$discount   = self::compute( $product );
		$categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
		$categories = is_wp_error( $categories ) ? array() : $categories;
		$term_id    = $discount ? WDP_Util::find_best_match( WDP_Taxonomy::all_rules(), $discount, $categories ) : null;

		if ( $term_id ) {
			wp_set_object_terms( $product_id, array( $term_id ), WDP_Taxonomy::TAXONOMY, false );
		} else {
			wp_set_object_terms( $product_id, array(), WDP_Taxonomy::TAXONOMY, false );
		}

		if ( $discount ) {
			update_post_meta( $product_id, self::META_PERCENT, $discount['percent'] );
			update_post_meta( $product_id, self::META_FIXED, $discount['fixed'] );
		} else {
			delete_post_meta( $product_id, self::META_PERCENT );
			delete_post_meta( $product_id, self::META_FIXED );
		}

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $product_id );
		}

		/**
		 * پس از اختصاص/حذف صفحه تخفیف یک محصول.
		 *
		 * @param int        $product_id
		 * @param int|null   $term_id
		 * @param array|null $discount
		 */
		do_action( 'wdp_product_assigned', $product_id, $term_id, $discount );
	}

	/**
	 * محاسبه تخفیف فعلی محصول (درصد و مبلغ)؛ null یعنی محصول الان تخفیف فعالی ندارد.
	 * برای محصولات متغیر، کمترین قیمت متغیرها ملاک است.
	 *
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

	/* ─── بازبینی همه محصولات ──────────────────────────────────── */

	/**
	 * محصولاتی که الان تخفیف دارند + محصولاتی که قبلاً به یک صفحه تخفیف
	 * وصل بودند را دوباره بررسی می‌کند (برای شروع/پایان تخفیف زمان‌بندی‌شده
	 * ووکامرس، یا تغییر بازه یک صفحه).
	 *
	 * @return int تعداد محصولات بررسی‌شده
	 */
	public static function recalculate_all() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return 0;
		}

		$ids = function_exists( 'wc_get_product_ids_on_sale' ) ? wc_get_product_ids_on_sale() : array();

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
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );

		foreach ( $ids as $id ) {
			self::assign( $id );
		}
		return count( $ids );
	}

	/* ─── ابزار بررسی محصول (چرا این محصول در صفحه‌ای قرار نگرفت؟) ──── */

	/**
	 * گزارش کامل وضعیت یک محصول برای عیب‌یابی: تخفیف فعلی، دسته‌بندی‌ها،
	 * نتیجه بررسی هر صفحه تخفیف و اینکه کدام صفحه انتخاب می‌شود.
	 *
	 * @return array
	 */
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

		$cat_ids               = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
		$cat_ids               = is_wp_error( $cat_ids ) ? array() : array_map( 'intval', $cat_ids );
		$result['category_ids']   = $cat_ids;
		$result['category_names'] = array();
		foreach ( $cat_ids as $cat_id ) {
			$cat_term = get_term( $cat_id, 'product_cat' );
			if ( $cat_term && ! is_wp_error( $cat_term ) ) {
				$result['category_names'][] = $cat_term->name;
			}
		}

		$rules  = WDP_Taxonomy::all_rules();
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

		$current_terms          = wp_get_object_terms( $product_id, WDP_Taxonomy::TAXONOMY, array( 'fields' => 'ids' ) );
		$result['current_terms'] = is_wp_error( $current_terms ) ? array() : array_map( 'intval', $current_terms );

		return $result;
	}

	/* ─── اکشن دسته‌ای در فهرست محصولات ──────────────────────────── */

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

	/* ─── جعبه اطلاعات در صفحه ویرایش محصول ─────────────────────── */

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

		echo '<p class="wdp-hint">این مقدار خودکار است و بعد از ذخیره محصول یا تغییر دسته‌بندی محصول به‌روزرسانی می‌شود.</p>';
		echo '</div>';
	}
}
