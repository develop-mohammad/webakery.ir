<?php
defined( 'ABSPATH' ) || exit;

/**
 * اعمال کارمزد قسطی / تخفیف نقدی روی سبد.
 *
 * منطق: هر درگاهی که در لیست نقدی نباشد = قسطی
 * (اسنپ‌پی، ترب‌پی، دیجی‌پی، تارا و ... بدون نیاز به نام‌بردن)
 */
class WBGP_Fees {

	public static function init() {
		add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'apply' ), 20 );
		add_action( 'wp_footer', array( __CLASS__, 'checkout_script' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_blocks_script' ), 30 );
		add_action( 'woocommerce_review_order_before_payment', array( __CLASS__, 'checkout_hint' ) );
	}

	/**
	 * راهنمای کوتاه کنار درگاه‌ها در تسویه کلاسیک.
	 */
	public static function checkout_hint() {
		if ( ! WBGP_Settings::get( 'installment_enabled', 1 ) && ! WBGP_Settings::get( 'cash_discount_enabled', 0 ) ) {
			return;
		}
		$parts = array();
		if ( WBGP_Settings::get( 'installment_enabled', 1 ) ) {
			$type = WBGP_Settings::get( 'installment_type', 'percent' );
			$amt  = WBGP_Settings::get( 'installment_amount', 15 );
			$parts[] = ( 'fixed' === $type )
				? sprintf( 'درگاه‌های اقساطی: کارمزد ثابت %s', wc_price( $amt ) )
				: sprintf( 'درگاه‌های اقساطی: کارمزد %s٪', wc_format_decimal( $amt, 2 ) );
		}
		if ( WBGP_Settings::get( 'cash_discount_enabled', 0 ) ) {
			$type = WBGP_Settings::get( 'cash_discount_type', 'percent' );
			$amt  = WBGP_Settings::get( 'cash_discount_amount', 0 );
			if ( $amt > 0 ) {
				$parts[] = ( 'fixed' === $type )
					? sprintf( 'پرداخت نقدی: تخفیف ثابت %s', wc_price( $amt ) )
					: sprintf( 'پرداخت نقدی: تخفیف %s٪', wc_format_decimal( $amt, 2 ) );
			}
		}
		if ( ! $parts ) {
			return;
		}
		echo '<div class="wbgp-checkout-hint" style="margin:0 0 14px;padding:10px 12px;border-radius:10px;background:#f0fdfa;border:1px solid #99f6e4;font-size:13px;line-height:1.7;color:#115e59">';
		echo '<strong>توجه قیمت‌گذاری درگاه:</strong> ' . esc_html( implode( ' | ', array_map( 'wp_strip_all_tags', $parts ) ) );
		echo '</div>';
	}

	/**
	 * شناسه درگاه انتخاب‌شده — از سشن، POST تسویه، یا درخواست AJAX.
	 */
	public static function chosen_method() {
		if ( ! function_exists( 'WC' ) ) {
			return '';
		}

		// هنگام update_checkout معمولاً payment_method در POST است
		if ( ! empty( $_POST['payment_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$method = sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( $method !== '' ) {
				if ( WC()->session ) {
					WC()->session->set( 'chosen_payment_method', $method );
				}
				return $method;
			}
		}

		if ( WC()->session ) {
			$method = (string) WC()->session->get( 'chosen_payment_method' );
			if ( $method !== '' ) {
				return $method;
			}
		}

		// بدون انتخاب کاربر، کارمزد/تخفیف اعمال نشود (جلوگیری از خطای اسنپ‌پی/ترب‌پی قبل از انتخاب)
		return '';
	}

	/**
	 * آیا درگاه نقدی است؟ (با aliasهای رایج زیبال/زرین‌پال)
	 */
	public static function is_cash( $method ) {
		if ( $method === '' ) {
			return false;
		}
		$cash = WBGP_Settings::cash_gateway_ids();
		$method_l = strtolower( $method );

		foreach ( $cash as $id ) {
			$id_l = strtolower( $id );
			if ( $id_l === $method_l ) {
				return true;
			}
		}

		// aliasهای رایج حتی اگر کاربر فقط یکی را نوشته باشد
		$aliases = array(
			'wc_zibal'   => array( 'wc_zibal', 'zibal', 'wc-zibal' ),
			'wc_zpal'    => array( 'wc_zpal', 'wc_zarinpal', 'zarinpal', 'wc-zarinpal', 'zarinpalgateway' ),
			'bacs'       => array( 'bacs' ), // کارت به کارت ووکامرس — نقدی اختیاری
			'cod'        => array( 'cod' ),
		);

		foreach ( $cash as $id ) {
			$key = strtolower( $id );
			// نرمال‌سازی کلید alias
			if ( false !== stripos( $key, 'zibal' ) ) {
				$key = 'wc_zibal';
			} elseif ( false !== stripos( $key, 'zpal' ) || false !== stripos( $key, 'zarin' ) ) {
				$key = 'wc_zpal';
			}
			if ( empty( $aliases[ $key ] ) ) {
				continue;
			}
			if ( in_array( $method_l, $aliases[ $key ], true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * محاسبه مبلغ کارمزد/تخفیف — قابل تست واحد.
	 *
	 * @return float
	 */
	public static function calc_amount( $subtotal, $type, $value ) {
		$subtotal = (float) $subtotal;
		$value    = (float) $value;
		if ( $value <= 0 || $subtotal <= 0 ) {
			return 0.0;
		}
		if ( 'fixed' === $type ) {
			$amount = $value;
		} else {
			$amount = $subtotal * ( $value / 100 );
		}
		// ووکامرس/IRT: گرد کردن به اعشار تنظیم‌شده فروشگاه
		if ( function_exists( 'wc_get_price_decimals' ) ) {
			return (float) wc_format_decimal( $amount, wc_get_price_decimals() );
		}
		return round( $amount, 2 );
	}

	/**
	 * تصمیم کارمزد/تخفیف برای یک درگاه — خروجی برای تست.
	 *
	 * @return array{kind:string,amount:float,label:string}|null
	 */
	public static function decide( $method, $subtotal, $settings = null ) {
		if ( $method === '' || $subtotal <= 0 ) {
			return null;
		}

		$get = static function ( $key, $default = null ) use ( $settings ) {
			if ( is_array( $settings ) && array_key_exists( $key, $settings ) ) {
				return $settings[ $key ];
			}
			return WBGP_Settings::get( $key, $default );
		};

		$is_cash = self::is_cash( $method );

		if ( ! $is_cash && $get( 'installment_enabled', 1 ) ) {
			$fee = self::calc_amount( $subtotal, $get( 'installment_type', 'percent' ), $get( 'installment_amount', 15 ) );
			if ( $fee > 0 ) {
				return array(
					'kind'   => 'fee',
					'amount' => $fee,
					'label'  => (string) $get( 'installment_label', 'کارمزد خرید اقساطی' ),
				);
			}
			return null;
		}

		if ( $is_cash && $get( 'cash_discount_enabled', 0 ) ) {
			$discount = self::calc_amount( $subtotal, $get( 'cash_discount_type', 'percent' ), $get( 'cash_discount_amount', 0 ) );
			if ( $discount > 0 ) {
				return array(
					'kind'   => 'discount',
					'amount' => $discount,
					'label'  => (string) $get( 'cash_discount_label', 'تخفیف پرداخت نقدی' ),
				);
			}
		}

		return null;
	}

	public static function apply( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( ! $cart || ! function_exists( 'WC' ) ) {
			return;
		}
		// سشن ممکن است در بعضی درخواست‌های REST خالی باشد؛ مانع کل محاسبه نشود اگر POST داریم
		if ( ! WC()->session && empty( $_POST['payment_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$method = self::chosen_method();
		if ( $method === '' ) {
			return;
		}

		$subtotal = (float) $cart->get_subtotal();
		$decision = self::decide( $method, $subtotal );
		if ( ! $decision ) {
			return;
		}

		$taxable = (bool) WBGP_Settings::get( 'taxable', 1 );
		if ( 'fee' === $decision['kind'] ) {
			$cart->add_fee( $decision['label'], $decision['amount'], $taxable );
			return;
		}
		if ( 'discount' === $decision['kind'] ) {
			$cart->add_fee( $decision['label'], -1 * $decision['amount'], $taxable );
		}
	}

	public static function checkout_script() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}
		// فقط چک‌اوت کلاسیک
		if ( has_block( 'woocommerce/checkout' ) ) {
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

	/**
	 * سازگاری با Checkout Blocks — با تغییر روش پرداخت، سبد را رفرش می‌کند.
	 */
	public static function enqueue_blocks_script() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}
		if ( ! has_block( 'woocommerce/checkout' ) ) {
			return;
		}
		$handle = 'wbgp-blocks-checkout';
		wp_register_script( $handle, false, array( 'wp-data', 'wc-blocks-checkout' ), WBGP_VERSION, true );
		wp_enqueue_script( $handle );
		wp_add_inline_script(
			$handle,
			"(function(){
				if (!window.wp || !wp.data || !wp.data.subscribe) return;
				var last = '';
				wp.data.subscribe(function(){
					try {
						var store = wp.data.select('wc/store/payment');
						if (!store || !store.getActivePaymentMethod) return;
						var m = store.getActivePaymentMethod();
						if (!m || m === last) return;
						last = m;
						var cart = wp.data.dispatch('wc/store/cart');
						if (cart && cart.invalidateResolutionForStore) {
							cart.invalidateResolutionForStore();
						}
					} catch (e) {}
				});
			})();"
		);
	}
}
