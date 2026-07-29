<?php
defined( 'ABSPATH' ) || exit;

/**
 * اعمال کارمزد قسطی / تخفیف نقدی روی سبد.
 */
class WBGP_Fees {

	public static function init() {
		add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'apply' ), 20 );
		add_action( 'wp_footer', array( __CLASS__, 'checkout_script' ) );
	}

	/**
	 * شناسه درگاه انتخاب‌شده.
	 */
	protected static function chosen_method() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return '';
		}
		$method = (string) WC()->session->get( 'chosen_payment_method' );
		if ( $method !== '' ) {
			return $method;
		}
		// فال‌بک: اولین درگاه در دسترس
		$available = WC()->payment_gateways() ? WC()->payment_gateways()->get_available_payment_gateways() : array();
		if ( $available ) {
			$keys = array_keys( $available );
			return (string) reset( $keys );
		}
		return '';
	}

	protected static function is_cash( $method ) {
		if ( $method === '' ) {
			return false;
		}
		$cash = WBGP_Settings::cash_gateway_ids();
		// تطبیق بدون حساسیت به حروف (WC_ZPal / wc_zpal)
		foreach ( $cash as $id ) {
			if ( strcasecmp( $id, $method ) === 0 ) {
				return true;
			}
		}
		return false;
	}

	protected static function calc_amount( $subtotal, $type, $value ) {
		$value = (float) $value;
		if ( $value <= 0 || $subtotal < 0 ) {
			return 0;
		}
		if ( 'fixed' === $type ) {
			return $value;
		}
		// percent
		return $subtotal * ( $value / 100 );
	}

	public static function apply( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( ! $cart || ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		$method   = self::chosen_method();
		if ( $method === '' ) {
			return;
		}

		$subtotal = (float) $cart->get_subtotal();
		$taxable  = (bool) WBGP_Settings::get( 'taxable', 1 );
		$is_cash  = self::is_cash( $method );

		// کارمزد قسطی — هر چیز غیرنقدی
		if ( ! $is_cash && WBGP_Settings::get( 'installment_enabled', 1 ) ) {
			$fee = self::calc_amount(
				$subtotal,
				WBGP_Settings::get( 'installment_type', 'percent' ),
				WBGP_Settings::get( 'installment_amount', 15 )
			);
			if ( $fee > 0 ) {
				$label = (string) WBGP_Settings::get( 'installment_label', 'کارمزد خرید اقساطی' );
				$cart->add_fee( $label, $fee, $taxable );
			}
			return;
		}

		// تخفیف نقدی — فقط زیبال / زرین‌پال (و سایر نقدی‌های تنظیم‌شده)
		if ( $is_cash && WBGP_Settings::get( 'cash_discount_enabled', 0 ) ) {
			$discount = self::calc_amount(
				$subtotal,
				WBGP_Settings::get( 'cash_discount_type', 'percent' ),
				WBGP_Settings::get( 'cash_discount_amount', 0 )
			);
			if ( $discount > 0 ) {
				$label = (string) WBGP_Settings::get( 'cash_discount_label', 'تخفیف پرداخت نقدی' );
				// مبلغ منفی = تخفیف در ووکامرس
				$cart->add_fee( $label, -1 * $discount, $taxable );
			}
		}
	}

	public static function checkout_script() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}
		?>
		<script type="text/javascript">
		jQuery(function($){
			$(document.body).on('change', 'input[name="payment_method"]', function(){
				$('body').trigger('update_checkout');
			});
		});
		</script>
		<?php
	}
}
