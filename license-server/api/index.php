<?php
/**
 * License Server API
 * endpoints: /api/?action=create|activate|validate|deactivate|revoke|ping|update|coupon_validate|coupon_list
 */
if ( file_exists( __DIR__ . '/../config.php' ) ) require_once __DIR__ . '/../config.php';
header( 'Content-Type: application/json; charset=utf-8' );
header( 'Access-Control-Allow-Origin: *' );

require_once __DIR__ . '/../includes/LicenseManager.php';
require_once __DIR__ . '/../includes/UpdateManager.php';

// کلید امنیتی API — در env یا admin panel تنظیم کنید
if ( ! defined( 'API_SECRET' ) ) {
    define( 'API_SECRET', getenv('WCCP_API_SECRET') ?: 'change-this-secret-key' );
}

$action = $_REQUEST['action'] ?? '';
$input  = json_decode( file_get_contents('php://input'), true ) ?: $_POST;

// اندپوینت‌های محافظت‌شده که نیاز به secret دارند
$protected = [ 'create', 'revoke', 'coupon_list' ];
if ( in_array( $action, $protected, true ) ) {
    $auth = $_SERVER['HTTP_X_WBLM_SECRET'] ?? '';
    if ( $auth !== API_SECRET ) { json_out( false, 'Unauthorized', 401 ); }
}

switch ( $action ) {

    case 'ping':
        json_out( true, 'pong' );

    case 'create':
        $email      = sanitize( $input['email']      ?? '' );
        $product    = sanitize( $input['product']    ?? 'wccp' );
        $note       = sanitize( $input['note']       ?? '' );
        $expires_at = sanitize( $input['expires_at'] ?? '' ) ?: null;
        $domain     = sanitize( $input['domain']     ?? '' );
        if ( ! $email ) { json_out( false, 'پارامتر email الزامی است.' ); }
        $lic = LicenseManager::create( $email, $product, $note, $expires_at, $domain );
        $out = $lic;
        if ( $domain !== '' ) {
            $acts = LicenseManager::activations_of( $lic['license_key'] );
            $out['activations'] = $acts;
        }
        json_out( true, 'لایسنس ساخته شد.', 200, $out );

    case 'revoke':
        $key = sanitize( $input['license_key'] ?? '' );
        if ( ! $key ) { json_out( false, 'پارامتر license_key الزامی است.' ); }
        LicenseManager::revoke( $key );
        json_out( true, 'لایسنس باطل شد.' );

    case 'activate':
        $key    = sanitize( $input['license_key'] ?? '' );
        $domain = sanitize( $input['domain'] ?? ($_SERVER['HTTP_REFERER'] ?? '') );
        $ip     = $_SERVER['REMOTE_ADDR'] ?? '';
        if ( ! $key || ! $domain ) { json_out( false, 'پارامتر license_key و domain الزامی است.' ); }
        $result = LicenseManager::activate( $key, $domain, $ip );
        json_out( $result['success'], $result['message'], 200, $result );
        break;

    case 'validate':
        $key     = sanitize( $input['license_key'] ?? '' );
        $domain  = sanitize( $input['domain']      ?? '' );
        $product = sanitize( $input['product']     ?? '' );
        if ( ! $key || ! $domain ) { json_out( false, 'پارامتر license_key و domain الزامی است.' ); }
        $result = LicenseManager::validate( $key, $domain, $product );
        json_out( $result['valid'], $result['valid'] ? 'معتبر' : ($result['error'] ?? 'نامعتبر'), 200, $result );
        break;

    case 'deactivate':
        $key    = sanitize( $input['license_key'] ?? '' );
        $domain = sanitize( $input['domain'] ?? '' );
        if ( ! $key || ! $domain ) { json_out( false, 'پارامتر license_key و domain الزامی است.' ); }
        $result = LicenseManager::deactivate_domain( $key, $domain );
        json_out( true, $result['message'] );
        break;

    // ─── به‌روزرسانی افزونه‌ها (WB_License + BAGET Updater) ─────
    case 'update':
        $req = array_merge(
            $_GET,
            is_array( $input ) ? $input : array()
        );
        $payload = UpdateManager::get_update_payload( array(
            'product'     => sanitize( $req['product'] ?? '' ),
            'version'     => sanitize( $req['version'] ?? '' ),
            'license_key' => sanitize( $req['license_key'] ?? ( $req['license'] ?? '' ) ),
            'domain'      => sanitize( $req['domain'] ?? '' ),
        ) );
        json_out(
            ! empty( $payload['success'] ),
            $payload['message'] ?? '—',
            200,
            $payload
        );
        break;

    // ─── کد تخفیف ───────────────────────────────────────────────
    case 'coupon_validate':
        $code    = sanitize( $input['code']    ?? '' );
        $product = sanitize( $input['product'] ?? 'wccp' );
        $amount  = (int) ( $input['amount'] ?? 0 );
        if ( ! $code ) { json_out( false, 'پارامتر code الزامی است.' ); }
        // اگر amount پاس داده نشد، از LS_PRICES استفاده می‌کنیم
        if ( $amount <= 0 ) {
            $prices = defined('LS_PRICES') && is_array(LS_PRICES) ? LS_PRICES : [];
            $amount = isset( $prices[ $product ] ) ? (int) $prices[ $product ] : 0;
            $promos = defined('LS_PROMOS') && is_array(LS_PROMOS) ? LS_PROMOS : [];
            if ( isset( $promos[ $product ]['price'], $promos[ $product ]['until'] )
                 && time() < (int) $promos[ $product ]['until'] ) {
                $amount = (int) $promos[ $product ]['price'];
            }
        }
        if ( $amount <= 0 ) { json_out( false, 'مبلغ برای اعتبارسنجی لازم است.' ); }
        $r = CouponManager::validate( $code, $product, $amount );
        json_out( $r['valid'], $r['message'], 200, [
            'discount' => $r['discount'],
            'final'    => $r['final'],
            'coupon'   => $r['coupon'] ? [
                'code'       => $r['coupon']['code'],
                'type'       => $r['coupon']['type'],
                'value'      => $r['coupon']['value'],
                'product'    => $r['coupon']['product'],
                'expires_at' => $r['coupon']['expires_at'],
            ] : null,
        ] );
        break;

    case 'coupon_list':
        // فقط برای ادمین (نیاز به secret دارد)
        $list = array_map( function( $c ) {
            return [
                'id'          => $c['id'],
                'code'        => $c['code'],
                'type'        => $c['type'],
                'value'       => $c['value'],
                'product'     => $c['product'],
                'max_uses'    => $c['max_uses'],
                'used_count'  => $c['used_count'],
                'min_amount'  => $c['min_amount'],
                'expires_at'  => $c['expires_at'],
                'status'      => $c['status'],
                'note'        => $c['note'],
                'created_at'  => $c['created_at'],
            ];
        }, Database::coupon_all() );
        json_out( true, 'لیست کدهای تخفیف', 200, [ 'coupons' => $list ] );
        break;

    default:
        json_out( false, 'action نامعتبر است. مقادیر مجاز: create, activate, validate, deactivate, revoke, ping, update, coupon_validate, coupon_list' );
}

function json_out( bool $success, string $message, int $code = 200, array $extra = [] ) {
    http_response_code( $code );
    echo json_encode( array_merge( [ 'success' => $success, 'message' => $message ], $extra ), JSON_UNESCAPED_UNICODE );
    exit;
}

function sanitize( string $v ): string {
    return trim( strip_tags( $v ) );
}
