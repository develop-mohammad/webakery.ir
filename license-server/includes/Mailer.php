<?php
/**
 * Mailer — ارسال ایمیل لایسنس به مشتری
 * از mail() خود PHP استفاده می‌کند؛ اگر هاست SMTP لازم دارد،
 * تنظیمات LS_SMTP_* را در config.php تعریف کنید (اختیاری).
 */
class Mailer {

    /** ارسال ایمیل حاوی کلید لایسنس به خریدار */
    public static function send_license_email( array $lic, string $domain = '' ): bool {
        $labels  = defined('LS_PLUGIN_LABELS') ? LS_PLUGIN_LABELS : [];
        $product_label = $labels[ $lic['product'] ] ?? $lic['product'];
        $base    = defined('LS_BASE_URL') ? rtrim(LS_BASE_URL, '/') : '';
        $portal  = $base . '/license-server/portal/';

        $to      = $lic['email'];
        $subject = '🔑 لایسنس شما — ' . $product_label;

        $expires_text = ! empty($lic['expires_at'])
            ? 'تاریخ انقضا: ' . htmlspecialchars($lic['expires_at'])
            : 'مادام‌العمر (بدون انقضا)';

        $domain_html = $domain !== ''
            ? '<tr><td style="padding:8px 12px;color:#64748b">دامنه فعال‌شده</td><td style="padding:8px 12px;font-weight:bold;direction:ltr;text-align:left">' . htmlspecialchars($domain) . '</td></tr>'
            : '';

        $from_domain = parse_url($base ?: 'https://webakery.ir', PHP_URL_HOST) ?: 'webakery.ir';

        $body = '<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Tahoma,Arial,sans-serif">
<div style="max-width:560px;margin:0 auto;padding:24px 12px">

    <!-- هدر -->
    <div style="background:linear-gradient(135deg,#7c3aed 0%,#2563eb 100%);border-radius:14px 14px 0 0;padding:32px 24px;text-align:center">
        <div style="font-size:40px;line-height:1;margin-bottom:12px">🔑</div>
        <h1 style="margin:0;color:#ffffff;font-size:20px;font-weight:bold">لایسنس شما آماده است 🎉</h1>
        <p style="margin:10px 0 0;color:rgba(255,255,255,.85);font-size:13px">' . htmlspecialchars($product_label) . '</p>
    </div>

    <!-- بدنه -->
    <div style="background:#ffffff;border-radius:0 0 14px 14px;padding:28px 24px;box-shadow:0 2px 12px rgba(0,0,0,.06)">

        <p style="margin:0 0 18px;color:#334155;font-size:14px;line-height:1.9">
            سلام 👋<br>
            لایسنس شما با موفقیت صادر شد. مشخصات آن به شرح زیر است:
        </p>

        <!-- جدول مشخصات -->
        <table style="width:100%;border-collapse:collapse;font-size:13px;border:1px solid #e2e8f0;border-radius:10px" cellpadding="0" cellspacing="0">
            <tr style="border-bottom:1px solid #e2e8f0">
                <td style="padding:8px 12px;color:#64748b">محصول</td>
                <td style="padding:8px 12px;font-weight:bold;color:#1e293b">' . htmlspecialchars($product_label) . '</td>
            </tr>
            <tr style="border-bottom:1px solid #e2e8f0">
                <td style="padding:8px 12px;color:#64748b;vertical-align:middle">کلید لایسنس</td>
                <td style="padding:8px 12px">
                    <span style="display:inline-block;background:#f5f3ff;border:1px dashed #c4b5fd;border-radius:8px;padding:8px 12px;font-family:Consolas,Courier,monospace;font-size:14px;font-weight:bold;color:#6d28d9;direction:ltr;text-align:left;word-break:break-all">'
                    . htmlspecialchars($lic['license_key']) .
                    '</span>
                </td>
            </tr>
            <tr style="border-bottom:1px solid #e2e8f0">
                <td style="padding:8px 12px;color:#64748b">وضعیت</td>
                <td style="padding:8px 12px"><span style="background:#dcfce7;color:#166534;padding:2px 10px;border-radius:99px;font-size:12px;font-weight:bold">فعال</span></td>
            </tr>
            <tr' . ( $domain_html !== '' ? ' style="border-bottom:1px solid #e2e8f0"' : '' ) . '>
                <td style="padding:8px 12px;color:#64748b">انقضا</td>
                <td style="padding:8px 12px;font-weight:bold;color:#1e293b">' . $expires_text . '</td>
            </tr>
            ' . $domain_html . '
        </table>

        <!-- دکمه پورتال -->
        <div style="text-align:center;margin:26px 0 18px">
            <a href="' . htmlspecialchars($portal) . '"
               style="display:inline-block;background:linear-gradient(135deg,#7c3aed 0%,#2563eb 100%);color:#ffffff;text-decoration:none;font-size:14px;font-weight:bold;padding:13px 34px;border-radius:10px">
                ورود به پورتال مشتری
            </a>
        </div>

        <p style="margin:0;color:#64748b;font-size:12.5px;line-height:1.9;text-align:center">
            با همین آدرس ایمیل (<span style="direction:ltr;unicode-bidi:embed;color:#334155">' . htmlspecialchars($to) . '</span>)
            می‌توانید وارد پورتال مشتری شوید و همه لایسنس‌های خود را مشاهده و مدیریت کنید.
        </p>

    </div>

    <!-- فوتر -->
    <div style="text-align:center;padding:18px 12px;color:#94a3b8;font-size:11.5px;line-height:1.9">
        اگر سوالی دارید با پشتیبانی در تماس باشید:
        <a href="mailto:info@' . htmlspecialchars($from_domain) . '" style="color:#7c3aed;text-decoration:none">info@' . htmlspecialchars($from_domain) . '</a>
        <br>
        &copy; ' . date('Y') . ' ' . htmlspecialchars($from_domain) . '
    </div>

</div>
</body>
</html>';

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . ($labels ? 'WeBakery' : 'License Server') . ' <no-reply@' . $from_domain . '>',
            'Reply-To: info@' . $from_domain,
            'X-Mailer: PHP/' . phpversion(),
        ];

        return @mail( $to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers) );
    }
}
