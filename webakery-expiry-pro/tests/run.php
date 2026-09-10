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
wbe_check( 'فروردین ۳۱ روز است', 31 === WBE_Jalali::jalali_month_length( 1403, 1 ) );
wbe_check( 'مهر ۳۰ روز است', 30 === WBE_Jalali::jalali_month_length( 1403, 7 ) );
wbe_check( 'اسفند ۱۴۰۳ کبیسه است', 30 === WBE_Jalali::jalali_month_length( 1403, 12 ) );
wbe_check( 'اسفند ۱۴۰۲ ۲۹ روز است', 29 === WBE_Jalali::jalali_month_length( 1402, 12 ) );

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

$zero_keep_sale = WBE_Engine::sanitize_batches(
	array(
		array(
			'price'    => '1113200',
			'discount' => '0',
			'sale'     => '890560',
			'stock'    => '1',
			'expiry'   => '2026-12-01',
		),
	),
	'gregorian'
);
wbe_check( 'صفر کردن تخفیف جشنواره را برنمی‌گرداند', isset( $zero_keep_sale[0] ) && 0 === (int) $zero_keep_sale[0]['discount'] && ! isset( $zero_keep_sale[0]['sale'] ) );
$empty_disc = WBE_Engine::sanitize_batches(
	array(
		array(
			'price'    => '1113200',
			'discount' => '',
			'sale'     => '890560',
			'stock'    => '1',
			'expiry'   => '2026-12-01',
		),
	),
	'gregorian'
);
wbe_check( 'خالی کردن تخفیف جشنواره را برنمی‌گرداند', isset( $empty_disc[0] ) && 0 === (int) $empty_disc[0]['discount'] && ! isset( $empty_disc[0]['sale'] ) );
$infer_sale = WBE_Engine::sanitize_batches(
	array(
		array(
			'price'  => '200000',
			'sale'   => '160000',
			'stock'  => '1',
			'expiry' => '2026-12-01',
		),
	),
	'gregorian'
);
wbe_check( 'بدون کلید تخفیف، از جشنواره درصد ساخته می‌شود', isset( $infer_sale[0] ) && 20 === (int) $infer_sale[0]['discount'] );
$res0 = WBE_Engine::resolve_batch_sale( 1113200, 0, '890560', true );
wbe_check( 'resolve: درصد صفر فروش را حذف می‌کند', 0 === $res0['discount'] && ! isset( $res0['sale'] ) );

list( $join_sql, $order_sql ) = WBE_Engine::expiry_order_clauses( '', 'post_date DESC', 'wp_posts', 'wp_postmeta', 'ASC' );
wbe_check( 'سورت به متای انقضا وصل می‌شود', false !== strpos( $join_sql, '_wbe_active_expiry' ) );
wbe_check( 'نزدیک‌ترین انقضا اول می‌آید', false !== strpos( $order_sql, 'ASC' ) && false !== strpos( $order_sql, 'wbe_exp.meta_value' ) );
wbe_check( 'سورت انقضای تنوع متغیر را هم می‌بیند', false !== strpos( $order_sql, 'product_variation' ) );

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
wbe_check( 'مبلغ جشنواره دقیق ذخیره می‌شود', isset( $fest[0]['sale'] ) && 160000.0 === (float) $fest[0]['sale'] );
wbe_check( 'effective_sale مبلغ دستی را برمی‌گرداند', 160000.0 === WBE_Engine::effective_sale( $fest[0] ) );

$precise = WBE_Engine::apply_bulk_to_active(
	array( array( 'id' => 'p', 'price' => '100000', 'discount' => 0, 'stock' => 2, 'expiry' => '2026-06-01' ) ),
	array(
		'sale_mode'  => 'set',
		'sale_value' => 79960,
	),
	'2026-01-15'
);
wbe_check( 'جشنواره ۷۹۹۶۰ بدون رند اجباری به ۸۰۰۰۰', 79960.0 === (float) $precise[0]['sale'] && 79960.0 === WBE_Engine::effective_sale( $precise[0] ) );
wbe_check( 'درصد تقریبی از مبلغ دقیق ساخته می‌شود', 20 === (int) $precise[0]['discount'] );

