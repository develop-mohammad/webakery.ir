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
wbe_check( 'تخفیف پیش‌فرض صفر است', 0 === (int) $clean[0]['discount'] );

$disc_rows = WBE_Engine::sanitize_batches(
	array(
		array(
			'price'    => '200000',
			'discount' => '٪۲۰',
			'stock'    => '5',
			'expiry'   => '1405/01/10',
		),
	),
	'jalali'
);
wbe_check( 'درصد تخفیف فارسی ذخیره می‌شود', isset( $disc_rows[0] ) && 20 === (int) $disc_rows[0]['discount'] );
wbe_check( 'قیمت بعد از ۲۰٪', 160000.0 === WBE_Engine::sale_price( 200000, 20 ) );
wbe_check( 'بدون تخفیف همان قیمت است', 180000.0 === WBE_Engine::sale_price( 180000, 0 ) );
wbe_check( 'تخفیف ۱۰۰٪ صفر می‌شود', 0.0 === WBE_Engine::sale_price( 1000, 100 ) );
$over = WBE_Engine::sanitize_batches(
	array(
		array(
			'price'    => '10',
			'discount' => '150',
			'stock'    => '1',
			'expiry'   => '2026-12-01',
		),
	),
	'gregorian'
);
wbe_check( 'تخفیف بالای ۱۰۰ بریده می‌شود', 100 === (int) $over[0]['discount'] );

list( $join_sql, $order_sql ) = WBE_Engine::expiry_order_clauses( '', 'post_date DESC', 'wp_posts', 'wp_postmeta', 'ASC' );
wbe_check( 'سورت به متای انقضا وصل می‌شود', false !== strpos( $join_sql, '_wbe_active_expiry' ) );
wbe_check( 'نزدیک‌ترین انقضا اول می‌آید', false !== strpos( $order_sql, 'wbe_exp.meta_value ASC' ) );

wbe_check( 'تخفیف از قیمت فروش ووکامرس', 20 === WBE_Engine::discount_from_prices( 200000, 160000 ) );
wbe_check( 'بدون قیمت فروش تخفیف صفر است', 0 === WBE_Engine::discount_from_prices( 200000, '' ) );
$priced = array(
	array( 'id' => 'a', 'price' => '100000', 'discount' => 10, 'stock' => 4, 'expiry' => '2026-03-01' ),
	array( 'id' => 'b', 'price' => '90000', 'discount' => 0, 'stock' => 8, 'expiry' => '2026-10-01' ),
);
$pulled = WBE_Engine::apply_wc_price_to_active( $priced, '150000', '', '2026-01-15', false );
wbe_check( 'تغییر گروهی قیمت بچ فعال را عوض می‌کند', '150000' === $pulled[0]['price'] );
wbe_check( 'درصد تخفیف بچ فعال اگر فروش عوض نشده بماند', 10 === (int) $pulled[0]['discount'] );
wbe_check( 'قیمت رزرو دست نمی‌خورد', '90000' === $pulled[1]['price'] );
$pulled2 = WBE_Engine::apply_wc_price_to_active( $priced, '200000', '160000', '2026-01-15', true );
wbe_check( 'قیمت فروش گروهی تخفیف را مچ می‌کند', 20 === (int) $pulled2[0]['discount'] && '200000' === $pulled2[0]['price'] );
$expired_only = array(
	array( 'id' => 'old', 'price' => '10', 'discount' => 0, 'stock' => 1, 'expiry' => '2020-01-01' ),
);
$pulled3 = WBE_Engine::apply_wc_price_to_active( $expired_only, '50000', '', '2026-01-15', true );
wbe_check( 'بدون بچ فعال، اولین ردیف مچ می‌شود', '50000' === $pulled3[0]['price'] );
wbe_check( 'بدون بچ، قیمت ووکامرس بچ نمی‌سازد', array() === WBE_Engine::apply_wc_price_to_active( array(), '90000', '', '2026-01-15', true ) );
wbe_check( 'قیمت صفر بچ را عوض نمی‌کند', $priced === WBE_Engine::apply_wc_price_to_active( $priced, '0', '', '2026-01-15', true ) );
$same = WBE_Engine::apply_wc_price_to_active( $priced, '100000', '', '2026-01-15', false );
wbe_check( 'اگر قیمت از قبل یکی باشد آرایه دست نمی‌خورد', $priced === $same );

