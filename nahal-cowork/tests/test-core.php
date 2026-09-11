<?php
/**
 * تست‌های هسته بدون وردپرس.
 * اجرا: php tests/test-core.php
 */
define( 'ABSPATH', __DIR__ . '/../' );

require_once dirname( __DIR__ ) . '/includes/class-nck-phone.php';
require_once dirname( __DIR__ ) . '/includes/class-nck-jalali.php';
require_once dirname( __DIR__ ) . '/includes/class-nck-holidays.php';
require_once dirname( __DIR__ ) . '/includes/class-nck-shifts.php';
require_once dirname( __DIR__ ) . '/includes/class-nck-contract.php';
require_once dirname( __DIR__ ) . '/includes/class-nck-hall.php';
require_once dirname( __DIR__ ) . '/includes/class-nck-learner.php';
require_once dirname( __DIR__ ) . '/includes/class-nck-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-nck-pay.php';

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

echo "\n=== تلفن ===\n";
$check( '۰۹۱۲۳۴۵۶۷۸۹ نرمال می‌شود', '09123456789' === NCK_Phone::normalize( '۰۹۱۲۳۴۵۶۷۸۹' ) );
$check( '۹۱۲۳۴۵۶۷۸۹ با صفر تکمیل می‌شود', '09123456789' === NCK_Phone::normalize( '9123456789' ) );
$check( '۹۸۱۲ رقم بین‌المللی', '09123456789' === NCK_Phone::normalize( '989123456789' ) );
$check( 'شماره کوتاه رد می‌شود', null === NCK_Phone::normalize( '09123' ) );

echo "\n=== شمسی ===\n";
$g = NCK_Jalali::to_gregorian( 1403, 1, 1 );
$j = NCK_Jalali::to_jalali( $g[0], $g[1], $g[2] );
$check( 'رفت و برگشت نوروز ۱۴۰۳', array( 1403, 1, 1 ) === $j, implode( '/', $j ) );
$check( 'طول فروردین ۳۱', 31 === NCK_Jalali::month_length( 1403, 1 ) );

echo "\n=== شیفت و تعطیل ===\n";
$hours = array(
	'morning_start' => '8:00',
	'morning_end'   => '13:00',
	'evening_start' => '16:30',
	'evening_end'   => '22:00',
	'grace_minutes' => 15,
);
$am = new DateTimeImmutable( '2024-04-10 09:00:00', new DateTimeZone( 'Asia/Tehran' ) );
$pm = new DateTimeImmutable( '2024-04-10 18:00:00', new DateTimeZone( 'Asia/Tehran' ) );
$off = new DateTimeImmutable( '2024-04-10 14:00:00', new DateTimeZone( 'Asia/Tehran' ) );
$check( 'ساعت ۹ صبح است', NCK_Shifts::MORNING === NCK_Shifts::current_slot( $am, $hours ) );
$check( 'ساعت ۱۸ عصر است', NCK_Shifts::EVENING === NCK_Shifts::current_slot( $pm, $hours ) );
$check( 'ساعت ۱۴ خارج از شیفت', '' === NCK_Shifts::current_slot( $off, $hours ) );
$check( 'باقی‌مانده ۲۶ منهای ۳', 23 === NCK_Shifts::remaining( 26, 3 ) );

$nowruz = NCK_Shifts::day_status( 'morning', 1404, 1, 1, array( 'official' => 'عید نوروز', 'official_policy' => 'morning', 'full_close' => array() ) );
$check( 'صبح نوروز بسته است', empty( $nowruz['ok'] ) && 'official_morning' === $nowruz['code'] );
$eve_n = NCK_Shifts::day_status( 'evening', 1404, 1, 1, array( 'official' => 'عید نوروز', 'official_policy' => 'morning', 'full_close' => array() ) );
$check( 'عصر نوروز با سیاست صبح باز است', ! empty( $eve_n['ok'] ) );
$full = NCK_Shifts::day_status( 'evening', 1404, 6, 21, array( 'official' => '', 'official_policy' => 'morning', 'full_close' => array( '1404/06/21' => 'برنامه ویژه' ) ) );
$check( 'تعطیلی کامل هر دو شیفت', empty( $full['ok'] ) );

$gate = NCK_Shifts::can_check_in( array( 'quota' => 26, 'used' => 26, 'has_plan' => true, 'already' => false, 'day' => array( 'ok' => true ), 'member_active' => true ) );
$check( 'سهمیه تمام‌شده رد می‌شود', empty( $gate['ok'] ) );