$res_sum = WBE_Engine::reserved_stock( $bulk, '2026-01-15' );
wbe_check( 'موجودی رزرو = بچ‌های غیر فعال', 8 === $res_sum );

$set_res = WBE_Engine::set_reserved_stock( $bulk, 15, '2026-01-15' );
wbe_check( 'تنظیم موجودی رزرو روی بچ غیر فعال', 15 === (int) $set_res[1]['stock'] && 4 === (int) $set_res[0]['stock'] );
$via_ops = WBE_Engine::apply_bulk_to_active( $bulk, array( 'reserved' => 11 ), '2026-01-15' );
wbe_check( 'عملیات reserved در گروهی', 11 === (int) $via_ops[1]['stock'] && 11 === WBE_Engine::reserved_stock( $via_ops, '2026-01-15' ) );
wbe_check( 'reserved عملیات بچ است', true === WBE_Engine::has_batch_ops( array( 'reserved' => 3 ) ) );

$res_edit = WBE_Engine::apply_bulk_to_reserve(
	$bulk,
	array(
		'res_price'    => 88000,
		'res_discount' => 10,
		'res_stock'    => 20,
		'res_expiry'   => '2026-11-15',
	),
	'2026-01-15'
);
wbe_check( 'ویرایش قیمت رزرو', '88000' === (string) $res_edit[1]['price'] );
wbe_check( 'ویرایش تخفیف رزرو', 10 === (int) $res_edit[1]['discount'] );
wbe_check( 'ویرایش موجودی رزرو', 20 === (int) $res_edit[1]['stock'] );
wbe_check( 'ویرایش انقضای رزرو', '2026-11-15' === $res_edit[1]['expiry'] );
$res_row = WBE_Engine::bulk_row_from_record( 1, 'x', 'S', $bulk, 'gregorian', '', '', '2026-01-15' );
wbe_check( 'ردیف گروهی فیلد رزرو دارد', '90000' === $res_row['res_price'] && 8 === (int) $res_row['res_stock'] );
wbe_check( 'res_price عملیات بچ است', true === WBE_Engine::has_batch_ops( array( 'res_price' => 1 ) ) );

$three = array(
	array( 'id' => 'a', 'price' => '100', 'discount' => 0, 'stock' => 10, 'expiry' => '2026-06-01' ),
	array( 'id' => 'b', 'price' => '120', 'discount' => 0, 'stock' => 20, 'expiry' => '2027-01-01' ),
	array( 'id' => 'c', 'price' => '150', 'discount' => 0, 'stock' => 15, 'expiry' => '2029-01-01' ),
);
$rb = WBE_Engine::reserve_batches( $three, '2026-01-15' );
wbe_check( 'دو بچ رزرو از سه بچ', 2 === count( $rb ) );
$replaced = WBE_Engine::replace_reserve_batches(
	$three,
	array(
		array( 'price' => 120, 'stock' => 20, 'expiry' => '2027-01-01', 'discount' => 0 ),
		array( 'price' => 150, 'stock' => 15, 'expiry' => '2029-01-01', 'discount' => 0 ),
		array( 'price' => 180, 'stock' => 5, 'expiry' => '2030-01-01', 'discount' => 0 ),
	),
	'2026-01-15',
	'gregorian'
);
wbe_check( 'جایگزینی رزروها تعداد را عوض می‌کند', 4 === count( $replaced ) && '100' === (string) $replaced[0]['price'] );
$multi_row = WBE_Engine::bulk_row_from_record( 1, 'x', 'S', $three, 'gregorian', '', '', '2026-01-15' );
wbe_check( 'ردیف گروهی همه رزروها را دارد', 2 === count( $multi_row['reserves'] ) && 20 === (int) $multi_row['reserves'][0]['stock'] );

$expired_only = array(
	array( 'id' => 'old', 'price' => '10', 'stock' => 7, 'expiry' => '2025-12-01' ),
);
$row_expired = WBE_Engine::bulk_row_from_record( 3, 'منقضی', 'E1', $expired_only, 'gregorian', '', '', '2026-01-15' );
wbe_check( 'بدون بچ فعال، موجودی رزرو در ردیف می‌ماند', 7 === (int) $row_expired['reserved'] );
wbe_check( 'ردیف با بچ فعال، رزرو را از بچ بعدی می‌گیرد', 8 === (int) WBE_Engine::bulk_row_from_record( 7, 'شیر', 'S1', $bulk, 'gregorian', '', '', '2026-01-15' )['reserved'] );

