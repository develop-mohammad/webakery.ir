<?php
/**
 * تست‌های CLI فرم اینماد — بدون وردپرس و بدون درگاه واقعی.
 * اجرا: php enamad-order/tests/run.php
 */

$ROOT = dirname( __DIR__ );
require_once $ROOT . '/includes/helpers.php';
require_once $ROOT . '/includes/Database.php';
require_once $ROOT . '/includes/Invoice.php';

if ( ! defined( 'EO_SERVICE_TITLE' ) ) {
	define( 'EO_SERVICE_TITLE', 'خدمات دریافت نماد اعتماد الکترونیکی (اینماد)' );
}
if ( ! defined( 'EO_SUPPORT_EMAIL' ) ) {
	define( 'EO_SUPPORT_EMAIL', 'info@webakery.ir' );
}
if ( ! defined( 'EO_SUPPORT_TELEGRAM' ) ) {
	define( 'EO_SUPPORT_TELEGRAM', '@webakery_support' );
}

$failed = 0;
$passed = 0;

function assert_true( $cond, string $msg ): void {
	global $failed, $passed;
	if ( $cond ) {
		$passed++;
		echo "  OK  $msg\n";
	} else {
		$failed++;
		echo "  FAIL $msg\n";
	}
}

function assert_eq( $expected, $actual, string $msg ): void {
	assert_true( $expected === $actual, $msg . ' (expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ')' );
}

function capture_include( string $file, string $method = 'GET', array $post = [] ): string {
	$tmp  = tempnam( sys_get_temp_dir(), 'eo' );
	$code = '<?php
$_SERVER["REQUEST_METHOD"] = ' . var_export( $method, true ) . ';
$_SERVER["HTTPS"] = "on";
$_SERVER["HTTP_HOST"] = "webakery.ir";
$_SERVER["SCRIPT_NAME"] = "/enamad-order/" . basename(' . var_export( $file, true ) . ');
$_GET = [];
$_POST = ' . var_export( $post, true ) . ';
include ' . var_export( $file, true ) . ';
';
	file_put_contents( $tmp, $code );
	$out = [];
	exec( 'php ' . escapeshellarg( $tmp ) . ' 2>&1', $out );
	@unlink( $tmp );
	return implode( "\n", $out );
}

echo "== helpers ==\n";
assert_eq( '09123456789', eo_normalize_mobile( '۰۹۱۲۳۴۵۶۷۸۹' ), 'موبایل ارقام فارسی' );
assert_eq( '09123456789', eo_normalize_mobile( '+989123456789' ), 'موبایل با +98' );
assert_eq( '09123456789', eo_normalize_mobile( '9123456789' ), 'موبایل بدون صفر' );
assert_eq( '', eo_normalize_mobile( '02112345678' ), 'تلفن ثابت به‌عنوان موبایل رد شود' );
assert_eq( '02112345678', eo_normalize_phone( '۰۲۱-۱۲۳۴۵۶۷۸' ), 'تلفن ثابت با خط تیره و ارقام فارسی' );
assert_eq( '1234567890', eo_normalize_postal( '۱۲۳۴۵۶۷۸۹۰' ), 'کد پستی ۱۰ رقمی فارسی' );
assert_eq( '', eo_normalize_postal( '12345' ), 'کد پستی کوتاه رد شود' );
assert_eq( 'shop.ir', eo_normalize_website( 'https://shop.ir/' ), 'وب‌سایت بدون پروتکل' );
assert_eq( 'https://shop.ir', eo_website_url( 'shop.ir' ), 'URL کامل وب‌سایت' );
assert_eq( '2,750,000', eo_toman( 27500000 ), '۲ میلیون و ۷۵۰ هزار تومان از ریال' );
assert_eq( 22500000, eo_price_remaining_rial( 27500000 ), 'مانده ۲٫۲۵۰ میلیون تومان' );
assert_eq( 'وب‌بیکری', eo_brand(), 'برند پیش‌فرض وب‌بیکری' );
assert_true( eo_strlen( 'علی' ) === 3, 'طول رشته فارسی' );

