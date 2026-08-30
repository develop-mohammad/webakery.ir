<?php
defined( 'ABSPATH' ) || exit;

/**
 * اعمال گروهی تخفیف روی همه محصولات یک دسته‌بندی — تابع معکوسِ WDP_Assigner:
 * به‌جای تشخیصِ تخفیفی که قبلاً روی محصول گذاشته‌اید، اینجا خودِ افزونه
 * «قیمت حراج» ووکامرس را برای همه محصولات یک دسته‌بندی تنظیم می‌کند.
 * بعد از اعمال، WDP_Assigner طبق معمول محصول را به صفحه تخفیف درست می‌فرستد.
 */
class WDP_Bulk {

	/**
	 * شناسه همه محصولات یک یا چند دسته‌بندی (با/بدون زیردسته‌ها).
	 *
	 * @return int[]
	 */
	public static function get_category_product_ids( array $category_ids, $include_children = true ) {
		$category_ids = array_values( array_filter( array_map( 'intval', $category_ids ) ) );
		if ( ! $category_ids ) {
			return array();
		}

		$ids = $category_ids;
		if ( $include_children ) {
			foreach ( $category_ids as $cat_id ) {
				$children = get_term_children( $cat_id, 'product_cat' );
				if ( is_array( $children ) ) {
					$ids = array_merge( $ids, array_map( 'intval', $children ) );
				}
			}
			$ids = array_values( array_unique( $ids ) );
		}

		$products = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $ids,
					),
				),
			)
		);

		return array_map( 'intval', $products );
	}

	/**
	 * تخفیف داده‌شده را روی همه محصولات دسته‌بندی(ها) اعمال می‌کند (تنظیم «قیمت حراج» ووکامرس).
	 *
	 * @param int[]  $category_ids
	 * @param bool   $include_children شامل زیردسته‌ها هم بشود؟
	 * @param string $type             percent|fixed
	 * @param float  $value            مقدار تخفیف
	 * @param bool   $overwrite        روی محصولاتی هم که از قبل تخفیف فعال دارند اعمال شود؟
	 * @param string $date_from        تاریخ شروع تخفیف (اختیاری، فرمت Y-m-d)
	 * @param string $date_to          تاریخ پایان تخفیف (اختیاری، فرمت Y-m-d)
	 *
	 * @return int تعداد محصولاتی که تخفیف روی آن‌ها اعمال شد
	 */
	public static function apply( array $category_ids, $include_children, $type, $value, $overwrite, $date_from = '', $date_to = '' ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return 0;
		}
		$type  = 'fixed' === $type ? 'fixed' : 'percent';
		$value = (float) $value;
		if ( $value <= 0 ) {
			return 0;
		}

		$decimals    = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 0;
		$product_ids = self::get_category_product_ids( $category_ids, $include_children );
		$count       = 0;

		foreach ( $product_ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}
			if ( ! $overwrite && $product->is_on_sale() ) {
				continue;
			}

			$changed = false;

			if ( $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $variation_id ) {
					$variation = wc_get_product( $variation_id );
					if ( ! $variation ) {
						continue;
					}
					if ( ! $overwrite && $variation->is_on_sale() ) {
						continue;
					}
					$sale = WDP_Util::calc_sale_price( $variation->get_regular_price(), $type, $value, $decimals );
					if ( null === $sale ) {
						continue;
					}
					$variation->set_sale_price( (string) $sale );
					self::apply_dates( $variation, $date_from, $date_to );
					$variation->save();
					$changed = true;
				}
			} else {
				$sale = WDP_Util::calc_sale_price( $product->get_regular_price(), $type, $value, $decimals );
				if ( null !== $sale ) {
					$product->set_sale_price( (string) $sale );
					self::apply_dates( $product, $date_from, $date_to );
					$product->save();
					$changed = true;
				}
			}

			if ( $changed ) {
				$count++;
				if ( class_exists( 'WDP_Assigner' ) ) {
					WDP_Assigner::assign( $id ); // اطمینان از به‌روزرسانی فوری صفحه تخفیف.
				}
			}
		}

		return $count;
	}

	/** حذف تخفیف اعمال‌شده از همه محصولات دسته‌بندی(ها) (بازگرداندن به قیمت عادی) */
	public static function revert( array $category_ids, $include_children ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return 0;
		}

		$product_ids = self::get_category_product_ids( $category_ids, $include_children );
		$count       = 0;

		foreach ( $product_ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}

			$changed = false;

			if ( $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $variation_id ) {
					$variation = wc_get_product( $variation_id );
					if ( ! $variation || '' === $variation->get_sale_price() ) {
						continue;
					}
					$variation->set_sale_price( '' );
					self::apply_dates( $variation, '', '' );
					$variation->save();
					$changed = true;
				}
			} else {
				if ( '' !== $product->get_sale_price() ) {
					$product->set_sale_price( '' );
					self::apply_dates( $product, '', '' );
					$product->save();
					$changed = true;
				}
			}

			if ( $changed ) {
				$count++;
				if ( class_exists( 'WDP_Assigner' ) ) {
					WDP_Assigner::assign( $id );
				}
			}
		}

		return $count;
	}

	protected static function apply_dates( $product, $date_from, $date_to ) {
		$product->set_date_on_sale_from( $date_from ? $date_from : '' );
		$product->set_date_on_sale_to( $date_to ? $date_to : '' );
	}
}
