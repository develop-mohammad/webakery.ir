<?php
/**
 * Smoke: فقط پیامک واریز شاپرک (بدون پیامک پرداخت مشتری)
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

$root = dirname( __DIR__ );
require_once $root . '/includes/class-wap-sms.php';

assert( WAP_SMS::normalize_phone( '09121234567' ) === '09121234567' );
assert( WAP_SMS::normalize_phone( '9121234567' ) === '09121234567' );
assert( WAP_SMS::normalize_phone( '989121234567' ) === '09121234567' );
assert( WAP_SMS::normalize_phone( '۰۹۱۲۱۲۳۴۵۶۷' ) === '09121234567' );
assert( WAP_SMS::normalize_phone( '123' ) === '' );

$opts = WAP_SMS::get();
assert( (int) $opts['enabled'] === 0, 'payment SMS always off' );
assert( (int) $opts['settle_enabled'] === 1, 'settle SMS default on' );
assert( strpos( (string) $opts['settle_message'], 'شاپرک' ) !== false || strpos( (string) $opts['settle_message'], '{amount}' ) !== false );

$msg = WAP_SMS::render_settle_message( array(
	'amount'        => '1,250,000',
	'amount_rial'   => '12,500,000',
	'reference_id'  => 'REF-1',
	'reconcile_id'  => '9',
	'status'        => 'PAID',
	'reconciled_at' => '1405-01-01',
) );
assert( strpos( $msg, '1,250,000' ) !== false, 'amount in settle message' );
assert( strpos( $msg, 'REF-1' ) !== false, 'reference in settle message' );

$admin = file_get_contents( $root . '/includes/class-wap-admin.php' );
assert( strpos( $admin, 'پیامک بعد از پرداخت مشتری' ) === false, 'no payment SMS UI' );
assert( strpos( $admin, 'واریز شاپرک به حساب' ) !== false, 'settle SMS UI present' );
assert( strpos( $admin, 'wap_test_settle_sms' ) !== false, 'settle test action' );

$boot = file_get_contents( $root . '/hesabdar.php' );
assert( strpos( $boot, 'class-wap-payment-notify.php' ) === false, 'payment notify not loaded' );
assert( strpos( $boot, 'WAP_Payment_Notify' ) === false, 'payment notify not inited' );
assert( strpos( $boot, 'class-wap-zarinpal-reconcile.php' ) !== false, 'reconcile loaded' );
assert( strpos( $boot, "1.21.0" ) !== false, 'version 1.21.0' );
assert( ! is_readable( $root . '/includes/class-wap-payment-notify.php' ), 'payment notify file removed' );

echo "ALL SETTLE-ONLY SMS SMOKE TESTS PASSED\n";
