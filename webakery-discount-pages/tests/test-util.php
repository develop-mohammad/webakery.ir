<?php
/**
 * تست مستقل منطق محاسباتی WDP_Util — بدون نیاز به وردپرس/ووکامرس.
 *
 * اجرا:
 *   php tests/test-util.php
 */

define( 'ABSPATH', __DIR__ . '/' ); // فقط برای عبور از گارد defined('ABSPATH') در فایل کلاس.
require_once __DIR__ . '/../includes/class-wdp-util.php';

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

echo "\n=== ۱) تبدیل ارقام و اعداد ===\n";
$check( 'ارقام فارسی به لاتین تبدیل می‌شود', '1405' === WDP_Util::digits( '۱۴۰۵' ), WDP_Util::digits( '۱۴۰۵' ) );
$check( 'ارقام عربی به لاتین تبدیل می‌شود', '20' === WDP_Util::digits( '٢٠' ), WDP_Util::digits( '٢٠' ) );
$check( 'to_number روی ورودی فارسی درست کار می‌کند', 30.5 === WDP_Util::to_number( '۳۰.۵' ), (string) WDP_Util::to_number( '۳۰.۵' ) );
$check( 'to_number روی ورودی خالی صفر برمی‌گرداند', 0.0 === WDP_Util::to_number( '' ) );
$check( 'trim_zeros عدد صحیح را بدون اعشار نشان می‌دهد', '20' === WDP_Util::trim_zeros( 20.0 ) );
$check( 'trim_zeros اعشار غیرصفر را نگه می‌دارد', '20.5' === WDP_Util::trim_zeros( 20.5 ) );
$check( 'fa_digits عدد لاتین را فارسی می‌کند', '۱۴۰۵' === WDP_Util::fa_digits( '1405' ) );

echo "\n=== ۲) محاسبه تخفیف از قیمت اصلی و فروش ===\n";
$d = WDP_Util::compute_discount( 100000, 80000 );
$check( 'تخفیف ۲۰٪ درست محاسبه می‌شود', 20.0 === $d['percent'] && 20000.0 === $d['fixed'], wp_style_var_export( $d ) );

$d2 = WDP_Util::compute_discount( 200000, 100000 );
$check( 'تخفیف ۵۰٪ درست محاسبه می‌شود', 50.0 === $d2['percent'] && 100000.0 === $d2['fixed'] );

$check( 'بدون قیمت فروش، تخفیفی محاسبه نمی‌شود', null === WDP_Util::compute_discount( 100000, 0 ) );
$check( 'وقتی فروش >= اصلی، تخفیفی محاسبه نمی‌شود', null === WDP_Util::compute_discount( 100000, 100000 ) );
$check( 'وقتی قیمت اصلی صفر است، تخفیفی محاسبه نمی‌شود', null === WDP_Util::compute_discount( 0, 0 ) );

echo "\n=== ۳) تطبیق با صفحه تخفیف درست (برای جابه‌جایی خودکار محصول) ===\n";

$rules = array(
	array( 'term_id' => 1, 'type' => 'percent', 'min' => 10, 'max' => 20, 'priority' => 10 ),
	array( 'term_id' => 2, 'type' => 'percent', 'min' => 21, 'max' => 40, 'priority' => 10 ),
	array( 'term_id' => 3, 'type' => 'percent', 'min' => 41, 'max' => 60, 'priority' => 10 ),
	array( 'term_id' => 4, 'type' => 'fixed',   'min' => 50000, 'max' => 150000, 'priority' => 10 ),
);

$m1 = WDP_Util::find_best_match( $rules, array( 'percent' => 20.0, 'fixed' => 20000 ) );
$check( 'محصول با ۲۰٪ تخفیف در صفحه «۱۰ تا ۲۰٪» قرار می‌گیرد', 1 === $m1, 'matched=' . var_export( $m1, true ) );

$m2 = WDP_Util::find_best_match( $rules, array( 'percent' => 50.0, 'fixed' => 100000 ) );
// چون هم بازه درصدی و هم بازه مبلغ ثابت منطبق است، باریک‌ترین بازه (۴۱ تا ۶۰ = عرض ۱۹) نسبت به (۵۰هزار تا ۱۵۰هزار = عرض ۱۰۰هزار) برنده می‌شود.
$check( 'با تغییر تخفیف محصول از ۲۰٪ به ۵۰٪، صفحه به ۳ (۴۱ تا ۶۰٪) تغییر می‌کند', 3 === $m2, 'matched=' . var_export( $m2, true ) );

$m3 = WDP_Util::find_best_match( $rules, array( 'percent' => 5.0, 'fixed' => 1000 ) );
$check( 'خارج از همه بازه‌ها، هیچ صفحه‌ای منطبق نمی‌شود', null === $m3 );

$rules_overlap = array(
	array( 'term_id' => 10, 'type' => 'percent', 'min' => 0,  'max' => 100, 'priority' => 5 ),
	array( 'term_id' => 20, 'type' => 'percent', 'min' => 40, 'max' => 50, 'priority' => 5 ),
	array( 'term_id' => 30, 'type' => 'percent', 'min' => 45, 'max' => 55, 'priority' => 20 ),
);
$m4 = WDP_Util::find_best_match( $rules_overlap, array( 'percent' => 47.0, 'fixed' => 0 ) );
$check( 'در هم‌پوشانی بازه‌ها، اولویت بیشتر (priority) برنده می‌شود', 30 === $m4, 'matched=' . var_export( $m4, true ) );

$rules_same_priority = array(
	array( 'term_id' => 100, 'type' => 'percent', 'min' => 0,  'max' => 100, 'priority' => 10 ),
	array( 'term_id' => 200, 'type' => 'percent', 'min' => 40, 'max' => 50, 'priority' => 10 ),
);
$m5 = WDP_Util::find_best_match( $rules_same_priority, array( 'percent' => 45.0, 'fixed' => 0 ) );
$check( 'با اولویت برابر، باریک‌ترین بازه برنده می‌شود', 200 === $m5, 'matched=' . var_export( $m5, true ) );

$m6 = WDP_Util::find_best_match( array(), array( 'percent' => 20, 'fixed' => 20000 ) );
$check( 'بدون هیچ قانونی، نتیجه null است', null === $m6 );

echo "\n=== ۴) برچسب بازه ===\n";
$check( 'برچسب بازه درصدی', '۲۰ تا ۳۰ درصد' === WDP_Util::fa_digits( WDP_Util::range_label( 'percent', 20, 30 ) ) );
$check( 'برچسب بازه مبلغ ثابت', '۵۰۰۰۰ تا ۱۰۰۰۰۰ تومان' === WDP_Util::fa_digits( WDP_Util::range_label( 'fixed', 50000, 100000 ) ) );
$check( 'برچسب بازه با مقدار ثابت (بدون «تا»)', '۲۰ درصد' === WDP_Util::fa_digits( WDP_Util::range_label( 'percent', 20, 20 ) ) );

echo "\n────────────────────────────\n";
echo "نتیجه: {$pass} موفق / {$fail} ناموفق\n";
echo "────────────────────────────\n";

if ( $fail > 0 ) {
	exit( 1 );
}

/** کمک‌کننده کوچک برای نمایش دیباگ بدون وابستگی به wp_json_encode */
function wp_style_var_export( $v ) {
	return str_replace( "\n", ' ', var_export( $v, true ) );
}