$code = eo_generate_order_code();
assert_true( (bool) preg_match( '/^EN-\d{4}-[A-Z0-9]{4}$/', $code ), 'قالب کد سفارش: ' . $code );

$jalali = eo_jalali_now_str();
assert_true( strpos( $jalali, 'ساعت' ) !== false, 'تاریخ شمسی شامل ساعت است: ' . $jalali );

echo "\n== invoices ==\n";
$sample = [
	'order_code'     => 'EN-2508-TEST',
	'full_name'      => 'علی محمدی',
	'business_name'  => 'فروشگاه نمونه',
	'mobile'         => '09123456789',
	'landline'       => '02112345678',
	'email'          => 'ali@example.com',
	'postal_code'    => '1234567890',
	'website'        => 'example.ir',
	'tax_code'       => 'TAX-998877',
	'access_type'    => 'both',
	'access_note'    => 'هاست سی‌پنل',
	'amount'         => 27500000,
	'total_amount'   => 50000000,
	'remaining'      => 22500000,
	'status'         => 'paid',
	'created_at'     => '2026-08-20 12:00:00',
	'paid_at_jalali' => '۲۹ مرداد ۱۴۰۵ - ساعت ۱۵:۳۰',
];

$customer = eo_customer_invoice_html( $sample );
assert_true( strpos( $customer, 'EN-2508-TEST' ) !== false, 'فاکتور مشتری شامل کد سفارش' );
assert_true( strpos( $customer, '2,750,000' ) !== false, 'فاکتور مشتری شامل پیش‌پرداخت' );
assert_true( strpos( $customer, '5,000,000' ) !== false, 'فاکتور مشتری شامل هزینه کل' );
assert_true( strpos( $customer, '2,250,000' ) !== false, 'فاکتور مشتری شامل مانده' );
assert_true( strpos( $customer, 'علی محمدی' ) !== false, 'فاکتور مشتری شامل نام' );
assert_true( strpos( $customer, '<script' ) === false, 'فاکتور مشتری بدون اسکریپت تزریقی' );

$internal = eo_internal_invoice_html( $sample );
foreach ( [ '1234567890', '02112345678', '09123456789', 'ali@example.com', 'https://example.ir', 'TAX-998877', 'ایمیل info و هاست هر دو' ] as $need ) {
	assert_true( strpos( $internal, $need ) !== false, 'فاکتور جامع شامل «' . $need . '»' );
}

$tg = eo_internal_invoice_text( $sample );
assert_true( strpos( $tg, 'کد پستی' ) !== false && strpos( $tg, 'TAX-998877' ) !== false, 'متن تلگرام شامل چک‌لیست اینماد' );

$escaped = eo_customer_invoice_html( array_merge( $sample, [ 'full_name' => '<script>alert(1)</script>' ] ) );
assert_true( strpos( $escaped, '<script>alert(1)</script>' ) === false, 'XSS در نام متقاضی escape شود' );
assert_true( strpos( $escaped, '&lt;script&gt;' ) !== false, 'XSS به entity تبدیل شود' );

echo "\n== database ==\n";
$tmp = sys_get_temp_dir() . '/eo-orders-test-' . uniqid() . '.json';
EO_Database::set_file( $tmp );
EO_Database::insert( $sample + [ 'track_id' => 'trk-1', 'status' => 'pending' ] );
$found = EO_Database::find_by_code( 'EN-2508-TEST' );
assert_true( is_array( $found ) && $found['email'] === 'ali@example.com', 'یافتن سفارش با کد' );
assert_true( EO_Database::find_by_track( 'trk-1' ) !== null, 'یافتن سفارش با trackId' );
EO_Database::update_by_track( 'trk-1', [ 'status' => 'paid' ] );
$paid = EO_Database::find_by_track( 'trk-1' );
assert_eq( 'paid', $paid['status'] ?? '', 'به‌روزرسانی وضعیت پرداخت' );
assert_eq( 1, EO_Database::total(), 'تعداد کل سفارش‌ها' );
assert_eq( 1, EO_Database::count_by_status( 'paid' ), 'شمارش پرداخت‌شده' );
@unlink( $tmp );
EO_Database::reset();

