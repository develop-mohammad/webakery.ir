<?php
/**
 * تست عملکردی افزونه «کد تخفیف دسته‌بندی» روی یک نصب واقعی وردپرس + ووکامرس.
 *
 * اجرا:
 *   wp eval-file wp-content/plugins/webakery-category-coupons/tests/test-coupons.php
 *
 * پیش‌نیاز: ووکامرس فعال + چند دسته‌بندی محصول و محصول نمونه.
 */

defined( 'ABSPATH' ) || exit;

$pass = 0;
$fail = 0;

$check = function ( $label, $ok, $detail = '' ) use ( &$pass, &$fail ) {
	if ( $ok ) {
		$pass++;
		echo "  ok   — {$label}" . ( $detail ? " ({$detail})" : '' ) . "\n";
	} else {
		$fail++;
		echo "  FAIL — {$label}" . ( $detail ? " ({$detail})" : '' ) . "\n";
	}
};

$term = function ( $slug ) {
	$t = get_term_by( 'slug', $slug, 'product_cat' );
	return $t ? (int) $t->term_id : 0;
};

$product_in = function ( $slug ) {
	$q = get_posts( array(
		'post_type'      => 'product',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'tax_query'      => array( array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $slug ) ),
	) );
	return $q ? (int) $q[0] : 0;
};

echo "\n=== ۱) انتخاب مقدار تخفیف در بازه ===\n";

$values = array();
for ( $i = 0; $i < 300; $i++ ) {
	$values[] = WBCC_Generator::pick_amount( 40, 50, 5 );
}
$unique = array_values( array_unique( $values ) );
sort( $unique );
$check( 'بازه ۴۰ تا ۵۰ با پله ۵ فقط ۴۰/۴۵/۵۰ می‌دهد', array( 40.0, 45.0, 50.0 ) === $unique, implode( ',', $unique ) );
$check( 'هر سه مقدار بازه دیده می‌شوند', 3 === count( $unique ) );

$free = array();
for ( $i = 0; $i < 200; $i++ ) {
	$free[] = WBCC_Generator::pick_amount( 30, 40, 0 );
}
$check( 'بدون پله همه مقادیر داخل ۳۰ تا ۴۰ هستند', min( $free ) >= 30 && max( $free ) <= 40, 'min=' . min( $free ) . ' max=' . max( $free ) );
$check( 'مقدار ثابت (۲۵ تا ۲۵) همیشه ۲۵ است', 25.0 === WBCC_Generator::pick_amount( 25, 25, 5 ) );
$check( 'بازه برعکس (۵۰ تا ۴۰) هم درست کار می‌کند', in_array( WBCC_Generator::pick_amount( 50, 40, 10 ), array( 40.0, 50.0 ), true ) );

echo "\n=== ۲) پاک‌سازی ورودی فرم (ارقام فارسی، بازه برعکس، درصد >۱۰۰) ===\n";

$clean = WBCC_Campaigns::sanitize( array(
	'name'         => '  تخفیف تستی  ',
	'min'          => '۵۰',
	'max'          => '۴۰',
	'step'         => '۵',
	'type'         => 'percent',
	'categories'   => array( '16', 'x', '0', '16' ),
	'prefix'       => 'off!کد-2',
	'expires_days' => '۷',
	'code_length'  => '999',
) );
$check( 'ارقام فارسی به لاتین تبدیل می‌شوند', 40.0 === $clean['min'] && 50.0 === $clean['max'], "min={$clean['min']} max={$clean['max']}" );
$check( 'دسته‌بندی‌های تکراری/نامعتبر حذف می‌شوند', array( 16 ) === $clean['categories'], wp_json_encode( $clean['categories'] ) );
$check( 'پیشوند فقط حروف/اعداد لاتین می‌شود', 'OFF-2' === $clean['prefix'], $clean['prefix'] );
$check( 'طول کد در بازه مجاز محدود می‌شود', 20 === $clean['code_length'], (string) $clean['code_length'] );

