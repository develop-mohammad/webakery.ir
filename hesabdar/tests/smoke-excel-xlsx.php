<?php
/**
 * Smoke test for XLSX builder (ZipArchive OOXML).
 */
error_reporting( E_ALL );
define( 'ABSPATH', '/' );

function fail( $m ) { fwrite( STDERR, "FAIL: $m\n" ); exit( 1 ); }
function ok( $c, $m ) { if ( ! $c ) fail( $m ); }
function wp_tempnam( $p ) { return tempnam( sys_get_temp_dir(), $p ); }

require dirname( __DIR__ ) . '/includes/class-wap-excel.php';

ok( class_exists( 'ZipArchive' ), 'ZipArchive available' );

$bin = WAP_Excel::build( array(
	'خلاصه' => array(
		'headers' => array( 'بخش', 'مقدار' ),
		'rows'    => array( array( 'تست', 12345 ), array( 'ریال', 19500000 ) ),
	),
	'واریز شاپرک' => array(
		'headers' => array( 'شناسه', 'مبلغ ریال', 'ارجاع' ),
		'rows'    => array(
			array( '20355143', 19500000, '1405,05,27N1041000000016886912' ),
		),
	),
) );

ok( $bin !== '', 'xlsx binary non-empty' );
ok( substr( $bin, 0, 2 ) === 'PK', 'xlsx is zip' );

$tmp = tempnam( sys_get_temp_dir(), 'xlsxchk' );
file_put_contents( $tmp, $bin );
$zip = new ZipArchive();
ok( true === $zip->open( $tmp ), 'open xlsx zip' );
ok( false !== $zip->locateName( 'xl/workbook.xml' ), 'has workbook' );
ok( false !== $zip->locateName( 'xl/worksheets/sheet1.xml' ), 'has sheet1' );
ok( false !== $zip->locateName( 'xl/worksheets/sheet2.xml' ), 'has sheet2' );
$sheet2 = $zip->getFromName( 'xl/worksheets/sheet2.xml' );
ok( strpos( $sheet2, '19500000' ) !== false, 'rial amount in sheet' );
ok( strpos( $sheet2, '20355143' ) !== false, 'reconcile id in sheet' );
$zip->close();
@unlink( $tmp );

echo "ALL EXCEL XLSX SMOKE TESTS PASSED\n";
