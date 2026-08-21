<?php
/**
 * تست عملکردی افزونه «صفحه‌های تخفیف هوشمند» روی یک نصب واقعی وردپرس + ووکامرس.
 *
 * اجرا:
 *   wp eval-file wp-content/plugins/webakery-discount-pages/tests/test-discount-pages.php
 *
 * پیش‌نیاز: ووکامرس فعال.
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

$make_product = function ( $regular, $sale = '' ) {
	$product = new WC_Product_Simple();
	$product->set_name( 'محصول تست صفحه تخفیف' );
	$product->set_regular_price( (string) $regular );
	if ( '' !== $sale ) {
		$product->set_sale_price( (string) $sale );
	}
	$product->set_status( 'publish' );
	$product->save();
	return $product;
};

$assigned_terms = function ( $product_id ) {
	$terms = wp_get_object_terms( $product_id, WDP_Taxonomy::TAXONOMY, array( 'fields' => 'ids' ) );
	return is_wp_error( $terms ) ? array() : array_map( 'intval', $terms );
};

echo "\n=== ۰) پیش‌نیازها ===\n";
$check( 'تکسونومی صفحه تخفیف ثبت شده', taxonomy_exists( WDP_Taxonomy::TAXONOMY ) );
$check( 'موتور تشخیص در دسترس است', WDP_Plugin::licensed() );

echo "\n=== ۱) ساخت دو صفحه تخفیف (۱۰ تا ۲۰٪ و ۴۰ تا ۶۰٪) ===\n";

$term_low = wp_insert_term( 'تست ۱۰ تا ۲۰ درصد', WDP_Taxonomy::TAXONOMY );
$term_low = is_wp_error( $term_low ) ? 0 : (int) $term_low['term_id'];
update_term_meta( $term_low, '_wdp_type', 'percent' );
update_term_meta( $term_low, '_wdp_min', 10 );
update_term_meta( $term_low, '_wdp_max', 20 );
update_term_meta( $term_low, '_wdp_priority', 10 );

$term_high = wp_insert_term( 'تست ۴۰ تا ۶۰ درصد', WDP_Taxonomy::TAXONOMY );
$term_high = is_wp_error( $term_high ) ? 0 : (int) $term_high['term_id'];
update_term_meta( $term_high, '_wdp_type', 'percent' );
update_term_meta( $term_high, '_wdp_min', 40 );
update_term_meta( $term_high, '_wdp_max', 60 );
update_term_meta( $term_high, '_wdp_priority', 10 );

$check( 'دو صفحه تخفیف ساخته شدند', $term_low && $term_high, "low={$term_low} high={$term_high}" );

echo "\n=== ۲) محصول با ۲۰٪ تخفیف باید در صفحه اول قرار بگیرد ===\n";

$product = $make_product( 100000, 80000 ); // 20% تخفیف
WDP_Assigner::assign( $product->get_id() );

$terms = $assigned_terms( $product->get_id() );
$check( 'محصول با ۲۰٪ تخفیف به صفحه «۱۰ تا ۲۰٪» رفت', array( $term_low ) === $terms, 'terms=' . implode( ',', $terms ) );

$percent = get_post_meta( $product->get_id(), WDP_Assigner::META_PERCENT, true );
$check( 'درصد تخفیف محصول در متا ذخیره شد', 20.0 === (float) $percent, "percent={$percent}" );

echo "\n=== ۳) با تغییر تخفیف محصول به ۵۰٪، خودکار به صفحه دوم منتقل شود ===\n";

$product->set_sale_price( '50000' ); // 50% تخفیف
$product->save();
WDP_Assigner::assign( $product->get_id() );

$terms2 = $assigned_terms( $product->get_id() );
$check( 'محصول از صفحه اول خارج و به صفحه دوم («۴۰ تا ۶۰٪») منتقل شد', array( $term_high ) === $terms2, 'terms=' . implode( ',', $terms2 ) );

echo "\n=== ۴) با پایان تخفیف، محصول از همه صفحه‌ها خارج شود ===\n";

$product->set_sale_price( '' );
$product->save();
WDP_Assigner::assign( $product->get_id() );

$terms3 = $assigned_terms( $product->get_id() );
$check( 'محصول بدون تخفیف در هیچ صفحه‌ای نیست', array() === $terms3, 'terms=' . implode( ',', $terms3 ) );
$check( 'متای درصد تخفیف پاک شد', '' === get_post_meta( $product->get_id(), WDP_Assigner::META_PERCENT, true ) );

echo "\n=== ۵) صفحه تخفیف با مبلغ ثابت ===\n";

$term_fixed = wp_insert_term( 'تست ۱۰۰۰۰ تا ۳۰۰۰۰ تومان', WDP_Taxonomy::TAXONOMY );
$term_fixed = is_wp_error( $term_fixed ) ? 0 : (int) $term_fixed['term_id'];
update_term_meta( $term_fixed, '_wdp_type', 'fixed' );
update_term_meta( $term_fixed, '_wdp_min', 10000 );
update_term_meta( $term_fixed, '_wdp_max', 30000 );
update_term_meta( $term_fixed, '_wdp_priority', 10 );

$product2 = $make_product( 100000, 80000 ); // 20,000 تومان تخفیف مبلغی
WDP_Assigner::assign( $product2->get_id() );
$terms4 = $assigned_terms( $product2->get_id() );
$check( 'محصول با ۲۰,۰۰۰ تومان تخفیف به صفحه مبلغ ثابت رفت', array( $term_fixed ) === $terms4, 'terms=' . implode( ',', $terms4 ) );

echo "\n=== ۶) بازبینی خودکار (recalculate_all) ===\n";

$count = WDP_Assigner::recalculate_all();
$check( 'بازبینی همه محصولات بدون خطا اجرا شد', $count >= 1, "count={$count}" );

echo "\n=== ۷) صفحه تخفیف محدود به یک دسته‌بندی محصول ===\n";

$cat_home = wp_insert_term( 'تست لوازم خانگی', 'product_cat' );
$cat_home = is_wp_error( $cat_home ) ? 0 : (int) $cat_home['term_id'];
$cat_cloth = wp_insert_term( 'تست پوشاک', 'product_cat' );
$cat_cloth = is_wp_error( $cat_cloth ) ? 0 : (int) $cat_cloth['term_id'];

$term_home_only = wp_insert_term( 'تست ۲۰ تا ۳۰٪ فقط لوازم خانگی', WDP_Taxonomy::TAXONOMY );
$term_home_only = is_wp_error( $term_home_only ) ? 0 : (int) $term_home_only['term_id'];
update_term_meta( $term_home_only, '_wdp_type', 'percent' );
update_term_meta( $term_home_only, '_wdp_min', 20 );
update_term_meta( $term_home_only, '_wdp_max', 30 );
update_term_meta( $term_home_only, '_wdp_priority', 10 );
update_term_meta( $term_home_only, '_wdp_categories', array( $cat_home ) );

$term_general = wp_insert_term( 'تست ۲۰ تا ۳۰٪ عمومی', WDP_Taxonomy::TAXONOMY );
$term_general = is_wp_error( $term_general ) ? 0 : (int) $term_general['term_id'];
update_term_meta( $term_general, '_wdp_type', 'percent' );
update_term_meta( $term_general, '_wdp_min', 20 );
update_term_meta( $term_general, '_wdp_max', 30 );
update_term_meta( $term_general, '_wdp_priority', 10 );
update_term_meta( $term_general, '_wdp_categories', array() );

$product3 = $make_product( 100000, 75000 ); // 25% تخفیف
wp_set_object_terms( $product3->get_id(), array( $cat_home ), 'product_cat' );
WDP_Assigner::assign( $product3->get_id() );

$terms5 = $assigned_terms( $product3->get_id() );
$check(
	'محصول دسته «لوازم خانگی» با ۲۵٪ تخفیف به صفحه اختصاصی همان دسته می‌رود (نه صفحه عمومی)',
	array( $term_home_only ) === $terms5,
	'terms=' . implode( ',', $terms5 )
);

echo "\n=== ۸) تغییر دسته‌بندی محصول به‌تنهایی هم صفحه تخفیف را عوض می‌کند ===\n";

// هیچ تغییری در تخفیف نمی‌دهیم؛ فقط دسته‌بندی محصول را عوض می‌کنیم.
wp_set_object_terms( $product3->get_id(), array( $cat_cloth ), 'product_cat' );

$terms6 = $assigned_terms( $product3->get_id() );
$check(
	'با تغییر دسته‌بندی محصول به «پوشاک» (بدون صفحه اختصاصی)، محصول خودکار به صفحه عمومی منتقل می‌شود',
	array( $term_general ) === $terms6,
	'terms=' . implode( ',', $terms6 )
);

echo "\n=== ۹) اعمال گروهی تخفیف روی یک دسته‌بندی (تابع معکوس) ===\n";

$cat_bulk = wp_insert_term( 'تست اعمال گروهی', 'product_cat' );
$cat_bulk = is_wp_error( $cat_bulk ) ? 0 : (int) $cat_bulk['term_id'];

$bulk_a = $make_product( 200000 ); // بدون تخفیف
$bulk_b = $make_product( 500000 ); // بدون تخفیف
wp_set_object_terms( $bulk_a->get_id(), array( $cat_bulk ), 'product_cat' );
wp_set_object_terms( $bulk_b->get_id(), array( $cat_bulk ), 'product_cat' );

$applied = WDP_Bulk::apply( array( $cat_bulk ), false, 'percent', 20, false );
$check( 'اعمال گروهی روی ۲ محصول دسته انجام شد', 2 === $applied, "applied={$applied}" );

$bulk_a_fresh = wc_get_product( $bulk_a->get_id() );
$bulk_b_fresh = wc_get_product( $bulk_b->get_id() );
$check(
	'قیمت فروش محصول اول درست محاسبه شد (۲۰٪ از ۲۰۰,۰۰۰ = ۱۶۰,۰۰۰)',
	160000.0 === (float) $bulk_a_fresh->get_sale_price(),
	'sale=' . $bulk_a_fresh->get_sale_price()
);
$check(
	'قیمت فروش محصول دوم درست محاسبه شد (۲۰٪ از ۵۰۰,۰۰۰ = ۴۰۰,۰۰۰)',
	400000.0 === (float) $bulk_b_fresh->get_sale_price(),
	'sale=' . $bulk_b_fresh->get_sale_price()
);
$check( 'بعد از اعمال گروهی، محصول اول خودکار در صفحه «۱۰ تا ۲۰٪» قرار گرفت', array( $term_low ) === $assigned_terms( $bulk_a->get_id() ) );

$applied_again = WDP_Bulk::apply( array( $cat_bulk ), false, 'percent', 50, false );
$check( 'بدون تیک «بازنویسی»، روی محصولات از‌قبل‌تخفیف‌دار دوباره اعمال نمی‌شود', 0 === $applied_again, "applied_again={$applied_again}" );

$applied_overwrite = WDP_Bulk::apply( array( $cat_bulk ), false, 'percent', 50, true );
$check( 'با تیک «بازنویسی»، تخفیف جدید جایگزین می‌شود', 2 === $applied_overwrite, "applied_overwrite={$applied_overwrite}" );
$bulk_a_fresh2 = wc_get_product( $bulk_a->get_id() );
$check( 'قیمت فروش با تخفیف جدید ۵۰٪ به‌روزرسانی شد (۱۰۰,۰۰۰)', 100000.0 === (float) $bulk_a_fresh2->get_sale_price(), 'sale=' . $bulk_a_fresh2->get_sale_price() );

$reverted = WDP_Bulk::revert( array( $cat_bulk ), false );
$check( 'حذف گروهی تخفیف، هر دو محصول را برگرداند', 2 === $reverted, "reverted={$reverted}" );
$bulk_a_fresh3 = wc_get_product( $bulk_a->get_id() );
$check( 'بعد از حذف، قیمت فروش خالی است', '' === $bulk_a_fresh3->get_sale_price() );
$check( 'بعد از حذف، محصول دیگر در هیچ صفحه تخفیفی نیست', array() === $assigned_terms( $bulk_a->get_id() ) );

echo "\n=== پاک‌سازی داده‌های تست ===\n";
wp_delete_post( $product->get_id(), true );
wp_delete_post( $product2->get_id(), true );
wp_delete_post( $product3->get_id(), true );
wp_delete_post( $bulk_a->get_id(), true );
wp_delete_post( $bulk_b->get_id(), true );
foreach ( array( $term_low, $term_high, $term_fixed, $term_home_only, $term_general ) as $t ) {
	if ( $t ) {
		wp_delete_term( $t, WDP_Taxonomy::TAXONOMY );
	}
}
foreach ( array( $cat_home, $cat_cloth, $cat_bulk ) as $c ) {
	if ( $c ) {
		wp_delete_term( $c, 'product_cat' );
	}
}
echo "  انجام شد.\n";

echo "\n────────────────────────────\n";
echo "نتیجه: {$pass} موفق / {$fail} ناموفق\n";
echo "────────────────────────────\n";

if ( $fail > 0 ) {
	exit( 1 );
}
