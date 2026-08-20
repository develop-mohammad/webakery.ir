<?php
/**
 * فرم پرداخت مرحله‌ای دریافت اینماد — webakery.ir
 * مستقل از وردپرس؛ متصل به درگاه پرداخت زیبال.
 * PHP 7.4+
 */

if ( isset( $_GET['debug'] ) ) {
	ini_set( 'display_errors', '1' );
	error_reporting( E_ALL );
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Invoice.php';

$MERCHANT = defined( 'EO_ZIBAL_MERCHANT' ) ? EO_ZIBAL_MERCHANT : 'zibal';
$AMOUNT   = defined( 'EO_PRICE_RIAL' ) ? (int) EO_PRICE_RIAL : 27500000;
$SERVICE  = defined( 'EO_SERVICE_TITLE' ) ? EO_SERVICE_TITLE : 'خدمات دریافت اینماد';
$SELF_URL = eo_self_url();

/* ══════════════ کال‌بک زیبال — تأیید و نمایش فاکتور ══════════════ */
if ( isset( $_GET['zibal_cb'] ) ) {
	$track_id = trim( $_GET['trackId'] ?? '' );
	$success  = (int) ( $_GET['success'] ?? 0 );

	if ( ! $track_id ) {
		eo_render_message( 'خطا', 'شناسه تراکنش دریافت نشد.' );
	}

	$order = EO_Database::find_by_track( $track_id );
	if ( ! $order ) {
		eo_render_message( 'خطا', 'سفارش مرتبط با این تراکنش پیدا نشد.' );
	}

	if ( ( $order['status'] ?? '' ) === 'paid' ) {
		eo_render_invoice_page( $order );
	}

	if ( $success !== 1 ) {
		EO_Database::update_by_track( $track_id, [ 'status' => 'failed' ] );
		eo_render_message(
			'پرداخت ناموفق',
			'پرداخت لغو شد یا با خطا مواجه شد. نگران نباشید — اطلاعات شما ذخیره شده و می‌توانید دوباره تلاش کنید.',
			'<a href="' . eo_e( $SELF_URL ) . '" class="btn btn-next" style="display:inline-block;text-align:center;text-decoration:none;padding:13px 26px">تلاش مجدد</a>'
		);
	}

	$verify = eo_zibal_post(
		'https://gateway.zibal.ir/v1/verify',
		[ 'merchant' => $MERCHANT, 'trackId' => (int) $track_id ]
	);
	$code = (int) ( $verify['result'] ?? 0 );
	if ( $code !== 100 && $code !== 201 ) {
		eo_render_message( 'خطای تأیید پرداخت', 'پرداخت تأیید نشد (کد ' . $code . '). با پشتیبانی تماس بگیرید.' );
	}

	EO_Database::update_by_track(
		$track_id,
		[
			'status'         => 'paid',
			'paid_at'        => date( 'Y-m-d H:i:s' ),
			'paid_at_jalali' => eo_jalali_now_str(),
		]
	);
	$order = EO_Database::find_by_track( $track_id );

	// اعلان فاکتور جامع داخلی برای اقدام اینماد (تلگرام + ایمیل — اختیاری)
	eo_notify_telegram( eo_internal_invoice_text( $order ) );
	eo_notify_email(
		'🟣 سفارش جدید اینماد — ' . ( $order['order_code'] ?? '' ),
		'<div dir="rtl" style="font-family:Tahoma;max-width:560px;margin:0 auto;padding:20px">'
		. '<h2 style="color:#4f46e5">فاکتور جامع داخلی — اقدام اینماد</h2>'
		. eo_internal_invoice_html( $order )
		. '</div>'
	);

	eo_render_invoice_page( $order );
}

/* ══════════════ ارسال فرم → شروع پرداخت ══════════════ */
$errors = [];
$old    = [];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['eo_submit'] ) ) {
	$old = $_POST;

	$full_name     = trim( (string) ( $_POST['full_name'] ?? '' ) );
	$business_name = trim( (string) ( $_POST['business_name'] ?? '' ) );
	$mobile        = eo_normalize_mobile( (string) ( $_POST['mobile'] ?? '' ) );
	$landline      = eo_normalize_phone( (string) ( $_POST['landline'] ?? '' ) );
	$email         = filter_var( trim( (string) ( $_POST['email'] ?? '' ) ), FILTER_VALIDATE_EMAIL );
	$postal_code   = eo_normalize_postal( (string) ( $_POST['postal_code'] ?? '' ) );
	$website       = eo_normalize_website( (string) ( $_POST['website'] ?? '' ) );
	$tax_code      = trim( (string) ( $_POST['tax_code'] ?? '' ) );
	$access_type   = preg_replace( '/[^a-z_]/', '', (string) ( $_POST['access_type'] ?? '' ) );
	$access_note   = trim( (string) ( $_POST['access_note'] ?? '' ) );
	$accept_terms  = ! empty( $_POST['accept_terms'] );

	if ( eo_strlen( $full_name ) < 3 )               { $errors['full_name']     = 'نام و نام خانوادگی را کامل وارد کنید.'; }
	if ( eo_strlen( $business_name ) < 2 )           { $errors['business_name'] = 'نام کسب‌وکار یا فروشگاه را وارد کنید.'; }
	if ( $mobile === '' )                            { $errors['mobile']        = 'شماره موبایل معتبر ایران وارد کنید (۰۹xxxxxxxxx).'; }
	if ( $landline === '' )                          { $errors['landline']      = 'شماره تلفن ثابت معتبر وارد کنید.'; }
	if ( ! $email )                                  { $errors['email']         = 'آدرس ایمیل معتبر وارد کنید.'; }
	if ( $postal_code === '' )                       { $errors['postal_code']   = 'کد پستی باید دقیقاً ۱۰ رقم باشد.'; }
	if ( $website === '' )                           { $errors['website']       = 'آدرس وب‌سایت را وارد کنید.'; }
	if ( $tax_code === '' )                          { $errors['tax_code']      = 'کد رهگیری پرونده مالیاتی را وارد کنید.'; }
	if ( ! in_array( $access_type, [ 'info_email', 'hosting', 'both' ], true ) ) { $errors['access_type'] = 'نوع دسترسی را انتخاب کنید.'; }
	if ( ! $accept_terms )                           { $errors['accept_terms']  = 'برای ادامه باید قوانین را تأیید کنید.'; }

	if ( empty( $errors ) ) {
		$order_code = eo_generate_order_code();
		while ( EO_Database::order_code_exists( $order_code ) ) {
			$order_code = eo_generate_order_code();
		}

		$callback = $SELF_URL . '?zibal_cb=1';
		$resp     = eo_zibal_post(
			'https://gateway.zibal.ir/v1/request',
			[
				'merchant'    => $MERCHANT,
				'amount'      => $AMOUNT,
				'callbackUrl' => $callback,
				'description' => $SERVICE . ' — ' . $full_name . ' (' . $website . ')',
			]
		);

		$res_code = $resp['result'] ?? -1;
		if ( $res_code !== 100 ) {
			$errors['_general'] = 'خطا در اتصال به درگاه پرداخت. کد: ' . $res_code . ' — ' . ( $resp['message'] ?? '' );
		} else {
			$track_id = (string) $resp['trackId'];
			EO_Database::insert(
				[
					'track_id'      => $track_id,
					'order_code'    => $order_code,
					'full_name'     => $full_name,
					'business_name' => $business_name,
					'mobile'        => $mobile,
					'landline'      => $landline,
					'email'         => $email,
					'postal_code'   => $postal_code,
					'website'       => $website,
					'tax_code'      => $tax_code,
					'access_type'   => $access_type,
					'access_note'   => $access_note,
					'amount'        => $AMOUNT,
					'total_amount'  => eo_price_total_rial(),
					'remaining'     => eo_price_remaining_rial( $AMOUNT ),
					'payment_kind'  => 'prepay',
					'status'        => 'pending',
					'created_at'    => date( 'Y-m-d H:i:s' ),
				]
			);

			header( 'Location: https://gateway.zibal.ir/start/' . $track_id );
			exit;
		}
	}
}