$over = WBCC_Campaigns::sanitize( array( 'type' => 'percent', 'min' => '10', 'max' => '250' ) );
$check( 'درصد بیشتر از ۱۰۰ به ۱۰۰ محدود می‌شود', 100.0 === $over['max'], (string) $over['max'] );

echo "\n=== ۳) ساخت کد تخفیف برای دسته‌بندی ===\n";

$home     = $term( 'home-appliance' );
$fridge   = $term( 'fridge' );
$clothing = $term( 'clothing' );
$check( 'دسته‌بندی‌های نمونه موجودند', $home && $fridge && $clothing, "home={$home} fridge={$fridge} clothing={$clothing}" );

$id_home = WBCC_Campaigns::save( array(
	'name'             => 'تخفیف لوازم خانگی (۴۰ تا ۵۰٪)',
	'categories'       => array( $home ),
	'include_children' => 1,
	'type'             => 'percent',
	'min'              => 40,
	'max'              => 50,
	'step'             => 5,
	'prefix'           => 'HOME',
	'expires_days'     => 7,
	'usage_limit'      => 1,
	'batch_count'      => 12,
	'enabled'          => 1,
) );
$campaign_home = WBCC_Campaigns::get( $id_home );
$check( 'کمپین ذخیره شد', (bool) $campaign_home, '#' . $id_home );
$check( 'زیردسته‌ها به دسته‌های کمپین اضافه می‌شوند',
	in_array( $fridge, WBCC_Campaigns::resolved_categories( $campaign_home ), true ),
	implode( ',', WBCC_Campaigns::resolved_categories( $campaign_home ) )
);

$res = WBCC_Generator::generate( $campaign_home, 12, 'manual' );
$check( 'ساخت ۱۲ کد موفق بود', ! empty( $res['ok'] ) && 12 === count( $res['coupons'] ), $res['message'] );

$amounts = array();
$codes   = array();
$ok_meta = true;
foreach ( $res['coupons'] as $row ) {
	$coupon    = new WC_Coupon( $row['id'] );
	$amounts[] = (float) $coupon->get_amount();
	$codes[]   = $row['code'];

	if ( 'percent' !== $coupon->get_discount_type()
		|| ! in_array( $fridge, $coupon->get_product_categories(), true )
		|| ! in_array( $home, $coupon->get_product_categories(), true )
		|| 1 !== (int) $coupon->get_usage_limit()
		|| (int) get_post_meta( $row['id'], '_wbcc_campaign', true ) !== $id_home ) {
		$ok_meta = false;
	}
}
$check( 'همه کدها درصدی + دسته‌بندی درست + سقف مصرف درست', $ok_meta );
$check( 'همه مقادیر داخل بازه ۴۰ تا ۵۰ با پله ۵ هستند',
	array() === array_diff( array_unique( $amounts ), array( 40.0, 45.0, 50.0 ) ),
	implode( ',', array_unique( $amounts ) )
);
$check( 'کدها یکتا هستند', count( $codes ) === count( array_unique( $codes ) ) );
$check( 'کدها با پیشوند کمپین و حروف بزرگ نمایش داده می‌شوند', 0 === strpos( $codes[0], 'HOME-' ), $codes[0] );
$first = new WC_Coupon( $res['coupons'][0]['id'] );
$check( 'کد در ووکامرس با حروف کوچک ذخیره می‌شود (استاندارد ووکامرس)',
	$first->get_code() === strtolower( $codes[0] ),
	$first->get_code()
);

$exp   = $first->get_date_expires();
$check( 'تاریخ انقضا ۷ روز بعد تنظیم شده',
	$exp && abs( $exp->getTimestamp() - ( time() + 7 * DAY_IN_SECONDS ) ) < 2 * DAY_IN_SECONDS,
	$exp ? $exp->format( 'Y-m-d' ) : 'null'
);

echo "\n=== ۴) اعمال واقعی تخفیف در سبد خرید ===\n";

