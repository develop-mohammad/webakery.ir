<?php
/**
 * بررسی آنلاین کد تخفیف — pay/check-coupon.php
 * خروجی JSON با ساختار:
 *   { valid, message, discount_toman, final_toman }
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/LicenseManager.php';

header( 'Content-Type: application/json; charset=utf-8' );
header( 'Access-Control-Allow-Origin: *' );

$code   = trim( $_POST['code']   ?? $_GET['code']   ?? '' );
$plugin = preg_replace( '/[^a-z0-9_-]/i', '', $_POST['plugin'] ?? $_GET['plugin'] ?? 'wccp' );
$amount = (int) ( $_POST['amount'] ?? $_GET['amount'] ?? 0 );

if ( $amount <= 0 ) {
    // اگر مبلغ پاس داده نشد، قیمت پایه‌ی محصول را از config می‌گیریم
    $prices = defined('LS_PRICES') && is_array(LS_PRICES) ? LS_PRICES : [];
    $amount = isset( $prices[ $plugin ] ) ? (int) $prices[ $plugin ] : 999990;

    // اگر تخفیف زمان‌دار فعال است، قیمت پایه همان تخفیف‌دار است
    $promos = defined('LS_PROMOS') && is_array(LS_PROMOS) ? LS_PROMOS : [];
    if ( isset( $promos[ $plugin ]['price'], $promos[ $plugin ]['until'] ) ) {
        if ( time() < (int) $promos[ $plugin ]['until'] ) {
            $amount = (int) $promos[ $plugin ]['price'];
        }
    }
}

if ( $code === '' ) {
    echo json_encode( [
        'valid'          => false,
        'message'        => 'کد تخفیف وارد نشده.',
        'discount_toman' => 0,
        'final_toman'    => (int) ( $amount / 10 ),
    ], JSON_UNESCAPED_UNICODE );
    exit;
}

$r = CouponManager::validate( $code, $plugin, $amount );

echo json_encode( [
    'valid'          => $r['valid'],
    'message'        => $r['message'],
    'discount_toman' => (int) ( $r['discount'] / 10 ),
    'final_toman'    => (int) ( $r['final'] / 10 ),
], JSON_UNESCAPED_UNICODE );
