<?php
/**
 * Smoke: پیامک فقط تاریخ+خالص + مسیر خرید→شاپرک→واریز
 */
error_reporting( E_ALL );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}
function get_option( $k, $d = false ) { return $d; }
function update_option( $k, $v, $a = true ) { return true; }
function sanitize_text_field( $v ) { return is_string( $v ) ? trim( strip_tags( $v ) ) : (string) $v; }
function sanitize_textarea_field( $v ) { return sanitize_text_field( $v ); }
function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }

$root = dirname( __DIR__ );
require_once $root . '/includes/class-wap-sms.php';

$tpl = WAP_SMS::fixed_settle_template();
assert( strpos( $tpl, '{amount_rial}' ) !== false );
assert( strpos( $tpl, '{payable_at}' ) !== false );
assert( strpos( $tpl, 'reconcile_id' ) === false );
assert( strpos( $tpl, 'reference_id' ) === false );

$msg = WAP_SMS::render_settle_message( array(
	'amount_rial' => '38,795,000',
	'payable_at'  => 'امروز ۰۴:۰۰',
	'reference_id'=> 'SHOULD-NOT-APPEAR',
) );
assert( strpos( $msg, '38,795,000' ) !== false );
assert( strpos( $msg, 'امروز ۰۴:۰۰' ) !== false );
assert( strpos( $msg, 'SHOULD-NOT-APPEAR' ) === false );

$portal = file_get_contents( $root . '/includes/class-wap-portal.php' );
assert( strpos( $portal, 'wap-flow-steps' ) !== false );
assert( strpos( $portal, 'خرید مشتری' ) !== false );
assert( strpos( $portal, 'تأیید شاپرک' ) !== false );
assert( strpos( $portal, 'واریز خالص به حساب' ) !== false );
assert( strpos( $portal, 'settles_pending' ) !== false );
assert( strpos( $portal, 'settles_paid' ) !== false );

$report = file_get_contents( $root . '/includes/class-wap-zarinpal-report.php' );
assert( strpos( $report, "'filter' => 'ALL'" ) !== false || strpos( $report, '"filter" => "ALL"' ) !== false || strpos( $report, "filter' => 'ALL'" ) !== false );

$admin = file_get_contents( $root . '/includes/class-wap-admin.php' );
assert( strpos( $admin, 'fixed_settle_template' ) !== false );
assert( strpos( $admin, 'name="wap_sms[settle_message]"' ) === false );

$boot = file_get_contents( $root . '/hesabdar.php' );
assert( strpos( $boot, '1.22.0' ) !== false );

echo "ALL NET-DEPOSIT SMS + FLOW SMOKE PASSED\n";