$added = WBE_Engine::apply_bulk_to_active(
	$bulk,
	array(
		'add_batch' => array(
			'price'  => 70000,
			'stock'  => 3,
			'expiry' => '2026-11-01',
		),
		'calendar'  => 'gregorian',
	),
	'2026-01-15'
);
wbe_check( 'افزودن بچ رزرو در گروهی', 3 === count( $added ) && 3 === (int) $added[2]['stock'] );
wbe_check( 'افزودن بچ عملیات بچ است', true === WBE_Engine::has_batch_ops( array( 'add_batch' => array( 'expiry' => '2026-01-01' ) ) ) );
wbe_check( 'تشخیص نام ویژگی برند', true === WBE_Engine::looks_like_brand( 'brand', 'Brand' ) );
wbe_check( 'ویژگی رنگ برند نیست', false === WBE_Engine::looks_like_brand( 'color', 'رنگ' ) );
wbe_check( 'ویژگی pa_brand در لیست است', in_array( 'pa_brand', WBE_Engine::brand_taxonomy_slugs(), true ) );

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
wbe_check( 'طبقه‌بندی برند ووکامرس در لیست است', in_array( 'product_brand', WBE_Engine::brand_taxonomy_slugs(), true ) );
wbe_check( 'برند Perfect WooCommerce Brands در لیست است', in_array( 'pwb-brand', WBE_Engine::brand_taxonomy_slugs(), true ) );
wbe_check( 'فیلتر برند داخل نام می‌گردد', true === WBE_Engine::text_has( 'شیر نستله', 'نستله' ) );
wbe_check( 'فیلتر برند نامربوط رد می‌شود', false === WBE_Engine::text_has( 'کامور', 'نستله' ) );
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
		'brand'   => 'نستله',
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
	&& 'نستله' === $plain_row['brand']
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

echo "\n=== محصول متغیر / تنوع ===\n";
wbe_check( 'شناسه موجودی سفارش = تنوع', 55 === WBE_Engine::order_item_stock_id( 10, 55 ) );
wbe_check( 'بدون تنوع = محصول', 10 === WBE_Engine::order_item_stock_id( 10, 0 ) );
$posted_var = WBE_Engine::posted_variation_data(
	array(
		55 => array( 'active' => array( 'price' => '10' ) ),
		0  => array( 'active' => array( 'price' => '99' ) ),
	),
	55,
	0
);
wbe_check( 'POST تنوع با شناسه تنوع خوانده می‌شود', is_array( $posted_var ) && '10' === $posted_var['active']['price'] );
$posted_loop = WBE_Engine::posted_variation_data(
	array(
		2 => array( 'active' => array( 'price' => '77' ) ),
	),
	88,
	2
);
wbe_check( 'POST تنوع قدیمی با ایندکس حلقه خوانده می‌شود', is_array( $posted_loop ) && '77' === $posted_loop['active']['price'] );
wbe_check( 'POST تنوع ناموجود خالی است', null === WBE_Engine::posted_variation_data( array(), 9, 3 ) );