echo "\n=== قرارداد فضای کار ===\n";
$check( 'مقدمه خوشحالیم است', 0 === strpos( NCK_Contract::default_intro(), 'خوشحالیم' ) );
$check( 'خواستیم در مقدمه نیست', false === strpos( NCK_Contract::default_intro(), 'خواستیم' ) );
$check( 'نهال بدون گیومه است', false === strpos( NCK_Contract::default_intro(), '«نهال»' ) );
$check( 'متن مقدمه', 'خوشحالیم که نهال را برای کار، تمرکز و رشد خود انتخاب کرده‌اید.' === NCK_Contract::default_intro() );
$check( 'جمله غلط قبلی تشخیص داده می‌شود', NCK_Settings::is_legacy_intro( NCK_Contract::legacy_intro() ) );
$check( 'گیومه قبلی هم میراث است', NCK_Settings::is_legacy_intro( 'خوشحالیم که «نهال» را برای کار، تمرکز و رشد خود انتخاب کرده‌اید.' ) );
$check( 'جمله درست میراث نیست', ! NCK_Settings::is_legacy_intro( NCK_Contract::default_intro() ) );
$vars = NCK_Contract::vars_from( array( 'name' => 'سارا محمدی', 'phone' => '09121234567', 'title' => 'ms', 'org' => 'مجموعه فرهنگی نهال' ) );
$filled = NCK_Contract::fill_template( NCK_Contract::default_preamble(), $vars );
$check( 'نام در مقدمه می‌نشیند', false !== strpos( $filled, 'سارا محمدی' ) );
$check( 'عنوان خانم', 'خانم' === $vars['title'] );
$sig = NCK_Contract::validate_signature( 'not-an-image' );
$check( 'امضای نامعتبر رد می‌شود', empty( $sig['ok'] ) );

function nck_test_make_sig( $mime = 'png' ) {
	$im    = imagecreatetruecolor( 200, 80 );
	$white = imagecolorallocate( $im, 255, 255, 255 );
	$ink   = imagecolorallocate( $im, 22, 18, 16 );
	imagefilledrectangle( $im, 0, 0, 199, 79, $white );
	imagesetthickness( $im, 5 );
	imageline( $im, 18, 52, 70, 28, $ink );
	imageline( $im, 70, 28, 120, 58, $ink );
	imageline( $im, 120, 58, 182, 24, $ink );
	ob_start();
	if ( 'jpeg' === $mime ) {
		imagejpeg( $im, null, 90 );
		$prefix = 'data:image/jpeg;base64,';
	} else {
		imagepng( $im );
		$prefix = 'data:image/png;base64,';
	}
	$bin = ob_get_clean();
	imagedestroy( $im );
	return $prefix . base64_encode( $bin );
}

function nck_test_sig_ink_pixels( $data_url ) {
	$comma = strpos( $data_url, ',' );
	$raw   = base64_decode( substr( $data_url, $comma + 1 ), true );
	$im    = imagecreatefromstring( $raw );
	$w     = imagesx( $im );
	$h     = imagesy( $im );
	$clear = 0;
	$ink   = 0;
	for ( $y = 0; $y < $h; $y++ ) {
		for ( $x = 0; $x < $w; $x++ ) {
			$a = ( imagecolorat( $im, $x, $y ) >> 24 ) & 0x7F;
			if ( $a > 110 ) {
				$clear++;
			} else {
				$ink++;
			}
		}
	}
	imagedestroy( $im );
	return array( $clear, $ink );
}

$tiny = imagecreatetruecolor( 12, 12 );
imagefilledrectangle( $tiny, 0, 0, 11, 11, imagecolorallocate( $tiny, 0, 0, 0 ) );
ob_start();
imagepng( $tiny );
$tiny_bin = ob_get_clean();
imagedestroy( $tiny );
$tiny_sig = NCK_Contract::validate_signature( 'data:image/png;base64,' . base64_encode( $tiny_bin ) );
$check( 'امضای خیلی کوچک رد می‌شود', empty( $tiny_sig['ok'] ) );

$png_src = nck_test_make_sig( 'png' );
$png_ok  = NCK_Contract::validate_signature( $png_src );
$check( 'PNG امضا قبول می‌شود', ! empty( $png_ok['ok'] ) );

$jpeg_src = nck_test_make_sig( 'jpeg' );
$jpeg_ok  = NCK_Contract::validate_signature( $jpeg_src );
$check( 'JPEG امضا قبول می‌شود', ! empty( $jpeg_ok['ok'] ) );

$prepared = NCK_Contract::prepare_signature( $png_src );
$check( 'پردازش امضا موفق است', ! empty( $prepared['ok'] ) && ! empty( $prepared['data'] ) );
$check( 'خروجی امضا PNG است', 0 === strpos( $prepared['data'], 'data:image/png;base64,' ) );
$pixels = nck_test_sig_ink_pixels( $prepared['data'] );
$check( 'پس‌زمینه سفید شفاف می‌شود', $pixels[0] > 1000, $pixels[0] );
$check( 'جوهر امضا باقی می‌ماند', $pixels[1] > 30, $pixels[1] );

$jpeg_prep = NCK_Contract::prepare_signature( $jpeg_src );
$check( 'JPEG هم جوهرسازی می‌شود', ! empty( $jpeg_prep['ok'] ) );
$jpeg_px = nck_test_sig_ink_pixels( $jpeg_prep['data'] );
$check( 'JPEG پس‌زمینه شفاف است', $jpeg_px[0] > 800, $jpeg_px[0] );

$pad_html = (string) file_get_contents( dirname( __DIR__ ) . '/templates/sign-pad.php' );
$check( 'قالب امضا آپلود عکس دارد', false !== strpos( $pad_html, 'data-nck-sign-file' ) );
$check( 'قالب امضا پیش‌نمایش پلاک دارد', false !== strpos( $pad_html, 'data-nck-sign-ink' ) );

