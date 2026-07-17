<?php
/**
 * پورتال مشتری — ورود با ایمیل + دامنه سایت
 * بدون نیاز به ثبت‌نام — همان ایمیل و دامنه‌ای که هنگام خرید وارد شده
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/LicenseManager.php';

$login_error = '';

/* ─── خروج ───────────────────────────────────────────────────── */
if ( isset($_GET['logout']) ) {
    session_destroy();
    header('Location: ./');
    exit;
}

/* ─── ساخت لینک Google OAuth ─────────────────────────────────── */
function google_auth_url(): string {
    $state = bin2hex(random_bytes(16));
    $_SESSION['google_oauth_state'] = $state;
    $params = http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'email profile',
        'state'         => $state,
        'prompt'        => 'select_account',
    ]);
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
}

/* ─── ورود دستی (ایمیل + دامنه اختیاری) ─────────────────────── */
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login']) ) {
    $email_raw  = trim($_POST['email']  ?? '');
    $domain_raw = trim($_POST['domain'] ?? '');
    $email_val  = filter_var($email_raw, FILTER_VALIDATE_EMAIL);

    if ( ! $email_val ) {
        $login_error = 'یک ایمیل معتبر وارد کنید.';
    } else {
        $matched      = false;
        $clean_domain = $domain_raw !== '' ? LicenseManager::clean_domain($domain_raw) : '';

        if ( $clean_domain !== '' ) {
            // ─── حالت ۱: ایمیل + دامنه ───
            // تطابق در لایسنس‌ها (از طریق فعال‌سازی‌ها)
            $all = Database::all_data();
            foreach ( $all['licenses'] as $lic ) {
                if ( strtolower($lic['email']) !== strtolower($email_val) ) continue;
                $acts = Database::activations_of($lic['license_key']);
                foreach ( $acts as $act ) {
                    if ( LicenseManager::clean_domain($act['domain']) === $clean_domain ) {
                        $matched = true;
                        break 2;
                    }
                }
            }
            // تطابق در پرداخت‌های موفق (دامنه ثبت‌شده هنگام خرید)
            if ( ! $matched ) {
                foreach ( $all['payments'] as $pay ) {
                    if ( strtolower($pay['email'] ?? '') === strtolower($email_val)
                         && LicenseManager::clean_domain($pay['domain'] ?? '') === $clean_domain
                         && $pay['status'] === 'paid' ) {
                        $matched = true;
                        break;
                    }
                }
            }
        } else {
            // ─── حالت ۲: فقط ایمیل ───
            // اگر لایسنسی (هر وضعیتی) برای این ایمیل وجود دارد → ورود
            if ( Database::license_exists_for_email($email_val) ) {
                $matched = true;
            } elseif ( Database::payment_paid_exists_for_email($email_val) ) {
                $matched = true;
            }
        }

        if ( $matched ) {
            $_SESSION['portal_email']  = $email_val;
            $_SESSION['portal_domain'] = $clean_domain;
            header('Location: ./');
            exit;
        } else {
            if ( $clean_domain !== '' ) {
                $login_error = 'ترکیب ایمیل و دامنه یافت نشد. می‌توانید فقط ایمیل را وارد کنید (دامنه را خالی بگذارید).';
            } else {
                $login_error = 'برای این ایمیل هنوز لایسنسی ثبت نشده. اگر لایسنس دارید، با همون ایمیل از طریق Google وارد شوید.';
            }
        }
    }
}

$authed = ! empty($_SESSION['portal_email']);
$me_email  = $authed ? $_SESSION['portal_email']  : '';

/* ─── بارگذاری داده‌های کاربر ────────────────────────────────── */
$licenses = [];
$payments = [];

if ( $authed ) {
    $all = Database::all_data();

    // مجموعه‌ی کلیدهای لایسنسی که در پرداخت‌های موفق ثبت شده‌اند
    $paid_license_keys = [];
    foreach ( $all['payments'] as $p ) {
        if ( ( $p['status'] ?? '' ) === 'paid' && ! empty( $p['license_key'] ) ) {
            $paid_license_keys[ $p['license_key'] ] = true;
        }
    }

    foreach ( $all['licenses'] as $lic ) {
        if ( strtolower($lic['email']) === strtolower($me_email) ) {
            $lic['activations']    = Database::activations_of($lic['license_key']);
            // اگر این کلید در پرداخت‌های موفق نبود → لایسنس دستی است
            $lic['is_manual']      = ! isset( $paid_license_keys[ $lic['license_key'] ] );
            $licenses[] = $lic;
        }
    }

    foreach ( $all['payments'] as $pay ) {
        if ( strtolower($pay['email'] ?? '') === strtolower($me_email) ) {
            $payments[] = $pay;
        }
    }
    $payments = array_reverse($payments);
}

