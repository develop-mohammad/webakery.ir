<?php
/**
 * تست محلی API آپدیت — بدون وردپرس.
 * اجرا: php tools/test-update-api.php
 */
$root = dirname( __DIR__ );
require_once $root . '/config.php';
require_once $root . '/includes/Database.php';
require_once $root . '/includes/LicenseManager.php';
require_once $root . '/includes/UpdateManager.php';

$product     = $argv[1] ?? 'hesabdar';
$version     = $argv[2] ?? '1.9.0';
$license_key = $argv[3] ?? '';
$domain      = $argv[4] ?? 'example.com';

$payload = UpdateManager::get_update_payload( [
    'product'     => $product,
    'version'     => $version,
    'license_key' => $license_key,
    'domain'      => $domain,
] );

echo json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . PHP_EOL;

if ( ! empty( $payload['success'] ) && ! empty( $payload['update_available'] ) ) {
    echo PHP_EOL . "OK: نسخه {$payload['version']} برای {$product} (نصب‌شده: {$version}) — در افزونه‌ها باید آپدیت دیده شود." . PHP_EOL;
    $zip = UpdateManager::zip_path( $product );
    echo $zip ? "ZIP: {$zip} (" . filesize( $zip ) . " bytes)" . PHP_EOL : "WARN: فایل ZIP روی سرور نیست." . PHP_EOL;
} elseif ( ! empty( $payload['success'] ) ) {
    echo PHP_EOL . "INFO: سرور پاسخ داد ولی update_available=false (نسخه سرور بالاتر از نصب‌شده نیست)." . PHP_EOL;
} else {
    echo PHP_EOL . "FAIL: " . ( $payload['message'] ?? 'خطا' ) . PHP_EOL;
    exit( 1 );
}