$id_clothing = WBCC_Campaigns::save( array(
	'name'         => 'تخفیف پوشاک (۳۰ تا ۴۰٪)',
	'categories'   => array( $clothing ),
	'type'         => 'percent',
	'min'          => 30,
	'max'          => 40,
	'step'         => 10,
	'prefix'       => 'CLTH',
	'expires_days' => 3,
	'usage_limit'  => 0,
	'enabled'      => 1,
) );
$campaign_clothing = WBCC_Campaigns::get( $id_clothing );
$res_clothing      = WBCC_Generator::generate( $campaign_clothing, 1, 'manual' );
$check( 'کد تخفیف پوشاک ساخته شد', ! empty( $res_clothing['ok'] ), $res_clothing['message'] );

$code_home     = $codes[0];
$amount_home   = (float) $res['coupons'][0]['amount'];
$code_clothing = $res_clothing['coupons'][0]['code'];

$product_home     = $product_in( 'home-appliance' );
$product_clothing = $product_in( 'clothing' );
$check( 'محصول نمونه در هر دو دسته موجود است', $product_home && $product_clothing, "home={$product_home} clothing={$product_clothing}" );

if ( function_exists( 'wc_load_cart' ) ) {
	wc_load_cart();
	WC()->cart->empty_cart();
	WC()->cart->add_to_cart( $product_home, 1 );
	WC()->cart->calculate_totals();

	$subtotal = (float) WC()->cart->get_subtotal();
	WC()->cart->apply_coupon( $code_home );
	WC()->cart->calculate_totals();
	$discount = (float) WC()->cart->get_discount_total();
	$expected = round( $subtotal * $amount_home / 100, wc_get_price_decimals() );

	$check( 'کد دسته «لوازم خانگی» روی محصول همان دسته اعمال شد',
		abs( $discount - $expected ) < 1,
		"subtotal={$subtotal} amount={$amount_home}% discount={$discount} expected={$expected}"
	);

	WC()->cart->remove_coupons();
	$applied = WC()->cart->apply_coupon( $code_clothing );
	WC()->cart->calculate_totals();
	$cross = (float) WC()->cart->get_discount_total();
	$check( 'کد دسته «پوشاک» روی محصول لوازم خانگی تخفیف نمی‌دهد', 0.0 === $cross, "discount={$cross} applied=" . var_export( $applied, true ) );

	WC()->cart->empty_cart();
	WC()->cart->remove_coupons();
	WC()->cart->add_to_cart( $product_clothing, 1 );
	WC()->cart->apply_coupon( $code_clothing );
	WC()->cart->calculate_totals();
	$sub2 = (float) WC()->cart->get_subtotal();
	$dis2 = (float) WC()->cart->get_discount_total();
	$amt2 = (float) $res_clothing['coupons'][0]['amount'];
	$check( 'کد دسته «پوشاک» روی محصول پوشاک درست اعمال شد',
		abs( $dis2 - round( $sub2 * $amt2 / 100, wc_get_price_decimals() ) ) < 1,
		"subtotal={$sub2} amount={$amt2}% discount={$dis2}"
	);
	WC()->cart->empty_cart();
	WC()->cart->remove_coupons();
} else {
	echo "  skip — wc_load_cart در دسترس نیست\n";
}

echo "\n=== ۵) ساخت خودکار زمان‌بندی‌شده ===\n";

$id_auto = WBCC_Campaigns::save( array(
	'name'          => 'کمپین خودکار کتاب',
	'categories'    => array( $term( 'books' ) ),
	'type'          => 'percent',
	'min'           => 20,
	'max'           => 25,
	'step'          => 5,
	'prefix'        => 'BOOK',
	'auto_enabled'  => 1,
	'auto_interval' => 'daily',
	'auto_count'    => 3,
	'enabled'       => 1,
) );

$before = WBCC_Generator::list_coupons( array( 'campaign' => $id_auto, 'limit' => 1 ) )['total'];
WBCC_Cron::run();
$after     = WBCC_Generator::list_coupons( array( 'campaign' => $id_auto, 'limit' => 1 ) )['total'];
$auto_camp = WBCC_Campaigns::get( $id_auto );