echo "\n=== اجاره سالن ===\n";
$nine = '001000000';
$digit = NCK_Hall::nid_check_digit( $nine );
$nid   = $nine . $digit;
$check( 'کد ملی ساختگی الگوریتم را پاس می‌کند', $nid === NCK_Hall::normalize_nid( $nid ), $nid );
$check( 'کد ملی تکراری رقم رد می‌شود', null === NCK_Hall::normalize_nid( '1111111111' ) );
$check( 'مبلغ فارسی', 5000000 === NCK_Hall::parse_amount( '۵٬۰۰۰٬۰۰۰ تومان' ) );
$check( 'فرمت مبلغ', false !== strpos( NCK_Hall::format_money( 500000 ), 'تومان' ) );

$ok = NCK_Hall::validate(
	array(
		'name'        => 'علی رضایی',
		'honorific'   => 'mr',
		'national_id' => $nid,
		'phone'       => '09121234567',
		'hall_name'   => 'سالن همایش',
		'amount'      => '۲۰۰۰۰۰۰',
		'event_date'  => '1404/06/20',
		'start_hour'  => '16:00',
		'end_hour'    => '20:00',
		'chairs'      => '۸۰',
		'payment'     => 'card',
		'pay_amount'  => '۲۰۰۰۰۰۰',
	)
);
$check( 'قرارداد سالن کامل قبول می‌شود', ! empty( $ok['ok'] ), isset( $ok['message'] ) ? $ok['message'] : '' );
$check( 'ساعت پایان در payload', ! empty( $ok['payload']['end_hour'] ) && '20:00' === $ok['payload']['end_hour'] );
$check( 'روش پرداخت سالن در payload سایت است', ! empty( $ok['ok'] ) && 'site' === $ok['payload']['payment'] );
$check( 'پیگیری سالن خودکار است', ! empty( $ok['payload']['pay_ref'] ) && 0 === strpos( $ok['payload']['pay_ref'], 'NCK-' ) );

$no_pay_hall = NCK_Hall::validate(
	array(
		'name'        => 'علی رضایی',
		'honorific'   => 'mr',
		'national_id' => $nid,
		'phone'       => '09121234567',
		'hall_name'   => 'سالن همایش',
		'amount'      => '۲۰۰۰۰۰۰',
		'event_date'  => '1404/06/20',
		'start_hour'  => '16:00',
		'end_hour'    => '20:00',
		'chairs'      => '۸۰',
	)
);
$check( 'سالن بدون روش پرداخت با سایت ثبت می‌شود', ! empty( $no_pay_hall['ok'] ) && 'site' === $no_pay_hall['payload']['payment'] );
$check( 'سالن بدون پیگیری خودکار شماره می‌گیرد', ! empty( $no_pay_hall['payload']['pay_ref'] ) && 0 === strpos( $no_pay_hall['payload']['pay_ref'], 'NCK-' ) );

$bad_time = NCK_Hall::validate(
	array(
		'name'        => 'علی رضایی',
		'honorific'   => 'mr',
		'national_id' => $nid,
		'phone'       => '09121234567',
		'hall_name'   => 'سالن اصلی',
		'amount'      => '1000',
		'event_date'  => '1404/06/20',
		'start_hour'  => '20:00',
		'end_hour'    => '16:00',
		'chairs'      => '10',
	)
);
$check( 'ساعت وارونه رد می‌شود', empty( $bad_time['ok'] ) );

$out_of_hours = NCK_Hall::validate(
	array(
		'name'        => 'علی رضایی',
		'honorific'   => 'mr',
		'national_id' => $nid,
		'phone'       => '09121234567',
		'hall_name'   => 'سالن اصلی',
		'amount'      => '1000',
		'event_date'  => '1404/06/20',
		'start_hour'  => '8:00',
		'end_hour'    => '10:00',
		'chairs'      => '10',
	)
);
$check( 'ساعت ۸ صبح خارج از بازه رد می‌شود', empty( $out_of_hours['ok'] ) );

$gap_hours = NCK_Hall::validate(
	array(
		'name'        => 'علی رضایی',
		'honorific'   => 'mr',
		'national_id' => $nid,
		'phone'       => '09121234567',
		'hall_name'   => 'سالن اصلی',
		'amount'      => '1000',
		'event_date'  => '1404/06/20',
		'start_hour'  => '12:00',
		'end_hour'    => '17:00',
		'chairs'      => '10',
	)
);
$check( 'عبور از فاصله ۱۳ تا ۱۶ رد می‌شود', empty( $gap_hours['ok'] ) );

$afternoon = NCK_Hall::validate(
	array(
		'name'        => 'علی رضایی',
		'honorific'   => 'mr',
		'national_id' => $nid,
		'phone'       => '09121234567',
		'hall_name'   => 'سالن اصلی',
		'amount'      => '1000',
		'event_date'  => '1404/06/20',
		'start_hour'  => '14:00',
		'end_hour'    => '15:00',
		'chairs'      => '10',
	)
);
$check( 'ساعت ۱۴ خارج از تایم رد می‌شود', empty( $afternoon['ok'] ) );

