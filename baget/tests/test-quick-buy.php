<?php
/**
 * تست واحد خرید سریع Baget — بدون وردپرس کامل.
 * اجرا: php baget/tests/test-quick-buy.php
 */

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/../' );
	}
	if ( ! defined( 'WCCP_VERSION' ) ) {
		define( 'WCCP_VERSION', '1.6.0' );
	}
	if ( ! defined( 'WCCP_URL' ) ) {
		define( 'WCCP_URL', 'https://shop.test/wp-content/plugins/baget/' );
	}

	$GLOBALS['wccp_opts']          = array();
	$GLOBALS['wccp_ajax']          = false;
	$GLOBALS['wccp_is_cart']       = false;
	$GLOBALS['wccp_is_checkout']   = false;
	$GLOBALS['wccp_cart_empty']    = false;
	$GLOBALS['wccp_redirect']      = '';
	$GLOBALS['wccp_filters']       = array();

	function get_option( $key, $default = false ) {
		return array_key_exists( $key, $GLOBALS['wccp_opts'] ) ? $GLOBALS['wccp_opts'][ $key ] : $default;
	}
	function update_option( $key, $value, $autoload = true ) {
		$GLOBALS['wccp_opts'][ $key ] = $value;
		return true;
	}
	function sanitize_text_field( $s ) {
		return trim( strip_tags( (string) $s ) );
	}
	function add_filter( $hook, $cb, $prio = 10, $args = 1 ) {
		$GLOBALS['wccp_filters'][ $hook ][] = $cb;
	}
	function add_action( $hook, $cb, $prio = 10, $args = 1 ) {
		$GLOBALS['wccp_filters'][ $hook ][] = $cb;
	}
	function is_admin() {
		return false;
	}
	function wp_doing_ajax() {
		return ! empty( $GLOBALS['wccp_ajax'] );
	}
	function wc_get_checkout_url() {
		return 'https://shop.test/checkout/';
	}
	function home_url( $path = '' ) {
		return 'https://shop.test' . $path;
	}
	function wp_enqueue_script() {}
	function wp_localize_script() {}
	function wp_safe_redirect( $url ) {
		$GLOBALS['wccp_redirect'] = $url;
	}
	function is_cart() {
		return ! empty( $GLOBALS['wccp_is_cart'] );
	}
	function is_checkout() {
		return ! empty( $GLOBALS['wccp_is_checkout'] );
	}
	function WC() {
		return (object) array(
			'cart' => new class() {
				public function is_empty() {
					return ! empty( $GLOBALS['wccp_cart_empty'] );
				}
			},
		);
	}

	class WooCommerce {}
}

namespace WCCP {
	require_once dirname( __DIR__ ) . '/includes/QuickBuy.php';
}

namespace {
	$pass = 0;
	$fail = 0;
	$check = static function ( $label, $ok, $detail = '' ) use ( &$pass, &$fail ) {
		if ( $ok ) {
			$pass++;
			echo "  ok   — {$label}" . ( $detail !== '' ? " ({$detail})" : '' ) . "\n";
		} else {
			$fail++;
			echo "  FAIL — {$label}" . ( $detail !== '' ? " ({$detail})" : '' ) . "\n";
		}
	};

	echo "=== Baget QuickBuy ===\n";

	$clean = \WCCP\QuickBuy::sanitize(
		array(
			'enabled'       => '1',
			'button_text'   => '  <b>خرید آنی</b>  ',
			'redirect_cart' => 1,
		)
	);
	$check( 'متن دکمه تگ HTML را حذف می‌کند', 'خرید آنی' === $clean['button_text'], $clean['button_text'] );
	$check( 'enabled از رشته ۱ به ۱ تبدیل می‌شود', 1 === $clean['enabled'] );
	$check( 'redirect_cart روشن می‌ماند', 1 === $clean['redirect_cart'] );

	$off = \WCCP\QuickBuy::sanitize( array( 'enabled' => 0, 'button_text' => '', 'redirect_cart' => 0 ) );
	$check( 'متن خالی به «خرید» برمی‌گردد', 'خرید' === $off['button_text'], $off['button_text'] );
	$check( 'غیرفعال کردن خرید سریع ذخیره می‌شود', 0 === $off['enabled'] && 0 === $off['redirect_cart'] );