/* ══════════════ رندر فرم مرحله‌ای ══════════════ */
eo_render_wizard( $errors, $old, $AMOUNT, $SERVICE );

/* ══════════════ توابع رندر صفحه ══════════════ */

function eo_val( array $old, string $key ): string {
	return eo_e( (string) ( $old[ $key ] ?? '' ) );
}

function eo_page_head( string $title, string $description = '' ): void {
	if ( $description === '' ) {
		$description = 'پرداخت آنلاین خدمات دریافت نماد اعتماد الکترونیکی (اینماد) — webakery.ir';
	}
	$self = eo_self_url();
	echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head>'
		. '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
		. '<title>' . eo_e( $title ) . '</title>'
		. '<meta name="description" content="' . eo_e( $description ) . '">'
		. '<meta name="theme-color" content="#4f46e5">'
		. '<meta property="og:type" content="website">'
		. '<meta property="og:locale" content="fa_IR">'
		. '<meta property="og:site_name" content="webakery.ir">'
		. '<meta property="og:title" content="' . eo_e( $title ) . '">'
		. '<meta property="og:description" content="' . eo_e( $description ) . '">'
		. '<meta property="og:url" content="' . eo_e( $self ) . '">'
		. '<meta name="twitter:card" content="summary">'
		. '<meta name="twitter:title" content="' . eo_e( $title ) . '">'
		. '<meta name="twitter:description" content="' . eo_e( $description ) . '">'
		. '<link rel="preconnect" href="https://fonts.googleapis.com">'
		. '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
		. '<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">'
		. '<link rel="stylesheet" href="assets/style.css">'
		. '</head><body><div class="wrap">'
		. '<div class="brand"><span class="dot"></span> webakery.ir</div>';
}