$morning_ok = NCK_Hall::validate(
	array(
		'name'        => 'علی رضایی',
		'honorific'   => 'mr',
		'national_id' => $nid,
		'phone'       => '09121234567',
		'hall_name'   => 'سالن اصلی',
		'amount'      => '1000',
		'event_date'  => '1404/06/20',
		'start_hour'  => '9:00',
		'end_hour'    => '13:00',
		'chairs'      => '10',
	)
);
$check( 'بازه صبح ۹ تا ۱۳ قبول می‌شود', ! empty( $morning_ok['ok'] ), isset( $morning_ok['message'] ) ? $morning_ok['message'] : '' );
$check( 'ساعت صبح نرمال ۹ است', ! empty( $morning_ok['ok'] ) && '09:00' === $morning_ok['payload']['start_hour'] );

$slots = NCK_Hall::time_slots();
$check( '۲۲ شیار ساعت سالن', 22 === count( $slots ) );
$check( '۹ صبح در شیارها هست', in_array( '09:00', $slots, true ) );
$check( '۲۲ شب در شیارها هست', in_array( '22:00', $slots, true ) );
$check( '۱۴ در شیارها نیست', ! in_array( '14:00', $slots, true ) );

$check( 'بازه مجاور تداخل ندارد', false === NCK_Hall::ranges_overlap( '16:00', '20:00', '20:00', '22:00' ) );
$check( 'بازه هم‌پوشان تداخل دارد', true === NCK_Hall::ranges_overlap( '16:00', '20:00', '18:00', '22:00' ) );
$month_map = NCK_Hall::group_month_payloads(
	array(
		array( 'event_date' => '1404/06/20', 'start_hour' => '16:00', 'end_hour' => '20:00', 'hall_name' => 'سالن همایش' ),
		array( 'event_date' => '1404/06/20', 'start_hour' => '09:00', 'end_hour' => '13:00', 'hall_name' => 'سالن اصلی' ),
		array( 'event_date' => '1404/07/01', 'start_hour' => '16:00', 'end_hour' => '18:00', 'hall_name' => 'سالن همایش' ),
	),
	1404,
	6,
	'سالن همایش'
);
$check( 'رزرو ماه فقط سالن انتخاب‌شده', isset( $month_map['1404/06/20'] ) && 1 === count( $month_map['1404/06/20'] ) );
$check( 'ماه بعد در تقویم این ماه نیست', ! isset( $month_map['1404/07/01'] ) );
$empty_month = NCK_Hall::month_bookings( 1404, 6, 'سالن همایش' );
$check( 'بدون وردپرس رزرو ماه خالی است', array() === $empty_month );

$no_nid = NCK_Hall::validate( array( 'name' => 'علی رضایی', 'phone' => '09121234567', 'hall_name' => 'سالن', 'amount' => '1', 'event_date' => '1404/01/01', 'start_hour' => '8:00', 'end_hour' => '10:00', 'chairs' => '1' ) );
$check( 'بدون کد ملی رد می‌شود', empty( $no_nid['ok'] ) );

$plans = NCK_Shifts::plan_types( 'both' );
$check( 'پلن هر دو دو اشتراک می‌سازد', array( 'morning', 'evening' ) === $plans );
$check( 'پلن سالن شیفت فضای کار نیست', array() === NCK_Shifts::plan_types( 'hall' ) );
$check( 'پنج بسته فضای کار', 5 === count( NCK_Shifts::packages() ) );
$check( 'قیمت ۱ ماهه تک‌شیفت', 1950000 === NCK_Shifts::package_price( 'm1_one' ) );
$check( 'قیمت ۱ ماهه دو شیفت', 3900000 === NCK_Shifts::package_price( 'm1_two' ) );
$check( 'قیمت ۲ ماهه تک‌شیفت', 3600000 === NCK_Shifts::package_price( 'm2_one' ) );
$check( 'قیمت ۲ ماهه دو شیفت', 7200000 === NCK_Shifts::package_price( 'm2_two' ) );
$check( 'قیمت ۳ ماهه یک شیفت', 5250000 === NCK_Shifts::package_price( 'm3_one' ) );
$check( 'تک‌شیفت بدون نوبت ناقص است', '' === NCK_Shifts::compose_plan( 'm1_one', '' ) );
$check( '۱ ماهه صبح', 'm1_am' === NCK_Shifts::compose_plan( 'm1_one', 'morning' ) );
$check( '۱ ماهه دو شیفت', 'm1_both' === NCK_Shifts::compose_plan( 'm1_two', '' ) );
$check( '۳ ماهه عصر', 'm3_pm' === NCK_Shifts::compose_plan( 'm3_one', 'evening' ) );
$check( 'دو شیفت دو اشتراک می‌سازد', array( 'morning', 'evening' ) === NCK_Shifts::plan_types( 'm2_both' ) );
$check( 'برچسب ۱ ماهه دو شیفت', 'اشتراک ۱ ماهه دو شیفت' === NCK_Shifts::plan_labels()['m1_both'] );