	$GLOBALS['wccp_opts'] = array();
	$defaults             = \WCCP\QuickBuy::settings();
	$check( 'بدون option ذخیره‌شده، پیش‌فرض فعال است', 1 === $defaults['enabled'] && 1 === $defaults['redirect_cart'] );
	$check( 'پیش‌فرض متن دکمه «خرید» است', 'خرید' === $defaults['button_text'] );
	$check( 'is_enabled بدون ذخیره true است', true === \WCCP\QuickBuy::is_enabled() );

	\WCCP\QuickBuy::save_settings( array( 'enabled' => 0, 'button_text' => 'پرداخت', 'redirect_cart' => 1 ) );
	$saved = \WCCP\QuickBuy::settings();
	$check( 'بعد از ذخیره خاموش، is_enabled false است', false === \WCCP\QuickBuy::is_enabled() );
	$check( 'متن سفارشی ذخیره می‌شود', 'پرداخت' === $saved['button_text'] );

	\WCCP\QuickBuy::save_settings( array( 'enabled' => 1, 'button_text' => 'خرید', 'redirect_cart' => 1 ) );
	$qb = \WCCP\QuickBuy::instance();

	$url = $qb->redirect_to_checkout( 'https://shop.test/cart/' );
	$check( 'ریدایرکت افزودن به سبد به checkout است', 'https://shop.test/checkout/' === $url, $url );

	$GLOBALS['wccp_ajax'] = true;
	$ajax_url             = $qb->redirect_to_checkout( 'https://shop.test/cart/' );
	$check( 'در Ajax مقصد PHP عوض نمی‌شود (JS هندل می‌کند)', 'https://shop.test/cart/' === $ajax_url, $ajax_url );
	$GLOBALS['wccp_ajax'] = false;

	$check( 'متن دکمه تک‌محصولی سفارشی است', 'خرید' === $qb->single_button_text( 'افزودن به سبد خرید' ) );

	$variable = new class() {
		public function is_type( $t ) {
			return 'variable' === $t;
		}
		public function is_purchasable() {
			return true;
		}
		public function is_in_stock() {
			return true;
		}
	};
	$simple = new class() {
		public function is_type( $t ) {
			return 'simple' === $t;
		}
		public function is_purchasable() {
			return true;
		}
		public function is_in_stock() {
			return true;
		}
	};
	$check( 'محصول متغیر در آرشیو متن «انتخاب گزینه‌ها» را حفظ می‌کند', 'انتخاب گزینه‌ها' === $qb->loop_button_text( 'انتخاب گزینه‌ها', $variable ) );
	$check( 'محصول ساده در آرشیو متن خرید می‌گیرد', 'خرید' === $qb->loop_button_text( 'افزودن به سبد خرید', $simple ) );

	$args = $qb->loop_add_to_cart_args( array( 'class' => 'button add_to_cart_button ajax_add_to_cart' ) );
	$check( 'کلاس ajax_add_to_cart حذف می‌شود', false === strpos( $args['class'], 'ajax_add_to_cart' ), $args['class'] );
	$check( 'Ajax افزودن به سبد خاموش می‌شود', 'no' === $qb->disable_ajax_add_to_cart( 'yes' ) );

	$GLOBALS['wccp_is_cart']    = true;
	$GLOBALS['wccp_cart_empty'] = false;
	$target                     = \WCCP\QuickBuy::cart_redirect_target();
	$check( 'صفحه سبد غیرخالی به پرداخت می‌رود', 'https://shop.test/checkout/' === $target, $target );

	$GLOBALS['wccp_cart_empty'] = true;
	$empty_target               = \WCCP\QuickBuy::cart_redirect_target();
	$check( 'سبد خالی ریدایرکت نمی‌شود', '' === $empty_target );

	$js = file_get_contents( dirname( __DIR__ ) . '/assets/quick-buy.js' );
	$check( 'اسکریپت فرانت به added_to_cart گوش می‌دهد', false !== strpos( $js, 'added_to_cart' ) );
	$check( 'اسکریپت Store API بلاک را پوشش می‌دهد', false !== strpos( $js, 'add-item' ) );

	$admin = file_get_contents( dirname( __DIR__ ) . '/templates/admin-quick-buy.php' );
	$check( 'قالب ادمین تب خرید سریع وجود دارد', false !== strpos( $admin, 'wccp_save_quick_buy' ) );

	echo "\n{$pass} passed, {$fail} failed\n";
	exit( $fail > 0 ? 1 : 0 );
}
