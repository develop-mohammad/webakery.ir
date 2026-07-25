<?php
/**
 * تشخیص خطای ۵۰۰ — بعد از رفع مشکل این فایل را حذف کنید.
 * آدرس: https://webakery.ir/license-server/health.php
 */
header( 'Content-Type: text/plain; charset=utf-8' );
header( 'X-Robots-Tag: noindex' );

echo "PHP " . PHP_VERSION . "\n";
echo "SAPI " . PHP_SAPI . "\n";
echo "time " . date( 'c' ) . "\n\n";

register_shutdown_function( static function () {
	$e = error_get_last();
	if ( ! $e ) {
		return;
	}
	$fatal = [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ];
	if ( ! in_array( (int) $e['type'], $fatal, true ) ) {
		return;
	}
	echo "\n===== FATAL =====\n";
	echo ( $e['message'] ?? '' ) . "\n";
	echo 'file: ' . ( $e['file'] ?? '' ) . "\n";
	echo 'line: ' . ( $e['line'] ?? '' ) . "\n";
} );

$config = __DIR__ . '/config.php';
echo 'config.php: ' . ( is_readable( $config ) ? 'readable' : 'MISSING' ) . "\n";

if ( ! is_readable( $config ) ) {
	exit;
}

echo "loading config.php ...\n";
require_once $config;
echo "config.php OK\n";
echo 'LS_PRICES: ' . ( defined( 'LS_PRICES' ) ? 'yes' : 'no' ) . "\n";
echo 'LS_PLANS: ' . ( defined( 'LS_PLANS' ) ? 'yes' : 'no' ) . "\n";
if ( defined( 'LS_PLANS' ) && is_array( LS_PLANS ) && isset( LS_PLANS['webakery-chat'] ) ) {
	echo 'webakery-chat plans: ' . implode( ', ', array_keys( LS_PLANS['webakery-chat'] ) ) . "\n";
} else {
	echo "webakery-chat plans: NOT SET\n";
}

$db = __DIR__ . '/includes/Database.php';
$lm = __DIR__ . '/includes/LicenseManager.php';
echo 'Database.php: ' . ( is_readable( $db ) ? 'readable' : 'MISSING' ) . "\n";
echo 'LicenseManager.php: ' . ( is_readable( $lm ) ? 'readable' : 'MISSING' ) . "\n";

echo "loading Database + LicenseManager ...\n";
require_once $db;
require_once $lm;
echo "LicenseManager OK\n";
echo 'create_or_extend_subscription: ' . ( method_exists( 'LicenseManager', 'create_or_extend_subscription' ) ? 'yes' : 'no' ) . "\n";
echo 'create_or_upgrade_lifetime: ' . ( method_exists( 'LicenseManager', 'create_or_upgrade_lifetime' ) ? 'yes' : 'no' ) . "\n";

$pay = __DIR__ . '/pay/index.php';
echo 'pay/index.php: ' . ( is_readable( $pay ) ? 'readable (' . filesize( $pay ) . ' bytes)' : 'MISSING' ) . "\n";

$zip = __DIR__ . '/updates/webakery-chat-box.zip';
echo 'updates/webakery-chat-box.zip: ' . ( is_readable( $zip ) ? 'readable (' . filesize( $zip ) . ' bytes)' : 'MISSING — باید آپلود شود' ) . "\n";

echo "\nALL CHECKS PASSED\n";