echo "\n=== پذیرش فراگیر ===\n";
$learner_ok = array(
	'name'          => 'آوا محمدی',
	'national_id'  => $nid,
	'birth_date'    => '1392/06/15',
	'grade'         => 'هشتم',
	'school'        => 'نمونه دولتی',
	'phone'         => '',
	'address'       => 'تهران، خیابان نهال',
	'father_name'   => 'علی محمدی',
	'father_phone'  => '۰۹۱۲۱۲۳۴۵۶۷',
	'payment'       => 'site',
	'term'          => array( 'ai', 'english' ),
	'seasonal'      => array( 'bogus' ),
	'heard'         => array( 'instagram', 'unknown' ),
	'group_work'    => 'coop',
	'problem'       => 'tries',
	'learning'      => 'mixed',
	'goals'         => array( 'ai', 'confidence' ),
	'agree_rules'   => 1,
	'agree_contact' => 1,
	'agree_photo'   => 1,
);
$lr = NCK_Learner::validate( $learner_ok );
$check( 'فرم پذیرش کامل قبول می‌شود', ! empty( $lr['ok'] ), isset( $lr['message'] ) ? $lr['message'] : '' );
$check( 'تلفن تماس از پدر گرفته می‌شود', ! empty( $lr['payload']['contact_phone'] ) && '09121234567' === $lr['payload']['contact_phone'] );
$check( 'گزینه ناشناس آشنایی حذف می‌شود', array( 'instagram' ) === $lr['payload']['heard'] );
$check( 'دوره فصلی نامعتبر حذف می‌شود', array() === $lr['payload']['seasonal'] );
$check( 'دو دوره ترمی می‌ماند', 2 === count( $lr['payload']['term'] ) );

$no_course = $learner_ok;
$no_course['term'] = array();
$no_course['seasonal'] = array();
$check( 'بدون دوره رد می‌شود', empty( NCK_Learner::validate( $no_course )['ok'] ) );

$no_parent = $learner_ok;
$no_parent['father_name'] = '';
$no_parent['mother_name'] = '';
$check( 'بدون نام والدین رد می‌شود', empty( NCK_Learner::validate( $no_parent )['ok'] ) );

$no_pay = $learner_ok;
$no_pay['payment'] = '';
$lr_auto = NCK_Learner::validate( $no_pay );
$check( 'پذیرش بدون روش پرداخت سایت می‌شود', ! empty( $lr_auto['ok'] ) && 'site' === $lr_auto['payload']['payment'] );
$check( 'پذیرش پیگیری خودکار دارد', ! empty( $lr_auto['payload']['pay_ref'] ) && 0 === strpos( $lr_auto['payload']['pay_ref'], 'NCK-' ) );

$no_goal = $learner_ok;
$no_goal['goals'] = array();
$check( 'بدون هدف رد می‌شود', empty( NCK_Learner::validate( $no_goal )['ok'] ) );

$no_pledge = $learner_ok;
$no_pledge['agree_photo'] = '';
$check( 'بدون موافقت تصویر رد می‌شود', empty( NCK_Learner::validate( $no_pledge )['ok'] ) );

$check( 'هشت گزینه آشنایی', 8 === count( NCK_Learner::heard_options() ) );
$check( 'پنج دوره ترمی', 5 === count( NCK_Learner::term_options() ) );
$check( 'برچسب ADHD در پزشکی هست', isset( NCK_Learner::medical_options()['adhd'] ) );
$learner_ok['pay_amount'] = '۲٬۵۰۰٬۰۰۰';
$lr_pay = NCK_Learner::validate( $learner_ok );
$check( 'مبلغ پذیرش در payload می‌ماند', ! empty( $lr_pay['ok'] ) && 2500000 === (int) $lr_pay['payload']['pay_amount'] );

require_once dirname( __DIR__ ) . '/includes/class-nck-forms.php';

echo "\n=== فرم‌ساز سفارشی ===\n";
$blank = NCK_Forms::blank();
$blank['title'] = 'کارگاه رباتیک';
$blank['slug'] = 'workshop';
$saved = NCK_Forms::save( $blank );
$check( 'فرم با slug انگلیسی ذخیره می‌شود', 'workshop' === $saved['slug'], $saved['slug'] );
$check( 'بازیابی با slug', null !== NCK_Forms::by_slug( 'workshop' ) );
$check( 'شورت‌کد فرم', '[nahal_form slug="workshop"]' === NCK_Forms::shortcode( $saved ) );
$dup = NCK_Forms::blank();
$dup['title'] = 'کارگاه دوم';
$dup['slug'] = 'workshop';
$dup_saved = NCK_Forms::save( $dup );
$check( 'slug تکراری یکتا می‌شود', 'workshop-2' === $dup_saved['slug'], $dup_saved['slug'] );
$check( 'بدون ووکامرس شناسه محصول صفر است', 0 === (int) $saved['product_id'] );
$check( 'SKU محصول فرم از شناسه ساخته می‌شود', 'nck-form-fabc123' === NCK_Pay::form_product_sku( 'fabc123' ) );
$check( 'نام محصول فرم از عنوان می‌آید', 'کارگاه رباتیک' === NCK_Pay::form_product_name( $saved ) );
$named = $saved;
$named['payment']['item_name'] = 'ثبت‌نام کارگاه';
$check( 'نام محصول از عنوان ردیف سفارش است', 'ثبت‌نام کارگاه' === NCK_Pay::form_product_name( $named ) );
$check( 'ساخت محصول بدون ووکامرس صفر است', 0 === NCK_Pay::ensure_form_product( $saved ) );
$keep = NCK_Forms::sanitize_form( array_merge( $saved, array( 'product_id' => 88 ) ) );
$check( 'شناسه محصول در sanitize می‌ماند', 88 === (int) $keep['product_id'] );