$keep = WBE_Engine::decide_posted_batches(
	array(
		array( 'price' => '150000', 'stock' => '4', 'expiry' => '' ),
	),
	'gregorian'
);
wbe_check( 'فرم ناقص بچ موجود را پاک نمی‌کند', null === $keep );
$clear = WBE_Engine::decide_posted_batches( array(), 'gregorian' );
wbe_check( 'فرم خالی یعنی پاک کردن بچ', array() === $clear );
$ok_post = WBE_Engine::decide_posted_batches(
	array(
		array( 'price' => '100', 'stock' => '2', 'expiry' => '2026-12-01' ),
	),
	'gregorian'
);
wbe_check( 'فرم معتبر بچ ذخیره می‌شود', is_array( $ok_post ) && 1 === count( $ok_post ) );
wbe_check( 'هم‌والد بدون مبدأ', array( 12, 13 ) === WBE_Engine::sibling_ids( 11, array( 11, 12, 13, 11, 0 ) ) );
wbe_check( 'اعلان ذخیره ناقص تقویم را می‌گوید', false !== strpos( WBE_Engine::incomplete_batches_notice(), 'تقویم' ) );
wbe_check( 'عنوان ردیف تنوع', 'شیر — سایز: بزرگ' === WBE_Engine::variation_row_title( 'شیر', 'سایز: بزرگ' ) );
wbe_check( 'عنوان بدون ویژگی', 'شیر' === WBE_Engine::variation_row_title( 'شیر', '' ) );
wbe_check( 'والد متغیر موجودی ندارد', false === WBE_Engine::type_owns_stock( 'variable' ) );
wbe_check( 'تنوع موجودی دارد', true === WBE_Engine::type_owns_stock( 'variation' ) );
wbe_check( 'ساده موجودی دارد', true === WBE_Engine::type_owns_stock( 'simple' ) );
$row_var = WBE_Engine::bulk_row_from_record(
	99,
	'شیر — رنگ: قرمز',
	'SKU-R',
	array(
		array( 'id' => 'a', 'price' => '100', 'stock' => 3, 'expiry' => '2026-06-01', 'discount' => 0 ),
	),
	'jalali',
	'',
	'',
	'2026-01-15',
	array(
		'is_variation' => true,
		'parent_id'    => 50,
		'brand'        => 'پگاه',
		'status'       => 'publish',
	)
);
wbe_check( 'ردیف گروهی پرچم تنوع دارد', ! empty( $row_var['is_variation'] ) && 50 === (int) $row_var['parent_id'] );

echo "\n=== یکتایی ردیف گروهی ===\n";
$dup_sql = array();
for ( $i = 0; $i < 100; $i++ ) {
	$r                 = new stdClass();
	$r->ID             = 10;
	$r->product_type   = ( 99 === $i ) ? 'variable' : '';
	$r->post_title     = 'شیر';
	$dup_sql[]         = $r;
}
$uniq_sql = WBE_Engine::unique_sql_records_by_id( $dup_sql );
wbe_check( '۱۰۰ کپی SQL یک محصول می‌شود', 1 === count( $uniq_sql ), (string) count( $uniq_sql ) );
wbe_check( 'نوع متغیر در کپی‌ها حفظ می‌شود', 'variable' === $uniq_sql[0]->product_type );
$dup_rows = array();
for ( $i = 0; $i < 100; $i++ ) {
	$dup_rows[] = array( 'id' => 7, 'name' => 'الف' );
	$dup_rows[] = array( 'id' => 8, 'name' => 'ب' );
}
$uniq_rows = WBE_Engine::unique_bulk_rows( $dup_rows );
wbe_check( 'ردیف‌های تکراری جدول حذف می‌شوند', 2 === count( $uniq_rows ), (string) count( $uniq_rows ) );

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

echo "\n=== فیلتر برند ویرایش گروهی ===\n";
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook = '', $cb = null, $pri = 10, $args = 1 ) {
		return true;
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}
require_once dirname( __DIR__ ) . '/includes/class-wbe-admin-bulk.php';
wbe_check( 'بدون برند، فیلتر آماده نیست', false === WBE_Admin_Bulk::has_brand_filter( array( 'brand' => '' ) ) );
wbe_check( 'برند خالی فاصله هم آماده نیست', false === WBE_Admin_Bulk::has_brand_filter( array( 'brand' => '  ' ) ) );
wbe_check( 'با شناسه برند، فیلتر آماده است', true === WBE_Admin_Bulk::has_brand_filter( array( 'brand' => '42' ) ) );