echo "\n=== ویرایش گروهی قیمت ===\n";
wbe_check( 'تنظیم مبلغ', 150000.0 === WBE_Engine::change_amount( 100000, 'set', 150000 ) );
wbe_check( 'افزایش مبلغ', 110000.0 === WBE_Engine::change_amount( 100000, 'inc', 10000 ) );
wbe_check( 'کاهش مبلغ', 90000.0 === WBE_Engine::change_amount( 100000, 'dec', 10000 ) );
wbe_check( 'افزایش درصدی', 110.0 === WBE_Engine::change_amount( 100, 'inc_pct', 10 ) );
wbe_check( 'کاهش درصدی', 160.0 === WBE_Engine::change_amount( 200, 'dec_pct', 20 ) );
wbe_check( 'منفی صفر می‌شود', 0.0 === WBE_Engine::change_amount( 50, 'dec', 80 ) );
wbe_check( 'حالت ناشناخته مبلغ فعلی را نگه می‌دارد', 12.0 === WBE_Engine::change_amount( 12, 'none', 99 ) );
wbe_check( 'مبلغ فارسی خوانده می‌شود', 20000.0 === WBE_Engine::parse_amount( '۲۰٬۰۰۰' ) );
wbe_check( 'مبلغ خالی null است', null === WBE_Engine::parse_amount( '' ) );
wbe_check( 'صفر معتبر است', 0.0 === WBE_Engine::parse_amount( '0' ) );

$bulk = array(
	array( 'id' => 'a', 'price' => '200000', 'discount' => 10, 'stock' => 4, 'expiry' => '2026-03-01' ),
	array( 'id' => 'b', 'price' => '90000', 'discount' => 0, 'stock' => 8, 'expiry' => '2026-10-01' ),
);
$only_disc = WBE_Engine::apply_bulk_to_active( $bulk, array( 'discount' => 25 ), '2026-01-15' );
wbe_check( 'فقط درصد تخفیف بچ فعال', 25 === (int) $only_disc[0]['discount'] && '200000' === $only_disc[0]['price'] );
wbe_check( 'رزرو در ویرایش گروهی دست نمی‌خورد', '90000' === $only_disc[1]['price'] && 0 === (int) $only_disc[1]['discount'] );

$fest = WBE_Engine::apply_bulk_to_active(
	$bulk,
	array(
		'sale_mode'  => 'set',
		'sale_value' => 160000,
		'discount'   => 50,
	),
	'2026-01-15'
);
wbe_check( 'مبلغ جشنواره درصد را می‌سازد و بر درصد خام اولویت دارد', 20 === (int) $fest[0]['discount'] );

$reg = WBE_Engine::apply_bulk_to_active(
	$bulk,
	array(
		'regular_mode'  => 'inc_pct',
		'regular_value' => 10,
		'discount'      => 10,
	),
	'2026-01-15'
);
wbe_check( 'افزایش درصدی قیمت اصلی', '220000' === $reg[0]['price'] );
wbe_check( 'بعد از تغییر قیمت اصلی درصد جدا اعمال می‌شود', 10 === (int) $reg[0]['discount'] );

$clear = WBE_Engine::apply_bulk_to_active( $bulk, array( 'clear_sale' => true ), '2026-01-15' );
wbe_check( 'حذف تخفیف گروهی', 0 === (int) $clear[0]['discount'] );

$eq = WBE_Engine::apply_bulk_to_active(
	$bulk,
	array(
		'sale_mode'  => 'set',
		'sale_value' => 200000,
	),
	'2026-01-15'
);
wbe_check( 'مبلغ جشنواره برابر قیمت اصلی تخفیف را صفر می‌کند', 0 === (int) $eq[0]['discount'] );

