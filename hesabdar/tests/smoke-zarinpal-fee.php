<?php
/**
 * Smoke tests for Zarinpal fee formula + report helpers.
 */
error_reporting( E_ALL );
define( 'ABSPATH', '/' );

function fail( $m ) { fwrite( STDERR, "FAIL: $m\n" ); exit( 1 ); }
function ok( $c, $m ) { if ( ! $c ) fail( $m ); }

function wp_json_encode( $d ) { return json_encode( $d ); }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
class WP_Error {
	private $m;
	public function __construct( $c, $m ) { $this->m = $m; }
	public function get_error_message() { return $this->m; }
}

require dirname( __DIR__ ) . '/includes/class-wap-zarinpal-fee.php';

// Official tariff samples (verified against feeCalculation API)
ok( WAP_Zarinpal_Fee::estimate_toman( 10000 ) === 550, '10k toman => 550' );
ok( WAP_Zarinpal_Fee::estimate_toman( 50000 ) === 750, '50k => 750' );
ok( WAP_Zarinpal_Fee::estimate_toman( 195000 ) === 1475, '195k => 1475' );
ok( WAP_Zarinpal_Fee::estimate_toman( 1000000 ) === 5500, '1M => 5500' );
ok( WAP_Zarinpal_Fee::estimate_toman( 5000000 ) === 16500, '5M => 16500 (cap+fixed)' );
ok( WAP_Zarinpal_Fee::estimate_toman( 16000000 ) === 16500, '16M still capped' );
ok( WAP_Zarinpal_Fee::estimate_rial( 1950000 ) === 14750, 'rial estimate' );
ok( (int) WAP_Zarinpal_Fee::net_after_fee_toman( 195000 ) === 193525, 'net after fee' );

$r = WAP_Zarinpal_Fee::resolve_fee( 195000, false );
ok( $r['fee_toman'] === 1475 && $r['source'] === 'formula', 'resolve formula' );

ok( strpos( WAP_Zarinpal_Fee::tariff_note(), '۰٫۵٪' ) !== false, 'tariff note' );

echo "ALL ZARINPAL FEE SMOKE TESTS PASSED\n";
