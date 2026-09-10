<?php
namespace WCCP;

defined( 'ABSPATH' ) || exit;

/**
 * خرید سریع ووکامرس: دکمه خرید → صفحه پرداخت (سبد فعلی حفظ می‌شود).
 */
class QuickBuy {

	const OPTION = 'wccp_quick_buy';

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		if ( ! class_exists( 'WooCommerce' ) && ! function_exists( 'WC' ) ) {
			return;
		}
		add_filter( 'woocommerce_add_to_cart_redirect', array( $this, 'redirect_to_checkout' ), 99, 2 );
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( $this, 'single_button_text' ), 20 );
		add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'loop_button_text' ), 20, 2 );
		add_filter( 'woocommerce_loop_add_to_cart_args', array( $this, 'loop_add_to_cart_args' ), 20 );
		add_filter( 'pre_option_woocommerce_enable_ajax_add_to_cart', array( $this, 'disable_ajax_add_to_cart' ) );
		add_action( 'template_redirect', array( $this, 'maybe_skip_cart' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/** @return array{enabled:int,button_text:string,redirect_cart:int} */
	public static function defaults() {
		return array(
			'enabled'       => 1,
			'button_text'   => 'خرید',
			'redirect_cart' => 1,
		);
	}

	/** @return array{enabled:int,button_text:string,redirect_cart:int} */
	public static function settings() {
		$raw = get_option( self::OPTION, false );
		if ( ! is_array( $raw ) ) {
			return self::defaults();
		}
		return self::sanitize( $raw );
	}

	/**
	 * @param array $data
	 * @return array{enabled:int,button_text:string,redirect_cart:int}
	 */
	public static function sanitize( $data ) {
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		$defaults = self::defaults();
		$text     = isset( $data['button_text'] ) ? sanitize_text_field( (string) $data['button_text'] ) : '';
		$text     = trim( $text );
		if ( $text === '' ) {
			$text = $defaults['button_text'];
		}
		return array(
			'enabled'       => empty( $data['enabled'] ) ? 0 : 1,
			'button_text'   => $text,
			'redirect_cart' => empty( $data['redirect_cart'] ) ? 0 : 1,
		);
	}

	/**
	 * @param array $data
	 * @return array{enabled:int,button_text:string,redirect_cart:int}
	 */
	public static function save_settings( array $data ) {
		$clean = self::sanitize( $data );
		update_option( self::OPTION, $clean, false );
		return $clean;
	}

	public static function is_enabled() {
		$s = self::settings();
		return ! empty( $s['enabled'] );
	}

	/** @return string */
	public static function checkout_url() {
		if ( function_exists( 'wc_get_checkout_url' ) ) {
			return (string) wc_get_checkout_url();
		}
		$checkout = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'checkout' ) : '';
		return $checkout ? (string) $checkout : home_url( '/checkout/' );
	}

	/**
	 * @param string           $url
	 * @param \WC_Product|null $product
	 * @return string
	 */
	public function redirect_to_checkout( $url, $product = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! self::is_enabled() ) {
			return $url;
		}
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return $url;
		}
		$checkout = self::checkout_url();
		return $checkout ? $checkout : $url;
	}

	/** @param string $text */
	public function single_button_text( $text ) {
		if ( ! self::is_enabled() ) {
			return $text;
		}
		$s = self::settings();
		return $s['button_text'] !== '' ? $s['button_text'] : $text;
	}

	/**
	 * در آرشیو، دکمه محصول متغیر/گروه‌بندی‌شده را عوض نکن (باید برود صفحه محصول).
	 *
	 * @param string           $text
	 * @param \WC_Product|null $product
	 */
	public function loop_button_text( $text, $product = null ) {
		if ( ! self::is_enabled() ) {
			return $text;
		}
		if ( is_object( $product ) && method_exists( $product, 'is_type' ) ) {
			if ( $product->is_type( 'variable' ) || $product->is_type( 'grouped' ) || $product->is_type( 'external' ) ) {
				return $text;
			}
			if ( method_exists( $product, 'is_purchasable' ) && ! $product->is_purchasable() ) {
				return $text;
			}
			if ( method_exists( $product, 'is_in_stock' ) && ! $product->is_in_stock() ) {
				return $text;
			}
		}
		$s = self::settings();
		return $s['button_text'] !== '' ? $s['button_text'] : $text;
	}

	/** @param array $args */
	public function loop_add_to_cart_args( $args ) {
		if ( ! self::is_enabled() || ! is_array( $args ) ) {
			return $args;
		}
		if ( ! empty( $args['class'] ) ) {
			$args['class'] = trim( preg_replace( '/\s+/', ' ', str_replace( 'ajax_add_to_cart', '', (string) $args['class'] ) ) );
		}
		return $args;
	}

	/**
	 * @param mixed $value
	 * @return string|false
	 */
	public function disable_ajax_add_to_cart( $value ) {
		if ( ! self::is_enabled() ) {
			return $value;
		}
		return 'no';
	}

	public function maybe_skip_cart() {
		$url = self::cart_redirect_target();
		if ( ! $url ) {
			return;
		}
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * اگر صفحه سبد باید رد شود، URL پرداخت را برمی‌گرداند.
	 *
	 * @return string
	 */
	public static function cart_redirect_target() {
		if ( ! self::is_enabled() ) {
			return '';
		}
		$s = self::settings();
		if ( empty( $s['redirect_cart'] ) ) {
			return '';
		}
		if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
			return '';
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return '';
		}
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart || WC()->cart->is_empty() ) {
			return '';
		}
		return self::checkout_url();
	}

	public function enqueue() {
		if ( is_admin() || ! self::is_enabled() ) {
			return;
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return;
		}
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		wp_enqueue_script(
			'wccp-quick-buy',
			WCCP_URL . 'assets/quick-buy.js',
			array( 'jquery' ),
			WCCP_VERSION,
			true
		);
		wp_localize_script(
			'wccp-quick-buy',
			'WCCP_QUICK_BUY',
			array(
				'enabled'     => 1,
				'checkoutUrl' => self::checkout_url(),
			)
		);
	}
}