function eo_page_foot( string $extra = '' ): void {
	echo '</div>' . $extra . '</body></html>';
}

function eo_render_message( string $title, string $msg, string $extra = '' ): void {
	eo_page_head( $title );
	echo '<div class="card"><div class="result-icon">⚠️</div>'
		. '<div class="result-title">' . eo_e( $title ) . '</div>'
		. '<p style="text-align:center;color:#374151;font-size:13px;margin:14px 0;line-height:1.8">' . eo_e( $msg ) . '</p>'
		. ( $extra !== '' ? '<div style="text-align:center;margin-top:10px">' . $extra . '</div>' : '' )
		. '</div>';
	eo_page_foot();
	exit;
}

function eo_render_invoice_page( array $order ): void {
	eo_page_head( 'فاکتور شما — ' . ( $order['order_code'] ?? '' ) );
	echo '<div class="card">' . eo_customer_invoice_html( $order ) . '</div>';
	eo_page_foot();
	exit;
}

function eo_render_wizard( array $errors, array $old, int $amount, string $service ): void {
	eo_page_head(
		$service . ' — پرداخت آنلاین',
		'ثبت سفارش اینماد با پیش‌پرداخت ' . eo_toman( $amount ) . ' تومان — اطلاعات را مرحله‌به‌مرحله وارد کنید و فاکتور بگیرید.'
	);

	$access_options = [
		'info_email' => [ 'دسترسی به ایمیل info سایت', 'یک کاربر یا فوروارد موقت روی ایمیل info@ سایتتان برای ما ایجاد می‌کنید.' ],
		'hosting'    => [ 'دسترسی به هاست وب‌سایت', 'یک کاربری با دسترسی محدود روی پنل هاست/سی‌پنل برایمان می‌سازید.' ],
		'both'       => [ 'هر دو (ایمیل info و هاست)', 'سریع‌ترین حالت — روند دریافت اینماد کوتاه‌تر می‌شود.' ],
	];

	echo '<div class="hero">'
		. '<div class="ic">🛡️</div>'
		. '<h1>' . eo_e( $service ) . '</h1>'
		. '<div class="price">' . eo_toman( $amount ) . ' تومان <small>پیش‌پرداخت — از مجموع ' . eo_toman( eo_price_total_rial() ) . ' تومان</small></div>'
		. '</div>';

	echo '<div class="stepper">';
	for ( $i = 0; $i < 5; $i++ ) {
		echo '<div class="seg"></div>';
	}
	echo '</div>';
	echo '<div class="step-label" id="step_label">مرحله ۱ از ۵</div>';

	// تشخیص مرحله‌ای که باید در صورت خطای سرور نمایش داده شود
	$step_fields = [
		0 => [ 'full_name', 'business_name', 'mobile' ],
		1 => [ 'landline', 'email', 'postal_code' ],
		2 => [ 'website', 'tax_code' ],
		3 => [ 'access_type' ],
		4 => [ 'accept_terms', '_general' ],
	];
	$active_step = 0;
	if ( ! empty( $errors ) ) {
		foreach ( $step_fields as $idx => $fields ) {
			if ( array_intersect( $fields, array_keys( $errors ) ) ) {
				$active_step = $idx;
				break;
			}
		}
	}

	echo '<div class="card">';

	if ( ! empty( $errors['_general'] ) ) {
		echo '<div class="notice-error">' . eo_e( $errors['_general'] ) . '</div>';
	}

	echo '<form method="post" id="eo_form" novalidate>';

	/* ── مرحله ۱: اطلاعات متقاضی ── */
	echo '<div class="step' . ( 0 === $active_step ? ' active' : '' ) . '">'
		. '<h2>👤 اطلاعات متقاضی</h2>'
		. '<p class="step-desc">این اطلاعات دقیقاً همان چیزی است که اینماد در فرآیند بررسی از آن استفاده می‌کند؛ لطفاً دقیق وارد کنید.</p>'
		. eo_field( 'full_name', 'نام و نام خانوادگی متقاضی', $old, $errors, [ 'placeholder' => 'مثلاً: علی محمدی' ] )
		. eo_field( 'business_name', 'نام کسب‌وکار / فروشگاه', $old, $errors, [ 'placeholder' => 'مثلاً: فروشگاه اینترنتی وب‌آکری' ] )
		. eo_field( 'mobile', 'شماره موبایل به نام متقاضی', $old, $errors, [ 'placeholder' => '09xxxxxxxxx', 'type' => 'tel', 'pattern' => '^0?9\\d{9}$' ] )
		. '<div class="nav-row"><button type="button" class="btn btn-next js-next">مرحله بعد ←</button></div>'
		. '</div>';

	/* ── مرحله ۲: اطلاعات تماس ── */
	echo '<div class="step' . ( 1 === $active_step ? ' active' : '' ) . '">'
		. '<h2>☎️ اطلاعات تماس</h2>'
		. '<p class="step-desc">تلفن ثابت و ایمیل فعال، برای مکاتبات رسمی پرونده اینماد شما لازم است.</p>'
		. eo_field( 'landline', 'شماره تلفن ثابت (منزل، دفتر یا مغازه)', $old, $errors, [ 'placeholder' => '021xxxxxxxx', 'type' => 'tel', 'pattern' => '^0?\\d{8,11}$' ] )
		. eo_field( 'email', 'آدرس ایمیل فعال', $old, $errors, [ 'placeholder' => 'name@example.com', 'type' => 'email' ] )
		. eo_field( 'postal_code', 'کد پستی (منزل، دفتر یا محل کار)', $old, $errors, [ 'placeholder' => '۱۰ رقمی', 'pattern' => '^\\d{10}$' ] )
		. '<div class="nav-row"><button type="button" class="btn btn-back js-back">→ قبلی</button><button type="button" class="btn btn-next js-next">مرحله بعد ←</button></div>'
		. '</div>';

	/* ── مرحله ۳: وبسایت و مالیات ── */
	echo '<div class="step' . ( 2 === $active_step ? ' active' : '' ) . '">'
		. '<h2>🌐 وب‌سایت و پرونده مالیاتی</h2>'
		. '<p class="step-desc">این دو مورد مستقیماً در پروندهٔ اینماد شما ثبت می‌شود.</p>'
		. eo_field( 'website', 'آدرس وب‌سایت', $old, $errors, [ 'placeholder' => 'example.com' ] )
		. eo_field( 'tax_code', 'کد رهگیری پرونده مالیاتی', $old, $errors, [ 'placeholder' => 'کد رهگیری دریافتی از سامانه مالیاتی' ] )
		. '<div class="nav-row"><button type="button" class="btn btn-back js-back">→ قبلی</button><button type="button" class="btn btn-next js-next">مرحله بعد ←</button></div>'
		. '</div>';

	/* ── مرحله ۴: دسترسی‌ها ── */
	echo '<div class="step' . ( 3 === $active_step ? ' active' : '' ) . '">'
		. '<h2>🔑 دسترسی برای تکمیل فرآیند</h2>'
		. '<p class="step-desc">برای درج کد اینماد و تأیید مالکیت سایت، به یکی از این دسترسی‌ها نیاز داریم. اطلاعات ورود (رمز عبور) را اینجا وارد نکنید — بعد از پرداخت از طریق ایمیل یا تلگرام امن برایتان ارسال می‌کنیم که کجا وارد کنید.</p>'
		. '<div class="field' . ( isset( $errors['access_type'] ) ? ' has-error' : '' ) . '">'
		. '<label>نوع دسترسی</label>'
		. '<div class="radio-group">';
	foreach ( $access_options as $val => $meta ) {
		$checked = ( ( $old['access_type'] ?? '' ) === $val ) ? ' checked' : '';
		echo '<label class="radio-card">'
			. '<input type="radio" name="access_type" value="' . eo_e( $val ) . '" data-required data-error-msg="نوع دسترسی را انتخاب کنید."' . $checked . '>'
			. '<span><span class="rt">' . eo_e( $meta[0] ) . '</span><br><span class="rd">' . eo_e( $meta[1] ) . '</span></span>'
			. '</label>';
	}
	echo '</div>'
		. '<span class="err-msg" style="' . ( isset( $errors['access_type'] ) ? 'display:block' : '' ) . '">' . eo_e( $errors['access_type'] ?? 'یکی از گزینه‌ها را انتخاب کنید.' ) . '</span>'
		. '</div>'
		. '<div class="field">'
		. '<label>توضیح دسترسی <span class="opt">(اختیاری)</span></label>'
		. '<textarea name="access_note" placeholder="مثلاً: هاست روی دایرکت‌ادمین است / ایمیل روی Google Workspace است">' . eo_val( $old, 'access_note' ) . '</textarea>'
		. '</div>'
		. '<div class="nav-row"><button type="button" class="btn btn-back js-back">→ قبلی</button><button type="button" class="btn btn-next js-next">مرحله بعد ←</button></div>'
		. '</div>';

	/* ── مرحله ۵: بازبینی و پرداخت ── */
	echo '<div class="step' . ( 4 === $active_step ? ' active' : '' ) . '">'
		. '<h2>✅ بازبینی نهایی و پرداخت</h2>'
		. '<p class="step-desc">قبل از پرداخت پیش‌پرداخت، اطلاعات زیر را یک بار بازبینی کنید.</p>'
		. '<div class="summary" id="review_summary"></div>'
		. '<div class="summary" style="margin-top:-6px">'
		. '<div class="row"><span class="k">هزینه کل خدمات</span><span class="v">' . eo_toman( eo_price_total_rial() ) . ' تومان</span></div>'
		. '<div class="row"><span class="k">پیش‌پرداخت الان</span><span class="v">' . eo_toman( $amount ) . ' تومان</span></div>'
		. '<div class="row"><span class="k">مانده پس از این پرداخت</span><span class="v">' . eo_toman( eo_price_remaining_rial( $amount ) ) . ' تومان</span></div>'
		. '</div>'
		. '<div class="field' . ( isset( $errors['accept_terms'] ) ? ' has-error' : '' ) . '">'
		. '<label class="check-line"><input type="checkbox" name="accept_terms" value="1" data-required data-error-msg="برای ادامه باید این مورد را تأیید کنید."' . ( ! empty( $old['accept_terms'] ) ? ' checked' : '' ) . '> اطلاعات فوق را تأیید می‌کنم و می‌دانم پس از پرداخت، اطلاعات ورود (رمز عبور ایمیل/هاست) را جداگانه و امن برای وب‌آکری ارسال خواهم کرد.</label>'
		. '<span class="err-msg" style="' . ( isset( $errors['accept_terms'] ) ? 'display:block' : '' ) . '">' . eo_e( $errors['accept_terms'] ?? 'برای ادامه باید این مورد را تأیید کنید.' ) . '</span>'
		. '</div>'
		. '<div class="nav-row"><button type="button" class="btn btn-back js-back">→ قبلی</button>'
		. '<button type="submit" name="eo_submit" value="1" class="btn btn-pay">پرداخت پیش‌پرداخت ' . eo_toman( $amount ) . ' تومان با زیبال 💳</button></div>'
		. '</div>';

	echo '</form>';
	echo '</div>'; // .card

	echo '<div class="trust-row"><span>🔒 پرداخت امن با زیبال</span><span>🧾 صدور فاکتور رسمی</span><span>🛟 پشتیبانی اختصاصی</span></div>';
	echo '<p class="footer-note">این لینک را می‌توانید در تلگرام و اینستاگرام هم برای مشتریان خود ارسال کنید.<br>'
		. '<button type="button" class="print-btn" id="copy_share_link" style="margin-top:10px">🔗 کپی لینک صفحه</button>'
		. '<br>© ' . date( 'Y' ) . ' webakery.ir</p>';

	eo_page_foot( '<script src="assets/wizard.js"></script>' );
	exit;
}

