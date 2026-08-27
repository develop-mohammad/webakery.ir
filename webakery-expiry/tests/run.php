<?php
/**
 * تست‌های مستقل انقضای کالا — بدون وردپرس.
 * اجرا: php tests/run.php
 */
error_reporting( E_ALL );
define( 'ABSPATH', sys_get_temp_dir() . '/' );

require dirname( __DIR__ ) . '/includes/class-wbe-jalali.php';
require dirname( __DIR__ ) . '/includes/class-wbe-engine.php';

$pass = 0;
$fail = 0;

function wbe_check( $label, $ok, $detail = '' ) {
	global $pass, $fail;
	if ( $ok ) {
		$pass++;
		echo "  ok   — {$label}" . ( $detail !== '' ? " ({$detail})" : '' ) . "\n";
	} else {
		$fail++;
		echo "  FAIL — {$label}" . ( $detail !== '' ? " ({$detail})" : '' ) . "\n";
	}
}

echo "\n=== جلالی ===\n";
$g = WBE_Jalali::to_gregorian( 1403, 1, 1 );
wbe_check( '۱ فروردین ۱۴۰۳ میلادی است', array( 2024, 3, 20 ) === $g, implode( '-', $g ) );
$j = WBE_Jalali::to_jalali( 2024, 3, 20 );
wbe_check( '۲۰ مارس ۲۰۲۴ شمسی است', array( 1403, 1, 1 ) === $j, implode( '/', $j ) );
$ymd = WBE_Jalali::parse_to_ymd( '۱۴۰۵/۰۶/۰۵', 'jalali' );
wbe_check( 'ارقام فارسی شمسی به Y-m-d تبدیل می‌شود', (bool) preg_match( '/^2026-\d{2}-\d{2}$/', $ymd ), $ymd );
$back = WBE_Jalali::format_ymd( $ymd, 'jalali', false );
wbe_check( 'برگشت به شمسی ۱۴۰۵/۰۶/۰۵', '1405/06/05' === $back, $back );
$g2 = WBE_Jalali::parse_to_ymd( '2026-08-27', 'gregorian' );
wbe_check( 'میلادی با خط تیره', '2026-08-27' === $g2, $g2 );
wbe_check( 'عدد فارسی', 150000.0 === WBE_Jalali::number( '۱۵۰٬۰۰۰' ) );

echo "\n=== انتخاب بچ فعال ===\n";
$today = '2026-01-15';
$batches = array(
	array( 'id' => 'a', 'price' => '100', 'stock' => 5, 'expiry' => '2026-06-01' ),
	array( 'id' => 'b', 'price' => '80', 'stock' => 9, 'expiry' => '2026-03-01' ),
	array( 'id' => 'c', 'price' => '70', 'stock' => 2, 'expiry' => '2026-12-01' ),
);
$idx = WBE_Engine::active_index( $batches, $today );
wbe_check( 'نزدیک‌ترین انقضا فعال است', 1 === $idx, (string) $idx );

$same = array(
	array( 'id' => 'first', 'price' => '10', 'stock' => 1, 'expiry' => '2026-04-01' ),
	array( 'id' => 'second', 'price' => '99', 'stock' => 50, 'expiry' => '2026-04-01' ),
);
$idx = WBE_Engine::active_index( $same, $today );
wbe_check( 'انقضای برابر = ترتیب ورود ادمین', 0 === $idx, (string) $idx );

$expired = array(
	array( 'id' => 'old', 'price' => '10', 'stock' => 10, 'expiry' => '2026-01-01' ),
	array( 'id' => 'next', 'price' => '20', 'stock' => 4, 'expiry' => '2026-08-01' ),
);
$idx = WBE_Engine::active_index( $expired, $today );
wbe_check( 'انقضای گذشته با موجودی رد می‌شود', 1 === $idx, (string) $idx );

$zero = array(
	array( 'id' => 'z', 'price' => '10', 'stock' => 0, 'expiry' => '2026-02-01' ),
	array( 'id' => 'ok', 'price' => '12', 'stock' => 3, 'expiry' => '2026-09-01' ),
);
$idx = WBE_Engine::active_index( $zero, $today );
wbe_check( 'موجودی صفر رد می‌شود', 1 === $idx, (string) $idx );

echo "\n=== سوییچ پس از صفر شدن ===\n";
$loop = array(
	array( 'id' => 'one', 'price' => '100', 'stock' => 2, 'expiry' => '2026-02-01' ),
	array( 'id' => 'two', 'price' => '150', 'stock' => 8, 'expiry' => '2026-10-01' ),
);
$r = WBE_Engine::consume( $loop, 2, $today );
wbe_check( 'مصرف از بچ فعال', 'one' === $r['batch_id'] && 0 === (int) $r['batches'][0]['stock'] );
$idx = WBE_Engine::active_index( $r['batches'], $today );
wbe_check( 'بعد از صفر، رزرو جایگزین می‌شود', 1 === $idx && 'two' === $r['batches'][ $idx ]['id'] );
wbe_check( 'قیمت رزرو تا صفر شدن مخفی می‌ماند (هنوز بچ اول فعال بود)', 2 === (int) $loop[0]['stock'] );