echo "\n=== ویرایش گروهی — فیلتر و عملیات ===\n";
$ff = WBE_Admin_Bulk::filters_from_request(
	array(
		's'          => 'شیر',
		'wbe_cat'    => '12',
		'wbe_scope'  => 'batches',
		'wbe_status' => 'draft',
		'wbe_brand'  => '42',
	)
);
wbe_check( 'فیلتر جستجو خوانده می‌شود', 'شیر' === $ff['q'] );
wbe_check( 'فیلتر دسته و برند خوانده می‌شود', 12 === (int) $ff['category'] && '42' === $ff['brand'] );
wbe_check( 'فیلتر محدوده و وضعیت خوانده می‌شود', 'batches' === $ff['scope'] && 'draft' === $ff['status'] );
$bad = WBE_Admin_Bulk::filters_from_request( array( 'wbe_scope' => 'hack', 'wbe_status' => 'trash' ) );
wbe_check( 'محدوده نامعتبر = همه', 'all' === $bad['scope'] );
wbe_check( 'وضعیت نامعتبر خالی می‌شود', '' === $bad['status'] );

$ops_req = WBE_Admin_Bulk::ops_from_request(
	array(
		'wbe_regular_mode'  => 'set',
		'wbe_regular_value' => '۱۵۰۰۰۰',
		'wbe_discount'      => '۲۰',
		'wbe_stock_mode'    => 'set',
		'wbe_stock_value'   => '8',
		'wbe_expiry'        => '2026-09-01',
		'wbe_sale_from'     => '2026-08-01',
		'wbe_sale_to'       => '2026-08-31',
		'wbe_set_status'    => 'publish',
		'wbe_add_price'     => '90000',
		'wbe_add_discount'  => '10',
		'wbe_add_stock'     => '4',
		'wbe_add_expiry'    => '2026-12-01',
	),
	'gregorian'
);
wbe_check( 'نوار گروهی قیمت اصلی را می‌خواند', isset( $ops_req['regular_mode'] ) && 150000.0 === (float) $ops_req['regular_value'] );
wbe_check( 'نوار گروهی تخفیف را می‌خواند', 20.0 === (float) $ops_req['discount'] );
wbe_check( 'نوار گروهی موجودی و انقضا', 'set' === $ops_req['stock_mode'] && '2026-09-01' === $ops_req['expiry'] );
wbe_check( 'نوار گروهی بازه جشنواره', '2026-08-01' === $ops_req['sale_from'] && '2026-08-31' === $ops_req['sale_to'] );
wbe_check( 'نوار گروهی افزودن رزرو با تخفیف', isset( $ops_req['add_batch'] ) && 10 === (int) $ops_req['add_batch']['discount'] && 4 === (int) $ops_req['add_batch']['stock'] );
wbe_check( 'عملیات نوار معنادار است', true === WBE_Admin_Bulk::ops_meaningful( $ops_req ) );
wbe_check( 'نوار خالی معنادار نیست', false === WBE_Admin_Bulk::ops_meaningful( array() ) );

$ops_row = WBE_Admin_Bulk::ops_from_row(
	array(
		'regular'      => '120000',
		'discount'     => '15',
		'sale'         => '102000',
		'stock'        => '6',
		'expiry'       => '2026-10-10',
		'from'         => '2026-09-01',
		'to'           => '2026-09-20',
		'name'         => 'شیر پگاه',
		'sku'          => 'SKU-1',
		'status'       => 'draft',
		'add_price'    => '80000',
		'add_discount' => '۵',
		'add_stock'    => '2',
		'add_expiry'   => '2027-01-01',
	),
	'gregorian'
);
wbe_check( 'ردیف: قیمت تنظیم می‌شود', 'set' === $ops_row['regular_mode'] && 120000.0 === (float) $ops_row['regular_value'] );
wbe_check( 'ردیف: تخفیف و موجودی', 15.0 === (float) $ops_row['discount'] && 6.0 === (float) $ops_row['stock'] );
wbe_check( 'ردیف: نام و SKU و وضعیت', 'شیر پگاه' === $ops_row['name'] && 'SKU-1' === $ops_row['sku'] && 'draft' === $ops_row['status'] );
wbe_check( 'ردیف: افزودن رزرو تخفیف را حفظ می‌کند', 5 === (int) $ops_row['add_batch']['discount'] );
wbe_check( 'ردیف: بازه جشنواره', '2026-09-01' === $ops_row['sale_from'] && '2026-09-20' === $ops_row['sale_to'] );

