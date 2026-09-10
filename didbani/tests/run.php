<?php
/**
 * اجرای تست‌های بدون وردپرس:
 *   php tests/run.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'DID_UA', 'Didbani/1.0 (+https://webakery.ir)' );

require_once dirname( __DIR__ ) . '/includes/class-did-geo.php';
require_once dirname( __DIR__ ) . '/includes/class-did-text.php';
require_once dirname( __DIR__ ) . '/includes/class-did-url.php';
require_once dirname( __DIR__ ) . '/includes/class-did-html.php';
require_once dirname( __DIR__ ) . '/includes/class-did-robots.php';
require_once dirname( __DIR__ ) . '/includes/class-did-http.php';
require_once dirname( __DIR__ ) . '/includes/class-did-provider-bing.php';
require_once dirname( __DIR__ ) . '/includes/class-did-provider-serp.php';
require_once dirname( __DIR__ ) . '/includes/class-did-rank.php';
require_once dirname( __DIR__ ) . '/includes/class-did-provider-backlinks.php';

$fails = 0;
$pass  = 0;

function did_assert( $cond, $label ) {
	global $fails, $pass;
	if ( $cond ) {
		$pass++;
		echo "ok  {$label}\n";
		return;
	}
	$fails++;
	echo "FAIL  {$label}\n";
}

/* متن */
did_assert( DID_Text::normalize( '  خرید  فرش‌۱۲  ' ) === 'خرید فرش 12', 'normalize persian digits and zwnj' );
did_assert( DID_Text::contains( 'خرید فرش دستباف', 'خرید فرش' ), 'contains phrase' );
did_assert( ! DID_Text::contains( 'فرش ماشینی', 'موکت' ), 'does not contain missing phrase' );
$hits = DID_Text::keyword_hits( 'خرید فرش', 'خرید فرش دستباف', 'فروشگاه', 'متن بدون آن' );
did_assert( 1 === $hits['in_title'] && 0 === $hits['in_h1'] && 0 === $hits['in_body'], 'keyword hits flags' );

/* URL */
did_assert( 'example.ir' === DID_Url::host( 'https://www.Example.ir/path' ), 'host strips www and case' );
did_assert( DID_Url::host_matches( 'blog.example.ir', 'example.ir' ), 'subdomain matches tracked host' );
did_assert( ! DID_Url::host_matches( 'example.ir.evil.com', 'example.ir' ), 'suffix attack rejected' );
did_assert( 'https://example.ir/a' === DID_Url::absolute( '/a', 'https://example.ir/shop/' ), 'absolute path' );
did_assert( ! DID_Url::is_htmlish( 'https://x.ir/file.pdf' ), 'pdf skipped' );

/* HTML */
$html = file_get_contents( __DIR__ . '/fixtures/sample.html' );
$p    = DID_Html::parse( $html, 'https://rival-a.ir/shop/' );
did_assert( 'نمونه' === $p['title'], 'html title' );
did_assert( false !== strpos( $p['meta_description'], 'فرش دستباف' ), 'html meta' );
did_assert( 'خرید فرش دستباف' === $p['h1'], 'html h1' );
did_assert( 'https://rival-a.ir/shop/' === $p['canonical'], 'html canonical' );
did_assert( in_array( 'https://rival-a.ir/about', $p['links'], true ), 'internal link kept' );
did_assert( ! in_array( 'https://other.com/out', $p['links'], true ), 'external link dropped' );
did_assert( false === strpos( $p['body_text'], 'var ignore' ), 'script stripped from body' );
did_assert( $p['word_count'] > 5, 'word count' );

/* robots */
$rb = DID_Robots::parse(
	"User-agent: *\nDisallow: /admin\nAllow: /admin/public\nSitemap: https://x.ir/sitemap.xml\n"
);
did_assert( in_array( '/admin', $rb['disallow'], true ), 'robots disallow' );
did_assert( in_array( 'https://x.ir/sitemap.xml', $rb['sitemaps'], true ), 'robots sitemap' );
did_assert( ! DID_Robots::allowed( 'https://x.ir/admin/secret', $rb ), 'admin blocked' );
did_assert( DID_Robots::allowed( 'https://x.ir/shop', $rb ), 'shop allowed' );

