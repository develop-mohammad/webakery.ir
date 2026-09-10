<?php
/**
 * تست واحد سازنده کوئری و پارس پاسخ گوگل — بدون وردپرس و بدون شبکه.
 */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once dirname( __DIR__ ) . '/includes/class-wbgs-suggest.php';
require_once dirname( __DIR__ ) . '/includes/class-wbgs-affixes.php';

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
wbgs_assert( in_array( 'کفش چیست', $mods, true ), 'question suffix probe' );
wbgs_assert( in_array( 'چگونه کفش', $mods, true ), 'چگونه prefix probe' );
wbgs_assert( in_array( 'کفش ترین', $mods, true ), 'ترین suffix probe' );
wbgs_assert( in_array( 'کفش شهر', $mods, true ), 'شهر suffix probe' );
wbgs_assert( in_array( 'کفش تهران', $mods, true ), 'city suffix probe' );
wbgs_assert( in_array( 'بهترین کفش', $mods, true ), 'بهترین prefix probe' );
wbgs_assert( in_array( 'قیمت کفش', $mods, true ), 'قیمت prefix probe' );
wbgs_assert( in_array( 'ضد کفش', $mods, true ), 'morph prefix probe' );
wbgs_assert( ! in_array( 'تهران کفش', $mods, true ), 'city is suffix-only, not invented reverse' );

$yt = 'window.google.ac.h(["کفش",[["کفش فوتبال",0,[512]],["کفش مجلسی",0,[512]]]])';
wbgs_assert( '["کفش",[["کفش فوتبال",0,[512]],["کفش مجلسی",0,[512]]]]' === WBGS_Suggest::unwrap_suggest( $yt ), 'unwrap youtube ac.h' );
$yt_items = WBGS_Suggest::parse_enriched( $yt );
wbgs_assert( 2 === count( $yt_items ), 'youtube body yields real phrases' );
wbgs_assert( 'کفش فوتبال' === $yt_items[0]['text'], 'youtube first phrase' );
wbgs_assert( false !== strpos( WBGS_Suggest::suggest_url( 'کفش', 'fa', 'ir', 'youtube' ), 'client=youtube' ), 'youtube suggest client' );
wbgs_assert( false !== strpos( WBGS_Suggest::suggest_url( 'کفش', 'fa', 'ir', 'youtube' ), 'ds=yt' ), 'youtube ds=yt' );
wbgs_assert( 'youtube' === WBGS_Suggest::normalize_source( 'YOUTUBE' ), 'source youtube' );

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
wbgs_assert( 'ناوبری/راهبری' === WBGS_Intent::labels()[ WBGS_Intent::NAVIGATIONAL ], 'navigational label is ناوبری/راهبری' );

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
require_once dirname( __DIR__ ) . '/includes/class-wbgs-trends.php';
wbgs_assert( '2303' === WBGS_Ads::geo_id( 'ir' ), 'iran geo constant' );
wbgs_assert( '1056' === WBGS_Ads::lang_id( 'fa' ), 'persian language constant' );
wbgs_assert( 'IR' === WBGS_Trends::geo( 'ir' ), 'trends geo IR' );
wbgs_assert( 'ایران' === WBGS_Trends::geo_label( 'IR' ), 'trends geo label Iran' );
wbgs_assert( false !== strpos( WBGS_Trends::rss_url( 'ir' ), 'geo=IR' ), 'official Iran RSS' );
wbgs_assert( false !== strpos( WBGS_Trends::explore_url( 'کفش', 'ir', 'fa' ), 'geo=IR' ), 'explore locked to Iran' );
wbgs_assert( false !== strpos( WBGS_Trends::explore_url( 'کفش', 'ir', 'fa' ), 'q=' ), 'explore has query' );
$trend_xml = file_get_contents( dirname( __DIR__ ) . '/tests/fixtures/trends-ir.rss' );
$trend_rows = WBGS_Trends::parse_rss( $trend_xml );
wbgs_assert( 2 === count( $trend_rows ), 'parse two real RSS items' );
wbgs_assert( 'خرید کفش' === $trend_rows[0]['title'], 'rss title kept' );
wbgs_assert( '200+' === $trend_rows[0]['traffic'], 'rss traffic kept, not invented' );
wbgs_assert( WBGS_Trends::phrase_matches( 'خرید کفش مردانه', $trend_rows[0] ), 'extracted phrase matches Iran trend' );
wbgs_assert( ! WBGS_Trends::phrase_matches( 'لپ تاپ استوک', $trend_rows[0] ), 'unrelated phrase is not a trend hit' );
wbgs_assert( 'برانکو' === WBGS_Trends::match_one( 'برانکو', $trend_rows )['title'], 'exact trend match' );

