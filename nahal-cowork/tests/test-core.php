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
$vars = NCK_Contract::vars_from( array( 'name' => 'سارا محمدی', 'phone' => '09121234567', 'title' => 'ms', 'org' => 'مجموعه فرهنگی نهال' ) );
$filled = NCK_Contract::fill_template( NCK_Contract::default_preamble(), $vars );
$check( 'نام در مقدمه می‌نشیند', false !== strpos( $filled, 'سارا محمدی' ) );
$check( 'عنوان خانم', 'خانم' === $vars['title'] );
$sig = NCK_Contract::validate_signature( 'not-an-image' );
$check( 'امضای نامعتبر رد می‌شود', empty( $sig['ok'] ) );

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
	)
);
$check( 'قرارداد سالن کامل قبول می‌شود', ! empty( $ok['ok'] ), isset( $ok['message'] ) ? $ok['message'] : '' );
$check( 'ساعت پایان در payload', ! empty( $ok['payload']['end_hour'] ) && '20:00' === $ok['payload']['end_hour'] );

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

$no_nid = NCK_Hall::validate( array( 'name' => 'علی رضایی', 'phone' => '09121234567', 'hall_name' => 'سالن', 'amount' => '1', 'event_date' => '1404/01/01', 'start_hour' => '8:00', 'end_hour' => '10:00', 'chairs' => '1' ) );
$check( 'بدون کد ملی رد می‌شود', empty( $no_nid['ok'] ) );

$plans = NCK_Shifts::plan_types( 'both' );
$check( 'پلن هر دو دو اشتراک می‌سازد', array( 'morning', 'evening' ) === $plans );
$check( 'پلن سالن شیفت فضای کار نیست', array() === NCK_Shifts::plan_types( 'hall' ) );

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
$check( 'بدون وضعیت پرداخت رد می‌شود', empty( NCK_Learner::validate( $no_pay )['ok'] ) );

$no_goal = $learner_ok;
$no_goal['goals'] = array();
$check( 'بدون هدف رد می‌شود', empty( NCK_Learner::validate( $no_goal )['ok'] ) );

$no_pledge = $learner_ok;
$no_pledge['agree_photo'] = '';
$check( 'بدون موافقت تصویر رد می‌شود', empty( NCK_Learner::validate( $no_pledge )['ok'] ) );

$check( 'هشت گزینه آشنایی', 8 === count( NCK_Learner::heard_options() ) );
$check( 'پنج دوره ترمی', 5 === count( NCK_Learner::term_options() ) );
$check( 'برچسب ADHD در پزشکی هست', isset( NCK_Learner::medical_options()['adhd'] ) );

echo "\n--- {$pass} موفق، {$fail} ناموفق ---\n";
exit( $fail ? 1 : 0 );