$opts = NCK_Forms::parse_options( "ai|هوش مصنوعی\nenglish|زبان" );
$check( 'گزینه با کلید و برچسب', isset( $opts['ai'] ) && 'هوش مصنوعی' === $opts['ai'] );

$steps = NCK_Forms::display_steps( $saved );
$labels = array();
foreach ( $steps as $st ) {
	$labels[] = $st['label'];
}
$check( 'مرحله پرداخت تزریق می‌شود', in_array( 'پرداخت', $labels, true ) );

$ok_form = NCK_Forms::validate(
	$saved,
	array(
		'name'       => 'سارا محمدی',
		'phone'      => '۰۹۱۲۱۲۳۴۵۶۷',
		'payment'    => 'card',
		'pay_amount' => '۱۲۰۰۰۰۰',
		'agree'      => 1,
	)
);
$check( 'فرم کامل با پرداخت قبول می‌شود', ! empty( $ok_form['ok'] ), isset( $ok_form['message'] ) ? $ok_form['message'] : '' );
$check( 'مبلغ فرم در payload', ! empty( $ok_form['ok'] ) && 1200000 === (int) $ok_form['payload']['pay_amount'] );
$check( 'روش پرداخت فرم سایت است', ! empty( $ok_form['ok'] ) && 'site' === $ok_form['payload']['payment'] );
$check( 'فرم پیگیری خودکار دارد', ! empty( $ok_form['ok'] ) && 0 === strpos( $ok_form['payload']['pay_ref'], 'NCK-' ) );
$check( 'payload فرم شناسه محصول دارد', ! empty( $ok_form['ok'] ) && isset( $ok_form['payload']['product_id'] ) );

$no_amt = NCK_Forms::validate( $saved, array( 'name' => 'سارا محمدی', 'phone' => '09121234567', 'payment' => 'site', 'agree' => 1 ) );
$check( 'پرداخت بدون مبلغ رد می‌شود', empty( $no_amt['ok'] ) );

$no_phone = NCK_Forms::validate( $saved, array( 'name' => 'سارا محمدی', 'payment' => 'site', 'pay_amount' => '1000', 'agree' => 1 ) );
$check( 'بدون موبایل رد می‌شود', empty( $no_phone['ok'] ) );

$no_agree = NCK_Forms::validate( $saved, array( 'name' => 'سارا محمدی', 'phone' => '09121234567', 'payment' => 'site', 'pay_amount' => '1000' ) );
$check( 'بدون پذیرش صحت رد می‌شود', empty( $no_agree['ok'] ) );

echo "\n=== ثبت سفارش حسابدار / ووکامرس ===\n";
$check( 'مبلغ صفر ثبت نمی‌شود', false === NCK_Pay::should_record( 0 ) );
$check( 'مبلغ مثبت ثبت می‌شود', true === NCK_Pay::should_record( 2000000 ) );
$check( 'پرداخت در محل تکمیل‌شده است', 'completed' === NCK_Pay::status_for_method( 'onsite' ) );
$check( 'کارت به کارت در انتظار است', 'on-hold' === NCK_Pay::status_for_method( 'card' ) );
$check( 'پرداخت سایت در انتظار تسویه است', 'pending' === NCK_Pay::status_for_method( 'site' ) );
$split = NCK_Pay::split_name( 'سارا محمدی' );
$check( 'جدا کردن نام خانوادگی', 'سارا' === $split['first'] && 'محمدی' === $split['last'] );

$hall_pay = NCK_Pay::from_contract(
	'hall',
	array( 'full_name' => 'علی رضایی', 'phone' => '09121234567' ),
	array( 'amount' => 2000000, 'hall_name' => 'سالن همایش', 'payment' => 'card' )
);
$check( 'اجاره سالن سفارش می‌سازد', ! empty( $hall_pay['ok'] ) && 2000000 === $hall_pay['order']['amount'] );
$check( 'عنوان سفارش سالن', false !== strpos( $hall_pay['order']['item_name'], 'سالن همایش' ) );
$check( 'اجاره سالن روش کارت از فرم', 'on-hold' === $hall_pay['order']['status'] );

$learn_skip = NCK_Pay::from_contract(
	'learner',
	array( 'full_name' => 'آوا محمدی', 'phone' => '09121234567' ),
	array( 'payment' => 'site' )
);
$check( 'پذیرش بدون مبلغ سفارش نمی‌سازد', ! empty( $learn_skip['skipped'] ) );

$learn_pay = NCK_Pay::from_contract(
	'learner',
	array( 'full_name' => 'آوا محمدی', 'phone' => '09121234567' ),
	array( 'payment' => 'site', 'pay_amount' => 2500000 )
);
$check( 'پذیرش با مبلغ سفارش می‌سازد', ! empty( $learn_pay['ok'] ) && 2500000 === $learn_pay['order']['amount'] );

$form_pay = NCK_Pay::from_contract(
	'form',
	array( 'full_name' => 'سارا محمدی', 'phone' => '09121234567' ),
	array( 'pay_amount' => 1200000, 'payment' => 'card', 'form_title' => 'کارگاه رباتیک' )
);
$check( 'فرم سفارشی سفارش می‌سازد', ! empty( $form_pay['ok'] ) && 'on-hold' === $form_pay['order']['status'] );

