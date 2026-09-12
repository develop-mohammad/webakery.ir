<?php
/**
 * Smoke: overlay مقایسه روزانه شبیه Search Console
 */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) {
		return time();
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $d ) { return json_encode( $d ); }
}

$root = dirname( __DIR__ );
require_once $root . '/includes/class-wap-jalali.php';
require_once $root . '/includes/class-wap-chart.php';

if ( ! class_exists( 'WAP_Data' ) ) {
	class WAP_Data {
		public static function is_paid_order( $o ) {
			return true;
		}
	}
}

$checks = array(
	$root . '/includes/class-wap-chart.php' => array( 'build_date_overlay', 'daily_sequence', 'wap-gsc-metrics', 'is-dual' ),
	$root . '/includes/class-wap-portal.php' => array( 'build_date_overlay', 'ماه جدید', 'ماه قدیم' ),
	$root . '/assets/style.css' => array( 'wap-gsc-metrics', 'wap-gsc-footnote', 'is-dual' ),
	$root . '/hesabdar.php' => array( '1.22.0' ),
);
foreach ( $checks as $file => $needles ) {
	$c = file_get_contents( $file );
	foreach ( $needles as $n ) {
		if ( strpos( $c, $n ) === false ) {
			fwrite( STDERR, "FAIL $file missing $n\n" );
			exit( 1 );
		}
	}
	echo 'OK ' . basename( $file ) . "\n";
}

$seq = WAP_Chart::daily_sequence( array(), '1405/06/01', '1405/06/31' );
if ( count( $seq ) !== 31 ) {
	fwrite( STDERR, 'FAIL day count ' . count( $seq ) . "\n" );
	exit( 1 );
}
$ov = WAP_Chart::build_date_overlay( array(), array(), '1405/06/01', '1405/06/31', '1404/06/01', '1404/06/31' );
if ( count( $ov['a'] ) !== 31 || count( $ov['b'] ) !== 31 ) {
	fwrite( STDERR, "FAIL overlay length\n" );
	exit( 1 );
}
echo "OK overlay unit\n";
echo "ALL GSC OVERLAY CHECKS PASSED\n";