/**
 * ساخت HTML یک فیلد ورودی متنی با پیام خطا.
 *
 * @param array $opts می‌تواند placeholder, type, pattern را داشته باشد.
 */
function eo_field( string $name, string $label, array $old, array $errors, array $opts = [] ): string {
	$type    = $opts['type'] ?? 'text';
	$pattern = isset( $opts['pattern'] ) ? ' data-pattern="' . eo_e( $opts['pattern'] ) . '"' : '';
	$has_err = isset( $errors[ $name ] );
	$val     = eo_val( $old, $name );

	return '<div class="field' . ( $has_err ? ' has-error' : '' ) . '">'
		. '<label>' . eo_e( $label ) . '</label>'
		. '<input type="' . eo_e( $type ) . '" name="' . eo_e( $name ) . '" value="' . $val . '" '
		. 'placeholder="' . eo_e( $opts['placeholder'] ?? '' ) . '" data-required' . $pattern
		. ' data-error-msg="' . eo_e( $errors[ $name ] ?? 'این فیلد را کامل و صحیح وارد کنید.' ) . '">'
		. '<span class="err-msg" style="' . ( $has_err ? 'display:block' : '' ) . '">' . eo_e( $errors[ $name ] ?? '' ) . '</span>'
		. '</div>';
}