wbe_check( 'بدون بچ، گروهی چیزی نمی‌سازد', array() === WBE_Engine::apply_bulk_to_active( array(), array( 'discount' => 15 ), '2026-01-15' ) );
$expired_only = array(
	array( 'id' => 'old', 'price' => '10', 'discount' => 5, 'stock' => 1, 'expiry' => '2020-01-01' ),
);
wbe_check( 'بدون بچ فعال، گروهی دست نمی‌زند', $expired_only === WBE_Engine::apply_bulk_to_active( $expired_only, array( 'discount' => 40 ), '2026-01-15' ) );
wbe_check( 'بدون عملیات قیمت، آرایه همان است', $bulk === WBE_Engine::apply_bulk_to_active( $bulk, array(), '2026-01-15' ) );
wbe_check( 'عملیات قیمت تشخیص داده می‌شود', true === WBE_Engine::has_price_ops( array( 'discount' => 10 ) ) );
wbe_check( 'عملیات خالی تشخیص داده می‌شود', false === WBE_Engine::has_price_ops( array() ) );
wbe_check( 'موجودی هم عملیات بچ است', true === WBE_Engine::has_batch_ops( array( 'stock' => 3 ) ) );
wbe_check( 'گرد کردن به بالا', 109.0 === WBE_Engine::round_money( 108.9, 'ceil' ) );
wbe_check( 'گرد کردن به پایین', 108.0 === WBE_Engine::round_money( 108.9, 'floor' ) );
$st = WBE_Engine::apply_bulk_to_active( $bulk, array( 'stock' => 99, 'stock_mode' => 'set' ), '2026-01-15' );
wbe_check( 'موجودی گروهی فقط بچ فعال', 99 === (int) $st[0]['stock'] && 8 === (int) $st[1]['stock'] );
$ex = WBE_Engine::apply_bulk_to_active( $bulk, array( 'expiry' => '2026-12-15' ), '2026-01-15' );
wbe_check( 'انقضای گروهی فقط بچ فعال', '2026-12-15' === $ex[0]['expiry'] && '2026-10-01' === $ex[1]['expiry'] );
$tiny = array(
	array( 'id' => 't', 'price' => '99', 'discount' => 0, 'stock' => 1, 'expiry' => '2026-03-01' ),
);
$ceilp = WBE_Engine::apply_bulk_to_active(
	$tiny,
	array(
		'regular_mode'  => 'inc_pct',
		'regular_value' => 10,
		'round'         => 'ceil',
	),
	'2026-01-15'
);
wbe_check( 'افزایش درصدی با گرد کردن به بالا', '109' === $ceilp[0]['price'] );
$row = WBE_Engine::bulk_row_from_record( 7, 'شیر', 'S1', $bulk, 'gregorian', '2026-01-01', '2026-01-31', '2026-01-15' );
wbe_check( 'ردیف گروهی از بچ فعال ساخته می‌شود', 7 === $row['id'] && '200000' === $row['regular'] && 10 === (int) $row['discount'] && 4 === (int) $row['stock'] );
wbe_check( 'تاریخ جشنواره در ردیف گروهی', '2026-01-01' === $row['from'] && '2026-01-31' === $row['to'] );

$plain = WBE_Engine::apply_plain_state( 100000, '', 5, array( 'regular_mode' => 'set', 'regular_value' => 120000 ) );
wbe_check( 'بدون بچ قیمت اصلی تنظیم می‌شود', '120000' === $plain['regular'] && '' === $plain['sale'] && 0 === (int) $plain['discount'] );
$plain_d = WBE_Engine::apply_plain_state( 100000, '', 5, array( 'discount' => 20 ) );
wbe_check( 'بدون بچ تخفیف قیمت فروش می‌سازد', '80000' === $plain_d['sale'] && 20 === (int) $plain_d['discount'] );
$plain_i = WBE_Engine::apply_plain_state( 100000, 80000, 5, array( 'stock_mode' => 'inc', 'stock' => 3 ) );
wbe_check( 'بدون بچ موجودی افزایش می‌یابد', 8 === (int) $plain_i['stock'] && '80000' === $plain_i['sale'] );
$plain_row = WBE_Engine::bulk_row_from_record(
	9,
	'نان',
	'N1',
	array(),
	'gregorian',
	'',
	'',
	'2026-01-15',
	array(
		'regular' => '50000',
		'sale'    => '40000',
		'stock'   => '12',
		'status'  => 'draft',
	)
);
wbe_check(
	'ردیف گروهی بدون بچ از ووکامرس می‌آید',
	'50000' === $plain_row['regular']
	&& 20 === (int) $plain_row['discount']
	&& 12 === (int) $plain_row['stock']
	&& 'draft' === $plain_row['status']
	&& false === $plain_row['has_batches']
	&& false === $plain_row['has_active']
);

