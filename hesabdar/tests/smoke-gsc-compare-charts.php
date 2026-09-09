<?php
$root = dirname( __DIR__ );
require_once $root . '/includes/class-wap-chart.php';

$primary = array(
	'1404-01' => array( 'label' => 'فروردین', 'count' => 10, 'total' => 5500000 ),
	'1404-02' => array( 'label' => 'اردیبهشت', 'count' => 14, 'total' => 8000000 ),
	'1404-03' => array( 'label' => 'خرداد', 'count' => 8, 'total' => 4000000 ),
	'1404-04' => array( 'label' => 'تیر', 'count' => 16, 'total' => 9500000 ),
);
$compare = array(
	'1403-01' => array( 'label' => 'فروردین', 'count' => 7, 'total' => 4200000 ),
	'1403-02' => array( 'label' => 'اردیبهشت', 'count' => 11, 'total' => 6100000 ),
	'1403-03' => array( 'label' => 'خرداد', 'count' => 9, 'total' => 4500000 ),
	'1403-04' => array( 'label' => 'تیر', 'count' => 12, 'total' => 7000000 ),
);
$aligned = WAP_Chart::align_period_series( $primary, $compare );
if ( count( $aligned['rows'] ) !== 4 ) {
	fwrite( STDERR, "FAIL period align count\n" );
	exit( 1 );
}
if ( (int) $aligned['a'][3] !== 9500000 || (int) $aligned['b'][3] !== 7000000 ) {
	fwrite( STDERR, "FAIL period values\n" );
	exit( 1 );
}
echo "OK period align\n";

$pa = array(
	array( 'pid' => 1, 'name' => 'محصول الف', 'sku' => 'A', 'qty' => 5, 'revenue' => 1000000, 'orders' => 3 ),
	array( 'pid' => 2, 'name' => 'محصول ب', 'sku' => 'B', 'qty' => 2, 'revenue' => 500000, 'orders' => 2 ),
);
$pb = array(
	array( 'pid' => 1, 'name' => 'محصول الف', 'sku' => 'A', 'qty' => 3, 'revenue' => 700000, 'orders' => 2 ),
	array( 'pid' => 3, 'name' => 'محصول ج', 'sku' => 'C', 'qty' => 1, 'revenue' => 200000, 'orders' => 1 ),
);
$prod = WAP_Chart::align_product_series( $pa, $pb, 10 );
if ( count( $prod['rows'] ) < 2 ) {
	fwrite( STDERR, "FAIL product align\n" );
	exit( 1 );
}
echo "OK product align\n";

ob_start();
WAP_Chart::render_gsc_line(
	array( 'labels' => $aligned['labels'], 'a' => $aligned['a'], 'b' => $aligned['b'] ),
	array( 'title' => 'تست', 'legend_a' => 'A', 'legend_b' => 'B', 'dual' => true )
);
$html = ob_get_clean();
if ( strpos( $html, 'wap-gsc-line-a' ) === false || strpos( $html, 'wap-gsc-line-b' ) === false ) {
	fwrite( STDERR, "FAIL gsc svg markup\n" );
	exit( 1 );
}
echo "OK gsc render\n";

$main = file_get_contents( $root . '/hesabdar.php' );
if ( strpos( $main, "1.13.0" ) === false || strpos( $main, 'class-wap-chart.php' ) === false ) {
	fwrite( STDERR, "FAIL version/bootstrap\n" );
	exit( 1 );
}
echo "OK version\n";
echo "ALL GSC COMPARE CHECKS PASSED\n";
