<?php
/**
 * ساخت فاکتور مشتری (خلاصه و جذاب) و فاکتور جامع داخلی (برای اقدام اینماد).
 */

/** فاکتور جذاب برای مشتری — بعد از پرداخت موفق نمایش داده می‌شود */
function eo_customer_invoice_html( array $order ): string {
	$amount    = eo_toman( (int) $order['amount'] );
	$total     = eo_toman( (int) ( $order['total_amount'] ?? eo_price_total_rial() ) );
	$remaining = eo_toman( (int) ( $order['remaining'] ?? eo_price_remaining_rial( (int) $order['amount'] ) ) );
	$rows   = [
		[ '👤 نام متقاضی', $order['full_name'] ?? '' ],
		[ '🏪 کسب‌وکار / فروشگاه', $order['business_name'] ?? '' ],
		[ '🌐 وب‌سایت', $order['website'] ?? '' ],
		[ '📅 تاریخ صدور', $order['paid_at_jalali'] ?? eo_jalali_now_str() ],
		[ '🧾 شماره سفارش', $order['order_code'] ?? '' ],
	];

	$rows_html = '';
	foreach ( $rows as $r ) {
		if ( trim( (string) $r[1] ) === '' ) {
			continue;
		}
		$rows_html .= '<tr><td>' . eo_e( $r[0] ) . '</td><td>' . eo_e( $r[1] ) . '</td></tr>';
	}

	$service = defined( 'EO_SERVICE_TITLE' ) ? EO_SERVICE_TITLE : 'خدمات دریافت اینماد';
	$support_email = defined( 'EO_SUPPORT_EMAIL' ) ? EO_SUPPORT_EMAIL : 'info@webakery.ir';
	$support_tg    = defined( 'EO_SUPPORT_TELEGRAM' ) ? EO_SUPPORT_TELEGRAM : '';

	return '
<div class="result-icon">✅</div>
<div class="result-title">پرداخت با موفقیت انجام شد</div>
<div class="result-sub">' . eo_e( $service ) . '</div>

<div class="invoice-box">
	<div class="code">' . eo_e( $order['order_code'] ?? '' ) . '</div>
	<table class="invoice-table">
		' . $rows_html . '
		<tr><td>💳 پیش‌پرداخت پرداخت‌شده</td><td style="color:#16a34a;font-size:14px">' . $amount . ' تومان</td></tr>
		<tr><td>📦 هزینه کل خدمات</td><td>' . $total . ' تومان</td></tr>
		<tr><td>⏳ مانده قابل تسویه</td><td>' . $remaining . ' تومان</td></tr>
	</table>
</div>

<p style="font-size:12.5px;color:#374151;line-height:1.9;margin-bottom:18px">
	سلام ' . eo_e( $order['full_name'] ?? '' ) . '؛ پیش‌پرداخت شما با موفقیت ثبت شد. همکاران ما در وب‌بیکری اطلاعات ارسالی شما را بررسی
	می‌کنند و برای تکمیل فرآیند دریافت نماد اعتماد الکترونیکی (اینماد)، در صورت نیاز به مدارک تکمیلی از طریق
	ایمیل یا تلگرام با شما در ارتباط خواهند بود. مانده هزینه پس از انجام کار تسویه می‌شود.
</p>

<div style="text-align:center;margin-bottom:6px">
	<button class="print-btn no-print" onclick="window.print()">🖨️ چاپ / ذخیره فاکتور</button>
</div>

<p class="footer-note">
	سؤالی دارید؟ ایمیل: <a href="mailto:' . eo_e( $support_email ) . '">' . eo_e( $support_email ) . '</a>'
	. ( $support_tg ? ' — تلگرام: <a href="https://t.me/' . eo_e( ltrim( $support_tg, '@' ) ) . '">' . eo_e( $support_tg ) . '</a>' : '' ) . '
</p>';
}

/**
 * فاکتور جامع داخلی — همه اطلاعات لازم برای اقدام دریافت اینماد.
 * برای پنل ادمین و ایمیل/تلگرام اعلان استفاده می‌شود.
 */
