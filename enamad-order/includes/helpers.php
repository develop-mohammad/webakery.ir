<?php
/**
 * توابع کمکی enamad-order — بدون وابستگی به وردپرس.
 */

/** تبدیل ارقام فارسی/عربی به انگلیسی + حذف کاراکترهای اضافه */
function eo_digits_en( string $str ): string {
	$map = [
		'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
		'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
		'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
	];
	return strtr( trim( $str ), $map );
}

/** نرمال‌سازی شماره موبایل ایران به قالب 09xxxxxxxxx — خروجی '' یعنی نامعتبر */
function eo_normalize_mobile( string $raw ): string {
	$raw = eo_digits_en( $raw );
	$raw = preg_replace( '/[\s\-()]/', '', $raw );
	$raw = preg_replace( '/^\+?98/', '0', $raw );
	$raw = preg_replace( '/^0098/', '0', $raw );
	if ( $raw !== '' && $raw[0] !== '0' ) {
		$raw = '0' . $raw;
	}
	if ( ! preg_match( '/^09\d{9}$/', $raw ) ) {
		return '';
	}
	return $raw;
}

/** نرمال‌سازی تلفن ثابت (با یا بدون کد شهر) — فقط ارقام، حداقل ۸ رقم */
function eo_normalize_phone( string $raw ): string {
	$raw = eo_digits_en( $raw );
	$raw = preg_replace( '/[\s\-()]/', '', $raw );
	$raw = preg_replace( '/^\+?98/', '0', $raw );
	if ( $raw !== '' && $raw[0] !== '0' ) {
		$raw = '0' . $raw;
	}
	return preg_match( '/^0\d{7,10}$/', $raw ) ? $raw : '';
}

/** نرمال‌سازی کد پستی — دقیقاً ۱۰ رقم */
function eo_normalize_postal( string $raw ): string {
	$raw = eo_digits_en( $raw );
	$raw = preg_replace( '/\D/', '', $raw );
	return preg_match( '/^\d{10}$/', $raw ) ? $raw : '';
}

/** نرمال‌سازی آدرس وب‌سایت — بدون پروتکل، پروتکل هنگام نمایش اضافه می‌شود */
function eo_normalize_website( string $raw ): string {
	$raw = trim( $raw );
	$raw = preg_replace( '#^https?://#i', '', $raw );
	$raw = rtrim( $raw, '/' );
	return $raw;
}

function eo_website_url( string $domain ): string {
	return $domain !== '' ? 'https://' . $domain : '';
}

/** htmlspecialchars کوتاه */
function eo_e( ?string $s ): string {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}

/** طول رشته با پشتیبانی از UTF-8 بدون وابستگی سخت به mbstring */
function eo_strlen( string $s ): int {
	if ( function_exists( 'mb_strlen' ) ) {
		return mb_strlen( $s, 'UTF-8' );
	}
	return strlen( $s );
}

/** فرمت مبلغ ریال به تومان با جداکننده هزارگان */
function eo_toman( int $rial ): string {
	return number_format( (int) ( $rial / 10 ) );
}

/** درخواست POST به درگاه زیبال */
function eo_zibal_post( string $url, array $data ): array {
	$ch = curl_init( $url );
	curl_setopt( $ch, CURLOPT_POST, true );
	curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $data ) );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json' ] );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_TIMEOUT, 20 );
	$body = curl_exec( $ch );
	curl_close( $ch );
	$decoded = json_decode( $body ?: '{}', true );
	return is_array( $decoded ) ? $decoded : [ 'result' => -1, 'message' => 'پاسخ نامعتبر درگاه' ];
}

/** ساخت کد سفارش کوتاه و یکتا، مثل EN-4032-K7QX */
function eo_generate_order_code(): string {
	$part = strtoupper( substr( bin2hex( random_bytes( 3 ) ), 0, 4 ) );
	return 'EN-' . date( 'ym' ) . '-' . $part;
}

/* ─── تقویم شمسی (مستقل، بدون وابستگی به وردپرس) ─────────────────── */