$cowork_skip = NCK_Pay::from_contract( 'cowork', array( 'full_name' => 'سارا', 'phone' => '09121234567', 'plan' => '' ), array() );
$check( 'فضای کار بدون بسته سفارش ندارد', ! empty( $cowork_skip['skipped'] ) );

$cowork_pkg = NCK_Pay::from_contract(
	'cowork',
	array( 'full_name' => 'سارا محمدی', 'phone' => '09121234567', 'plan' => 'm1_am' ),
	array( 'payment' => 'card' )
);
$check( 'فضای کار مبلغ بسته را می‌گیرد', ! empty( $cowork_pkg['ok'] ) && 1950000 === $cowork_pkg['order']['amount'] );

$cowork_pay = NCK_Pay::from_contract(
	'cowork',
	array( 'full_name' => 'سارا محمدی', 'phone' => '09121234567', 'plan' => 'morning' ),
	array( 'pay_amount' => 1500000, 'payment' => 'card' )
);
$check( 'فضای کار با مبلغ سفارش می‌سازد', ! empty( $cowork_pay['ok'] ) && 1500000 === $cowork_pay['order']['amount'] );
$check( 'روش پرداخت فضای کار از فرم می‌آید', 'on-hold' === $cowork_pay['order']['status'] );

$check( 'ورودی کارت فرانت سایت می‌شود', 'site' === NCK_Pay::parse_front_payment( array( 'payment' => 'card', 'pay_amount' => '1000' ) )['payload']['payment'] );
$check( 'گزینه پرداخت کاربر فقط سایت است', array( 'site' ) === array_keys( NCK_Learner::payment_options() ) );
$check( 'برچسب کارت برای چاپ قدیمی می‌ماند', 'کارت به کارت' === NCK_Learner::payment_label( 'card' ) );
$no_method = NCK_Pay::parse_front_payment( array( 'pay_amount' => '1000' ) );
$check( 'بدون روش پرداخت سایت می‌شود', ! empty( $no_method['ok'] ) && 'site' === $no_method['payload']['payment'] );
$check( 'پیگیری خودکار پیشوند NCK دارد', ! empty( $no_method['payload']['pay_ref'] ) && 0 === strpos( $no_method['payload']['pay_ref'], 'NCK-' ) );
$ok_pay = NCK_Pay::parse_front_payment( array( 'payment' => 'site', 'pay_amount' => '۲۰۰۰۰۰۰' ) );
$check( 'پرداخت فضای کار با مبلغ فارسی', ! empty( $ok_pay['ok'] ) && 2000000 === (int) $ok_pay['payload']['pay_amount'] );
$fee_pay = NCK_Pay::parse_front_payment( array( 'payment' => 'onsite' ), 900000 );
$check( 'شهریه تنظیمات به‌عنوان مبلغ پیش‌فرض', ! empty( $fee_pay['ok'] ) && 900000 === (int) $fee_pay['payload']['pay_amount'] );
$ignore_ref = NCK_Pay::parse_front_payment( array( 'payment' => 'site', 'pay_amount' => '1000', 'pay_ref' => 'USER-REF' ) );
$check( 'ورودی پیگیری کاربر نادیده گرفته می‌شود', ! empty( $ignore_ref['ok'] ) && 'USER-REF' !== $ignore_ref['payload']['pay_ref'] && 0 === strpos( $ignore_ref['payload']['pay_ref'], 'NCK-' ) );

$draft = NCK_Pay::draft( array( 'name' => 'علی رضایی', 'amount' => 10, 'payment' => 'onsite', 'item_name' => 'تست' ) );
$check( 'created_via نهال است', 'nahal-cowork' === $draft['created_via'] );
$check( 'ووکامرس در تست هسته خاموش است', false === NCK_Pay::wc_ready() );
$gate_ok = NCK_Pay::assert_can_checkout( 1000, 'site' );
$check( 'بدون وردپرس درگاه تست آزاد است', ! empty( $gate_ok['ok'] ) );
$sent = NCK_Pay::send_to_checkout(
	array( 'id' => 1 ),
	array( 'amount' => 1000, 'item_name' => 'تست', 'kind' => 'cowork' ),
	array()
);
$check( 'ارسال به درگاه بدون ووکامرس رد می‌شود', empty( $sent['ok'] ) && false !== strpos( $sent['message'], 'ووکامرس' ) );
$check( 'لوگو پیش‌فرض خالی است', 0 === (int) NCK_Settings::defaults()['logo_id'] );
$check( 'بدون وردپرس آدرس لوگو خالی است', '' === NCK_Settings::logo_url() );