$ops_empty_res = WBE_Admin_Bulk::ops_from_row( array( 'reserves' => array() ), 'gregorian' );
wbe_check( 'حذف همه رزروها در عملیات می‌آید', isset( $ops_empty_res['reserves'] ) && array() === $ops_empty_res['reserves'] );
wbe_check( 'حذف رزروها معنادار است', true === WBE_Admin_Bulk::ops_meaningful( $ops_empty_res ) );

$ops_multi_res = WBE_Admin_Bulk::ops_from_row(
	array(
		'reserves' => array(
			array( 'id' => 'r1', 'price' => '90000', 'discount' => '0', 'stock' => '3', 'expiry' => '2027-01-01' ),
			array( 'id' => 'r2', 'price' => '70000', 'discount' => '10', 'stock' => '5', 'expiry' => '2028-01-01' ),
			array( 'price' => '1', 'stock' => '1', 'expiry' => '' ),
		),
	),
	'gregorian'
);
wbe_check( 'رزرو بدون انقضا رد می‌شود', 2 === count( $ops_multi_res['reserves'] ) );
wbe_check( 'رزرو دوم تخفیف دارد', 10 === (int) $ops_multi_res['reserves'][1]['discount'] );

$cleared = WBE_Engine::replace_reserve_batches(
	array(
		array( 'id' => 'a', 'price' => '100', 'stock' => 4, 'expiry' => '2026-06-01', 'discount' => 0 ),
		array( 'id' => 'b', 'price' => '90', 'stock' => 8, 'expiry' => '2027-01-01', 'discount' => 0 ),
	),
	array(),
	'2026-01-15',
	'gregorian'
);
wbe_check( 'جایگزینی رزرو خالی فقط فعال را نگه می‌دارد', 1 === count( $cleared ) && 'a' === $cleared[0]['id'] );

$pipeline = WBE_Engine::apply_bulk_to_active(
	array(
		array( 'id' => 'a', 'price' => '100000', 'stock' => 4, 'expiry' => '2026-06-01', 'discount' => 0 ),
	),
	$ops_req,
	'2026-01-15'
);
wbe_check( 'اعمال نوار روی بچ فعال', '150000' === (string) $pipeline[0]['price'] && 20 === (int) $pipeline[0]['discount'] && 8 === (int) $pipeline[0]['stock'] );
wbe_check( 'اعمال نوار بچ رزرو می‌افزاید', 2 === count( $pipeline ) && 10 === (int) $pipeline[1]['discount'] );

$ten_dup = array();
for ( $pid = 1; $pid <= 10; $pid++ ) {
	for ( $c = 0; $c < 100; $c++ ) {
		$rec               = new stdClass();
		$rec->ID           = $pid;
		$rec->product_type = ( 0 === $c && $pid <= 2 ) ? 'variable' : '';
		$ten_dup[]         = $rec;
	}
}
$ten_u = WBE_Engine::unique_sql_records_by_id( $ten_dup );
wbe_check( '۱۰ محصول × ۱۰۰ کپی = ۱۰ ردیف', 10 === count( $ten_u ), (string) count( $ten_u ) );
wbe_check( 'نوع متغیر بین کپی‌ها گم نمی‌شود', 'variable' === $ten_u[0]->product_type && 'variable' === $ten_u[1]->product_type );

$bulk_rows = array();
for ( $i = 0; $i < 10; $i++ ) {
	for ( $j = 0; $j < 100; $j++ ) {
		$bulk_rows[] = array( 'id' => 100 + $i, 'name' => 'p' . $i );
	}
}
wbe_check( 'جدول گروهی ۱۰۰۰ کپی را ۱۰ می‌کند', 10 === count( WBE_Engine::unique_bulk_rows( $bulk_rows ) ) );

