<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * نمایش انقضا برای مشتری + سورت فروشگاه بر اساس تاریخ انقضا.
 */
class WBE_Frontend {

	public static function register() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'near_price' ), 25 );
		add_action( 'woocommerce_after_shop_loop_item_title', array( __CLASS__, 'on_loop' ), 15 );
		add_filter( 'the_content', array( __CLASS__, 'in_description' ), 20 );
		add_filter( 'woocommerce_blocks_product_grid_item_html', array( __CLASS__, 'block_grid_html' ), 10, 3 );
		add_shortcode( 'webakery_expiry', array( __CLASS__, 'shortcode' ) );
		add_filter( 'woocommerce_catalog_orderby', array( __CLASS__, 'orderby_options' ) );
		add_filter( 'woocommerce_default_catalog_orderby_options', array( __CLASS__, 'orderby_options' ) );
		add_filter( 'woocommerce_get_catalog_ordering_args', array( __CLASS__, 'ordering_args' ) );
		add_filter( 'posts_clauses', array( __CLASS__, 'posts_clauses' ), 20, 2 );
	}

	public static function assets() {
		$need = false;
		if ( function_exists( 'is_product' ) && is_product() ) {
			$need = true;
		}
		if ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) ) {
			$need = true;
		}
		if ( ! $need ) {
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

	public static function on_loop() {
		$s = WBE_Settings::get();
		if ( empty( $s['show_on_loop'] ) ) {
			return;
		}
		echo self::field_html( get_the_ID(), 'loop' ); // phpcs:ignore WordPress.Security.EscapeOutput
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

	public static function block_grid_html( $html, $data, $product ) {
		unset( $data );
		$s = WBE_Settings::get();
		if ( empty( $s['show_on_loop'] ) || ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return $html;
		}
		$field = self::field_html( $product->get_id(), 'loop' );
		if ( ! $field ) {
			return $html;
		}
		if ( false !== strpos( $html, 'wbe-expiry' ) ) {
			return $html;
		}
		if ( false !== strpos( $html, '</li>' ) ) {
			return str_replace( '</li>', $field . '</li>', $html );
		}
		return $html . $field;
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => get_the_ID(),
			),
			$atts,
			'webakery_expiry'
		);
		wp_enqueue_style( 'wbe-frontend', WBE_URL . 'assets/frontend.css', array(), WBE_VERSION );
		return self::field_html( (int) $atts['id'], 'compact' );
	}

	/**
	 * فقط برای محصولات تنظیم‌شده و فقط بچ فعال.
	 *
	 * @param int    $product_id
	 * @param string $variant compact|block|loop
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
		$map   = array(
			'compact' => 'wbe-expiry wbe-expiry--compact',
			'loop'    => 'wbe-expiry wbe-expiry--loop',
			'block'   => 'wbe-expiry wbe-expiry--block',
		);
		$class = isset( $map[ $variant ] ) ? $map[ $variant ] : $map['block'];
		$html  = '<div class="' . esc_attr( $class ) . '" dir="rtl">';
		$html .= '<span class="wbe-expiry__label">تاریخ انقضا</span>';
		$html .= '<span class="wbe-expiry__value">' . esc_html( $date ) . '</span>';
		$html .= '</div>';
		return $html;
	}

	public static function orderby_options( $options ) {
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		if ( empty( WBE_Settings::get()['catalog_sort'] ) ) {
			return $options;
		}
		$options['wbe_expiry']      = 'تاریخ انقضا: نزدیک‌ترین';
		$options['wbe_expiry-desc'] = 'تاریخ انقضا: دورترین';
		return $options;
	}

	public static function ordering_args( $args ) {
		if ( empty( WBE_Settings::get()['catalog_sort'] ) ) {
			return $args;
		}
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'wbe_expiry' === $orderby ) {
			$args['orderby'] = 'wbe_expiry';
			$args['order']   = 'ASC';
			unset( $args['meta_key'] );
		} elseif ( 'wbe_expiry-desc' === $orderby ) {
			$args['orderby'] = 'wbe_expiry';
			$args['order']   = 'DESC';
			unset( $args['meta_key'] );
		}
		return $args;
	}

	public static function posts_clauses( $clauses, $query ) {
		if ( ! $query instanceof WP_Query ) {
			return $clauses;
		}
		$order = 'ASC';
		$want  = false;
		if ( is_admin() ) {
			if ( $query->is_main_query() && 'wbe_expiry' === $query->get( 'orderby' ) ) {
				$want  = true;
				$order = ( 'DESC' === strtoupper( (string) $query->get( 'order' ) ) ) ? 'DESC' : 'ASC';
			}
		} elseif ( $query->is_main_query() && ! empty( WBE_Settings::get()['catalog_sort'] ) ) {
			$ob = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			if ( 'wbe_expiry' === $ob || 'wbe_expiry-desc' === $ob ) {
				$want  = true;
				$order = ( 'wbe_expiry-desc' === $ob ) ? 'DESC' : 'ASC';
			} elseif ( 'wbe_expiry' === $query->get( 'orderby' ) ) {
				$want  = true;
				$order = ( 'DESC' === strtoupper( (string) $query->get( 'order' ) ) ) ? 'DESC' : 'ASC';
			}
		}
		if ( ! $want ) {
			return $clauses;
		}
		global $wpdb;
		$pair = WBE_Engine::expiry_order_clauses(
			isset( $clauses['join'] ) ? $clauses['join'] : '',
			isset( $clauses['orderby'] ) ? $clauses['orderby'] : '',
			$wpdb->posts,
			$wpdb->postmeta,
			$order
		);
		$clauses['join']    = $pair[0];
		$clauses['orderby'] = $pair[1];
		return $clauses;
	}
}
