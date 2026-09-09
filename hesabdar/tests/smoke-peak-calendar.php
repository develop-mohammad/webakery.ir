<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) {
		return time();
	}
}

$root = dirname( __DIR__ );
require_once $root . '/includes/class-wap-jalali.php';
require_once $root . '/includes/class-wap-analytics.php';

$checks = array(
	$root . '/includes/class-wap-analytics.php' => array( 'peak_calendar', 'heat_tone', 'weekdays' ),
	$root . '/includes/class-wap-portal.php' => array( 'wap-peak-cal', 'wap-peak-dayview', 'wap-peak-weekcal', 'پیک خرید — تقویم شمسی' ),
	$root . '/assets/style.css' => array( 'wap-peak-cal__grid', 'wap-peak-dayview__fill', 'wap-peak-weekcal__cell', 'is-l5' ),
	$root . '/hesabdar.php' => array( '1.16.2' ),
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

$cal = WAP_Analytics::peak_calendar( array(), array( 'date_from' => '1405/06/01', 'date_to' => '1405/06/31' ) );
if ( empty( $cal['months'] ) || $cal['months'][0]['m'] !== 6 ) {
	fwrite( STDERR, "FAIL month build\n" );
	exit( 1 );
}
$days = array_filter( $cal['months'][0]['days'], function( $d ) { return empty( $d['blank'] ); } );
if ( count( $days ) !== 31 ) {
	fwrite( STDERR, 'FAIL day count ' . count( $days ) . "\n" );
	exit( 1 );
}

$ht = WAP_Analytics::heat_tone( 12, 12 );
if ( $ht['fg'] !== '#ffffff' || $ht['level'] !== 5 ) {
	fwrite( STDERR, "FAIL heat_tone peak\n" );
	exit( 1 );
}
$hl = WAP_Analytics::heat_tone( 1, 12 );
if ( $hl['level'] !== 1 || $hl['fg'] === '#ffffff' ) {
	fwrite( STDERR, "FAIL heat_tone low\n" );
	exit( 1 );
}

echo "OK heat_tone\n";
echo "OK peak_calendar unit\n";
echo "ALL PEAK CALENDAR CHECKS PASSED\n";