echo "\n=== موجودی رزرو ویرایش تکی ===\n";
$single_rows = WBE_Engine::merge_active_and_reserve_rows(
	array(
		'id'       => 'act',
		'price'    => '100000',
		'discount' => '10',
		'stock'    => '5',
		'expiry'   => '2026-06-01',
	),
	array(
		array( 'id' => 'r1', 'price' => '90000', 'discount' => '0', 'stock' => '3', 'expiry' => '2027-01-01' ),
		array( 'id' => 'r2', 'price' => '80000', 'discount' => '15', 'stock' => '7', 'expiry' => '2028-01-01' ),
		'bad',
	)
);
wbe_check( 'فرم تکی فعال + ۲ رزرو را جمع می‌کند', 3 === count( $single_rows ) );
$single_clean = WBE_Engine::sanitize_batches( $single_rows, 'gregorian' );
wbe_check( 'ذخیره تکی ۳ بچ معتبر دارد', 3 === count( $single_clean ) );
$single_idx = WBE_Engine::active_index( $single_clean, '2026-01-15' );
wbe_check( 'در تکی نزدیک‌ترین انقضا فعال است', 0 === $single_idx );
$single_res = WBE_Engine::reserve_batches( $single_clean, '2026-01-15' );
wbe_check( 'در تکی ۲ بچ رزرو می‌ماند', 2 === count( $single_res ) );
wbe_check( 'رزرو دوم تخفیف ۱۵٪ دارد', 15 === (int) $single_res[1]['discount'] && 7 === (int) $single_res[1]['stock'] );
$single_cleared = WBE_Engine::replace_reserve_batches( $single_clean, array(), '2026-01-15', 'gregorian' );
wbe_check( 'حذف رزرو در تکی فقط فعال را نگه می‌دارد', 1 === count( $single_cleared ) );

$single_view = file_get_contents( dirname( __DIR__ ) . '/includes/views/product-batches.php' );
$var_view    = file_get_contents( dirname( __DIR__ ) . '/includes/views/product-variation-batches.php' );
$bulk_view   = file_get_contents( dirname( __DIR__ ) . '/includes/views/bulk-prices.php' );
wbe_check( 'ویرایش تکی فیلد SKU ندارد', false === strpos( $single_view, 'name="wbe_sku"' ) && false === strpos( $var_view, '[sku]' ) );
wbe_check( 'ویرایش تکی فیلد وضعیت ندارد', false === strpos( $single_view, 'name="wbe_status"' ) );
wbe_check( 'ویرایش تکی فیلد نام ندارد', false === strpos( $single_view, 'name="wbe_name"' ) && false === strpos( $var_view, '[name]' ) );
wbe_check( 'ویرایش گروهی ستون نام دارد', false !== strpos( $bulk_view, 'نام محصول' ) );
wbe_check( 'نوار گروهی افزودن رزرو ندارد', false === strpos( $bulk_view, 'افزودن بچ رزرو جدید' ) );
wbe_check( 'ویرایش گروهی ستون SKU دارد', false !== strpos( $bulk_view, '>SKU<' ) );
wbe_check( 'ویرایش گروهی ستون وضعیت دارد', false !== strpos( $bulk_view, '>وضعیت<' ) );
wbe_check( 'تکی فیلد جشنواره تقویم دارد', false !== strpos( $single_view, 'class="wbe-date"' ) );
wbe_check( 'تنوع دکمه کپی به همه دارد', false !== strpos( $var_view, 'wbe-copy-variations' ) );
wbe_check( 'گروهی فیلد تاریخ تقویم دارد', false !== strpos( $bulk_view, 'wbe-date' ) );
wbe_check( 'اسکریپت تقویم شمسی هست', is_file( dirname( __DIR__ ) . '/assets/datepicker.js' ) );

