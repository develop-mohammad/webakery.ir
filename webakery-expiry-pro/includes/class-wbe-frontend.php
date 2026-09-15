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
		add_filter( 'woocommerce_short_description', array( __CLASS__, 'prepend_short_description' ), 4 );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'before_excerpt' ), 19 );
		add_action( 'woocommerce_after_shop_loop_item_title', array( __CLASS__, 'on_loop' ), 15 );
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
		if ( ! empty( WBE_Settings::get()['show_sale_countdown'] ) ) {
			wp_enqueue_script(
				'wbe-countdown',
				WBE_URL . 'assets/frontend.js',
				array(),
				WBE_VERSION,
				true
			);
		}
	}

	public static function want_product_page() {
		$s = WBE_Settings::get();
		return ! empty( $s['show_near_price'] ) || ! empty( $s['show_in_description'] );
	}

	public static function prepend_short_description( $content ) {
		if ( is_admin() && ! ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
			return $content;
		}
		if ( ! function_exists( 'is_product' ) || ! is_product() || ! self::want_product_page() ) {
			return $content;
		}
		$html = self::field_html( get_the_ID(), 'block' );
		if ( ! $html ) {
			return $content;
		}
		return $html . $content;
	}

	/**
	 * اگر توضیحات کوتاه خالی باشد فیلتر excerpt اجرا نمی‌شود — همین‌جا اول صفحه می‌گذاریم.
	 */
	public static function before_excerpt() {
		if ( ! function_exists( 'is_product' ) || ! is_product() || ! self::want_product_page() ) {
			return;
		}
		global $post;
		if ( $post && trim( (string) $post->post_excerpt ) !== '' ) {
			return;
		}
		echo self::field_html( get_the_ID(), 'block' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function on_loop() {
		$s = WBE_Settings::get();
		if ( empty( $s['show_on_loop'] ) ) {
			return;
		}
		echo self::field_html( get_the_ID(), 'loop' ); // phpcs:ignore WordPress.Security.EscapeOutput
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
		if ( ! empty( WBE_Settings::get()['show_sale_countdown'] ) ) {
			wp_enqueue_script( 'wbe-countdown', WBE_URL . 'assets/frontend.js', array(), WBE_VERSION, true );
		}
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
		$html .= '<p class="wbe-expiry__row"><span class="wbe-expiry__label">تاریخ انقضا</span> ';
		$html .= '<span class="wbe-expiry__value">' . esc_html( $date ) . '</span></p>';
		$timer = self::sale_countdown_html( $product_id, $active, $variant );
		if ( $timer !== '' ) {
			$html .= $timer;
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * تایمر معکوس تا پایان فروش — بدون متن تاریخ از/تا. اختیاری از تنظیمات.
	 *
	 * @param int    $product_id
	 * @param array  $active
	 * @param string $variant
	 * @return string
	 */
	public static function sale_countdown_html( $product_id, $active, $variant ) {
		if ( 'loop' === $variant ) {
			return '';
		}
		if ( ! WBE_Product::countdown_enabled( $product_id ) ) {
			return '';
		}
		if ( WBE_Engine::discount_of( $active ) <= 0 ) {
			return '';
		}
		$today = WBE_Jalali::today_ymd();
		$pair  = WBE_Product::sale_ymd_pair( $product_id );
		$from  = $pair[0];
		$to    = $pair[1] !== '' ? $pair[1] : ( isset( $active['expiry'] ) ? (string) $active['expiry'] : '' );
		if ( ! WBE_Engine::sale_window_live( $from, $to, $today ) ) {
			return '';
		}
		$end_ts = 0;
		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( $product && method_exists( $product, 'get_date_on_sale_to' ) ) {
				$dt = $product->get_date_on_sale_to( 'edit' );
				if ( is_object( $dt ) && method_exists( $dt, 'getTimestamp' ) ) {
					$end_ts = (int) $dt->getTimestamp();
				}
			}
		}
		if ( $end_ts <= 0 ) {
			$end_ts = WBE_Engine::ymd_end_ts( $to );
		}
		$parts = WBE_Engine::countdown_parts( $end_ts, time() );
		if ( $parts['remaining'] <= 0 ) {
			return '';
		}
		$pad = function ( $n ) {
			$n = max( 0, (int) $n );
			$s = (string) $n;
			if ( strlen( $s ) < 2 ) {
				$s = '0' . $s;
			}
			return WBE_Jalali::en_to_fa( $s );
		};
		$unit = function ( $key, $val, $label ) use ( $pad ) {
			$html  = '<span class="wbe-countdown__unit" data-unit="' . esc_attr( $key ) . '">';
			$html .= '<span class="wbe-countdown__num">' . esc_html( $pad( $val ) ) . '</span>';
			$html .= '<span class="wbe-countdown__lbl">' . esc_html( $label ) . '</span>';
			$html .= '</span>';
			return $html;
		};
		$html  = '<div class="wbe-countdown" data-end="' . esc_attr( (string) $end_ts ) . '">';
		$html .= '<span class="wbe-countdown__title">مانده تا پایان کمپین</span>';
		$html .= '<span class="wbe-countdown__units">';
		$html .= $unit( 'd', $parts['days'], 'روز' );
		$html .= $unit( 'h', $parts['hours'], 'ساعت' );
		$html .= $unit( 'm', $parts['minutes'], 'دقیقه' );
		$html .= $unit( 's', $parts['seconds'], 'ثانیه' );
		$html .= '</span></div>';
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
