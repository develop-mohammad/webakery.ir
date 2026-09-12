<?php
/**
 * Smoke: نمایش تسویه‌های واریزشده + پیامک settle
 */
error_reporting( E_ALL );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}
function get_option( $k, $d = false ) { return $d; }
function update_option( $k, $v, $a = true ) { return true; }
function current_time( $t ) { return date( 'Y-m-d H:i:s' ); }
function sanitize_text_field( $v ) { return is_string( $v ) ? trim( strip_tags( $v ) ) : (string) $v; }
function sanitize_textarea_field( $v ) { return sanitize_text_field( $v ); }
function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }

$root = dirname( __DIR__ );
require_once $root . '/includes/class-wap-jalali.php';
require_once $root . '/includes/class-wap-zarinpal-report.php';

assert( WAP_Zarinpal_Report::status_label( 'PAID' ) === 'تسویه شده' );
assert( WAP_Zarinpal_Report::status_label( 'IN_PROGRESS' ) === 'در حال انجام' );

$portal = file_get_contents( $root . '/includes/class-wap-portal.php' );
assert( strpos( $portal, 'مبلغ خالص تسویه' ) !== false );
assert( strpos( $portal, 'تاریخ تخمینی واریز' ) !== false );
assert( strpos( $portal, 'شناسه ارجاع بانکی' ) !== false );
assert( strpos( $portal, 'wap-settle-badge' ) !== false );

$report = file_get_contents( $root . '/includes/class-wap-zarinpal-report.php' );
assert( strpos( $report, 'WAP_Payment_Notify' ) === false, 'no deleted Payment_Notify' );
assert( strpos( $report, 'WAP_Gateway::is_family' ) !== false );
assert( strpos( $report, 'payable_display' ) !== false );

$sms = file_get_contents( $root . '/includes/class-wap-sms.php' );
assert( strpos( $sms, 'مبلغ خالص' ) !== false || strpos( $sms, 'amount_rial' ) !== false );

$boot = file_get_contents( $root . '/hesabdar.php' );
assert( strpos( $boot, '1.23.0' ) !== false );
assert( strpos( $boot, 'class-wap-zarinpal-reconcile.php' ) !== false );

echo "ALL SETTLE DISPLAY SMOKE TESTS PASSED\n";