wbgs_assert( 4 === WBGS_Suggest::word_count( 'خرید کفش اسپرت مردانه' ), 'word count longtail' );
wbgs_assert( WBGS_Suggest::is_longtail( 'خرید کفش اسپرت مردانه' ), '4 words is longtail' );
wbgs_assert( ! WBGS_Suggest::is_longtail( 'خرید کفش' ), '2 words is not longtail' );
$exp = WBGS_Suggest::expand_queries( 'کفش مردانه' );
wbgs_assert( array( 'کفش مردانه ' ) === $exp, 'expand is trailing space, not invented words' );
wbgs_assert( array() === WBGS_Suggest::expand_queries( 'کفش' ), 'single word is not expanded' );

require_once dirname( __DIR__ ) . '/includes/class-wbgs-frontend.php';
wbgs_assert( 'sajest' === WBGS_Frontend::sanitize_slug( '' ), 'empty slug falls back' );
wbgs_assert( 'sajest' === WBGS_Frontend::sanitize_slug( 'wp-admin' ), 'reserved slug blocked' );
wbgs_assert( 'my-tool' === WBGS_Frontend::sanitize_slug( 'My Tool' ), 'slug sanitized' );
wbgs_assert( array( 'webakery_suggest', 'wbgs_suggest' ) === WBGS_Frontend::shortcode_tags(), 'primary and alias shortcode tags' );
wbgs_assert( '[webakery_suggest]' === WBGS_Frontend::primary_shortcode(), 'primary shortcode markup' );
wbgs_assert( WBGS_Frontend::content_has_shortcode( '[webakery_suggest]' ), 'detects [webakery_suggest] on a page' );
wbgs_assert( WBGS_Frontend::content_has_shortcode( '[wbgs_suggest class="wide"]' ), 'detects alias with optional class' );
wbgs_assert( ! WBGS_Frontend::content_has_shortcode( 'متن بدون شورت‌کد' ), 'plain text is not a shortcode' );
wbgs_assert( ! WBGS_Frontend::content_has_shortcode( '' ), 'empty content is not a shortcode' );
$front_src = file_get_contents( dirname( __DIR__ ) . '/includes/class-wbgs-frontend.php' );
wbgs_assert( false !== strpos( $front_src, "add_shortcode( \$tag" ), 'registers both shortcode aliases' );
wbgs_assert( false === strpos( $front_src, "current_user_can( 'manage_options' )" ), 'unlicensed guests see lock, not a blank shortcode' );
$embed_src = file_get_contents( dirname( __DIR__ ) . '/templates/front-embed.php' );
wbgs_assert( false !== strpos( $embed_src, 'برای استخراج، لایسنس را فعال کنید' ), 'embed has lock message' );
wbgs_assert( false !== strpos( $embed_src, 'google-home.php' ), 'embed renders google-home UI' );

