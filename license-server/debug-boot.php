<?php
/**
 * نمایش خطای واقعی — بعد از رفع، حذف کنید.
 * https://webakery.ir/license-server/debug-boot.php
 */
ini_set( 'display_errors', '1' );
ini_set( 'display_startup_errors', '1' );
error_reporting( E_ALL );
header( 'Content-Type: text/plain; charset=utf-8' );

echo "1) PHP " . PHP_VERSION . "\n";

try {
	echo "2) loading config.php ...\n";
	require_once __DIR__ . '/config.php';
	echo "   config OK\n";
} catch ( Throwable $e ) {
	echo "CONFIG EXCEPTION: " . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n";
	exit;
}

echo "3) LS_PLANS = " . ( defined( 'LS_PLANS' ) ? 'yes' : 'NO' ) . "\n";
echo "4) loading LicenseManager ...\n";
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/LicenseManager.php';
echo "   LicenseManager OK\n";
echo "5) DONE — حالا برو به /license-server/pay/?plugin=webakery-chat\n";
