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
$learner_ok['pay_amount'] = '۲٬۵۰۰٬۰۰۰';
$lr_pay = NCK_Learner::validate( $learner_ok );
$check( 'مبلغ پذیرش در payload می‌ماند', ! empty( $lr_pay['ok'] ) && 2500000 === (int) $lr_pay['payload']['pay_amount'] );

require_once dirname( __DIR__ ) . '/includes/class-nck-forms.php';
require_once dirname( __DIR__ ) . '/includes/class-nck-pay.php';

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
$check( 'روش پرداخت کارت', ! empty( $ok_form['ok'] ) && 'card' === $ok_form['payload']['payment'] );

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
$check( 'پرداخت سایت در حال انجام است', 'processing' === NCK_Pay::status_for_method( 'site' ) );
$split = NCK_Pay::split_name( 'سارا محمدی' );
$check( 'جدا کردن نام خانوادگی', 'سارا' === $split['first'] && 'محمدی' === $split['last'] );

$hall_pay = NCK_Pay::from_contract(
	'hall',
	array( 'full_name' => 'علی رضایی', 'phone' => '09121234567' ),
	array( 'amount' => 2000000, 'hall_name' => 'سالن همایش' )
);
$check( 'اجاره سالن سفارش می‌سازد', ! empty( $hall_pay['ok'] ) && 2000000 === $hall_pay['order']['amount'] );
$check( 'عنوان سفارش سالن', false !== strpos( $hall_pay['order']['item_name'], 'سالن همایش' ) );

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

$cowork_skip = NCK_Pay::from_contract( 'cowork', array( 'full_name' => 'سارا', 'phone' => '09121234567', 'plan' => 'morning' ), array() );
$check( 'فضای کار بدون شهریه سفارش ندارد', ! empty( $cowork_skip['skipped'] ) );

$draft = NCK_Pay::draft( array( 'name' => 'علی رضایی', 'amount' => 10, 'payment' => 'onsite', 'item_name' => 'تست' ) );
$check( 'created_via نهال است', 'nahal-cowork' === $draft['created_via'] );
$check( 'ووکامرس در تست هسته خاموش است', false === NCK_Pay::wc_ready() );

echo "\n--- {$pass} موفق، {$fail} ناموفق ---\n";
exit( $fail ? 1 : 0 );