if ( ! function_exists( 'get_option' ) ) {
	$GLOBALS['wbgs_test_options'] = array();
	function get_option( $k, $d = false ) {
		return array_key_exists( $k, $GLOBALS['wbgs_test_options'] ) ? $GLOBALS['wbgs_test_options'][ $k ] : $d;
	}
	function update_option( $k, $v ) {
		$GLOBALS['wbgs_test_options'][ $k ] = $v;
		return true;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-wbgs-taxonomy.php';
wbgs_assert( WBGS_Taxonomy::SHORT === WBGS_Taxonomy::length( 'خرید کفش' ), '2 words is short-tail' );
wbgs_assert( WBGS_Taxonomy::MID === WBGS_Taxonomy::length( 'خرید کفش ورزشی' ), '3 words is mid-tail' );
wbgs_assert( WBGS_Taxonomy::LONG === WBGS_Taxonomy::length( 'خرید کفش ورزشی مردانه نایک' ), '4+ words is long-tail' );
wbgs_assert( WBGS_Taxonomy::is_geo( 'بهترین رستوران در شیراز' ), 'geo شیراز' );
wbgs_assert( WBGS_Taxonomy::is_seasonal( 'خرید لباس زمستانی' ), 'seasonal زمستان' );
wbgs_assert( WBGS_Taxonomy::is_branded( 'پشتیبانی دیجی کالا' ), 'branded دیجی کالا' );
wbgs_assert( ! WBGS_Taxonomy::is_branded( 'خرید لپ تاپ استوک' ), 'unbranded generic' );
wbgs_assert( WBGS_Taxonomy::is_lsi( 'قهوه', 'اسپرسو تلخ' ), 'LSI related without seed' );
wbgs_assert( ! WBGS_Taxonomy::is_lsi( 'کفش', 'خرید کفش' ), 'contains seed is not LSI' );
wbgs_assert( 'طولانی' === WBGS_Taxonomy::length_labels()[ WBGS_Taxonomy::LONG ], 'length label long is طولانی' );
wbgs_assert( 'کوتاه' === WBGS_Taxonomy::length_labels()[ WBGS_Taxonomy::SHORT ], 'length label short is کوتاه' );
wbgs_assert( 'میان‌رده' === WBGS_Taxonomy::length_labels()[ WBGS_Taxonomy::MID ], 'length label mid is میان‌رده' );
wbgs_assert( 'فصلی یا موقت' === WBGS_Taxonomy::extra_labels()['seasonal'], 'seasonal label is فصلی یا موقت' );
wbgs_assert( 'طول و حجم جستجو' === WBGS_Taxonomy::axis_titles()['length'], 'axis 1 title' );
wbgs_assert( 'قصد کاربر از جستجو' === WBGS_Taxonomy::axis_titles()['intent'], 'axis 2 title' );
wbgs_assert( 'موقعیت جغرافیایی و زمان' === WBGS_Taxonomy::axis_titles()['geo_time'], 'axis 3 title' );
wbgs_assert( 'مفهوم و ارتباط' === WBGS_Taxonomy::axis_titles()['semantic'], 'axis 4 title' );
wbgs_assert( 'نام برند' === WBGS_Taxonomy::axis_titles()['brand'], 'axis 5 title' );
wbgs_assert( 'پیشوند و پسوند' === WBGS_Taxonomy::axis_titles()['affix'], 'axis 6 title' );
$long_tax = WBGS_Taxonomy::classify( 'کفش', 'خرید کفش ورزشی مردانه نایک' );
wbgs_assert( 'طولانی' === $long_tax['length_fa'], 'classify length_fa is طولانی' );
wbgs_assert( in_array( 'buy', $long_tax['affixes'], true ), 'خرید tagged as buy affix' );
wbgs_assert( in_array( 'audience', $long_tax['affixes'], true ), 'مردانه tagged as audience' );
$q_tax = WBGS_Taxonomy::classify( 'کفش', 'کفش چیست' );
wbgs_assert( in_array( 'question', $q_tax['affixes'], true ), 'چیست tagged as question' );
$best_tax = WBGS_Taxonomy::classify( 'کفش', 'بهترین کفش تهران' );
wbgs_assert( in_array( 'superlative', $best_tax['affixes'], true ), 'بهترین tagged superlative' );
wbgs_assert( in_array( 'city', $best_tax['affixes'], true ), 'تهران tagged city' );
$price_tax = WBGS_Taxonomy::classify( 'کفش', 'قیمت کفش' );
wbgs_assert( in_array( 'price', $price_tax['affixes'], true ), 'قیمت tagged price' );
wbgs_assert( in_array( 'buy', WBGS_Affixes::match_ids( 'خرید کفش' ), true ), 'affix match خرید' );
wbgs_assert( in_array( 'morph_prefix', WBGS_Affixes::match_ids( 'ضد کفش' ), true ), 'first-token morph prefix' );
wbgs_assert( ! in_array( 'morph_prefix', WBGS_Affixes::match_ids( 'کفش ضدآب' ), true ), 'ضد mid-phrase is not morph prefix' );
wbgs_assert( in_array( 'morph_suffix', WBGS_Affixes::match_ids( 'کفش ها' ), true ), 'ها last-token morph suffix' );

require_once dirname( __DIR__ ) . '/includes/class-wbgs-work.php';
wbgs_assert( WBGS_Work::is_question( 'کفش چیست' ), 'question چیست' );
wbgs_assert( WBGS_Work::is_question( 'چگونه کفش بخریم' ), 'question چگونه' );
wbgs_assert( WBGS_Work::is_question( 'why shoes' ), 'question why' );
wbgs_assert( ! WBGS_Work::is_question( 'خرید کفش' ), 'خرید is not a question' );

$buy = array(
	'text'      => 'خرید کفش',
	'intent'    => WBGS_Intent::TRANSACTIONAL,
	'relevance' => 800,
	'rank'      => 1,
	'count'     => 3,
);
$ask = array(
	'text'      => 'کفش مردانه چیست',
	'intent'    => WBGS_Intent::INFORMATIONAL,
	'relevance' => 200,
	'rank'      => 8,
	'count'     => 1,
);
$comp_buy = WBGS_Work::competition( $buy );
$comp_ask = WBGS_Work::competition( $ask );
wbgs_assert( $comp_buy > $comp_ask, 'short transactional outranks long informational' );
wbgs_assert( $comp_buy >= 1 && $comp_buy <= 100, 'competition clamped' );
wbgs_assert( 'high' === WBGS_Work::competition_band( 80 )['key'], 'band high' );
wbgs_assert( 'low' === WBGS_Work::competition_band( 20 )['key'], 'band low' );
wbgs_assert( 'مردانه' === WBGS_Work::cluster_name( 'کفش', 'کفش مردانه اسپرت' ), 'cluster from real branch' );

$title = WBGS_Work::pick_title( array( 'کفش مردانه', 'خرید کفش دیجی کالا', 'کفش چیست' ) );
wbgs_assert( in_array( $title, array( 'کفش مردانه', 'کفش چیست', 'خرید کفش دیجی کالا' ), true ), 'title is a real phrase' );
wbgs_assert( false === strpos( $title, 'راهنمای کامل' ), 'title does not invent words' );
$meta = WBGS_Work::pick_meta( array( 'کفش مردانه', 'کفش زنانه', 'قیمت کفش' ), 'کفش مردانه' );
wbgs_assert( false !== strpos( $meta, 'کفش زنانه' ), 'meta uses real phrases' );
wbgs_assert( false === strpos( $meta, 'خرید آنلاین با بهترین قیمت' ), 'meta does not invent pitch' );

$work_rows = array(
	array( 'text' => 'کفش مردانه', 'relevance' => 601, 'rank' => 1, 'count' => 1 ),
	array( 'text' => 'خرید کفش', 'relevance' => 400, 'rank' => 2, 'count' => 1 ),
	array( 'text' => 'کفش چیست', 'relevance' => 300, 'rank' => 3, 'count' => 1 ),
);
$enriched = WBGS_Work::enrich_all( 'کفش', $work_rows );
wbgs_assert( 3 === count( $enriched ), 'enrich keeps real rows only' );
wbgs_assert( ! empty( $enriched[2]['question'] ), 'کفش چیست marked question' );
wbgs_assert( isset( $enriched[0]['relative_competition'] ), 'relative competition present' );
wbgs_assert( isset( $enriched[0]['title'] ) && $enriched[0]['title'] !== '', 'on-page title from real phrase' );

$csv = WBGS_Work::csv_document( 'کفش', $work_rows );
wbgs_assert( 0 === strpos( $csv, "\xEF\xBB\xBF" ), 'excel csv has utf-8 BOM' );
wbgs_assert( false !== strpos( $csv, 'relative_competition' ), 'csv has competition column' );
wbgs_assert( false !== strpos( $csv, 'question' ), 'csv has question column' );
wbgs_assert( false !== strpos( $csv, 'length' ), 'csv has length column' );
wbgs_assert( false !== strpos( $csv, 'branded' ), 'csv has branded column' );
wbgs_assert( false !== strpos( $csv, 'affixes' ), 'csv has affixes column' );
wbgs_assert( false !== strpos( $csv, 'iran_trend' ), 'csv has iran_trend column' );
wbgs_assert( false !== strpos( $csv, 'cluster' ), 'csv has cluster column' );

$briefs = WBGS_Work::briefs( 'کفش', $work_rows );
wbgs_assert( ! empty( $briefs[0]['is_pillar'] ), 'first brief is pillar' );
wbgs_assert( in_array( $briefs[0]['h1'], array( 'کفش مردانه', 'خرید کفش', 'کفش چیست' ), true ) || $briefs[0]['h1'] === 'کفش', 'pillar H1 is a real phrase or seed if seed appeared' );
foreach ( $briefs as $brief ) {
	foreach ( $brief['h2'] as $h2 ) {
		$found = false;
		foreach ( $work_rows as $wr ) {
			if ( $wr['text'] === $h2 ) {
				$found = true;
			}
		}
		wbgs_assert( $found, 'H2 is a real google phrase: ' . $h2 );
	}
}

$cmp = WBGS_Work::compare(
	array( array( 'text' => 'کفش مردانه' ), array( 'text' => 'خرید کفش' ) ),
	array( array( 'text' => 'کفش مردانه' ), array( 'text' => 'کفش زنانه' ) )
);
wbgs_assert( 1 === $cmp['stats']['shared'], 'compare shared' );
wbgs_assert( 1 === $cmp['stats']['only_a'], 'compare only A' );
wbgs_assert( 1 === $cmp['stats']['only_b'], 'compare only B' );
wbgs_assert( 'کفش مردانه' === $cmp['both'][0]['text'], 'shared phrase kept' );

require_once dirname( __DIR__ ) . '/includes/class-wbgs-reports.php';
$saved = WBGS_Reports::save( 'کفش', $work_rows, 'u1' );
wbgs_assert( ! empty( $saved['ok'] ) && ! empty( $saved['report']['id'] ), 'report saves' );
$got = WBGS_Reports::get( $saved['report']['id'], 'u1' );
wbgs_assert( is_array( $got ) && 'کفش' === $got['seed'], 'report reopened' );
wbgs_assert( 3 === count( $got['rows'] ), 'saved rows are real phrases' );
$list = WBGS_Reports::list_for( 'u1' );
wbgs_assert( 1 === count( $list ), 'report listed' );
wbgs_assert( WBGS_Reports::delete( $saved['report']['id'], 'u1' ), 'report deleted' );
wbgs_assert( null === WBGS_Reports::get( $saved['report']['id'], 'u1' ), 'deleted report gone' );
wbgs_assert( false === WBGS_Reports::save( '', array(), 'u1' )['ok'], 'empty report rejected' );

require_once dirname( __DIR__ ) . '/includes/class-wbgs-export.php';
$xmind = WBGS_Export::xmind( 'کفش', $work_rows );
wbgs_assert( ! empty( $xmind['ok'] ) && $xmind['bin'] !== '', 'xmind zip built from real phrases' );
wbgs_assert( false !== strpos( $xmind['name'], '.xmind' ), 'xmind filename' );
$tmpx = tempnam( sys_get_temp_dir(), 'xm' );
file_put_contents( $tmpx, $xmind['bin'] );
$zx = new ZipArchive();
wbgs_assert( true === $zx->open( $tmpx ), 'xmind is a zip' );
wbgs_assert( false !== $zx->locateName( 'content.json' ), 'xmind has content.json' );
$sheet = json_decode( $zx->getFromName( 'content.json' ), true );
$zx->close();
@unlink( $tmpx );
wbgs_assert( isset( $sheet[0]['rootTopic']['title'] ) && 'کفش' === $sheet[0]['rootTopic']['title'], 'xmind root is seed' );
$cal = WBGS_Export::calendar( 'کفش', $work_rows );
wbgs_assert( ! empty( $cal[0]['items'] ), 'calendar has week 1' );
$html = WBGS_Export::html_report( 'کفش', $work_rows );
wbgs_assert( false !== strpos( $html, 'کفش' ), 'html report has seed' );
wbgs_assert( false === strpos( $html, 'ChatGPT' ), 'html report is not an AI article' );

if ( $failed ) {
	echo "\n$failed failed\n";
	exit( 1 );
}

echo "\nall passed\n";
exit( 0 );