$plugin_labels = [ 'wccp' => 'BAGET — ویرایشگر صفحه پرداخت' ];
if ( defined('LS_PLUGIN_LABELS') && is_array(LS_PLUGIN_LABELS) ) {
    $plugin_labels = array_merge($plugin_labels, LS_PLUGIN_LABELS);
}

function portal_status_badge(string $s): string {
    $map = [
        'active'  => ['#dcfce7','#166534','فعال'],
        'revoked' => ['#fee2e2','#991b1b','باطل'],
        'expired' => ['#fef3c7','#92400e','منقضی'],
    ];
    [$bg,$fg,$lb] = $map[$s] ?? ['#f3f4f6','#374151',$s];
    return '<span style="background:'.$bg.';color:'.$fg.';padding:2px 10px;border-radius:99px;font-size:11px;font-weight:700">'.$lb.'</span>';
}
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>پورتال مشتری — webakery.ir</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Tahoma,Arial,sans-serif;background:#f3f4f6;color:#1f2937;font-size:14px;min-height:100vh}

/* ─── header ─── */
.ls-header{background:linear-gradient(135deg,#1e1b4b,#4c1d95);color:#fff;padding:0 24px;display:flex;align-items:center;justify-content:space-between;height:56px}
.ls-header__brand{display:flex;align-items:center;gap:10px;font-weight:700;font-size:16px}
.ls-header__brand span{font-size:22px}
.ls-header__user{display:flex;align-items:center;gap:14px;font-size:13px}
.ls-header__user a{color:#c4b5fd;text-decoration:none;font-size:12px;border:1px solid #6d28d9;padding:4px 12px;border-radius:6px}
.ls-header__user a:hover{background:#6d28d9}

/* ─── wrap ─── */
.wrap{max-width:860px;margin:0 auto;padding:28px 16px}

/* ─── login card ─── */
.login-outer{min-height:calc(100vh - 56px);display:flex;align-items:center;justify-content:center;padding:24px}
.login-card{background:#fff;border-radius:16px;box-shadow:0 4px 32px rgba(0,0,0,.10);padding:36px 32px;width:100%;max-width:420px}
.login-card h2{font-size:20px;color:#111827;margin-bottom:6px}
.login-card p{color:#6b7280;font-size:13px;margin-bottom:24px;line-height:1.7}
.field{margin-bottom:14px}
.field label{display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px}
.field input{width:100%;padding:10px 14px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:14px;font-family:inherit;direction:ltr;transition:border-color .15s}
.field input:focus{outline:none;border-color:#6c63ff;box-shadow:0 0 0 3px rgba(108,99,255,.1)}
.field .hint{font-size:11px;color:#9ca3af;margin-top:4px}
.btn-login{width:100%;padding:13px;background:#6c63ff;color:#fff;border:none;border-radius:10px;font-size:15px;font-family:inherit;font-weight:700;cursor:pointer;margin-top:4px;transition:background .15s}
.btn-login:hover{background:#5a52e0}
.login-help{margin-top:22px;padding-top:18px;border-top:1px solid #f3f4f6}
.login-help h3{font-size:12px;font-weight:700;color:#374151;margin-bottom:10px}
.login-help ol{padding-right:18px;color:#6b7280;font-size:12px;line-height:2.2}
.login-help ol li strong{color:#4f46e5}
.error-box{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px}
.btn-google{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:12px;background:#fff;color:#374151;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;font-family:Tahoma,Arial,sans-serif;font-weight:700;cursor:pointer;text-decoration:none;transition:box-shadow .15s,border-color .15s;margin-bottom:4px}
.btn-google:hover{border-color:#6c63ff;box-shadow:0 2px 12px rgba(108,99,255,.15)}
.divider{display:flex;align-items:center;gap:10px;margin:16px 0;color:#d1d5db;font-size:12px}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:#e5e7eb}
details summary::-webkit-details-marker{display:none}

/* ─── dashboard ─── */
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:22px}
.stat-card{background:#fff;border-radius:10px;padding:16px 18px;box-shadow:0 1px 4px rgba(0,0,0,.06);border-right:4px solid #6c63ff}
.stat-card__num{font-size:26px;font-weight:800;color:#4f46e5}
.stat-card__lbl{font-size:11px;color:#9ca3af;margin-top:2px}
.card{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.06);padding:22px;margin-bottom:18px}
.card h2{font-size:15px;color:#374151;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:8px}
.lic-card{border:1.5px solid #e5e7eb;border-radius:10px;padding:16px;margin-bottom:12px}
.lic-card--active{border-color:#22c55e;background:#f0fdf4}
.lic-card--revoked{border-color:#ef4444;background:#fef2f2}
.lic-card__head{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:12px}
.key-box{display:flex;align-items:center;gap:10px;background:#f5f3ff;border:1.5px solid #c4b5fd;border-radius:8px;padding:10px 14px;direction:ltr}
.key-text{font-family:monospace;font-size:13px;color:#4f46e5;font-weight:700;flex:1;word-break:break-all}
.btn-copy{background:#6c63ff;color:#fff;border:none;border-radius:6px;padding:6px 14px;font-size:12px;cursor:pointer;font-family:inherit;white-space:nowrap;transition:background .15s}
.btn-copy:hover{background:#5a52e0}
.domain-tag{display:inline-block;background:#ede9fe;color:#6c63ff;padding:3px 10px;border-radius:6px;font-size:11px;margin:2px}
table{width:100%;border-collapse:collapse}
th,td{padding:10px 12px;text-align:right;border-bottom:1px solid #f0f0f0;font-size:13px}
th{font-size:11px;font-weight:600;color:#9ca3af;background:#f9fafb}
.support-box{background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px 18px;display:flex;align-items:center;gap:12px;margin-top:4px}
.support-box p{color:#1d4ed8;font-size:13px;line-height:1.7}
.support-box a{color:#6c63ff;font-weight:700}
@media(max-width:600px){.stats{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>

<div class="ls-header">
    <div class="ls-header__brand">
        <span>🔑</span> پورتال مشتریان
        <span style="font-size:12px;font-weight:400;color:#a78bfa">webakery.ir</span>
    </div>
    <?php if ($authed): ?>
    <div class="ls-header__user">
        <?php if (!empty($_SESSION['portal_name'])): ?>
            <span>👤 <?= htmlspecialchars($_SESSION['portal_name']) ?></span>
        <?php else: ?>
            <span><?= htmlspecialchars($me_email) ?></span>
        <?php endif; ?>
        <a href="?logout=1">خروج</a>
    </div>
    <?php endif; ?>
</div>

<?php if ( ! $authed ): ?>

<!-- ─── صفحه ورود ────────────────────────────────────────── -->
<div class="login-outer">
    <div class="login-card">
        <h2>ورود / ثبت‌نام</h2>
        <p>با حساب Google خود وارد شوید یا ثبت‌نام کنید.<br>اگر قبلاً خرید داشتید، لایسنس‌هایتان نمایش داده می‌شود.</p>

        <?php if ($login_error): ?>
            <div class="error-box"><?= htmlspecialchars($login_error) ?></div>
        <?php endif; ?>

        <!-- دکمه Google -->
        <a href="<?= htmlspecialchars(google_auth_url()) ?>" class="btn-google">
            <svg width="20" height="20" viewBox="0 0 48 48" style="flex-shrink:0">
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
            </svg>
            ورود / ثبت‌نام با Google
        </a>

        <div class="divider"><span>یا</span></div>

        <details>
            <summary style="cursor:pointer;font-size:12px;color:#9ca3af;text-align:center;list-style:none;margin-bottom:12px">ورود با ایمیل ↓</summary>
            <form method="post" style="margin-top:10px">
                <input type="hidden" name="login" value="1">
                <div class="field">
                    <label>📧 ایمیل</label>
                    <input type="email" name="email" placeholder="yourname@gmail.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                    <div class="hint">همان ایمیلی که لایسنس با آن صادر شده</div>
                </div>
                <div class="field">
                    <label>🌐 آدرس سایت (اختیاری)</label>
                    <input type="text" name="domain" placeholder="myshop.ir"
                           value="<?= htmlspecialchars($_POST['domain'] ?? '') ?>">
                    <div class="hint">اگر یادتان هست وارد کنید — در غیر این صورت خالی بگذارید تا با فقط ایمیل وارد شوید.</div>
                </div>
                <button type="submit" class="btn-login">ورود</button>
            </form>
        </details>

        <div class="login-help">
            <h3>📋 راهنما</h3>
            <ol>
                <li>ساده‌ترین راه: روی <strong>«ورود / ثبت‌نام با Google»</strong> کلیک کنید و Gmail خود را انتخاب کنید.</li>
                <li>اگر لایسنس دارید (خریداری یا دستی ساخته‌شده توسط ادمین): لایسنس‌هایتان نمایش داده می‌شود.</li>
                <li>راه جایگزین: فقط <strong>ایمیل</strong> خود را در فرم بالا وارد کنید (دامنه اختیاری است).</li>
                <li>اگر هنوز خریداری نکرده‌اید: می‌توانید از همان صفحه خرید کنید.</li>
            </ol>
            <p style="margin-top:10px;font-size:11px;color:#9ca3af">در صورت مشکل: <a href="mailto:info@webakery.ir" style="color:#6c63ff">info@webakery.ir</a></p>
        </div>
    </div>
</div>

<?php else: ?>

<!-- ─── داشبورد مشتری ──────────────────────────────────── -->
<div class="wrap">

    <?php
    $active_count  = 0;
    foreach ($licenses as $l) {
        if ($l['status'] === 'active') $active_count++;
    }
    ?>
    <div class="stats">
        <div class="stat-card">
            <div class="stat-card__num"><?= $active_count ?></div>
            <div class="stat-card__lbl">لایسنس فعال</div>
        </div>
        <div class="stat-card" style="border-color:#8b5cf6">
            <div class="stat-card__num" style="color:#7c3aed"><?= count($licenses) ?></div>
            <div class="stat-card__lbl">کل لایسنس‌ها</div>
        </div>
        <div class="stat-card" style="border-color:#10b981">
            <div class="stat-card__num" style="color:#059669"><?= count($payments) ?></div>
            <div class="stat-card__lbl">خرید ثبت‌شده</div>
        </div>
    </div>

    <!-- لایسنس‌ها -->
    <div class="card">
        <h2>🔑 لایسنس‌های شما</h2>

        <?php if ( ! $licenses ): ?>
            <div style="text-align:center;padding:32px 16px">
                <div style="font-size:48px;margin-bottom:12px">🛒</div>
                <div style="font-size:16px;font-weight:700;color:#374151;margin-bottom:8px">هنوز لایسنسی ندارید</div>
                <p style="color:#6b7280;font-size:13px;margin-bottom:20px;line-height:1.9">
                    حساب Google شما ثبت شد.<br>
                    برای دریافت لایسنس پلاگین BAGET یک بار خرید انجام دهید.
                </p>
                <a href="<?= defined('WCCP_CHECKOUT_URL') ? esc_attr(WCCP_CHECKOUT_URL) : 'https://webakery.ir/license-server/pay/?plugin=wccp' ?>"
                   style="display:inline-block;padding:13px 28px;background:#6c63ff;color:#fff;border-radius:10px;text-decoration:none;font-size:15px;font-weight:700">
                    خرید لایسنس BAGET
                </a>
                <div style="margin-top:12px;font-size:12px;color:#9ca3af">
                    پس از خرید، لایسنس شما به همین ایمیل اختصاص می‌یابد
                </div>
            </div>
        <?php else: foreach ($licenses as $lic):
            $status = $lic['status'];
            if ($status === 'active' && !empty($lic['expires_at']) && strtotime($lic['expires_at']) < time()) $status = 'expired';
            $uid = 'lk_' . substr(md5($lic['license_key']), 0, 8);
            $pname = $plugin_labels[$lic['product'] ?? 'wccp'] ?? strtoupper($lic['product'] ?? 'WCCP');
        ?>
        <div class="lic-card lic-card--<?= $status ?>">
            <div class="lic-card__head">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <span style="background:#ede9fe;color:#6c63ff;padding:3px 12px;border-radius:99px;font-size:12px;font-weight:700">
                        <?= htmlspecialchars($pname) ?>
                    </span>
                    <?= portal_status_badge($status) ?>
                    <?php if ( ! empty( $lic['is_manual'] ) ): ?>
                        <span style="background:#fef3c7;color:#92400e;padding:3px 12px;border-radius:99px;font-size:11px;font-weight:700" title="این لایسنس توسط ادمین برای شما صادر شده است">✋ دستی</span>
                    <?php endif; ?>
                </div>
                <div style="font-size:11px;color:#9ca3af">تاریخ صدور: <?= htmlspecialchars($lic['created_at'] ?? '—') ?></div>
            </div>

            <div class="key-box">
                <span class="key-text" id="<?= $uid ?>"><?= htmlspecialchars($lic['license_key']) ?></span>
                <button class="btn-copy"
                    onclick="navigator.clipboard.writeText(document.getElementById('<?= $uid ?>').innerText);this.textContent='✓ کپی شد!';setTimeout(()=>this.textContent='کپی کلید',2000)">
                    کپی کلید
                </button>
            </div>

            <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:6px;align-items:center">
                <span style="font-size:12px;color:#6b7280">🌐 دامنه‌های فعال:</span>
                <?php if ( $lic['activations'] ):
                    foreach ($lic['activations'] as $act): ?>
                        <span class="domain-tag"><?= htmlspecialchars($act['domain']) ?></span>
                    <?php endforeach;
                else: ?>
                    <span style="font-size:12px;color:#f59e0b">هنوز روی هیچ دامنه‌ای فعال نشده — کلید را در پلاگین وردپرس وارد کنید</span>
                <?php endif; ?>
            </div>

            <?php if ( !empty($lic['expires_at']) ): ?>
            <div style="margin-top:6px;font-size:12px;color:#6b7280">📅 انقضا: <?= htmlspecialchars($lic['expires_at']) ?></div>
            <?php else: ?>
            <div style="margin-top:6px;font-size:12px;color:#22c55e">✅ لایسنس مادام‌العمر</div>
            <?php endif; ?>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- تاریخچه خریدها -->
    <?php if ($payments): ?>
    <div class="card">
        <h2>💳 تاریخچه خریدها</h2>
        <table>
            <thead>
                <tr>
                    <th>تاریخ</th>
                    <th>دامنه</th>
                    <th>پلاگین</th>
                    <th>مبلغ (تومان)</th>
                    <th>وضعیت</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($payments as $p):
                $smap  = ['paid'=>['#dcfce7','#166534','موفق'],'failed'=>['#fee2e2','#991b1b','ناموفق'],'pending'=>['#e0f2fe','#0369a1','در انتظار']];
                [$bg,$fg,$lb] = $smap[$p['status']] ?? ['#f3f4f6','#374151',$p['status']];
            ?>
            <tr>
                <td style="font-size:12px;color:#9ca3af"><?= htmlspecialchars($p['created_at'] ?? '—') ?></td>
                <td><?= htmlspecialchars($p['domain'] ?? '—') ?></td>
                <td><span style="background:#ede9fe;color:#6c63ff;padding:2px 8px;border-radius:99px;font-size:11px"><?= htmlspecialchars($plugin_labels[$p['plugin'] ?? ''] ?? strtoupper($p['plugin'] ?? '—')) ?></span></td>
                <td><?= number_format((int)(($p['amount'] ?? 0) / 10)) ?></td>
                <td><span style="background:<?=$bg?>;color:<?=$fg?>;padding:2px 10px;border-radius:99px;font-size:11px;font-weight:700"><?=$lb?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- پشتیبانی -->
    <div class="support-box">
        <span style="font-size:28px">💬</span>
        <p>
            اگر مشکلی دارید یا سایت‌تان تغییر کرده و نیاز به انتقال لایسنس دارید،<br>
            با پشتیبانی تماس بگیرید: <a href="mailto:info@webakery.ir">info@webakery.ir</a>
        </p>
    </div>

</div>
<?php endif; ?>

</body>
</html>