function eo_gregorian_to_jalali( int $gy, int $gm, int $gd ): array {
	$g_days = [ 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 ];
	$jy     = ( $gy <= 1600 ) ? 0 : 979;
	$gy    -= ( $gy <= 1600 ) ? 621 : 1600;
	$gy2    = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
	$days   = ( 365 * $gy ) + (int) ( ( $gy2 + 3 ) / 4 ) - (int) ( ( $gy2 + 99 ) / 100 )
		+ (int) ( ( $gy2 + 399 ) / 400 ) - 80 + $gd + $g_days[ $gm - 1 ];
	$jy    += 33 * (int) ( $days / 12053 );
	$days   = $days % 12053;
	$jy    += 4 * (int) ( $days / 1461 );
	$days   = $days % 1461;
	if ( $days > 365 ) {
		$jy   += (int) ( ( $days - 1 ) / 365 );
		$days  = ( $days - 1 ) % 365;
	}
	$jm_days = [ 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29 ];
	$i       = 0;
	for ( ; $i < 11 && $days >= $jm_days[ $i ]; $i++ ) {
		$days -= $jm_days[ $i ];
	}
	return [ $jy, $i + 1, $days + 1 ];
}

/** تاریخ شمسی امروز به‌صورت رشته «۱ مرداد ۱۴۰۳ - ساعت ۱۴:۰۵» */
function eo_jalali_now_str(): string {
	$months = [ 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' ];
	[ $jy, $jm, $jd ] = eo_gregorian_to_jalali( (int) date( 'Y' ), (int) date( 'n' ), (int) date( 'j' ) );
	$fa_digits = [ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ];
	$fa        = [ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ];
	$time      = str_replace( $fa_digits, $fa, date( 'H:i' ) );
	$day       = str_replace( $fa_digits, $fa, (string) $jd );
	$year      = str_replace( $fa_digits, $fa, (string) $jy );
	return $day . ' ' . $months[ $jm - 1 ] . ' ' . $year . ' - ساعت ' . $time;
}

/** آدرس پایه سرور (پروتکل + هاست) در نبود EO_BASE_URL */
function eo_detect_base_url(): string {
	if ( defined( 'EO_BASE_URL' ) && EO_BASE_URL !== '' ) {
		return rtrim( EO_BASE_URL, '/' );
	}
	$proto = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) ? 'https' : 'http';
	return $proto . '://' . ( $_SERVER['HTTP_HOST'] ?? 'localhost' );
}

/** آدرس کامل خود همین اسکریپت (independent از نام پوشه نصب) */
function eo_self_url(): string {
	$dir = rtrim( str_replace( '\\', '/', dirname( $_SERVER['SCRIPT_NAME'] ?? '/enamad-order/index.php' ) ), '/' );
	return eo_detect_base_url() . $dir . '/';
}

/** ارسال پیام به تلگرام ادمین (اختیاری) */
function eo_notify_telegram( string $text ): bool {
	$token = defined( 'EO_TG_BOT_TOKEN' ) ? trim( EO_TG_BOT_TOKEN ) : '';
	$chat  = defined( 'EO_TG_CHAT_ID' ) ? trim( EO_TG_CHAT_ID ) : '';
	if ( $token === '' || $chat === '' ) {
		return false;
	}
	$url = 'https://api.telegram.org/bot' . rawurlencode( $token ) . '/sendMessage';
	$ch  = curl_init( $url );
	curl_setopt( $ch, CURLOPT_POST, true );
	curl_setopt( $ch, CURLOPT_POSTFIELDS, [ 'chat_id' => $chat, 'text' => $text ] );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_TIMEOUT, 15 );
	curl_exec( $ch );
	$ok = curl_errno( $ch ) === 0;
	curl_close( $ch );
	return $ok;
}

/** ارسال ایمیل فاکتور جامع داخلی به تیم (اختیاری) */
function eo_notify_email( string $subject, string $html_body ): bool {
	$to = defined( 'EO_NOTIFY_EMAIL' ) ? trim( EO_NOTIFY_EMAIL ) : '';
	if ( $to === '' || ! filter_var( $to, FILTER_VALIDATE_EMAIL ) ) {
		return false;
	}
	$from_domain = parse_url( eo_detect_base_url(), PHP_URL_HOST ) ?: 'webakery.ir';
	$headers     = [
		'MIME-Version: 1.0',
		'Content-Type: text/html; charset=UTF-8',
		'From: فرم اینماد <no-reply@' . $from_domain . '>',
		'X-Mailer: PHP/' . phpversion(),
	];
	return @mail( $to, '=?UTF-8?B?' . base64_encode( $subject ) . '?=', $html_body, implode( "\r\n", $headers ) );
}
