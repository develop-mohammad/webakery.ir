<?php
/**
 * تست سازگاری قیمت‌گذاری درگاه با ووکامرس + درگاه‌های قسطی/نقدی
 * اجرا: wp eval-file tests/test-gateway-fees.php
 */

if ( ! class_exists( 'WBGP_Fees' ) || ! class_exists( 'WBGP_Settings' ) ) {
	fwrite( STDERR, "FAIL: plugin classes not loaded\n" );
	exit( 1 );
}

function wbgp_assert( $ok, $msg ) {
	if ( $ok ) {
		echo "PASS  $msg\n";
		$GLOBALS['wbgp_passed']++;
	} else {
		echo "FAIL  $msg\n";
		$GLOBALS['wbgp_failed']++;
	}
}

$GLOBALS['wbgp_failed'] = 0;
$GLOBALS['wbgp_passed'] = 0;

// تنظیمات تست
$settings = array(
	'installment_enabled'   => 1,
	'installment_type'      => 'percent',
	'installment_amount'    => 15,
	'installment_label'     => 'کارمزد خرید اقساطی',
	'cash_discount_enabled' => 1,
	'cash_discount_type'    => 'percent',
	'cash_discount_amount'  => 5,
	'cash_discount_label'   => 'تخفیف پرداخت نقدی',
);

// اطمینان از لیست نقدی پیش‌فرض
update_option(
	'wbgp_settings',
	array_merge(
		WBGP_Settings::defaults(),
		array(
			'cash_gateways'         => "wc_zibal\nzibal\nWC_ZPal\nWC_ZarinPal\nzarinpal",
			'installment_enabled'   => 1,
			'installment_type'      => 'percent',
			'installment_amount'    => 15,
			'cash_discount_enabled' => 1,
			'cash_discount_type'    => 'percent',
			'cash_discount_amount'  => 5,
		)
	)
);

$subtotal = 1000000; // ۱٬۰۰۰٬۰۰۰

echo "=== Unit: calc_amount ===\n";
wbgp_assert( abs( WBGP_Fees::calc_amount( 1000, 'percent', 15 ) - 150 ) < 0.01, '15% of 1000 = 150' );
wbgp_assert( abs( WBGP_Fees::calc_amount( 1000, 'fixed', 200 ) - 200 ) < 0.01, 'fixed 200' );
wbgp_assert( WBGP_Fees::calc_amount( 0, 'percent', 15 ) === 0.0, 'zero subtotal => 0' );
wbgp_assert( WBGP_Fees::calc_amount( 1000, 'percent', 0 ) === 0.0, 'zero rate => 0' );

echo "=== Unit: is_cash (Zibal / ZarinPal aliases) ===\n";
foreach ( array( 'wc_zibal', 'zibal', 'WC_Zibal', 'WC_ZPal', 'zarinpal', 'WC_ZarinPal' ) as $id ) {
	wbgp_assert( WBGP_Fees::is_cash( $id ), "cash alias recognized: $id" );
}

echo "=== Unit: installment gateways (SnappPay / TorobPay / ...) ===\n";
$installment_ids = array(
	'snapppay',
	'WC_SnappPay',
	'snapp_pay',
	'torobpay',
	'torob_pay',
	'WC_TorobPay',
	'digipay',
	'tara',
	'payzito',
	'woocommerce_snapppay',
	'installment',
	'unknown_gateway_xyz',
);
foreach ( $installment_ids as $id ) {
	wbgp_assert( ! WBGP_Fees::is_cash( $id ), "NOT cash (installment): $id" );
}

echo "=== Unit: decide() outcomes ===\n";
foreach ( $installment_ids as $id ) {
	$d = WBGP_Fees::decide( $id, $subtotal, $settings );
	$ok = $d && 'fee' === $d['kind'] && abs( $d['amount'] - 150000 ) < 1;
	wbgp_assert( $ok, "fee 15% on $id => " . ( $d['amount'] ?? 'null' ) );
}

foreach ( array( 'wc_zibal', 'WC_ZPal', 'zarinpal' ) as $id ) {
	$d = WBGP_Fees::decide( $id, $subtotal, $settings );
	$ok = $d && 'discount' === $d['kind'] && abs( $d['amount'] - 50000 ) < 1;
	wbgp_assert( $ok, "discount 5% on $id => " . ( $d['amount'] ?? 'null' ) );
}