$xml = '<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://x.ir/a</loc></url></urlset>';
$locs = DID_Robots::sitemap_locs( $xml );
did_assert( array( 'https://x.ir/a' ) === $locs, 'sitemap loc' );

/* Bing / Serp parse + rank match */
$bing = DID_Provider_Bing::parse(
	array(
		'webPages' => array(
			'value' => array(
				array( 'url' => 'https://rival-a.ir/p', 'name' => 'A' ),
				array( 'url' => 'https://myshop.ir/p', 'name' => 'Me' ),
				array( 'url' => 'https://other.com/', 'name' => 'X' ),
			),
		),
	)
);
did_assert( 3 === count( $bing ) && 2 === $bing[1]['position'], 'bing parse positions' );

$serp = DID_Provider_Serp::parse(
	array(
		'organic_results' => array(
			array( 'position' => 1, 'link' => 'https://www.rival-a.ir/', 'title' => 'R' ),
			array( 'position' => 4, 'link' => 'https://myshop.ir/buy', 'title' => 'M' ),
		),
	)
);
$found = DID_Rank::match(
	$serp,
	array(
		'myshop.ir'  => 1,
		'rival-a.ir' => 2,
		'rival-b.ir' => 3,
	)
);
did_assert( isset( $found[1] ) && 4 === $found[1]['position'], 'own rank 4' );
did_assert( isset( $found[2] ) && 1 === $found[2]['position'], 'competitor rank 1' );
did_assert( ! isset( $found[3] ), 'missing competitor not in results' );

$dfs = DID_Provider_DataForSeo::parse(
	array(
		'tasks' => array(
			array(
				'result' => array(
					array(
						'items' => array(
							array( 'type' => 'organic', 'rank_group' => 2, 'url' => 'https://myshop.ir/', 'title' => 'Me' ),
							array( 'type' => 'paid', 'rank_group' => 1, 'url' => 'https://ads.com/', 'title' => 'Ad' ),
						),
					),
				),
			),
		),
	)
);
did_assert( 1 === count( $dfs ) && 2 === $dfs[0]['position'], 'dataforseo skips paid' );

$cse = DID_Provider_Cse::parse(
	array(
		'items' => array(
			array( 'link' => 'https://myshop.ir/', 'title' => 'Me' ),
		),
	),
	0
);
did_assert( 1 === $cse[0]['position'], 'cse parse' );

/* HTTP mock */
DID_Http::$transport = function ( $url, $args ) {
	return array(
		'did_http'     => true,
		'ok'           => true,
		'status'       => 200,
		'body'         => '{"webPages":{"value":[{"url":"https://myshop.ir/","name":"Me"}]}}',
		'error'        => '',
		'final_url'    => $url,
		'content_type' => 'application/json',
	);
};
$pack = DID_Provider_Bing::search( 'خرید فرش', 10, 'fake-key', 'fa-IR' );
did_assert( ! empty( $pack['ok'] ) && isset( $pack['results'][0] ), 'bing search via mock transport' );

$empty = DID_Provider_Bing::search( 'خرید فرش', 10, '', 'fa-IR' );
did_assert( empty( $empty['ok'] ), 'bing without key fails' );