require_once dirname( __DIR__ ) . '/includes/class-nck-admin.php';
echo "\n=== راهنمای شورت‌کد ===\n";
$guide = NCK_Admin::shortcode_guide();
$codes = array();
foreach ( $guide as $row ) {
	$codes[] = $row['code'];
}
$check( 'پنج شورت‌کد توضیح داده شده', 5 === count( $guide ) );
$check( 'قرارداد فضای کار', in_array( '[nahal_contract]', $codes, true ) );
$check( 'اجاره سالن', in_array( '[nahal_hall]', $codes, true ) );
$check( 'پذیرش فراگیر', in_array( '[nahal_admission]', $codes, true ) );
$check( 'فرم سفارشی با slug', in_array( '[nahal_form slug="workshop"]', $codes, true ) );
$check( 'پورتال عضو', in_array( '[nahal_portal]', $codes, true ) );
$help = (string) file_get_contents( dirname( __DIR__ ) . '/templates/admin-shortcodes.php' );
$check( 'قالب راهنما در افزونه هست', false !== strpos( $help, 'شورت‌کد چیست' ) );
$hall_tpl = (string) file_get_contents( dirname( __DIR__ ) . '/templates/hall.php' );
$pay_tpl  = (string) file_get_contents( dirname( __DIR__ ) . '/templates/pay-step.php' );
$check( 'قالب پرداخت مشترک هست', false !== strpos( $pay_tpl, 'data-nck-step-label="پرداخت"' ) );
$check( 'اجاره سالن مرحله پرداخت دارد', false !== strpos( $hall_tpl, 'pay-step.php' ) );
$check( 'پرداخت فقط سایت است', false !== strpos( $pay_tpl, 'name="payment" value="site"' ) );
$check( 'فیلد پیگیری دستی در پرداخت نیست', false === strpos( $pay_tpl, 'name="pay_ref"' ) );
$check( 'ورود برای درگاه سایت در پرداخت هست', false !== strpos( $pay_tpl, 'data-nck-login-needed' ) );
$check( 'توضیح ووکامرس در پرداخت هست', false !== strpos( $pay_tpl, 'ووکامرس' ) );
$mark_tpl = (string) file_get_contents( dirname( __DIR__ ) . '/templates/brand-mark.php' );
$check( 'قالب لوگوی مشترک هست', false !== strpos( $mark_tpl, 'nck-mark' ) );
$settings_tpl = (string) file_get_contents( dirname( __DIR__ ) . '/templates/admin-settings.php' );
$check( 'تنظیمات انتخاب لوگو دارد', false !== strpos( $settings_tpl, 'data-nck-logo-pick' ) );
$cowork_tpl = (string) file_get_contents( dirname( __DIR__ ) . '/templates/contract.php' );
$check( 'فرم قرارداد از قالب لوگو استفاده می‌کند', false !== strpos( $cowork_tpl, 'brand-mark.php' ) );
$check( 'فضای کار مرحله مفاد دارد', false !== strpos( $cowork_tpl, 'data-nck-step-label="مفاد قرارداد"' ) );
$check( 'دانلود قرارداد در مفاد هست', false !== strpos( $cowork_tpl, 'data-nck-download-review' ) );
$check( 'اجاره سالن مرحله مفاد دارد', false !== strpos( $hall_tpl, 'data-nck-step-label="مفاد قرارداد"' ) );
$check( 'دانلود قرارداد سالن هست', false !== strpos( $hall_tpl, 'data-nck-download-review' ) );
$check( 'تقویم شمسی اجاره سالن هست', false !== strpos( $hall_tpl, 'hall-schedule.php' ) );
$sched_tpl = (string) file_get_contents( dirname( __DIR__ ) . '/templates/hall-schedule.php' );
$check( 'تاریخ سالن از تقویم باز می‌شود', false !== strpos( $sched_tpl, 'data-nck-cal' ) );
$check( 'تقویم سالن ماهانه است', false !== strpos( $sched_tpl, 'nck-cal-month' ) );
$check( 'لیست ساعت پر در تقویم هست', false !== strpos( $sched_tpl, 'data-nck-cal-busy' ) );
$check( 'ساعت سالن رول دارد', false !== strpos( $sched_tpl, 'data-nck-time-rolls' ) );
$check( 'ورودی دستی ساعت سالن حذف شده', false === strpos( $sched_tpl, 'type="text"' ) );
$js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/frontend.js' );
$check( 'دانلود مفاد در جاوااسکریپت هست', false !== strpos( $js, 'data-nck-download-review' ) );
$check( 'کپی امضا به مفاد هست', false !== strpos( $js, 'copyReviewInk' ) );
$check( 'رفتن به درگاه در جاوااسکریپت هست', false !== strpos( $js, 'pay_url' ) );
$check( 'ورود اجباری پرداخت در جاوااسکریپت هست', false !== strpos( $js, 'need_login' ) );
$check( 'بارگذاری ماه سالن در جاوااسکریپت هست', false !== strpos( $js, 'nck_hall_month' ) );
$learner_tpl = (string) file_get_contents( dirname( __DIR__ ) . '/templates/learner.php' );
$check( 'پذیرش چیپ کارت ندارد', false === strpos( $learner_tpl, 'value="card"' ) );
$check( 'پذیرش پرداخت سایت پنهان دارد', false !== strpos( $learner_tpl, 'name="payment" value="site"' ) );
$forms_tpl = (string) file_get_contents( dirname( __DIR__ ) . '/templates/admin-forms.php' );
$check( 'ویرایشگر فرم محصول ووکامرس دارد', false !== strpos( $forms_tpl, 'محصول ووکامرس' ) );
$check( 'همگام‌سازی محصولات فرم در فهرست هست', false !== strpos( $forms_tpl, 'sync_all_products' ) );

echo "\n--- {$pass} موفق، {$fail} ناموفق ---\n";
exit( $fail ? 1 : 0 );
