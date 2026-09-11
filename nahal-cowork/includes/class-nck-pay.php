<?php
defined( 'ABSPATH' ) || exit;

/**
 * ثبت سفارش ووکامرس برای فرم‌هایی که به پرداخت می‌رسند.
 * حسابدار همان سفارش‌های ووکامرس را نشان می‌دهد؛ حسابدار بازنویسی نمی‌شود.
 */
class NCK_Pay {

	public static function method_labels() {
		return array(
			'site'   => 'پرداخت سایت نهال',
			'card'   => 'کارت به کارت',
			'onsite' => 'پرداخت در محل',
		);
	}

	public static function method_label( $method ) {
		$map = self::method_labels();
		$k   = (string) $method;
		return isset( $map[ $k ] ) ? $map[ $k ] : 'پرداخت نهال';
	}

	public static function status_for_method( $method ) {
		if ( 'onsite' === $method ) {
			return 'completed';
		}
		if ( 'card' === $method ) {
			return 'on-hold';
		}
		return 'pending';
	}

	public static function hooks() {
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'cart_prices' ), 20 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'cart_from_session' ), 20, 2 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_name', array( __CLASS__, 'cart_item_name' ), 10, 3 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_line_meta' ), 10, 4 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'order_meta' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'order_processed' ), 20, 1 );
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'on_order_paid' ) );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'on_order_paid' ) );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_order_paid' ) );
		add_action( 'template_redirect', array( __CLASS__, 'guard_checkout' ) );
	}

	public static function is_logged_in() {
		return function_exists( 'is_user_logged_in' ) && is_user_logged_in();
	}

	public static function requested_redirect() {
		if ( isset( $_POST['return_url'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$u = esc_url_raw( wp_unslash( $_POST['return_url'] ) ); // phpcs:ignore
			if ( $u && function_exists( 'wp_validate_redirect' ) ) {
				$u = wp_validate_redirect( $u, '' );
			}
			if ( $u ) {
				return $u;
			}
		}
		if ( function_exists( 'wp_get_referer' ) ) {
			$ref = wp_get_referer();
			if ( $ref && false === strpos( $ref, 'admin-ajax.php' ) ) {
				return $ref;
			}
		}
		return '';
	}

	public static function login_url( $redirect = '' ) {
		if ( $redirect === '' ) {
			$redirect = self::requested_redirect();
		}
		if ( $redirect === '' && function_exists( 'is_singular' ) && is_singular() && function_exists( 'get_permalink' ) ) {
			$redirect = (string) get_permalink();
		}
		if ( $redirect === '' && function_exists( 'home_url' ) && isset( $_SERVER['REQUEST_URI'] ) ) {
			$uri = wp_unslash( $_SERVER['REQUEST_URI'] ); // phpcs:ignore
			if ( false === strpos( $uri, 'admin-ajax.php' ) ) {
				$redirect = home_url( $uri );
			}
		}
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$account = wc_get_page_permalink( 'myaccount' );
			if ( $account && '#' !== $account ) {
				return add_query_arg(
					array(
						'redirect_to' => $redirect,
						'redirect'    => $redirect,
					),
					$account
				);
			}
		}
		if ( function_exists( 'wp_login_url' ) ) {
			return wp_login_url( $redirect );
		}
		return $redirect;
	}

	public static function checkout_url() {
		if ( function_exists( 'wc_get_checkout_url' ) ) {
			return wc_get_checkout_url();
		}
		return '';
	}

	/**
	 * پرداخت سایت مثل محصول ووکامرس است: ورود + سبد + درگاه.
	 *
	 * @return array{ok:bool,message?:string,need_login?:bool,login?:string}
	 */
	public static function assert_can_checkout( $amount, $payment = 'site' ) {
		if ( 'site' !== (string) $payment || ! self::should_record( $amount ) ) {
			return array( 'ok' => true );
		}
		if ( ! function_exists( 'is_user_logged_in' ) ) {
			return array( 'ok' => true );
		}
		if ( ! is_user_logged_in() ) {
			return array(
				'ok'         => false,
				'need_login' => true,
				'login'      => self::login_url(),
				'message'    => 'برای پرداخت از درگاه سایت، مثل خرید محصولات، ابتدا وارد حساب کاربری شوید.',
			);
		}
		if ( ! self::wc_ready() ) {
			return array( 'ok' => false, 'message' => 'ووکامرس فعال نیست؛ پرداخت از درگاه سایت ممکن نیست.' );
		}
		return array( 'ok' => true );
	}

	public static function make_ref() {
		$t   = class_exists( 'NCK_Jalali' ) ? NCK_Jalali::today() : array( 'y' => (int) gmdate( 'Y' ), 'm' => (int) gmdate( 'n' ), 'd' => (int) gmdate( 'j' ) );
		$ymd = sprintf( '%04d%02d%02d', (int) $t['y'], (int) $t['m'], (int) $t['d'] );
		try {
			$rand = strtoupper( bin2hex( random_bytes( 3 ) ) );
		} catch ( Exception $e ) {
			$rand = strtoupper( substr( md5( uniqid( (string) mt_rand(), true ) ), 0, 6 ) );
		}
		return 'NCK-' . $ymd . '-' . $rand;
	}

	public static function tracking_note( array $payload ) {
		$ref = isset( $payload['pay_ref'] ) ? trim( (string) $payload['pay_ref'] ) : '';
		if ( $ref === '' ) {
			return '';
		}
		return ' شماره پیگیری: ' . $ref;
	}

	public static function today_pay_date() {
		if ( class_exists( 'NCK_Jalali' ) ) {
			$t = NCK_Jalali::today();
			return NCK_Jalali::format( $t['y'], $t['m'], $t['d'] );
		}
		return gmdate( 'Y/m/d' );
	}

	public static function sync_enabled() {
		if ( ! class_exists( 'NCK_Settings' ) ) {
			return true;
		}
		return 1 === (int) NCK_Settings::get( 'wc_sync', 1 );
	}

	public static function wc_ready() {
		return function_exists( 'wc_create_order' ) && class_exists( 'WooCommerce' );
	}

	public static function hesabdar_ready() {
		return class_exists( 'WAP_Order_Service' ) || class_exists( 'WooCommerce_Accounting_Pro' );
	}

	public static function should_record( $amount ) {
		return (int) $amount > 0;
	}

	/**
	 * خواندن روش و مبلغ پرداخت از فرم فرانت.
	 *
	 * @return array{ok:bool,message?:string,payload?:array}
	 */
	public static function parse_front_payment( array $in, $fallback_amount = 0 ) {
		$opts    = class_exists( 'NCK_Learner' ) ? NCK_Learner::payment_options() : array(
			'site'   => 'سایت',
			'card'   => 'کارت به کارت',
			'onsite' => 'در محل کارت کشیده شد',
		);
		$payment = isset( $in['payment'] ) ? (string) $in['payment'] : '';
		if ( ! isset( $opts[ $payment ] ) ) {
			$payment = 'site';
		}

		$amount = 0;
		if ( isset( $in['pay_amount'] ) && trim( (string) $in['pay_amount'] ) !== '' ) {
			$amount = NCK_Hall::parse_amount( $in['pay_amount'] );
		}
		if ( $amount < 1 ) {
			$amount = max( 0, (int) $fallback_amount );
		}

		$pay_date = '';
		if ( isset( $in['pay_date'] ) && trim( (string) $in['pay_date'] ) !== '' ) {
			$pay_date = class_exists( 'NCK_Learner' ) ? NCK_Learner::format_date( $in['pay_date'] ) : trim( (string) $in['pay_date'] );
			if ( $pay_date === '' ) {
				return array( 'ok' => false, 'message' => 'تاریخ پرداخت را به صورت ۱۴۰۴/۰۶/۲۰ وارد کنید.' );
			}
		}
		if ( $pay_date === '' ) {
			$pay_date = self::today_pay_date();
		}

		return array(
			'ok'      => true,
			'payload' => array(
				'payment'    => $payment,
				'pay_amount' => $amount,
				'pay_date'   => $pay_date,
				'pay_ref'    => self::make_ref(),
			),
		);
	}

	public static function maybe_record( $contract ) {
		try {
			self::after_sign( $contract );
		} catch ( Exception $e ) {
			return;
		}
	}

	public static function after_sign( $contract ) {
		try {
			return self::after_sign_inner( $contract );
		} catch ( Exception $e ) {
			return array( 'ok' => false, 'message' => 'ثبت پرداخت انجام نشد.' );
		}
	}

	private static function after_sign_inner( $contract ) {
		if ( ! is_array( $contract ) || empty( $contract['id'] ) ) {
			return array( 'ok' => true, 'pay_url' => '' );
		}
		$kind    = class_exists( 'NCK_Contracts' ) ? NCK_Contracts::kind_of( $contract ) : ( isset( $contract['kind'] ) ? $contract['kind'] : '' );
		$payload = class_exists( 'NCK_Contracts' ) ? NCK_Contracts::payload( $contract ) : array();
		$built   = self::from_contract( $kind, $contract, $payload );
		if ( empty( $built['ok'] ) ) {
			return array( 'ok' => true, 'pay_url' => '', 'skipped' => true );
		}
		$method = isset( $built['order']['payment'] ) ? $built['order']['payment'] : 'site';
		if ( 'site' === $method ) {
			return self::send_to_checkout( $contract, $built['order'], $payload );
		}
		$created = self::create_order( $built['order'], $contract );
		if ( ! empty( $created['ok'] ) && ! empty( $created['order_id'] ) && class_exists( 'NCK_Contracts' ) ) {
			NCK_Contracts::merge_payload(
				(int) $contract['id'],
				array( 'wc_order_id' => (int) $created['order_id'] )
			);
		}
		if ( ! empty( $created['ok'] ) || ! empty( $created['skipped'] ) ) {
			return array( 'ok' => true, 'pay_url' => '' );
		}
		return $created;
	}

	private static function prepare_pay_product( $product ) {
		if ( ! $product ) {
			return 0;
		}
		$dirty = false;
		if ( 'publish' !== $product->get_status() ) {
			$product->set_status( 'publish' );
			$dirty = true;
		}
		if ( 'hidden' !== $product->get_catalog_visibility() ) {
			$product->set_catalog_visibility( 'hidden' );
			$dirty = true;
		}
		if ( ! $product->get_virtual() ) {
			$product->set_virtual( true );
			$dirty = true;
		}
		if ( ! $product->is_sold_individually() ) {
			$product->set_sold_individually( true );
			$dirty = true;
		}
		if ( method_exists( $product, 'get_tax_status' ) && 'none' !== $product->get_tax_status() ) {
			$product->set_tax_status( 'none' );
			$dirty = true;
		}
		if ( $dirty ) {
			$product->save();
		}
		return (int) $product->get_id();
	}

	public static function ensure_product() {
		if ( ! self::wc_ready() || ! function_exists( 'wc_get_product' ) ) {
			return 0;
		}
		$id = (int) get_option( 'nck_pay_product_id', 0 );
		if ( $id ) {
			$p = wc_get_product( $id );
			if ( $p ) {
				return self::prepare_pay_product( $p );
			}
		}
		if ( function_exists( 'wc_get_product_id_by_sku' ) ) {
			$sku_id = (int) wc_get_product_id_by_sku( 'nck-pay' );
			if ( $sku_id ) {
				$p = wc_get_product( $sku_id );
				if ( $p ) {
					update_option( 'nck_pay_product_id', $sku_id, false );
					return self::prepare_pay_product( $p );
				}
			}
		}
		try {
			$product = new WC_Product_Simple();
			$product->set_name( 'پرداخت قرارداد نهال' );
			$product->set_status( 'publish' );
			$product->set_catalog_visibility( 'hidden' );
			$product->set_virtual( true );
			$product->set_sold_individually( true );
			$product->set_tax_status( 'none' );
			$product->set_regular_price( 0 );
			$product->set_sku( 'nck-pay' );
			$product->save();
			$id = (int) $product->get_id();
			if ( $id ) {
				update_option( 'nck_pay_product_id', $id, false );
			}
			return $id;
		} catch ( Exception $e ) {
			return 0;
		}
	}

	/**
	 * @return array{ok:bool,message?:string,pay_url?:string}
	 */
	public static function send_to_checkout( array $contract, array $draft, array $payload = array() ) {
		if ( ! self::wc_ready() ) {
			return array( 'ok' => false, 'message' => 'ووکامرس فعال نیست؛ پرداخت از درگاه سایت ممکن نیست.' );
		}
		if ( function_exists( 'WC' ) && function_exists( 'wc_load_cart' ) && ( ! WC()->cart || ! WC()->session ) ) {
			wc_load_cart();
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return array( 'ok' => false, 'message' => 'سبد خرید ووکامرس در دسترس نیست.' );
		}
		if ( WC()->session && method_exists( WC()->session, 'has_session' ) && ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}

		$product_id = self::ensure_product();
		if ( ! $product_id ) {
			return array( 'ok' => false, 'message' => 'محصول پرداخت نهال ساخته نشد.' );
		}

		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( ! empty( $item['nck_contract_id'] ) || (int) $item['product_id'] === (int) $product_id ) {
				WC()->cart->remove_cart_item( $key );
			}
		}

		$added = WC()->cart->add_to_cart(
			$product_id,
			1,
			0,
			array(),
			array(
				'nck_contract_id' => (int) $contract['id'],
				'nck_kind'        => isset( $draft['kind'] ) ? $draft['kind'] : '',
				'nck_item_name'   => isset( $draft['item_name'] ) ? $draft['item_name'] : 'پرداخت نهال',
				'nck_amount'      => (float) $draft['amount'],
				'nck_pay_ref'     => isset( $payload['pay_ref'] ) ? (string) $payload['pay_ref'] : '',
			)
		);
		if ( ! $added ) {
			return array( 'ok' => false, 'message' => 'افزودن به سبد خرید ناموفق بود.' );
		}
		WC()->cart->calculate_totals();

		$url = self::checkout_url();
		if ( $url === '' && function_exists( 'wc_get_cart_url' ) ) {
			$url = wc_get_cart_url();
		}
		if ( $url === '' ) {
			return array( 'ok' => false, 'message' => 'صفحه پرداخت ووکامرس پیدا نشد.' );
		}

		if ( class_exists( 'NCK_Contracts' ) ) {
			NCK_Contracts::merge_payload(
				(int) $contract['id'],
				array(
					'pay_pending' => 1,
					'payment'     => 'site',
				)
			);
		}

		return array(
			'ok'      => true,
			'pay_url' => $url,
		);
	}

	public static function cart_keys() {
		return array( 'nck_contract_id', 'nck_kind', 'nck_item_name', 'nck_amount', 'nck_pay_ref' );
	}

	public static function cart_from_session( $item, $values ) {
		foreach ( self::cart_keys() as $key ) {
			if ( isset( $values[ $key ] ) ) {
				$item[ $key ] = $values[ $key ];
			}
		}
		return $item;
	}

	public static function cart_has_nck() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! empty( $item['nck_contract_id'] ) ) {
				return true;
			}
		}
		return false;
	}

	public static function guard_checkout() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return;
		}
		if ( self::is_logged_in() || ! self::cart_has_nck() ) {
			return;
		}
		wp_safe_redirect( self::login_url( self::checkout_url() ) );
		exit;
	}

	public static function cart_prices( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		if ( ! $cart || ! method_exists( $cart, 'get_cart' ) ) {
			return;
		}
		foreach ( $cart->get_cart() as $item ) {
			if ( empty( $item['nck_amount'] ) || empty( $item['data'] ) ) {
				continue;
			}
			$item['data']->set_price( (float) $item['nck_amount'] );
			if ( ! empty( $item['nck_item_name'] ) && method_exists( $item['data'], 'set_name' ) ) {
				$item['data']->set_name( $item['nck_item_name'] );
			}
		}
	}

	public static function cart_item_data( $data, $item ) {
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		if ( ! empty( $item['nck_pay_ref'] ) ) {
			$data[] = array(
				'name'  => 'پیگیری',
				'value' => $item['nck_pay_ref'],
			);
		}
		return $data;
	}

	public static function cart_item_name( $name, $item, $key ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! empty( $item['nck_item_name'] ) ) {
			return $item['nck_item_name'];
		}
		return $name;
	}

	public static function order_line_meta( $item, $cart_item_key, $values, $order ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( empty( $values['nck_contract_id'] ) ) {
			return;
		}
		$item->add_meta_data( '_nck_contract_id', (int) $values['nck_contract_id'], true );
		if ( ! empty( $values['nck_kind'] ) ) {
			$item->add_meta_data( '_nck_kind', $values['nck_kind'], true );
		}
		if ( ! empty( $values['nck_pay_ref'] ) ) {
			$item->add_meta_data( '_nck_pay_ref', $values['nck_pay_ref'], true );
		}
	}

	public static function order_meta( $order, $data ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! $order || ! method_exists( $order, 'get_items' ) ) {
			return;
		}
		foreach ( WC()->cart ? WC()->cart->get_cart() : array() as $item ) {
			if ( empty( $item['nck_contract_id'] ) ) {
				continue;
			}
			$order->update_meta_data( '_nck_contract_id', (int) $item['nck_contract_id'] );
			$order->update_meta_data( '_nck_kind', isset( $item['nck_kind'] ) ? $item['nck_kind'] : '' );
			$order->update_meta_data( '_nck_pay_ref', isset( $item['nck_pay_ref'] ) ? $item['nck_pay_ref'] : '' );
			$order->update_meta_data( '_nck_form', isset( $item['nck_kind'] ) ? $item['nck_kind'] : '' );
			break;
		}
	}

	public static function order_processed( $order_id ) {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( ! $order ) {
			return;
		}
		$cid = (int) $order->get_meta( '_nck_contract_id' );
		if ( $cid < 1 ) {
			foreach ( $order->get_items() as $item ) {
				$cid = (int) $item->get_meta( '_nck_contract_id' );
				if ( $cid ) {
					break;
				}
			}
		}
		if ( $cid < 1 || ! class_exists( 'NCK_Contracts' ) ) {
			return;
		}
		NCK_Contracts::merge_payload(
			$cid,
			array(
				'wc_order_id' => (int) $order_id,
				'pay_pending' => 1,
				'pay_ref'     => (string) $order->get_order_number(),
			)
		);
	}

	public static function on_order_paid( $order_id ) {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( ! $order || ! class_exists( 'NCK_Contracts' ) ) {
			return;
		}
		$cid = (int) $order->get_meta( '_nck_contract_id' );
		if ( $cid < 1 ) {
			foreach ( $order->get_items() as $item ) {
				$cid = (int) $item->get_meta( '_nck_contract_id' );
				if ( $cid ) {
					break;
				}
			}
		}
		if ( $cid < 1 ) {
			return;
		}
		$row = NCK_Contracts::get( $cid );
		if ( ! $row ) {
			return;
		}
		$payload = NCK_Contracts::payload( $row );
		if ( ! empty( $payload['paid'] ) ) {
			return;
		}
		$kind = NCK_Contracts::kind_of( $row );
		if ( 'cowork' === $kind && class_exists( 'NCK_Subscriptions' ) && ! empty( $row['member_id'] ) && ! empty( $row['plan'] ) ) {
			NCK_Subscriptions::replace_for_contract(
				(int) $row['member_id'],
				$cid,
				$row['plan'],
				(int) ( class_exists( 'NCK_Settings' ) ? NCK_Settings::get( 'shifts_per_month', 26 ) : 26 )
			);
		}
		NCK_Contracts::merge_payload(
			$cid,
			array(
				'paid'        => 1,
				'pay_pending' => 0,
				'wc_order_id' => (int) $order_id,
				'pay_ref'     => (string) $order->get_order_number(),
				'payment'     => 'site',
			)
		);
	}

	public static function split_name( $full ) {
		$full  = trim( (string) $full );
		$parts = preg_split( '/\s+/u', $full, 2 );
		if ( ! is_array( $parts ) || ! isset( $parts[0] ) || $parts[0] === '' ) {
			return array( 'first' => $full, 'last' => '' );
		}
		return array(
			'first' => $parts[0],
			'last'  => isset( $parts[1] ) ? $parts[1] : '',
		);
	}

	public static function draft( array $args ) {
		$amount  = isset( $args['amount'] ) ? (int) $args['amount'] : 0;
		$method  = isset( $args['payment'] ) ? (string) $args['payment'] : '';
		$name    = isset( $args['name'] ) ? (string) $args['name'] : '';
		$item    = isset( $args['item_name'] ) ? (string) $args['item_name'] : 'فرم نهال';
		$split   = self::split_name( $name );
		return array(
			'item_name'      => $item,
			'amount'        => $amount,
			'payment'       => $method,
			'payment_title' => self::method_label( $method ),
			'status'         => self::status_for_method( $method ),
			'created_via'   => 'nahal-cowork',
			'first_name'    => $split['first'],
			'last_name'     => $split['last'],
			'phone'         => isset( $args['phone'] ) ? (string) $args['phone'] : '',
			'email'         => isset( $args['email'] ) ? (string) $args['email'] : '',
			'kind'          => isset( $args['kind'] ) ? (string) $args['kind'] : '',
		);
	}

	/**
	 * @param array $contract ردیف قرارداد ذخیره‌شده
	 * @return array{ok:bool,skipped?:bool,reason?:string,order_id?:int,message?:string}
	 */
	public static function record_contract( array $contract ) {
		if ( ! self::sync_enabled() ) {
			return array( 'ok' => false, 'skipped' => true, 'reason' => 'disabled' );
		}

		$kind    = class_exists( 'NCK_Contracts' ) ? NCK_Contracts::kind_of( $contract ) : ( isset( $contract['kind'] ) ? $contract['kind'] : '' );
		$payload = class_exists( 'NCK_Contracts' ) ? NCK_Contracts::payload( $contract ) : array();
		if ( ! empty( $payload['wc_order_id'] ) ) {
			return array( 'ok' => true, 'skipped' => true, 'reason' => 'exists', 'order_id' => (int) $payload['wc_order_id'] );
		}

		$built = self::from_contract( $kind, $contract, $payload );
		if ( empty( $built['ok'] ) ) {
			return $built;
		}

		$created = self::create_order( $built['order'], $contract );
		if ( ! empty( $created['ok'] ) && ! empty( $created['order_id'] ) && class_exists( 'NCK_Contracts' ) ) {
			NCK_Contracts::merge_payload(
				(int) $contract['id'],
				array(
					'wc_order_id' => (int) $created['order_id'],
				)
			);
		}
		return $created;
	}

	/**
	 * @return array{ok:bool,skipped?:bool,reason?:string,order?:array}
	 */
	public static function from_contract( $kind, array $contract, array $payload ) {
		$kind = (string) $kind;
		$name = isset( $contract['full_name'] ) ? $contract['full_name'] : '';
		$phone = isset( $contract['phone'] ) ? $contract['phone'] : '';

		if ( 'hall' === $kind ) {
			$amount = isset( $payload['pay_amount'] ) ? (int) $payload['pay_amount'] : 0;
			if ( $amount < 1 ) {
				$amount = isset( $payload['amount'] ) ? (int) $payload['amount'] : 0;
			}
			$hall = isset( $payload['hall_name'] ) ? $payload['hall_name'] : 'سالن';
			if ( ! self::should_record( $amount ) ) {
				return array( 'ok' => false, 'skipped' => true, 'reason' => 'amount' );
			}
			$payment = isset( $payload['payment'] ) && $payload['payment'] !== '' ? $payload['payment'] : 'site';
			return array(
				'ok'    => true,
				'order' => self::draft(
					array(
						'kind'      => 'hall',
						'name'      => $name,
						'phone'     => $phone,
						'amount'    => $amount,
						'payment'   => $payment,
						'item_name' => 'اجاره سالن — ' . $hall,
					)
				),
			);
		}

		if ( 'learner' === $kind ) {
			$amount = isset( $payload['pay_amount'] ) ? (int) $payload['pay_amount'] : 0;
			if ( $amount < 1 && class_exists( 'NCK_Settings' ) ) {
				$amount = (int) NCK_Settings::get( 'learner_fee', 0 );
			}
			if ( ! self::should_record( $amount ) ) {
				return array( 'ok' => false, 'skipped' => true, 'reason' => 'amount' );
			}
			$payment = isset( $payload['payment'] ) ? $payload['payment'] : 'site';
			return array(
				'ok'    => true,
				'order' => self::draft(
					array(
						'kind'      => 'learner',
						'name'      => $name,
						'phone'     => $phone,
						'amount'    => $amount,
						'payment'   => $payment,
						'item_name' => 'پذیرش فراگیر نهال',
					)
				),
			);
		}

		if ( 'form' === $kind ) {
			$amount = isset( $payload['pay_amount'] ) ? (int) $payload['pay_amount'] : 0;
			if ( ! self::should_record( $amount ) ) {
				return array( 'ok' => false, 'skipped' => true, 'reason' => 'amount' );
			}
			$title = isset( $payload['item_name'] ) && $payload['item_name'] !== '' ? $payload['item_name'] : ( isset( $payload['form_title'] ) ? $payload['form_title'] : 'فرم نهال' );
			return array(
				'ok'    => true,
				'order' => self::draft(
					array(
						'kind'      => 'form',
						'name'      => $name,
						'phone'     => $phone,
						'email'     => isset( $payload['email'] ) ? $payload['email'] : '',
						'amount'    => $amount,
						'payment'   => isset( $payload['payment'] ) ? $payload['payment'] : 'site',
						'item_name' => $title,
					)
				),
			);
		}

		if ( 'cowork' === $kind ) {
			$amount = isset( $payload['pay_amount'] ) ? (int) $payload['pay_amount'] : 0;
			$plan   = isset( $contract['plan'] ) ? $contract['plan'] : '';
			if ( $amount < 1 && $plan && class_exists( 'NCK_Shifts' ) ) {
				$amount = NCK_Shifts::package_price( $plan );
			}
			if ( ! self::should_record( $amount ) ) {
				return array( 'ok' => false, 'skipped' => true, 'reason' => 'amount' );
			}
			$label = 'اشتراک فضای کار نهال';
			if ( class_exists( 'NCK_Shifts' ) ) {
				$labels = NCK_Shifts::plan_labels();
				if ( isset( $labels[ $plan ] ) ) {
					$label .= ' — ' . $labels[ $plan ];
				}
			}
			$payment = isset( $payload['payment'] ) && $payload['payment'] !== '' ? $payload['payment'] : 'site';
			return array(
				'ok'    => true,
				'order' => self::draft(
					array(
						'kind'      => 'cowork',
						'name'      => $name,
						'phone'     => $phone,
						'amount'    => $amount,
						'payment'   => $payment,
						'item_name' => $label,
					)
				),
			);
		}

		return array( 'ok' => false, 'skipped' => true, 'reason' => 'kind' );
	}

	/**
	 * @return array{ok:bool,skipped?:bool,reason?:string,order_id?:int,message?:string}
	 */
	public static function create_order( array $draft, array $contract = array() ) {
		if ( ! self::should_record( isset( $draft['amount'] ) ? $draft['amount'] : 0 ) ) {
			return array( 'ok' => false, 'skipped' => true, 'reason' => 'amount' );
		}
		if ( ! self::wc_ready() ) {
			return array( 'ok' => false, 'skipped' => true, 'reason' => 'woocommerce' );
		}

		try {
			$order = wc_create_order( array( 'status' => 'pending' ) );
			if ( is_wp_error( $order ) || ! $order ) {
				return array( 'ok' => false, 'skipped' => true, 'reason' => 'create', 'message' => 'ساخت سفارش ووکامرس ناموفق بود.' );
			}

			$item = new WC_Order_Item_Fee();
			$item->set_name( $draft['item_name'] );
			$item->set_total( (float) $draft['amount'] );
			$order->add_item( $item );

			$order->set_billing_first_name( $draft['first_name'] );
			$order->set_billing_last_name( $draft['last_name'] );
			if ( ! empty( $draft['email'] ) ) {
				$order->set_billing_email( $draft['email'] );
			}
			$order->set_billing_phone( $draft['phone'] );
			$order->set_created_via( 'nahal-cowork' );
			$order->set_payment_method( 'nahal-' . ( $draft['payment'] ? $draft['payment'] : 'pay' ) );
			$order->set_payment_method_title( $draft['payment_title'] );

			$order->update_meta_data( '_nck_kind', $draft['kind'] );
			$order->update_meta_data( '_nck_form', $draft['kind'] );
			if ( ! empty( $contract['id'] ) ) {
				$order->update_meta_data( '_nck_contract_id', (int) $contract['id'] );
			}
			if ( ! empty( $contract['print_token'] ) ) {
				$order->update_meta_data( '_nck_print_token', (string) $contract['print_token'] );
			}
			$order->add_order_note( 'ثبت‌شده از افزونه قرارداد نهال (' . $draft['item_name'] . ').' );
			$order->calculate_totals();
			$order->save();

			$status = $draft['status'];
			$allowed = array( 'pending', 'processing', 'on-hold', 'completed' );
			if ( ! in_array( $status, $allowed, true ) ) {
				$status = 'processing';
			}
			$order->update_status( $status, 'وضعیت اولیه بر اساس روش پرداخت فرم نهال.', true );

			return array(
				'ok'       => true,
				'order_id' => (int) $order->get_id(),
			);
		} catch ( Exception $e ) {
			return array( 'ok' => false, 'skipped' => true, 'reason' => 'exception', 'message' => $e->getMessage() );
		}
	}
}