$again = WBE_Engine::consume( $r['batches'], 1, $today );
$idx   = WBE_Engine::active_index( $again['batches'], $today );
wbe_check( 'حلقه روی رزرو ادامه دارد', 'two' === $again['batch_id'] && 7 === (int) $again['batches'][ $idx ]['stock'] );

$restored = WBE_Engine::restore( $again['batches'], 2, 'one' );
wbe_check( 'بازگشت موجودی به همان بچ', 2 === (int) $restored[0]['stock'] );
$idx = WBE_Engine::active_index( $restored, $today );
wbe_check( 'بعد از بازگشت، نزدیک‌ترین دوباره فعال است', 0 === $idx );

echo "\n=== پیکربندی ===\n";
wbe_check( 'آرایه خالی تنظیم نشده', false === WBE_Engine::is_configured( array() ) );
$clean = WBE_Engine::sanitize_batches(
	array(
		array( 'price' => '', 'stock' => '', 'expiry' => '' ),
		array( 'price' => '۱۲۰۰۰', 'stock' => '۳', 'expiry' => '1405/01/10', 'id' => 'x1' ),
	),
	'jalali'
);
wbe_check( 'ردیف خالی حذف می‌شود', 1 === count( $clean ), (string) count( $clean ) );
wbe_check( 'محصول با یک بچ تنظیم شده است', true === WBE_Engine::is_configured( $clean ) );

echo "\n=== خروجی اکسل RTL ===\n";
require dirname( __DIR__ ) . '/includes/class-wbe-export.php';
$xml = WBE_Export::xml_document(
	array(
		array(
			'name'      => 'شیر خشک',
			'sku'       => 'M1',
			'category'  => 'نوزاد',
			'brand'     => 'نستله',
			'expiry_fa' => '۱۴۰۵/۰۱/۱۰',
			'days'      => 12,
			'price'     => 180000,
			'stock'     => 12,
			'reserves'  => 1,
			'sold_qty'  => 48,
			'sold_amt'  => 8640000,
			'status'    => 'near',
		),
	)
);
wbe_check( 'ورک‌شیت راست‌چین است', false !== strpos( $xml, 'ss:RightToLeft="1"' ) );
wbe_check( 'هدر فارسی دارد', false !== strpos( $xml, 'تاریخ انقضا' ) );
wbe_check( 'ردیف نزدیک به انقضا رنگ جدا دارد', false !== strpos( $xml, 'ss:StyleID="near"' ) );
wbe_check( 'نام محصول در خروجی است', false !== strpos( $xml, 'شیر خشک' ) );

echo "\n=== هشدار انقضا ===\n";
wbe_check( '۵ روز = فوری', 'soon' === WBE_Engine::urgency( 5, 7, 30, 60 ) );
wbe_check( '۲۰ روز = یک ماه', 'month' === WBE_Engine::urgency( 20, 7, 30, 60 ) );
wbe_check( '۴۵ روز = دو ماه', 'two_months' === WBE_Engine::urgency( 45, 7, 30, 60 ) );
wbe_check( '۹۰ روز = بدون هشدار', '' === WBE_Engine::urgency( 90, 7, 30, 60 ) );
wbe_check( 'تاریخ گذشته = منقضی', 'expired' === WBE_Engine::urgency( -1, 7, 30, 60 ) );
wbe_check( 'مرز ۷ روز هنوز فوری است', 'soon' === WBE_Engine::urgency( 7, 7, 30, 60 ) );
wbe_check( 'شماره فارسی نرمال می‌شود', '09123456789' === WBE_Engine::normalize_phone( '۰۹۱۲۳۴۵۶۷۸۹' ) );
wbe_check( 'شماره با ۹۸', '09123456789' === WBE_Engine::normalize_phone( '989123456789' ) );
wbe_check( 'شماره نامعتبر رد می‌شود', '' === WBE_Engine::normalize_phone( '123' ) );

echo "\n=== آستانه نوتیف سفارشی ===\n";
$pts = WBE_Engine::clean_points( array( '۶۰', 7, 30, 30, 'x' ) );
wbe_check( 'آستانه‌ها یکتا و مرتب می‌شوند', array( 7, 30, 60 ) === $pts, implode( ',', $pts ) );
wbe_check( '۵ روز روی آستانه ۷ می‌افتد', 7 === WBE_Engine::match_point( 5, array( 7, 30, 60 ) ) );
wbe_check( '۲۰ روز روی آستانه ۳۰ می‌افتد', 30 === WBE_Engine::match_point( 20, array( 7, 30, 60 ) ) );
wbe_check( '۴۵ روز روی آستانه ۶۰ می‌افتد', 60 === WBE_Engine::match_point( 45, array( 7, 30, 60 ) ) );
wbe_check( '۹۰ روز خارج از بازه است', null === WBE_Engine::match_point( 90, array( 7, 30, 60 ) ) );
wbe_check( 'ادمین می‌تواند آستانه ۹۰ بگذارد', 90 === WBE_Engine::match_point( 80, array( 3, 90 ) ) );
wbe_check( 'همان روز روی کوچک‌ترین آستانه می‌افتد', 3 === WBE_Engine::match_point( 0, array( 3, 14 ) ) );
wbe_check( 'آستانه صفر همان روز انقضا است', 0 === WBE_Engine::match_point( 0, array( 0, 7 ) ) );

echo "\n--- {$pass} ok, {$fail} fail ---\n";
exit( $fail ? 1 : 0 );
