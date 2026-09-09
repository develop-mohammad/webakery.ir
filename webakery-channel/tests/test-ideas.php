<?php
/**
 * تست مستقل موتور ایده کانال‌یار — بدون وردپرس.
 *
 *   php webakery-channel/tests/test-ideas.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

require_once dirname( __DIR__ ) . '/includes/class-wbcn-ideas.php';

$pass = 0;
$fail = 0;

$check = static function ( $label, $ok, $detail = '' ) use ( &$pass, &$fail ) {
	if ( $ok ) {
		$pass++;
		echo "  ok   — {$label}" . ( $detail !== '' ? " ({$detail})" : '' ) . "\n";
	} else {
		$fail++;
		echo "  FAIL — {$label}" . ( $detail !== '' ? " ({$detail})" : '' ) . "\n";
	}
};

echo "\n=== کانال‌یار — موتور ایده ===\n";

$item = array(
	'title'   => 'چطور با پیامک ورود فروشگاه را ساده کنیم',
	'excerpt' => 'رمز فراموش‌شده سبد خرید را خالی می‌کند.',
	'content' => '<p>خیلی از فروشگاه‌ها هنوز فرم پیش‌فرض وردپرس را دارند. مشتری رمز را گم می‌کند.</p>
<ul><li>شماره موبایل را نرمال کنید</li><li>کد یک‌بارمصرف را هش کنید</li><li>محدودیت ارسال بگذارید</li></ul>
<p>رنگ و فونت را از قالب به ارث ببرید تا با المنتور هماهنگ باشد. پنل ایرانی وصل کنید.</p>
<p>هرگز کلید API را در قالب سخت‌کد نکنید.</p>',
	'url'     => 'https://webakery.ir/login-otp/',
	'type'    => 'post',
);

$plain = WBCN_Ideas::plain( $item['content'] );
$check( 'تگ HTML از متن حذف می‌شود', false === strpos( $plain, '<p>' ) && false === strpos( $plain, '<li>' ) );
$check( 'بولت‌ها در متن ساده می‌مانند', false !== strpos( $plain, 'هش' ) );

$sents = WBCN_Ideas::sentences( $plain );
$check( 'جمله‌ها از متن فارسی جدا می‌شوند', count( $sents ) >= 3, (string) count( $sents ) );

$points = WBCN_Ideas::points( $plain, $sents, $item['title'] );
$check( 'چک‌لیست از li ساخته می‌شود', count( $points ) >= 3, (string) count( $points ) );

$quote = WBCN_Ideas::best_quote( $sents, $item['title'] );
$check( 'نقل‌قول خالی نیست', WBCN_Ideas::len( $quote ) >= 10 );

$utm = WBCN_Ideas::with_utm( $item['url'] );
$check( 'UTM به لینک اضافه می‌شود', false !== strpos( $utm, 'utm_source=telegram' ) );
$check( 'لینک قبلی خراب نمی‌شود', 0 === strpos( $utm, 'https://webakery.ir/login-otp/' ) );

$evil = WBCN_Ideas::escape( '<script>alert(1)</script>' );
$check( 'عنوان مخرب escape می‌شود', false === strpos( $evil, '<script>' ) && false !== strpos( $evil, '&lt;script&gt;' ) );

$q = WBCN_Ideas::as_question( $item['title'] );
$check( 'عنوان به سؤال تبدیل می‌شود', false !== strpos( $q, '؟' ) || false !== strpos( $q, '?' ) );

$formats = array_keys( WBCN_Ideas::formats() );
$check( '۱۰ قالب ایده تعریف شده', 10 === count( $formats ), (string) count( $formats ) );

foreach ( $formats as $fmt ) {
	$built = WBCN_Ideas::build( $item, $fmt, 'webakery_ir' );
	$check( "قالب {$fmt} متن دارد", ! empty( $built['ok'] ) && WBCN_Ideas::len( $built['text'] ) > 40, (string) WBCN_Ideas::len( $built['text'] ) );
	$check( "قالب {$fmt} اسکریپت خام ندارد", false === strpos( $built['text'], '<script>' ) );
	$check( "قالب {$fmt} لینک ادامه دارد", false !== strpos( $built['text'], 'ادامه مطلب' ) || 'thread' === $fmt );
}

$thread = WBCN_Ideas::build( $item, 'thread', 'webakery_ir' );
$check( 'رشته سه قسمتی است', 3 === count( $thread['parts'] ), (string) count( $thread['parts'] ) );
$check( 'قسمت آخر رشته لینک دارد', false !== strpos( $thread['parts'][2], 'href=' ) );

$pub = WBCN_Ideas::publish_post( $item, 'webakery_ir', true );
$check( 'پست انتشار عنوان دارد', false !== strpos( $pub, 'پیامک' ) );
$check( 'پست انتشار امضای کانال دارد', false !== strpos( $pub, '@webakery_ir' ) );

$plan = WBCN_Ideas::week_plan( array( $item, WBCN_Ideas::sample_item() ), 'demochan' );
$check( 'تقویم هفته ۷ روز است', 7 === count( $plan ) );
$days = array_column( $plan, 'day' );
$check( 'شنبه در تقویم هست', in_array( 'شنبه', $days, true ) );
$check( 'جمعه در تقویم هست', in_array( 'جمعه', $days, true ) );
foreach ( $plan as $row ) {
	$check( 'روز ' . $row['day'] . ' متن دارد', $row['ok'] && WBCN_Ideas::len( $row['text'] ) > 20 );
}

$empty = WBCN_Ideas::build( array( 'title' => 'الف', 'content' => '' ), 'tip', '' );
$check( 'مطلب خیلی کوتاه هم قالب می‌سازد', $empty['text'] !== '' );

$clip = WBCN_Ideas::clip( str_repeat( 'آ', 50 ), 10 );
$check( 'clip طول را محدود می‌کند', WBCN_Ideas::len( $clip ) <= 11 );

echo "\n--- {$pass} ok, {$fail} fail ---\n";
exit( $fail > 0 ? 1 : 0 );