$check( 'اجرای خودکار ۳ کد ساخت', 3 === $after - $before, "before={$before} after={$after}" );
$check( 'زمان آخرین اجرا ثبت شد', (int) $auto_camp['last_run'] > 0 );

WBCC_Cron::run();
$after2 = WBCC_Generator::list_coupons( array( 'campaign' => $id_auto, 'limit' => 1 ) )['total'];
$check( 'اجرای دوباره قبل از سررسید دوره، کد جدید نمی‌سازد', $after2 === $after, "after2={$after2}" );

$log = get_option( 'wbcc_log', array() );
$check( 'گزارش اجرای خودکار ثبت شد', is_array( $log ) && ! empty( $log ) );

echo "\n=== ۶) دریافت کد توسط مشتری (محدود به ایمیل) ===\n";

$public = WBCC_Generator::generate( $campaign_clothing, 1, 'public', array( 'email' => 'customer@example.com' ) );
$check( 'کد اختصاصی مشتری ساخته شد', ! empty( $public['ok'] ) );
if ( ! empty( $public['ok'] ) ) {
	$pc = new WC_Coupon( $public['coupons'][0]['id'] );
	$check( 'کد فقط برای ایمیل مشتری معتبر است', array( 'customer@example.com' ) === $pc->get_email_restrictions(), implode( ',', $pc->get_email_restrictions() ) );
	$check( 'منبع ساخت «public» ثبت شد', 'public' === get_post_meta( $public['coupons'][0]['id'], '_wbcc_source', true ) );
}

echo "\n=== ۷) بدون دسته‌بندی، کد ساخته نمی‌شود ===\n";

$empty_campaign = WBCC_Campaigns::defaults();
$empty_res      = WBCC_Generator::generate( $empty_campaign, 1, 'manual' );
$check( 'کمپین بدون دسته‌بندی خطا می‌دهد', empty( $empty_res['ok'] ), $empty_res['message'] );

echo "\n=== ۸) فهرست و پاک‌سازی کدهای منقضی ===\n";

$all_list = WBCC_Generator::list_coupons( array( 'limit' => 200 ) );
$check( 'فهرست کدهای افزونه پر است', $all_list['total'] >= 16, 'total=' . $all_list['total'] );

$expired_campaign             = $campaign_clothing;
$expired_campaign['id']       = $id_clothing;
$expired_res                  = WBCC_Generator::generate( $expired_campaign, 1, 'manual' );
$expired_id                   = $expired_res['coupons'][0]['id'];
$expired_coupon               = new WC_Coupon( $expired_id );
$expired_coupon->set_date_expires( time() - 40 * DAY_IN_SECONDS );
$expired_coupon->save();

$deleted = WBCC_Generator::cleanup_expired( 7 );
$check( 'کد منقضی‌شده پاک شد', $deleted >= 1 && ! get_post( $expired_id ), "deleted={$deleted}" );

$manual_coupon = wp_insert_post( array( 'post_type' => 'shop_coupon', 'post_title' => 'NOT-OURS-1', 'post_status' => 'publish' ) );
$check( 'کد تخفیف دستی کاربر توسط افزونه حذف نمی‌شود', false === WBCC_Generator::delete_coupon( $manual_coupon ) && get_post( $manual_coupon ) );
wp_delete_post( $manual_coupon, true );

echo "\n=== ۹) تاریخ شمسی ===\n";
$ts = mktime( 12, 0, 0, 8, 17, 2026 ); // ۲۶ مرداد ۱۴۰۵
$check( 'تبدیل تاریخ میلادی به شمسی', '1405/05/26' === WBCC_Date::format( $ts ), WBCC_Date::format( $ts ) );
$check( 'ارقام فارسی', '۱۴۰۵' === WBCC_Date::fa_digits( '1405' ) );

echo "\n────────────────────────────\n";
echo "نتیجه: {$pass} موفق / {$fail} ناموفق\n";
echo "────────────────────────────\n";

if ( $fail > 0 ) {
	exit( 1 );
}
