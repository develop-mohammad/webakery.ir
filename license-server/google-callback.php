<?php
/**
 * Google OAuth callback — پورتال مشتری
 * Google بعد از تأیید کاربر به اینجا ریدایرکت می‌کند
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/LicenseManager.php';

function google_post( string $url, array $data ): array {
    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_POST,           true );
    curl_setopt( $ch, CURLOPT_POSTFIELDS,     http_build_query( $data ) );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_TIMEOUT,        15 );
    $body = curl_exec( $ch );
    curl_close( $ch );
    return json_decode( $body ?: '{}', true ) ?: [];
}

function google_get( string $url, string $token ): array {
    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_HTTPHEADER,     [ 'Authorization: Bearer ' . $token ] );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_TIMEOUT,        15 );
    $body = curl_exec( $ch );
    curl_close( $ch );
    return json_decode( $body ?: '{}', true ) ?: [];
}

$error = '';

/* ─── بررسی state برای جلوگیری از CSRF ─────────────────────── */
$state_get     = $_GET['state'] ?? '';
$state_session = $_SESSION['google_oauth_state'] ?? '';
unset( $_SESSION['google_oauth_state'] );

if ( ! $state_get || ! hash_equals( $state_session, $state_get ) ) {
    $error = 'خطای امنیتی — لطفاً دوباره تلاش کنید.';
}

/* ─── دریافت code از Google ─────────────────────────────────── */
if ( ! $error ) {
    $code = $_GET['code'] ?? '';
    if ( ! $code ) {
        $error = 'ورود با Google لغو شد یا با خطا مواجه شد.';
    }
}

/* ─── تبدیل code به access token ───────────────────────────── */
if ( ! $error ) {
    $token_data = google_post( 'https://oauth2.googleapis.com/token', [
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code',
    ] );

    $access_token = $token_data['access_token'] ?? '';
    if ( ! $access_token ) {
        $error = 'خطا در دریافت توکن از Google.';
    }
}

/* ─── دریافت اطلاعات کاربر از Google ───────────────────────── */
if ( ! $error ) {
    $user_info = google_get( 'https://www.googleapis.com/oauth2/v2/userinfo', $access_token );
    $email     = strtolower( trim( $user_info['email'] ?? '' ) );

    if ( ! $email || empty( $user_info['verified_email'] ) ) {
        $error = 'ایمیل Google تأیید نشده است.';
    }
}

/* ─── ورود/ثبت‌نام آزاد با Google — هر ایمیل تأیید‌شده مجاز است ─── */
// اگر لایسنسی نداشت، داشبورد با پیام خرید نمایش می‌شود

/* ─── ورود موفق ─────────────────────────────────────────────── */
if ( ! $error ) {
    $_SESSION['portal_email']  = $email;
    $_SESSION['portal_google'] = true;
    $_SESSION['portal_name']   = $user_info['name'] ?? '';
    header( 'Location: ./' );
    exit;
}

/* ─── نمایش خطا ─────────────────────────────────────────────── */
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>خطا در ورود — پورتال webakery.ir</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Tahoma,Arial,sans-serif;background:#f3f4f6;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#fff;border-radius:14px;box-shadow:0 4px 24px rgba(0,0,0,.09);padding:36px;max-width:440px;width:100%;text-align:center}
.icon{font-size:48px;margin-bottom:16px}
h2{color:#dc2626;font-size:18px;margin-bottom:12px}
p{color:#374151;font-size:13px;line-height:1.8;margin-bottom:20px}
a{display:inline-block;padding:11px 28px;background:#6c63ff;color:#fff;border-radius:8px;text-decoration:none;font-size:14px;font-weight:700}
a:hover{background:#5a52e0}
</style>
</head>
<body>
<div class="card">
    <div class="icon">⚠️</div>
    <h2>ورود ناموفق</h2>
    <p><?= htmlspecialchars( $error ) ?></p>
    <a href="./">بازگشت به پورتال</a>
</div>
</body>
</html>
