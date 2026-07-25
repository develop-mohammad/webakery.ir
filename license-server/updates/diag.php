<?php
/**
 * تشخیص خطای ۵۰۰ — این فایل را در updates آپلود کنید.
 * آدرس: https://webakery.ir/license-server/updates/diag.php
 * بعد از رفع مشکل حذف شود.
 */
ini_set( 'display_errors', '1' );
ini_set( 'display_startup_errors', '1' );
error_reporting( E_ALL );
header( 'Content-Type: text/plain; charset=utf-8' );

echo "STEP 0: updates/diag.php works\n";
echo 'PHP ' . PHP_VERSION . "\n\n";

register_shutdown_function(
	static function () {
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
		echo 'line: ' . (int) ( $e['line'] ?? 0 ) . "\n";
	}
);

$root = dirname( __DIR__ );
echo "STEP 1: license-server root = {$root}\n";

$files = [
	'config.php'                   => $root . '/config.php',
	'includes/Database.php'        => $root . '/includes/Database.php',
	'includes/LicenseManager.php'  => $root . '/includes/LicenseManager.php',
	'includes/Mailer.php'          => $root . '/includes/Mailer.php',
	'pay/index.php'                => $root . '/pay/index.php',
	'updates/webakery-chat-box.zip'=> $root . '/updates/webakery-chat-box.zip',
];

foreach ( $files as $label => $path ) {
	if ( ! file_exists( $path ) ) {
		echo "MISSING: {$label}\n";
		continue;
	}
	echo 'FOUND: ' . $label . ' (' . filesize( $path ) . " bytes)\n";
}

echo "\nSTEP 2: load config.php ...\n";
require_once $root . '/config.php';
echo "config.php OK\n";
echo 'LS_PLANS: ' . ( defined( 'LS_PLANS' ) ? 'yes' : 'NO' ) . "\n";
echo 'ZIBAL_MERCHANT: ' . ( defined( 'ZIBAL_MERCHANT' ) ? 'yes' : 'NO' ) . "\n";

echo "\nSTEP 3: load Database.php ...\n";
require_once $root . '/includes/Database.php';
echo "Database OK\n";

echo "\nSTEP 4: load LicenseManager.php ...\n";
require_once $root . '/includes/LicenseManager.php';
echo "LicenseManager OK\n";
echo 'lifetime method: ' . ( method_exists( 'LicenseManager', 'create_or_upgrade_lifetime' ) ? 'yes' : 'NO' ) . "\n";

echo "\nSTEP 5: load Mailer.php ...\n";
require_once $root . '/includes/Mailer.php';
echo "Mailer OK\n";

echo "\nALL GOOD — مشکل از فایل‌های اصلی برطرف است.\n";
echo "الان برو: https://webakery.ir/license-server/pay/?plugin=webakery-chat\n";
