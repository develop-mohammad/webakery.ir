<?php
defined( 'ABSPATH' ) || exit;

/**
 * موجودی محصولات — فقط از API ووکامرس (ساده، متغیر، وارییشن).
 * هیچ موجودی سفارشی در حسابدار نگه داشته نمی‌شود.
 */
class WAP_Stock {

	/**
	 * شناسه محصول قابل‌موجودی از آیتم سفارش:
	 * وارییشن اگر باشد، وگرنه محصول ساده/والد.
	 *
	 * @param WC_Order_Item_Product|object $item
	 */
	public static function id_from_order_item( $item ): int {
		if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) ) {
			return 0;
		}
		$vid = method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : 0;
		if ( $vid > 0 ) {
			return $vid;
		}
		return (int) $item->get_product_id();
	}

	/**
	 * موجودی عددی محصول از ووکامرس.
	 *
	 * - ساده / وارییشن: اگر خود محصول manage stock داشته باشد → get_stock_quantity
	 * - متغیر (variable): اگر والد manage کند → موجودی والد؛
	 *   وگرنه جمع موجودی وارییشن‌هایی که خودشان (نه parent) manage می‌کنند
	 *
	 * @param WC_Product|int|null $product محصول یا شناسه
	 * @return int|null null یعنی موجودی عددی در ووکامرس مدیریت نمی‌شود
	 */
	public static function get_quantity( $product ): ?int {
		$product = self::resolve( $product );
		if ( ! $product ) {
			return null;
		}

		// وارییشن یا ساده
		if ( ! $product->is_type( 'variable' ) ) {
			return self::own_managed_qty( $product );
		}

		// محصول متغیر
		if ( self::is_self_managing( $product ) ) {
			$qty = $product->get_stock_quantity();
			return $qty === null ? null : (int) $qty;
		}

		$sum = 0;
		$has = false;
		foreach ( $product->get_children() as $child_id ) {
			$child = wc_get_product( $child_id );
			$cqty  = self::own_managed_qty( $child );
			if ( $cqty === null ) {
				continue;
			}
			$has  = true;
			$sum += $cqty;
		}
		return $has ? $sum : null;
	}

	/**
	 * وضعیت موجودی ووکامرس (instock / outofstock / onbackorder).
	 *
	 * @param WC_Product|int|null $product
	 */
	public static function get_status( $product ): string {
		$product = self::resolve( $product );
		if ( ! $product ) {
			return '';
		}
		return (string) $product->get_stock_status();
	}

	/**
	 * برچسب فارسی وضعیت موجودی.
	 */
	public static function status_label( string $status ): string {
		$map = array(
			'instock'     => 'موجود',
			'outofstock'  => 'ناموجود',
			'onbackorder' => 'پیش‌سفارش',
		);
		return $map[ $status ] ?? $status;
	}

	/**
	 * متن نمایش برای جدول/جستجو: عدد موجودی یا برچسب وضعیت.
	 *
	 * @param WC_Product|int|null $product
	 */
	public static function format_display( $product ): string {
		$qty = self::get_quantity( $product );
		if ( $qty !== null ) {
			return number_format_i18n( $qty );
		}
		$status = self::get_status( $product );
		return $status !== '' ? self::status_label( $status ) : '—';
	}

	/**
	 * خلاصه برای ردیف فروش / جستجو.
	 *
	 * @return array{stock:int|null,stock_status:string,stock_display:string}
	 */
	public static function snapshot_for_id( int $product_id ): array {
		$product = $product_id > 0 ? wc_get_product( $product_id ) : null;
		$qty     = self::get_quantity( $product );
		$status  = self::get_status( $product );
		return array(
			'stock'         => $qty,
			'stock_status'  => $status,
			'stock_display' => self::format_display( $product ),
		);
	}

	/**
	 * آیا این محصول خودش (نه از طریق parent) موجودی را مدیریت می‌کند؟
	 *
	 * @param WC_Product $product
	 */
	private static function is_self_managing( $product ): bool {
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return false;
		}
		// وارییشن ممکن است manage_stock = 'parent' داشته باشد — آن را موجودی مستقل حساب نکن
		if ( $product->is_type( 'variation' ) && method_exists( $product, 'get_manage_stock' ) ) {
			$manage = $product->get_manage_stock();
			if ( $manage === 'parent' || $manage === false || $manage === 'no' ) {
				return false;
			}
			return ( $manage === true || $manage === 'yes' );
		}
		return (bool) $product->managing_stock();
	}

	/**
	 * موجودی عددی فقط اگر خود محصول manage کند.
	 *
	 * @param WC_Product|null $product
	 */
	private static function own_managed_qty( $product ): ?int {
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return null;
		}
		if ( ! self::is_self_managing( $product ) ) {
			return null;
		}
		$qty = $product->get_stock_quantity();
		return $qty === null ? null : (int) $qty;
	}

	/**
	 * @param WC_Product|int|null $product
	 * @return WC_Product|null
	 */
	private static function resolve( $product ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( (int) $product );
		}
		return ( $product && is_a( $product, 'WC_Product' ) ) ? $product : null;
	}
}