echo "\n=== بازه فروش فوق‌العاده ===\n";
wbe_check( 'بدون تاریخ، بازه زنده است', true === WBE_Engine::sale_window_live( '', '', '2026-01-15' ) );
wbe_check( 'قبل از شروع زنده نیست', false === WBE_Engine::sale_window_live( '2026-02-01', '2026-02-10', '2026-01-15' ) );
wbe_check( 'داخل بازه زنده است', true === WBE_Engine::sale_window_live( '2026-01-01', '2026-01-31', '2026-01-15' ) );
wbe_check( 'بعد از پایان زنده نیست', false === WBE_Engine::sale_window_live( '2026-01-01', '2026-01-10', '2026-01-15' ) );
wbe_check( 'همان روز پایان هنوز زنده است', true === WBE_Engine::sale_window_live( '', '2026-01-15', '2026-01-15' ) );
$range = WBE_Engine::sale_dates_text( '2026-01-01', '2026-01-31', 'gregorian', false );
wbe_check( 'متن بازه از و تا', 'از 2026/01/01 تا 2026/01/31' === $range, $range );
$until = WBE_Engine::sale_dates_text( '', '2026-01-31', 'gregorian', false );
wbe_check( 'فقط تا تاریخ پایان', 'تا 2026/01/31' === $until, $until );
wbe_check( 'رشته تاریخ به Y-m-d', '2026-08-28' === WBE_Jalali::datetime_to_ymd( '2026-08-28 23:59:59' ) );
wbe_check( 'خالی تاریخ ندارد', '' === WBE_Jalali::datetime_to_ymd( '' ) );
$cd = WBE_Engine::countdown_parts( 1000 + 90061, 1000 );
wbe_check( 'تایمر ۱ روز ۱ ساعت ۱ دقیقه ۱ ثانیه', 1 === $cd['days'] && 1 === $cd['hours'] && 1 === $cd['minutes'] && 1 === $cd['seconds'] );
$cd0 = WBE_Engine::countdown_parts( 1000, 2000 );
wbe_check( 'تایمر تمام‌شده صفر است', 0 === $cd0['remaining'] && 0 === $cd0['seconds'] );
wbe_check( 'پایان روز timestamp دارد', WBE_Engine::ymd_end_ts( '2026-08-28' ) > 0 );
wbe_check( 'تایمر سراسری خاموش = هیچ‌جا نیست', false === WBE_Engine::countdown_allowed( 0, 0 ) );
wbe_check( 'تایمر سراسری روشن و محصول مخفی نشده', true === WBE_Engine::countdown_allowed( 1, 0 ) );
wbe_check( 'یک تیک روی محصول تایمر را خاموش می‌کند', false === WBE_Engine::countdown_allowed( 1, 1 ) );

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
			'price'     => 162000,
			'discount'  => 10,
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
wbe_check( 'ستون تخفیف در اکسل هست', false !== strpos( $xml, 'تخفیف' ) );
wbe_check( 'ستون قیمت قبل تخفیف در اکسل هست', false !== strpos( $xml, 'قیمت قبل تخفیف' ) );
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

echo "\n=== فعال‌سازی وردپرس ===\n";
if ( ! defined( 'WBE_PATH' ) ) {
	define( 'WBE_PATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'WBE_FILE' ) ) {
	define( 'WBE_FILE', dirname( __DIR__ ) . '/webakery-expiry.php' );
}
if ( ! defined( 'WBE_VERSION' ) ) {
	define( 'WBE_VERSION', '1.2.0' );
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return $default;
	}
}
if ( ! function_exists( 'add_option' ) ) {
	$GLOBALS['wbe_added_options'] = array();
	function add_option( $key, $value, $deprecated = '', $autoload = 'yes' ) {
		$GLOBALS['wbe_added_options'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $hook = '' ) {
		return false;
	}
}
if ( ! function_exists( 'wp_schedule_event' ) ) {
	$GLOBALS['wbe_cron_set'] = false;
	function wp_schedule_event( $timestamp = 0, $recurrence = '', $hook = '' ) {
		$GLOBALS['wbe_cron_set'] = true;
		return true;
	}
}
require_once dirname( __DIR__ ) . '/includes/class-wbe-plugin.php';
try {
	WBE_Plugin::activate();
	wbe_check( 'فعال‌سازی کلاس تنظیمات را لود می‌کند', class_exists( 'WBE_Settings', false ) );
	wbe_check( 'تنظیمات پیش‌فرض نوشته می‌شود', isset( $GLOBALS['wbe_added_options']['wbe_settings'] ) && is_array( $GLOBALS['wbe_added_options']['wbe_settings'] ) );
	wbe_check( 'کرون روزانه ثبت می‌شود', ! empty( $GLOBALS['wbe_cron_set'] ) );
} catch ( Throwable $e ) {
	wbe_check( 'فعال‌سازی بدون fatal', false, $e->getMessage() );
}

echo "\n--- {$pass} ok, {$fail} fail ---\n";
exit( $fail ? 1 : 0 );
