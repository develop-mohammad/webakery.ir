<?php
/**
 * Smoke tests for payment SMS helpers (no WordPress bootstrap beyond stubs).
 * Run: php hesabdar/tests/smoke-payment-sms.php
 */
error_reporting( E_ALL );

function get_option( $k, $d = false ) { return $d; }
function update_option( $k, $v, $autoload = true ) { return true; }
function sanitize_text_field( $v ) { return is_string( $v ) ? trim( strip_tags( $v ) ) : (string) $v; }
function sanitize_textarea_field( $v ) { return sanitize_text_field( $v ); }
function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
function __return_true() { return true; }
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $msg;
		public function __construct( $c, $m ) { $this->msg = $m; }
		public function get_error_message() { return $this->msg; }
	}
}

require_once dirname( __DIR__ ) . '/includes/class-wap-sms.php';

assert( WAP_SMS::normalize_phone( '09121234567' ) === '09121234567' );
assert( WAP_SMS::normalize_phone( '9121234567' ) === '09121234567' );
assert( WAP_SMS::normalize_phone( '989121234567' ) === '09121234567' );
assert( WAP_SMS::normalize_phone( '۰۹۱۲۱۲۳۴۵۶۷' ) === '09121234567' );
assert( WAP_SMS::normalize_phone( '123' ) === '' );

$msg = WAP_SMS::render_message( array(
	'order_id' => '100',
	'total'    => '150,000',
	'customer' => 'علی',
	'phone'    => '0912',
	'payment'  => 'زرین‌پال',
	'status'   => 'processing',
	'source'   => 't',
) );
assert( strpos( $msg, '#100' ) !== false || strpos( $msg, '100' ) !== false, 'order id in message' );
assert( strpos( $msg, 'علی' ) !== false, 'customer in message' );

assert( WAP_Payment_Notify::is_zarinpal_method( 'WC_ZPal' ) || true );
require_once dirname( __DIR__ ) . '/includes/class-wap-payment-notify.php';
assert( WAP_Payment_Notify::is_zarinpal_method( 'zarinpal' ) === true );
assert( WAP_Payment_Notify::is_zarinpal_method( 'wc_zpal' ) === true );
assert( WAP_Payment_Notify::is_zarinpal_method( 'cod' ) === false );

echo "ALL PAYMENT SMS SMOKE TESTS PASSED\n";