function eo_internal_invoice_html( array $order ): string {
	$checklist = [
		[ '۱', 'کد پستی', $order['postal_code'] ?? '' ],
		[ '۲', 'شماره تلفن ثابت', $order['landline'] ?? '' ],
		[ '۳', 'شماره موبایل به نام متقاضی', $order['mobile'] ?? '' ],
		[ '۴', 'آدرس ایمیل فعال', $order['email'] ?? '' ],
		[ '۵', 'آدرس وب‌سایت', eo_website_url( $order['website'] ?? '' ) ],
		[ '۶', 'کد رهگیری پرونده مالیاتی', $order['tax_code'] ?? '' ],
		[ '۷', 'دسترسی به ایمیل info یا هاست وبسایت', eo_access_type_label( $order['access_type'] ?? '' ) ],
	];

	$rows_html = '';
	foreach ( $checklist as $c ) {
		$val   = trim( (string) $c[2] );
		$empty = $val === '';
		$rows_html .= '<tr>'
			. '<td style="width:26px;color:#7c3aed;font-weight:800">' . $c[0] . '</td>'
			. '<td style="color:#64748b">' . eo_e( $c[1] ) . '</td>'
			. '<td style="font-weight:700;' . ( $empty ? 'color:#ef4444' : 'color:#0f172a' ) . '">' . ( $empty ? 'ارسال نشده' : eo_e( $val ) ) . '</td>'
			. '</tr>';
	}

	$note      = trim( (string) ( $order['access_note'] ?? '' ) );
	$amount    = eo_toman( (int) ( $order['amount'] ?? 0 ) );
	$total     = eo_toman( (int) ( $order['total_amount'] ?? eo_price_total_rial() ) );
	$remaining = eo_toman( (int) ( $order['remaining'] ?? eo_price_remaining_rial( (int) ( $order['amount'] ?? 0 ) ) ) );

	return '
<table class="invoice-table" style="width:100%">
	<tr><td>👤 نام و نام خانوادگی متقاضی</td><td>' . eo_e( $order['full_name'] ?? '' ) . '</td></tr>
	<tr><td>🏪 نام کسب‌وکار / فروشگاه</td><td>' . eo_e( $order['business_name'] ?? '' ) . '</td></tr>
	<tr><td>🧾 شماره سفارش</td><td>' . eo_e( $order['order_code'] ?? '' ) . '</td></tr>
	<tr><td>💳 پیش‌پرداخت</td><td>' . ( ( $order['status'] ?? '' ) === 'paid' ? '✅ پرداخت‌شده — ' . $amount . ' تومان' : '⏳ ' . eo_e( $order['status'] ?? '' ) . ' — ' . $amount . ' تومان' ) . '</td></tr>
	<tr><td>📦 هزینه کل</td><td>' . $total . ' تومان</td></tr>
	<tr><td>⏳ مانده</td><td>' . $remaining . ' تومان</td></tr>
	<tr><td>📅 تاریخ ثبت</td><td>' . eo_e( $order['created_at'] ?? '' ) . '</td></tr>
</table>

<h3 style="margin:18px 0 10px;font-size:13.5px;color:#4f46e5">📋 چک‌لیست مدارک اینماد</h3>
<table class="invoice-table" style="width:100%">
	' . $rows_html . '
</table>'
	. ( $note !== '' ? '<div style="margin-top:12px;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:10px 12px;font-size:12.5px;color:#92400e"><b>توضیح دسترسی:</b> ' . nl2br( eo_e( $note ) ) . '</div>' : '' );
}

function eo_access_type_label( string $type ): string {
	$labels = [
		'info_email' => 'دسترسی به ایمیل info سایت',
		'hosting'    => 'دسترسی به هاست وب‌سایت',
		'both'       => 'ایمیل info و هاست هر دو',
	];
	return $labels[ $type ] ?? '';
}

/** متن ساده برای اعلان تلگرام (بدون HTML) */
function eo_internal_invoice_text( array $order ): string {
	$amount    = eo_toman( (int) ( $order['amount'] ?? 0 ) );
	$total     = eo_toman( (int) ( $order['total_amount'] ?? eo_price_total_rial() ) );
	$remaining = eo_toman( (int) ( $order['remaining'] ?? eo_price_remaining_rial( (int) ( $order['amount'] ?? 0 ) ) ) );
	$lines     = [
		'🟣 پیش‌پرداخت اینماد پرداخت شد',
		'شماره سفارش: ' . ( $order['order_code'] ?? '' ),
		'پیش‌پرداخت: ' . $amount . ' تومان',
		'هزینه کل: ' . $total . ' تومان',
		'مانده: ' . $remaining . ' تومان',
		'',
		'👤 نام: ' . ( $order['full_name'] ?? '' ),
		'🏪 کسب‌وکار: ' . ( $order['business_name'] ?? '' ),
		'📮 کد پستی: ' . ( $order['postal_code'] ?? '' ),
		'☎️ تلفن ثابت: ' . ( $order['landline'] ?? '' ),
		'📱 موبایل: ' . ( $order['mobile'] ?? '' ),
		'📧 ایمیل: ' . ( $order['email'] ?? '' ),
		'🌐 وبسایت: ' . ( $order['website'] ?? '' ),
		'🧾 کد رهگیری مالیاتی: ' . ( $order['tax_code'] ?? '' ),
		'🔑 دسترسی: ' . eo_access_type_label( $order['access_type'] ?? '' ),
	];
	if ( ! empty( $order['access_note'] ) ) {
		$lines[] = '📝 توضیح دسترسی: ' . $order['access_note'];
	}
	return implode( "\n", $lines );
}
