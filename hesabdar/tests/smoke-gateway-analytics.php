<?php
error_reporting( E_ALL );
define( 'ABSPATH', '/' );
function fail( $m ) { fwrite( STDERR, "FAIL: $m\n" ); exit( 1 ); }
function ok( $c, $m ) { if ( ! $c ) fail( $m ); echo "OK: $m\n"; }

require dirname( __DIR__ ) . '/includes/class-wap-gateway.php';

ok( WAP_Gateway::family( 'WC_ZPal' ) === 'zarinpal', 'zarinpal family' );
ok( WAP_Gateway::family( 'wc_zibal' ) === 'zibal', 'zibal family' );
ok( WAP_Gateway::family( 'torobpay' ) === 'torobpay', 'torob family' );
ok( WAP_Gateway::family( 'snapppay' ) === 'snapppay', 'snapp family' );
ok( WAP_Gateway::family( 'digipay' ) === 'digipay', 'digipay family' );
ok( WAP_Gateway::family( 'idpay' ) === 'idpay', 'idpay family' );
ok( WAP_Gateway::label( 'zibal' ) === 'زیبال', 'zibal label' );

// Zarinpal fee via gateway needs zarinpal fee class
require dirname( __DIR__ ) . '/includes/class-wap-zarinpal-fee.php';
ok( WAP_Gateway::estimate_fee_toman( 'zarinpal', 195000 ) === 1475, 'zarinpal fee via gateway' );

// Zibal 1% with min 2000 max 20000
ok( WAP_Gateway::estimate_fee_toman( 'zibal', 100000 ) === 2000, 'zibal min floor' ); // 1%=1000 -> min 2000
ok( WAP_Gateway::estimate_fee_toman( 'zibal', 500000 ) === 5000, 'zibal 1%' );
ok( WAP_Gateway::estimate_fee_toman( 'zibal', 5000000 ) === 20000, 'zibal cap' );

// IDPay 1% cap 5000
ok( WAP_Gateway::estimate_fee_toman( 'idpay', 1000000 ) === 5000, 'idpay cap' );
ok( WAP_Gateway::estimate_fee_toman( 'idpay', 200000 ) === 2000, 'idpay 1%' );

// Torob 6.6%
ok( WAP_Gateway::estimate_fee_toman( 'torobpay', 1000000 ) === 66000, 'torob 6.6%' );

// Digipay ~5%
ok( WAP_Gateway::estimate_fee_toman( 'digipay', 1000000 ) === 50000, 'digipay 5%' );

// Snapp default 0
ok( WAP_Gateway::estimate_fee_toman( 'snapppay', 1000000 ) === 0, 'snapp 0' );

require dirname( __DIR__ ) . '/includes/class-wap-traffic.php';
ok( WAP_Traffic::source_label( 'google_organic' ) === 'گوگل ارگانیک', 'traffic label google' );
ok( WAP_Traffic::source_label( 'social' ) === 'سوشال‌مدیا', 'traffic label social' );

echo "ALL GATEWAY/ANALYTICS SMOKE TESTS PASSED\n";
