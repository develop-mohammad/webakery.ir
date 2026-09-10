<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * فیلدهای بچ کنار قیمت محصول / تنوع در پیشخوان.
 */
class WBE_Admin_Product {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'woocommerce_product_options_pricing', array( $this, 'render' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'sync_after_save' ), 40 );
		add_action( 'woocommerce_variation_options_pricing', array( $this, 'render_variation' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation' ), 20, 2 );
		add_filter( 'manage_edit-product_columns', array( $this, 'columns' ) );
		add_action( 'manage_product_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_filter( 'manage_edit-product_sortable_columns', array( $this, 'sortable' ) );
		add_action( 'admin_notices', array( $this, 'incomplete_notice' ) );
		add_action( 'wp_ajax_wbe_copy_variation_batches', array( $this, 'ajax_copy_variation' ) );
	}

	/**
	 * @param int $product_id
	 */
	public static function flag_incomplete_save( $product_id ) {
		$product_id = (int) $product_id;
		if ( $product_id <= 0 || ! function_exists( 'get_current_user_id' ) ) {
			return;
		}
		$key  = 'wbe_incomplete_batches_' . (int) get_current_user_id();
		$ids  = get_transient( $key );
		$ids  = is_array( $ids ) ? $ids : array();
		$ids[] = $product_id;
		set_transient( $key, array_values( array_unique( array_map( 'intval', $ids ) ) ), 10 * MINUTE_IN_SECONDS );
	}

	public function incomplete_notice() {
		if ( ! function_exists( 'get_current_user_id' ) || ! current_user_can( 'edit_products' ) ) {
			return;
		}
		$key = 'wbe_incomplete_batches_' . (int) get_current_user_id();
		$ids = get_transient( $key );
		if ( ! is_array( $ids ) || ! $ids ) {
			return;
		}
		delete_transient( $key );
		$msg = class_exists( 'WBE_Engine' ) ? WBE_Engine::incomplete_batches_notice() : 'تاریخ انقضا ذخیره نشد.';
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}

	public function ajax_copy_variation() {
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز' ), 403 );
		}
		check_ajax_referer( 'wbe_admin', 'nonce' );
		$from = isset( $_POST['from'] ) ? (int) $_POST['from'] : 0;
		if ( $from <= 0 ) {
			wp_send_json_error( array( 'message' => 'تنوع مبدأ مشخص نیست.' ) );
		}
		$override = isset( $_POST['calendar'] ) ? sanitize_key( wp_unslash( $_POST['calendar'] ) ) : '';
		$cal      = in_array( $override, array( 'jalali', 'gregorian' ), true ) ? $override : WBE_Product::calendar( $from );
		$active   = isset( $_POST['active'] ) && is_array( $_POST['active'] ) ? wp_unslash( $_POST['active'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$reserve  = isset( $_POST['reserve'] ) && is_array( $_POST['reserve'] ) ? wp_unslash( $_POST['reserve'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$rows     = WBE_Engine::merge_active_and_reserve_rows( $active, $reserve );
		$batches  = WBE_Engine::decide_posted_batches( $rows, $cal );
		if ( ! is_array( $batches ) || ! $batches ) {
			wp_send_json_error( array( 'message' => 'اول تاریخ انقضا را از تقویم انتخاب کنید، بعد کپی کنید.' ) );
		}
		$parent = WBE_Product::parent_id( $from );
		$ids    = array();
		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $parent );
			if ( $product && method_exists( $product, 'get_children' ) ) {
				$ids = WBE_Engine::sibling_ids( $from, $product->get_children() );
			}
		}
		if ( ! $ids ) {
			wp_send_json_error( array( 'message' => 'تنوع دیگری برای کپی نیست.' ) );
		}
		$hide = ! empty( $_POST['hide_countdown'] ); // phpcs:ignore WordPress.Security.NonceVerification
		foreach ( $ids as $id ) {
			if ( ! current_user_can( 'edit_post', $id ) && ! current_user_can( 'edit_products' ) ) {
				continue;
			}
			WBE_Product::save_batches( $id, $batches, $override, true );
			WBE_Product::save_hide_countdown( $id, $hide );
		}
		wp_send_json_success(
			array(
				'copied'  => count( $ids ),
				'message' => count( $ids ) . ' تنوع با همین بچ‌ها به‌روز شد. این تنوع را هم ذخیره کنید.',
			)
		);
	}

	public function render() {
		global $post;
		if ( ! $post ) {
			return;
		}
		$pid = (int) $post->ID;
		if ( function_exists( 'wc_get_product' ) ) {
			$wc_check = wc_get_product( $pid );
			// پنل ساده فقط برای محصول غیرمتغیر؛ متغیرها روی هر تنوع فیلد دارند.
			if ( $wc_check && method_exists( $wc_check, 'is_type' ) && $wc_check->is_type( 'variable' ) ) {
				return;
			}
		}
		$batches        = WBE_Product::batches( $pid );
		$override       = get_post_meta( $pid, WBE_Product::META_CALENDAR, true );
		$effective      = WBE_Product::calendar( $pid );
		$global         = WBE_Settings::calendar();
		$hide_cd        = (string) get_post_meta( $pid, WBE_Product::META_HIDE_COUNTDOWN, true ) === '1';
		$wc_price     = '';
		$wc_sale        = '';
		$wc_disc        = '';
		$wc_stock       = '';
		$sale_from_fa   = '';
		$sale_to_fa     = '';
		if ( function_exists( 'wc_get_product' ) ) {
			$wc_product = wc_get_product( $pid );
			if ( $wc_product ) {
				$wc_price  = $wc_product->get_regular_price( 'edit' );
				$wc_sale   = $wc_product->get_sale_price( 'edit' );
				$wc_stock  = $wc_product->get_stock_quantity( 'edit' );
				$wc_disc   = (string) WBE_Engine::discount_from_prices( $wc_price, $wc_sale );
				if ( '0' === $wc_disc ) {
					$wc_disc = '';
				}
				$pair         = WBE_Product::sale_ymd_pair( $wc_product );
				$sale_from_fa = $pair[0] ? WBE_Jalali::format_ymd( $pair[0], $effective, false ) : '';
				$sale_to_fa   = $pair[1] ? WBE_Jalali::format_ymd( $pair[1], $effective, false ) : '';
			}
		}
		include WBE_PATH . 'includes/views/product-batches.php';
	}

	/**
	 * فیلدهای بچ داخل هر ردیف تنوع.
	 *
	 * @param int     $loop
	 * @param array   $variation_data
	 * @param WP_Post $variation
	 */
	public function render_variation( $loop, $variation_data, $variation ) {
		unset( $variation_data );
		$loop = (int) $loop;
		$pid  = is_object( $variation ) && isset( $variation->ID ) ? (int) $variation->ID : 0;
		if ( ! $pid ) {
			return;
		}
		$batches      = WBE_Product::batches( $pid );
		$override     = get_post_meta( $pid, WBE_Product::META_CALENDAR, true );
		$effective    = WBE_Product::calendar( $pid );
		$global       = WBE_Settings::calendar();
		$hide_cd      = (string) get_post_meta( $pid, WBE_Product::META_HIDE_COUNTDOWN, true ) === '1';
		$wc_price     = '';
		$wc_sale      = '';
		$wc_disc      = '';
		$wc_stock     = '';
		$sale_from_fa = '';
		$sale_to_fa   = '';
		$attr_label   = WBE_Product::variation_attributes_label( $pid );
		if ( function_exists( 'wc_get_product' ) ) {
			$wc_product = wc_get_product( $pid );
			if ( $wc_product ) {
				$wc_price    = $wc_product->get_regular_price( 'edit' );
				$wc_sale     = $wc_product->get_sale_price( 'edit' );
				$wc_stock    = $wc_product->get_stock_quantity( 'edit' );
				$wc_disc     = (string) WBE_Engine::discount_from_prices( $wc_price, $wc_sale );
				if ( '0' === $wc_disc ) {
					$wc_disc = '';
				}
				$pair         = WBE_Product::sale_ymd_pair( $wc_product );
				$sale_from_fa = $pair[0] ? WBE_Jalali::format_ymd( $pair[0], $effective, false ) : '';
				$sale_to_fa   = $pair[1] ? WBE_Jalali::format_ymd( $pair[1], $effective, false ) : '';
			}
		}
		include WBE_PATH . 'includes/views/product-variation-batches.php';
	}

	public function save( $product ) {
		if ( ! $product || ! current_user_can( 'edit_products' ) ) {
			return;
		}
		if ( method_exists( $product, 'is_type' ) && $product->is_type( 'variable' ) ) {
			return;
		}
		if ( ! isset( $_POST['wbe_batches_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbe_batches_nonce'] ) ), 'wbe_save_batches' ) ) {
			return;
		}
		$pid      = (int) $product->get_id();
		$override = isset( $_POST['wbe_calendar'] ) ? sanitize_key( wp_unslash( $_POST['wbe_calendar'] ) ) : '';
		$cal      = in_array( $override, array( 'jalali', 'gregorian' ), true ) ? $override : WBE_Settings::calendar();

		$active  = isset( $_POST['wbe_active'] ) && is_array( $_POST['wbe_active'] ) ? wp_unslash( $_POST['wbe_active'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$reserve = isset( $_POST['wbe_reserve'] ) && is_array( $_POST['wbe_reserve'] ) ? wp_unslash( $_POST['wbe_reserve'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$rows    = WBE_Engine::merge_active_and_reserve_rows( $active, $reserve );

		// سازگاری با فرم قدیمی wbe_batches اگر هنوز ارسال شود.
		if ( ! $rows && isset( $_POST['wbe_batches'] ) && is_array( $_POST['wbe_batches'] ) ) {
			$rows = wp_unslash( $_POST['wbe_batches'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}

		$batches = WBE_Engine::decide_posted_batches( $rows, $cal );
		if ( null === $batches ) {
			self::flag_incomplete_save( $pid );
			$batches = WBE_Product::batches( $pid );
		}
		WBE_Product::save_batches( $pid, $batches, $override, false );
		WBE_Product::save_hide_countdown( $pid, ! empty( $_POST['wbe_hide_countdown'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

		$active = $batches ? WBE_Engine::active_index( $batches, class_exists( 'WBE_Jalali' ) ? WBE_Jalali::today_ymd() : gmdate( 'Y-m-d' ) ) : null;
		if ( null !== $active && isset( $batches[ $active ] ) && WBE_Engine::discount_of( $batches[ $active ] ) <= 0 ) {
			WBE_Product::push_wc_sale_dates( $pid, array( 'clear_sale' => true ) );
		} else {
			$date_ops = array();
			if ( isset( $_POST['wbe_sale_from'] ) && '' !== trim( (string) wp_unslash( $_POST['wbe_sale_from'] ) ) ) {
				$from = WBE_Jalali::parse_to_ymd( wp_unslash( $_POST['wbe_sale_from'] ), $cal );
				if ( '' !== $from ) {
					$date_ops['sale_from'] = $from;
				}
			}
			if ( isset( $_POST['wbe_sale_to'] ) && '' !== trim( (string) wp_unslash( $_POST['wbe_sale_to'] ) ) ) {
				$to = WBE_Jalali::parse_to_ymd( wp_unslash( $_POST['wbe_sale_to'] ), $cal );
				if ( '' !== $to ) {
					$date_ops['sale_to'] = $to;
				}
			}
			if ( $date_ops ) {
				WBE_Product::push_wc_sale_dates( $pid, $date_ops );
			}
		}
	}

	/**
	 * ذخیره بچ‌های یک تنوع.
	 *
	 * @param int $variation_id
	 * @param int $loop
	 */
	public function save_variation( $variation_id, $loop ) {
		$variation_id = (int) $variation_id;
		$loop         = (int) $loop;
		if ( ! $variation_id || ! current_user_can( 'edit_products' ) ) {
			return;
		}
		if ( ! isset( $_POST['wbe_batches_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbe_batches_nonce'] ) ), 'wbe_save_batches' ) ) {
			return;
		}
		$all  = isset( $_POST['wbe_var'] ) && is_array( $_POST['wbe_var'] ) ? wp_unslash( $_POST['wbe_var'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$data = WBE_Engine::posted_variation_data( $all, $variation_id, $loop );
		if ( ! is_array( $data ) ) {
			return;
		}
		$override = isset( $data['calendar'] ) ? sanitize_key( $data['calendar'] ) : '';
		$cal      = in_array( $override, array( 'jalali', 'gregorian' ), true ) ? $override : WBE_Product::calendar( $variation_id );

		$active  = ( ! empty( $data['active'] ) && is_array( $data['active'] ) ) ? $data['active'] : array();
		$reserve = ( ! empty( $data['reserve'] ) && is_array( $data['reserve'] ) ) ? $data['reserve'] : array();
		$rows    = WBE_Engine::merge_active_and_reserve_rows( $active, $reserve );
		$batches = WBE_Engine::decide_posted_batches( $rows, $cal );
		if ( null === $batches ) {
			self::flag_incomplete_save( $variation_id );
			$batches = WBE_Product::batches( $variation_id );
		}
		WBE_Product::save_batches( $variation_id, $batches, $override, false );
		WBE_Product::save_hide_countdown( $variation_id, ! empty( $data['hide_countdown'] ) );

		$active_i = $batches ? WBE_Engine::active_index( $batches, class_exists( 'WBE_Jalali' ) ? WBE_Jalali::today_ymd() : gmdate( 'Y-m-d' ) ) : null;
		if ( null !== $active_i && isset( $batches[ $active_i ] ) && WBE_Engine::discount_of( $batches[ $active_i ] ) <= 0 ) {
			WBE_Product::push_wc_sale_dates( $variation_id, array( 'clear_sale' => true ) );
		} else {
			$date_ops = array();
			if ( isset( $data['sale_from'] ) && '' !== trim( (string) $data['sale_from'] ) ) {
				$from = WBE_Jalali::parse_to_ymd( $data['sale_from'], $cal );
				if ( '' !== $from ) {
					$date_ops['sale_from'] = $from;
				}
			}
			if ( isset( $data['sale_to'] ) && '' !== trim( (string) $data['sale_to'] ) ) {
				$to = WBE_Jalali::parse_to_ymd( $data['sale_to'], $cal );
				if ( '' !== $to ) {
					$date_ops['sale_to'] = $to;
				}
			}
			if ( $date_ops ) {
				WBE_Product::push_wc_sale_dates( $variation_id, $date_ops );
			}
		}
		WBE_Product::sync_wc( $variation_id );
	}

	public function sync_after_save( $product_id ) {
		$product_id = (int) $product_id;
		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( $product && method_exists( $product, 'is_type' ) && $product->is_type( 'variable' ) ) {
				return;
			}
		}
		WBE_Product::sync_wc( $product_id );
	}

	public function columns( $cols ) {
		$out = array();
		foreach ( $cols as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'price' === $key ) {
				$out['wbe_expiry'] = 'تاریخ انقضا';
			}
		}
		if ( ! isset( $out['wbe_expiry'] ) ) {
			$out['wbe_expiry'] = 'تاریخ انقضا';
		}
		return $out;
	}

	public function column( $column, $post_id ) {
		if ( 'wbe_expiry' !== $column ) {
			return;
		}
		$post_id = (int) $post_id;
		$exp     = get_post_meta( $post_id, WBE_Product::META_ACTIVE_EXPIRY, true );
		if ( ! $exp && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post_id );
			if ( $product && method_exists( $product, 'is_type' ) && $product->is_type( 'variable' ) && method_exists( $product, 'get_children' ) ) {
				$nearest = '';
				foreach ( $product->get_children() as $vid ) {
					$a = WBE_Product::active( (int) $vid );
					if ( $a && ! empty( $a['expiry'] ) && ( '' === $nearest || $a['expiry'] < $nearest ) ) {
						$nearest = $a['expiry'];
					}
				}
				$exp = $nearest;
			}
		}
		if ( ! $exp ) {
			$active = WBE_Product::active( $post_id );
			$exp    = $active ? $active['expiry'] : '';
		}
		if ( ! $exp ) {
			echo '—';
			return;
		}
		echo esc_html( WBE_Jalali::format_ymd( $exp, WBE_Product::calendar( $post_id ), true ) );
	}

	public function sortable( $cols ) {
		$cols['wbe_expiry'] = 'wbe_expiry';
		return $cols;
	}
}