wbgp_assert( null === WBGP_Fees::decide( '', $subtotal, $settings ), 'empty method => no fee' );
wbgp_assert( null === WBGP_Fees::decide( 'snapppay', 0, $settings ), 'empty cart => no fee' );

// fixed fee / fixed discount
$fixed_fee_settings = array_merge( $settings, array( 'installment_type' => 'fixed', 'installment_amount' => 25000 ) );
$d = WBGP_Fees::decide( 'snapppay', $subtotal, $fixed_fee_settings );
wbgp_assert( $d && abs( $d['amount'] - 25000 ) < 1, 'fixed installment fee 25000' );

$fixed_disc = array_merge( $settings, array( 'cash_discount_type' => 'fixed', 'cash_discount_amount' => 10000 ) );
$d = WBGP_Fees::decide( 'wc_zibal', $subtotal, $fixed_disc );
wbgp_assert( $d && 'discount' === $d['kind'] && abs( $d['amount'] - 10000 ) < 1, 'fixed cash discount 10000' );

echo "=== Integration: WooCommerce cart fees ===\n";

// اطمینان از سشن و سبد
if ( null === WC()->session ) {
	$session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
	WC()->session  = new $session_class();
	WC()->session->init();
}
if ( null === WC()->cart ) {
	WC()->cart = new WC_Cart();
}

// محصول تست
$product_id = wp_insert_post(
	array(
		'post_title'  => 'WBGP Test Product',
		'post_type'   => 'product',
		'post_status' => 'publish',
	)
);
update_post_meta( $product_id, '_regular_price', '1000000' );
update_post_meta( $product_id, '_price', '1000000' );
update_post_meta( $product_id, '_virtual', 'yes' );
wp_set_object_terms( $product_id, 'simple', 'product_type' );

WC()->cart->empty_cart();
WC()->cart->add_to_cart( $product_id, 1 );

$cases = array(
	'snapppay'   => array( 'fee', 150000 ),
	'torobpay'   => array( 'fee', 150000 ),
	'digipay'    => array( 'fee', 150000 ),
	'wc_zibal'   => array( 'discount', 50000 ),
	'WC_ZPal'    => array( 'discount', 50000 ),
	'zarinpal'   => array( 'discount', 50000 ),
);

foreach ( $cases as $method => $expect ) {
	WC()->session->set( 'chosen_payment_method', $method );
	// شبیه‌سازی POST مثل update_checkout
	$_POST['payment_method'] = $method;
	WC()->cart->calculate_totals();

	$fees = WC()->cart->get_fees();
	$sum  = 0.0;
	foreach ( $fees as $fee ) {
		$sum += (float) $fee->total;
	}

	if ( 'fee' === $expect[0] ) {
		$ok = abs( $sum - $expect[1] ) < 1;
		wbgp_assert( $ok, "cart fee for $method = $sum (expect {$expect[1]})" );
	} else {
		$ok = abs( $sum + $expect[1] ) < 1; // negative fee
		wbgp_assert( $ok, "cart discount for $method = $sum (expect -{$expect[1]})" );
	}
}

// بدون انتخاب درگاه نباید fee بماند
unset( $_POST['payment_method'] );
WC()->session->set( 'chosen_payment_method', '' );
WC()->cart->calculate_totals();
$fees = WC()->cart->get_fees();
wbgp_assert( empty( $fees ), 'no payment method => no fees on cart' );

echo "=== Plugin + WooCommerce active ===\n";
wbgp_assert( is_plugin_active( 'woocommerce/woocommerce.php' ), 'WooCommerce active' );
wbgp_assert( is_plugin_active( 'webakery-gateway-pricing/webakery-gateway-pricing.php' ), 'WBGP active' );
wbgp_assert( class_exists( 'WooCommerce' ), 'WooCommerce class exists' );

echo "\n----\nPassed: {$GLOBALS['wbgp_passed']}  Failed: {$GLOBALS['wbgp_failed']}\n";
exit( $GLOBALS['wbgp_failed'] > 0 ? 1 : 0 );
