<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WBE_Frontend {

	public static function register() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'near_price' ), 25 );
		add_filter( 'the_content', array( __CLASS__, 'in_description' ), 20 );
	}

	public static function assets() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		wp_enqueue_style(
			'wbe-frontend',
			WBE_URL . 'assets/frontend.css',
			array(),
			WBE_VERSION
		);
	}

	public static function near_price() {
		$s = WBE_Settings::get();
		if ( empty( $s['show_near_price'] ) ) {
			return;
		}
		echo self::field_html( get_the_ID(), 'compact' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function in_description( $content ) {
		if ( ! function_exists( 'is_product' ) || ! is_product() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$s = WBE_Settings::get();
		if ( empty( $s['show_in_description'] ) ) {
			return $content;
		}
		$html = self::field_html( get_the_ID(), 'block' );
		if ( ! $html ) {
			return $content;
		}
		return $content . $html;
	}

	/**
	 * فقط برای محصولات تنظیم‌شده و فقط بچ فعال.
	 *
	 * @param int    $product_id
	 * @param string $variant compact|block
	 * @return string
	 */
	public static function field_html( $product_id, $variant = 'block' ) {
		$product_id = (int) $product_id;
		if ( ! $product_id || ! WBE_Plugin::licensed() || ! WBE_Product::configured( $product_id ) ) {
			return '';
		}
		$active = WBE_Product::active( $product_id );
		if ( ! $active ) {
			return '';
		}
		$cal  = WBE_Product::calendar( $product_id );
		$date = WBE_Jalali::format_ymd( $active['expiry'], $cal, true );
		if ( $date === '' ) {
			return '';
		}
		$class = ( 'compact' === $variant ) ? 'wbe-expiry wbe-expiry--compact' : 'wbe-expiry wbe-expiry--block';
		$html  = '<div class="' . esc_attr( $class ) . '" dir="rtl">';
		$html .= '<span class="wbe-expiry__label">تاریخ انقضا</span>';
		$html .= '<span class="wbe-expiry__value">' . esc_html( $date ) . '</span>';
		$html .= '</div>';
		return $html;
	}
}
