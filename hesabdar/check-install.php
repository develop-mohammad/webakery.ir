<?php
/**
 * بررسی سلامت نصب Hesabdar — بعد از آپلود، این آدرس را در مرورگر باز کنید:
 * /wp-content/plugins/hesabdar/check-install.php
 * پس از بررسی، این فایل را حذف کنید.
 */
header( 'Content-Type: text/plain; charset=utf-8' );

$base = __DIR__;
echo "Hesabdar install check\n";
echo "======================\n\n";
echo 'PHP version: ' . PHP_VERSION . ( version_compare( PHP_VERSION, '7.4', '>=' ) ? ' OK' : ' FAIL (need 7.4+)' ) . "\n\n";

$required = array(
	'hesabdar.php',
	'includes/class-wb-license.php',
	'includes/class-wap-jalali.php',
	'includes/class-wap-data.php',
	'includes/class-wap-formula.php',
	'includes/class-wap-export.php',
	'includes/class-wap-google-sheets.php',
	'includes/class-wap-portal.php',
	'includes/class-wap-admin.php',
	'includes/class-wap-baget-fields.php',
	'includes/class-wap-baget-fields-stub.php',
	'includes/class-wci-exporter.php',
	'includes/class-wci-invoice.php',
	'includes/class-wci-bulk-invoice.php',
	'includes/class-wap-order-service.php',
	'includes/class-wci-admin-pages.php',
	'includes/class-wci-order-edit.php',
);

$missing = array();
foreach ( $required as $file ) {
	$path = $base . '/' . $file;
	$ok   = is_readable( $path );
	echo ( $ok ? '[OK] ' : '[MISSING] ' ) . $file . "\n";
	if ( ! $ok ) {
		$missing[] = $file;
	}
}

echo "\n";
if ( $missing ) {
	echo "RESULT: FAIL — ZIP ناقص است. فایل‌های بالا را دوباره آپلود کنید.\n";
	exit( 1 );
}

echo "Syntax check (include main file in isolation):\n";
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}
if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) { return trailingslashit( dirname( $file ) ); }
}
if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) { return 'http://example.com/wp-content/plugins/hesabdar/'; }
}
if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $s ) { return rtrim( $s, '/\\' ) . '/'; }
}

ob_start();
$err = null;
set_error_handler( function( $no, $msg ) use ( &$err ) { $err = $msg; return true; } );
try {
	include $base . '/hesabdar.php';
} catch ( Throwable $e ) {
	$err = $e->getMessage();
}
restore_error_handler();
ob_end_clean();

if ( $err ) {
	echo "RESULT: FAIL — " . $err . "\n";
	exit( 1 );
}

echo "RESULT: OK — فایل‌ها کامل‌اند. اگر فعال‌سازی باز هم خطا داد، wp-content/debug.log را بفرستید.\n";
