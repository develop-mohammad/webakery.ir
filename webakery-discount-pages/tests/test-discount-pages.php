<?php
/**
 * تست عملکردی افزونه «صفحه‌های تخفیف هوشمند» روی یک نصب واقعی وردپرس + ووکامرس.
 *
 * اجرا:
 *   wp eval-file wp-content/plugins/webakery-discount-pages/tests/test-discount-pages.php
 *
 * پیش‌نیاز: ووکامرس فعال. اگر لایسنس فعال نیست، دوره آزمایشی ۷ روزه باید
 * هنوز تمام نشده باشد (این تست‌ها به موتور تشخیص نیاز دارند).
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
$check( 'موتور تشخیص مجاز به کار است (لایسنس/دوره آزمایشی)', WDP_Plugin::licensed(), 'اگر این FAIL شد یعنی دوره آزمایشی تمام شده؛ بقیه تست‌ها هم شکست می‌خورند' );

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

echo "\n=== پاک‌سازی داده‌های تست ===\n";
wp_delete_post( $product->get_id(), true );
wp_delete_post( $product2->get_id(), true );
if ( $term_low ) {
	wp_delete_term( $term_low, WDP_Taxonomy::TAXONOMY );
}
if ( $term_high ) {
	wp_delete_term( $term_high, WDP_Taxonomy::TAXONOMY );
}
if ( $term_fixed ) {
	wp_delete_term( $term_fixed, WDP_Taxonomy::TAXONOMY );
}
echo "  انجام شد.\n";

echo "\n────────────────────────────\n";
echo "نتیجه: {$pass} موفق / {$fail} ناموفق\n";
echo "────────────────────────────\n";

if ( $fail > 0 ) {
	exit( 1 );
}