echo "\n=== کندی گروهی و گزارش باگ ===\n";
require_once dirname( __DIR__ ) . '/includes/class-wbe-support.php';
wbe_check( 'بدون برند لود گروهی باید رد شود', true === WBE_Support::bulk_should_skip_load( array( 'brand' => '' ) ) );
wbe_check( 'با برند لود گروهی مجاز است', false === WBE_Support::bulk_should_skip_load( array( 'brand' => 'نستله' ) ) );
wbe_check( 'ذخیره گروهی تکه‌تکه است', 40 === WBE_Admin_Bulk::chunk_size() );
wbe_check( 'آیدی تلگرام گزارش باگ', 'HAJITODAY' === WBE_Support::telegram_handle() );
wbe_check( 'لینک چت تلگرام درست است', 'https://t.me/HAJITODAY' === WBE_Support::telegram_chat_url() );
$rep = WBE_Support::report_text(
	'جدول گروهی کند شد',
	array(
		'version'        => '1.2.12',
		'page'           => 'webakery-expiry-bulk',
		'url'            => 'https://example.test/wp-admin',
		'has_screenshot' => true,
	)
);
$tg  = WBE_Support::telegram_chat_url( $rep );
wbe_check( 'متن گزارش نسخه و توضیح دارد', false !== strpos( $rep, '1.2.12' ) && false !== strpos( $rep, 'جدول گروهی کند شد' ) );
wbe_check( 'متن گزارش اسکرین را ذکر می‌کند', false !== strpos( $rep, 'اسکرین‌شات' ) );
wbe_check( 'لینک تلگرام متن را دارد', 0 === strpos( $tg, 'https://t.me/HAJITODAY?text=' ) && false !== strpos( $tg, rawurlencode( 'جدول گروهی کند شد' ) ) );
$help = file_get_contents( dirname( __DIR__ ) . '/includes/views/help.php' );
$bug  = file_get_contents( dirname( __DIR__ ) . '/includes/views/bug-report.php' );
wbe_check( 'راهنما صفحه دارد', false !== strpos( $help, 'رفع کندی' ) && false !== strpos( $help, 'موجودی رزرو' ) );
wbe_check( 'راهنما تقویم و کپی تنوع دارد', false !== strpos( $help, 'کپی بچ‌ها به همه تنوع‌ها' ) );
wbe_check( 'راهنما رایگان و پرو را یکسان می‌گوید', false !== strpos( $help, 'امکانات یکسان' ) );
wbe_check( 'فرم گزارش باگ تلگرام دارد', false !== strpos( $bug, 't.me' ) && false !== strpos( $bug, 'wbe-bug-capture' ) && false !== strpos( $bug, 'wbe-bug-desc' ) );

echo "\n=== پشتیبانی / تلگرام ===\n";
require_once dirname( __DIR__ ) . '/includes/class-wbe-support.php';
wbe_check( 'آدرس چت تلگرام بدون متن', 'https://t.me/HAJITODAY' === WBE_Support::telegram_chat_url() );
$tg = WBE_Support::telegram_chat_url( 'سلام' );
wbe_check( 'آدرس چت با متن از ? استفاده می‌کند', 0 === strpos( $tg, 'https://t.me/HAJITODAY?text=' ) );
wbe_check( 'لود گروهی بدون برند باید رد شود', true === WBE_Support::bulk_should_skip_load( array() ) );

echo "\n=== فعال‌سازی وردپرس ===\n";
if ( ! defined( 'WBE_PATH' ) ) {
	define( 'WBE_PATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'WBE_FILE' ) ) {
	define( 'WBE_FILE', dirname( __DIR__ ) . '/webakery-expiry.php' );
}
if ( ! defined( 'WBE_VERSION' ) ) {
	define( 'WBE_VERSION', '1.2.17' );
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
	wbe_check( 'رایگان و پرو امکانات یکسان — licensed همیشه فعال', true === WBE_Plugin::licensed() );
	$plugin_src = file_get_contents( dirname( __DIR__ ) . '/includes/class-wbe-plugin.php' );
	wbe_check( 'licensed به وضعیت لایسنس وابسته نیست', false === strpos( $plugin_src, 'WB_License::is_active' ) );
	wbe_check( 'پرو امکانات را قفل نمی‌کند', false !== strpos( $plugin_src, "'lock_features' => false" ) );
	$lic_file = dirname( __DIR__ ) . '/includes/class-wb-license.php';
	if ( is_file( $lic_file ) ) {
		$lic_src = file_get_contents( $lic_file );
		wbe_check( 'کلاینت لایسنس قفل امکانات اختیاری است', false !== strpos( $lic_src, 'locks_features' ) && false !== strpos( $lic_src, 'امکانات افزونه فعال می‌ماند' ) );
	}
} catch ( Throwable $e ) {
	wbe_check( 'فعال‌سازی بدون fatal', false, $e->getMessage() );
}

echo "\n--- {$pass} ok, {$fail} fail ---\n";
exit( $fail ? 1 : 0 );
