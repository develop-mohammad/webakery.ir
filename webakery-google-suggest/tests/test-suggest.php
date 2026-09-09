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

$chrome = '["کفش",["کفش مردانه","کفش زنانه"],["",""],[],{"google:suggestrelevance":[601,560]}]';
$rich   = WBGS_Suggest::parse_enriched( $chrome );
wbgs_assert( 2 === count( $rich ), 'parse chrome enriched count' );
wbgs_assert( 'کفش مردانه' === $rich[0]['text'], 'enriched text' );
wbgs_assert( 601 === $rich[0]['relevance'], 'real google relevance kept' );

$xssi = ")]}'\n" . $sample;
wbgs_assert( 3 === count( WBGS_Suggest::parse_response( $xssi ) ), 'strip XSSI prefix' );

wbgs_assert( array() === WBGS_Suggest::parse_response( 'not-json' ), 'invalid body is empty, not invented' );
wbgs_assert( array() === WBGS_Suggest::parse_response( '["کفش"]' ), 'missing suggestions array' );

$url = WBGS_Suggest::suggest_url( 'کفش ', 'fa', 'ir' );
wbgs_assert( false !== strpos( $url, 'suggestqueries.google.com' ), 'suggest URL host' );
wbgs_assert( false !== strpos( $url, 'client=chrome' ), 'suggest URL client' );

require_once dirname( __DIR__ ) . '/includes/class-wbgs-tree.php';
wbgs_assert( array( 'مردانه', 'اسپرت' ) === WBGS_Tree::branch_path( 'کفش', 'کفش مردانه اسپرت' ), 'prefix branch path' );
wbgs_assert( array( 'خرید' ) === WBGS_Tree::branch_path( 'کفش', 'خرید کفش' ), 'suffix branch path' );
$tree = WBGS_Tree::build(
	'کفش',
	array(
		array( 'text' => 'کفش مردانه', 'relevance' => 601, 'rank' => 1, 'count' => 2 ),
		array( 'text' => 'کفش مردانه اسپرت', 'relevance' => 500, 'rank' => 3, 'count' => 1 ),
		array( 'text' => 'خرید کفش', 'relevance' => 400, 'rank' => 4, 'count' => 1 ),
	)
);
wbgs_assert( isset( $tree['children']['مردانه'] ), 'tree has مردانه branch' );
wbgs_assert( isset( $tree['children']['خرید'] ), 'tree has خرید branch' );
wbgs_assert( $tree['count'] >= 3, 'tree counts leaves' );
wbgs_assert( $tree['children']['مردانه']['volume'] >= $tree['children']['خرید']['volume'], 'higher google score ranks higher' );

require_once dirname( __DIR__ ) . '/includes/class-wbgs-intent.php';
wbgs_assert( WBGS_Intent::INFORMATIONAL === WBGS_Intent::classify( 'کفش چیست' ), 'intent informational' );
wbgs_assert( WBGS_Intent::TRANSACTIONAL === WBGS_Intent::classify( 'خرید کفش' ), 'intent transactional' );
wbgs_assert( WBGS_Intent::COMMERCIAL === WBGS_Intent::classify( 'بهترین کفش' ), 'intent commercial' );
wbgs_assert( WBGS_Intent::NAVIGATIONAL === WBGS_Intent::classify( 'کفش دیجی کالا' ), 'intent navigational' );

$clusters = WBGS_Tree::clusters(
	'کفش',
	array(
		array( 'text' => 'کفش مردانه', 'relevance' => 601, 'rank' => 1, 'count' => 1, 'searches' => 1200 ),
		array( 'text' => 'خرید کفش', 'relevance' => 400, 'rank' => 2, 'count' => 1, 'searches' => 800 ),
	)
);
wbgs_assert( 'کفش' === $clusters['pillar'], 'pillar is the seed' );
wbgs_assert( count( $clusters['clusters'] ) >= 2, 'clusters from real phrases' );
$names = array_column( $clusters['clusters'], 'name' );
wbgs_assert( in_array( 'مردانه', $names, true ) && in_array( 'خرید', $names, true ), 'cluster names from google phrases' );

require_once dirname( __DIR__ ) . '/includes/class-wbgs-ads.php';
wbgs_assert( '2303' === WBGS_Ads::geo_id( 'ir' ), 'iran geo constant' );
wbgs_assert( '1056' === WBGS_Ads::lang_id( 'fa' ), 'persian language constant' );

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

