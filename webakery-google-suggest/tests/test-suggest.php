<?php
/**
 * تست واحد سازنده کوئری و پارس پاسخ گوگل — بدون وردپرس و بدون شبکه.
 */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once dirname( __DIR__ ) . '/includes/class-wbgs-suggest.php';

$failed = 0;

function wbgs_assert( $ok, $label ) {
	global $failed;
	if ( $ok ) {
		echo "OK  $label\n";
		return;
	}
	$failed++;
	echo "FAIL  $label\n";
}

$empty = WBGS_Suggest::build_queries( '   ', array( 'space' => true ) );
wbgs_assert( array() === $empty, 'empty seed yields no queries' );

$space = WBGS_Suggest::build_queries( 'کفش', array( 'space' => true ) );
wbgs_assert( 3 === count( $space ), 'seed + leading/trailing space = 3 queries' );
wbgs_assert( in_array( 'کفش', $space, true ), 'includes bare seed' );
wbgs_assert( in_array( 'کفش ', $space, true ), 'includes trailing space' );
wbgs_assert( in_array( ' کفش', $space, true ), 'includes leading space' );

$alpha = WBGS_Suggest::build_queries( 'کفش', array( 'alphabet' => true ) );
wbgs_assert( 1 + ( 32 * 2 ) === count( $alpha ), 'seed + 32 Persian letters prefix/suffix' );
wbgs_assert( in_array( 'کفش ا', $alpha, true ), 'includes کفش ا' );
wbgs_assert( in_array( 'ی کفش', $alpha, true ), 'includes ی کفش' );

$latin = WBGS_Suggest::build_queries( 'nike', array( 'latin' => true ) );
wbgs_assert( 1 + ( 26 * 2 ) === count( $latin ), 'latin a-z prefix/suffix' );

$mods = WBGS_Suggest::build_queries( 'کفش', array( 'modifiers' => true ) );
wbgs_assert( in_array( 'خرید کفش', $mods, true ), 'modifier prefix is a Google query, not a fake keyword' );

$sample = '["کفش",["کفش مردانه","کفش زنانه","خرید کفش"]]';
$parsed = WBGS_Suggest::parse_response( $sample );
wbgs_assert( array( 'کفش مردانه', 'کفش زنانه', 'خرید کفش' ) === $parsed, 'parse firefox JSON' );

$xssi = ")]}'\n" . $sample;
wbgs_assert( 3 === count( WBGS_Suggest::parse_response( $xssi ) ), 'strip XSSI prefix' );

wbgs_assert( array() === WBGS_Suggest::parse_response( 'not-json' ), 'invalid body is empty, not invented' );
wbgs_assert( array() === WBGS_Suggest::parse_response( '["کفش"]' ), 'missing suggestions array' );

$url = WBGS_Suggest::suggest_url( 'کفش ', 'fa', 'ir' );
wbgs_assert( false !== strpos( $url, 'suggestqueries.google.com' ), 'suggest URL host' );
wbgs_assert( false !== strpos( $url, 'client=firefox' ), 'suggest URL client' );

require_once dirname( __DIR__ ) . '/includes/class-wbgs-frontend.php';
wbgs_assert( 'sajest' === WBGS_Frontend::sanitize_slug( '' ), 'empty slug falls back' );
wbgs_assert( 'sajest' === WBGS_Frontend::sanitize_slug( 'wp-admin' ), 'reserved slug blocked' );
wbgs_assert( 'my-tool' === WBGS_Frontend::sanitize_slug( 'My Tool' ), 'slug sanitized' );

if ( $failed ) {
	echo "\n$failed failed\n";
	exit( 1 );
}

echo "\nall passed\n";
exit( 0 );