echo "\n== wizard HTML ==\n";
$html = capture_include( $ROOT . '/index.php' );
assert_true( strpos( $html, 'مرحله' ) !== false, 'صفحه شامل متن مرحله است' );
assert_true( substr_count( $html, 'class="step' ) >= 5, 'حداقل ۵ مرحله در فرم' );
assert_true( strpos( $html, 'name="postal_code"' ) !== false, 'فیلد کد پستی' );
assert_true( strpos( $html, 'name="landline"' ) !== false, 'فیلد تلفن ثابت' );
assert_true( strpos( $html, 'name="mobile"' ) !== false, 'فیلد موبایل' );
assert_true( strpos( $html, 'name="email"' ) !== false, 'فیلد ایمیل' );
assert_true( strpos( $html, 'name="website"' ) !== false, 'فیلد وب‌سایت' );
assert_true( strpos( $html, 'name="tax_code"' ) !== false, 'فیلد کد رهگیری مالیاتی' );
assert_true( strpos( $html, 'name="access_type"' ) !== false, 'فیلد نوع دسترسی' );
assert_true( strpos( $html, 'assets/wizard.js' ) !== false, 'اسکریپت ویزارد لود می‌شود' );
assert_true( strpos( $html, 'og:title' ) !== false, 'متای اشتراک‌گذاری OG' );
assert_true( strpos( $html, 'https://webakery.ir/enamad-order/' ) !== false, 'og:url یا کالبک به مسیر فرم اشاره دارد' );
assert_true( preg_match( '/<script src="assets\/wizard\.js"><\/script>\s*<\/body>/', $html ) === 1, 'اسکریپت قبل از </body> است' );
assert_true( strpos( $html, '2,750,000' ) !== false, 'قیمت پیش‌پرداخت ۲٫۷۵۰ میلیون در هدر' );
assert_true( strpos( $html, 'پیش‌پرداخت' ) !== false, 'برچسب پیش‌پرداخت در صفحه' );
assert_true( strpos( $html, 'وب‌بیکری' ) !== false, 'نام برند وب‌بیکری در صفحه' );
assert_true( strpos( $html, 'وب‌آکری' ) === false, 'نام اشتباه وب‌آکری در صفحه نباشد' );

echo "\n== validation POST ==\n";
$err_html = capture_include(
	$ROOT . '/index.php',
	'POST',
	[
		'eo_submit'     => '1',
		'full_name'     => 'ا',
		'business_name' => '',
		'mobile'        => '123',
		'landline'      => 'x',
		'email'         => 'not-an-email',
		'postal_code'   => '12',
		'website'       => '',
		'tax_code'      => '',
		'access_type'   => '',
	]
);
assert_true( strpos( $err_html, 'has-error' ) !== false, 'خطای اعتبارسنجی در HTML نمایش داده می‌شود' );
assert_true( strpos( $err_html, 'Location: https://gateway.zibal.ir' ) === false, 'با داده نامعتبر به درگاه ریدایرکت نشود' );

echo "\n== admin login page ==\n";
$admin_html = capture_include( $ROOT . '/admin.php' );
assert_true( strpos( $admin_html, 'ورود به پنل' ) !== false, 'صفحه ورود ادمین رندر می‌شود' );
assert_true( strpos( $admin_html, 'فاکتور جامع' ) !== false, 'عنوان فاکتور جامع در پنل' );

$cfg = (string) file_get_contents( $ROOT . '/config.php' );
assert_true( strpos( $cfg, '6a331116da557b902563c32f' ) !== false, 'مرچنت زیبال واقعی در config' );
assert_true( strpos( $cfg, 'fc6fd44c-0e7d-4693-ae42-f7ccc29116d9' ) === false, 'مرچنت UUID زرین‌پال در config نباشد' );

echo "\n------------------------------\n";
echo "$passed passed, $failed failed\n";
exit( $failed > 0 ? 1 : 0 );
