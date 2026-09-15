<?php
defined( 'ABSPATH' ) || exit;

/**
 * موجودی محصولات — فقط از API ووکامرس (ساده، متغیر، وارییشن).
 * هیچ موجودی سفارشی در حسابدار نگه داشته نمی‌شود.
 */
class WAP_Stock {

	/**
	 * موجودی عددی محصول از ووکامرس.
	 *
	 * - ساده / وارییشن: اگر manage stock روشن باشد، get_stock_quantity؛ وگرنه null
	 * - متغیر (variable): اگر والد manage stock داشته باشد همان؛ وگرنه جمع موجودی وارییشن‌هایی که manage می‌کنند
	 *
	 * @param WC_Product|int|null $product محصول یا شناسه
	 * @return int|null null یعنی موجودی در ووکامرس مدیریت نمی‌شود
	 */
	public static function get_quantity( $product ): ?int {
		$product = self::resolve( $product );
		if ( ! $product ) {
			return null;
		}

		if ( $product->is_type( 'variable' ) ) {
			if ( $product->managing_stock() ) {
				$qty = $product->get_stock_quantity();
				return $qty === null ? null : (int) $qty;
			}
			$sum  = 0;
			$has  = false;
			foreach ( $product->get_children() as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( ! $child || ! $child->managing_stock() ) {
					continue;
				}
				$has  = true;
				$sum += (int) $child->get_stock_quantity();
			}
			return $has ? $sum : null;
		}

		if ( $product->managing_stock() ) {
			$qty = $product->get_stock_quantity();
			return $qty === null ? null : (int) $qty;
		}

		return null;
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
	 * خلاصه برای ردیف‌های فروش محصول (pid از سفارش = والد برای متغیرها).
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