/* رشد/افت و بک‌لینک */
$up = DID_Rank::delta( 8, 3 );
did_assert( 'up' === $up['kind'] && 5 === $up['steps'], 'rank improved is growth' );
$down = DID_Rank::delta( 2, 9 );
did_assert( 'down' === $down['kind'] && 7 === $down['steps'], 'rank worse is drop' );
did_assert( 'enter' === DID_Rank::delta( 0, 4 )['kind'], 'new ranking is enter' );
did_assert( 'exit' === DID_Rank::delta( 6, 0 )['kind'], 'lost ranking is exit' );
did_assert( 'same' === DID_Rank::delta( 4, 4 )['kind'], 'unchanged rank' );
did_assert( 'desktop' === DID_Geo::sanitize_device( 'tablet' ), 'unknown device becomes desktop' );
did_assert( 'mobile' === DID_Geo::sanitize_device( 'mobile' ), 'mobile stays mobile' );
$qp = DID_Provider_Serp::query_params( 'خرید فرش', 10, 'k', 'fa', 'ir', array( 'device' => 'mobile', 'location' => 'Mashhad, Razavi Khorasan, Iran' ) );
did_assert( 'mobile' === $qp['device'] && false !== strpos( $qp['location'], 'Mashhad' ), 'serpapi mobile mashhad params' );
$qp2 = DID_Provider_Serp::query_params( 'خرید فرش', 10, 'k', 'fa', 'ir', array() );
did_assert( 'desktop' === $qp2['device'] && empty( $qp2['location'] ), 'serpapi defaults to national desktop' );

$own_only = DID_Rank::match(
	array(
		array( 'position' => 3, 'url' => 'https://myshop.ir/buy', 'title' => 'Me' ),
		array( 'position' => 8, 'url' => 'https://other.com/', 'title' => 'X' ),
	),
	array( 'myshop.ir' => 11 )
);
did_assert( isset( $own_only[11] ) && 3 === $own_only[11]['position'], 'rank works with own domain only' );

$sum = DID_Provider_Backlinks::parse_summary(
	array(
		'tasks' => array(
			array(
				'result' => array(
					array(
						'backlinks'         => 1240,
						'referring_domains' => 89,
						'referring_pages'   => 400,
						'rank'              => 120,
					),
				),
			),
		),
	)
);
did_assert( 1240 === $sum['backlinks'] && 89 === $sum['referring_domains'], 'backlink summary counts' );

$anchors = DID_Provider_Backlinks::parse_anchors(
	array(
		'tasks' => array(
			array(
				'result' => array(
					array(
						'items' => array(
							array( 'anchor' => 'خرید فرش', 'backlinks' => 40, 'referring_domains' => 12 ),
							array( 'anchor' => '', 'backlinks' => 3, 'referring_domains' => 1 ),
						),
					),
				),
			),
		),
	)
);
did_assert( 'خرید فرش' === $anchors[0]['anchor'] && 40 === $anchors[0]['backlinks'], 'anchor text kept' );
did_assert( '(بدون متن)' === $anchors[1]['anchor'], 'empty anchor labeled' );

DID_Http::$transport = function ( $url, $args ) {
	if ( false !== strpos( $url, 'backlinks/summary' ) ) {
		$body = '{"status_code":20000,"tasks":[{"status_code":20000,"result":[{"backlinks":10,"referring_domains":2,"referring_pages":4,"rank":9}]}]}';
	} elseif ( false !== strpos( $url, 'backlinks/anchors' ) ) {
		$body = '{"status_code":20000,"tasks":[{"status_code":20000,"result":[{"items":[{"anchor":"shop","backlinks":5,"referring_domains":2}]}]}]}';
	} else {
		$body = '{"webPages":{"value":[{"url":"https://myshop.ir/","name":"Me"}]}}';
	}
	return array(
		'did_http'     => true,
		'ok'           => true,
		'status'       => 200,
		'body'         => $body,
		'error'        => '',
		'final_url'    => $url,
		'content_type' => 'application/json',
	);
};
$bl = DID_Provider_Backlinks::fetch( 'myshop.ir', 'login', 'pass' );
did_assert( ! empty( $bl['ok'] ) && 10 === $bl['backlinks'] && 'shop' === $bl['anchors'][0]['anchor'], 'backlinks fetch via mock' );

echo "\n{$pass} passed, {$fails} failed\n";
exit( $fails ? 1 : 0 );
