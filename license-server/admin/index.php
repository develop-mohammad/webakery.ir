<?php
/**
 * License Server Admin Panel
 */
session_start();
if ( file_exists( __DIR__ . '/../config.php' ) ) require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/LicenseManager.php';
require_once __DIR__ . '/../includes/Mailer.php';
require_once __DIR__ . '/../includes/UpdateManager.php';
require_once __DIR__ . '/../includes/AdminAuth.php';

if ( ! defined('ADMIN_USER') ) define( 'ADMIN_USER', 'admin' );
if ( ! defined('ADMIN_PASS') ) define( 'ADMIN_PASS', 'change-this-password' );

if ( isset( $_POST['login'] ) ) {
    $u = (string) ( $_POST['u'] ?? '' );
    $p = (string) ( $_POST['p'] ?? '' );
    if ( AdminAuth::verify( $u, $p ) ) {
        $_SESSION['ls_auth'] = true;
        $_SESSION['ls_admin_user'] = AdminAuth::username();
    } else {
        $login_error = 'نام کاربری یا رمز اشتباه است.';
    }
}
if ( isset( $_GET['logout'] ) ) { session_destroy(); header('Location: ?'); exit; }

$authed = ! empty( $_SESSION['ls_auth'] );
$tab    = $_GET['tab'] ?? 'licenses';
$msg    = '';
$msg_type = 'info'; // info | success | error

if ( $authed ) {
    // ─── عملیات لایسنس ───────────────────────────────────────────
    if ( isset( $_POST['create'] ) ) {
        $email   = trim( $_POST['email']   ?? '' );
        $product = trim( $_POST['product'] ?? 'wccp' ) ?: 'wccp';
        $note    = trim( $_POST['note']    ?? '' );
        $expires = trim( $_POST['expires'] ?? '' ) ?: null;
        $domain  = trim( $_POST['domain']  ?? '' );
        if ( $email ) {
            $lic = LicenseManager::create( $email, $product, $note, $expires, $domain );
            $msg = 'لایسنس ایجاد شد: <strong>' . htmlspecialchars($lic['license_key']) . '</strong>'
                 . ( $domain !== '' ? ' و روی دامنه <strong>' . htmlspecialchars(LicenseManager::clean_domain($domain)) . '</strong> فعال شد.' : '' );
            $msg_type = 'success';
            if ( ! empty( $_POST['send_email'] ) ) {
                $sent = Mailer::send_license_email( $lic, $domain !== '' ? LicenseManager::clean_domain($domain) : '' );
                $msg .= $sent
                    ? ' — ایمیل حاوی کلید برای مشتری ارسال شد. 📧'
                    : ' — ⚠️ ارسال ایمیل ناموفق بود (تنظیمات ایمیل سرور را بررسی کنید).';
            }
        } else {
            $msg = 'ایمیل الزامی است.';
            $msg_type = 'error';
        }
    }
    if ( isset( $_GET['revoke'] ) )  { LicenseManager::revoke( $_GET['revoke'] );   $msg = 'لایسنس باطل شد.'; $msg_type = 'success'; }
    if ( isset( $_GET['restore'] ) ) { LicenseManager::restore( $_GET['restore'] ); $msg = 'لایسنس بازیابی شد.'; $msg_type = 'success'; }
    if ( isset( $_GET['delete'], $_GET['confirm'] ) ) { LicenseManager::delete( $_GET['delete'] ); $msg = 'لایسنس حذف شد.'; $msg_type = 'success'; }
    if ( isset( $_GET['remove_domain'], $_GET['key'] ) ) {
        LicenseManager::deactivate_domain( $_GET['key'], $_GET['remove_domain'] );
        $msg = 'دامنه از لایسنس جدا شد.';
        $msg_type = 'success';
    }

    // ─── عملیات کد تخفیف ─────────────────────────────────────────
    if ( isset( $_POST['coupon_create'] ) ) {
        $r = CouponManager::create( [
            'code'       => $_POST['coupon_code']       ?? '',
            'type'       => $_POST['coupon_type']       ?? 'percent',
            'value'      => $_POST['coupon_value']      ?? 0,
            'product'    => $_POST['coupon_product']    ?? 'all',
            'max_uses'   => $_POST['coupon_max_uses']   ?? 0,
            'min_amount' => $_POST['coupon_min_amount'] ?? 0,
            'expires_at' => $_POST['coupon_expires']    ?? '',
            'note'       => $_POST['coupon_note']       ?? '',
            'status'     => 'active',
        ] );
        $msg = $r['message'];
        $msg_type = $r['success'] ? 'success' : 'error';
    }
    if ( isset( $_POST['coupon_update'] ) ) {
        $r = CouponManager::update( $_POST['coupon_id'], [
            'code'       => $_POST['coupon_code']       ?? '',
            'type'       => $_POST['coupon_type']       ?? 'percent',
            'value'      => $_POST['coupon_value']      ?? 0,
            'product'    => $_POST['coupon_product']    ?? 'all',
            'max_uses'   => $_POST['coupon_max_uses']   ?? 0,
            'min_amount' => $_POST['coupon_min_amount'] ?? 0,
            'expires_at' => $_POST['coupon_expires']    ?? '',
            'note'       => $_POST['coupon_note']       ?? '',
            'status'     => $_POST['coupon_status']     ?? 'active',
        ] );
        $msg = $r['message'];
        $msg_type = $r['success'] ? 'success' : 'error';
    }
    if ( isset( $_GET['coupon_toggle'] ) ) {
        $r = CouponManager::toggle( $_GET['coupon_toggle'] );
        $msg = $r['message'];
        $msg_type = $r['success'] ? 'success' : 'error';
    }
    if ( isset( $_GET['coupon_delete'] ) ) {
        $r = CouponManager::delete( $_GET['coupon_delete'] );
        $msg = $r['message'];
        $msg_type = $r['success'] ? 'success' : 'error';
    }

    // ─── تغییر نام‌کاربری/رمز عبور پنل ادمین ──
    // در data/admin-auth.json ذخیره می‌شود و در صورت امکان config.php هم هم‌سان می‌شود.
    if ( isset( $_POST['save_account'] ) ) {
        $result = AdminAuth::update(
            (string) ( $_POST['current_pass'] ?? '' ),
            (string) ( $_POST['new_username'] ?? '' ),
            (string) ( $_POST['new_pass'] ?? '' ),
            (string) ( $_POST['confirm_pass'] ?? '' )
        );
        $msg      = $result['message'];
        $msg_type = $result['success'] ? 'success' : 'error';
        if ( ! empty( $result['success'] ) ) {
            $_SESSION['ls_admin_user'] = AdminAuth::username();
        }
    }

    $page    = max( 1, (int)($_GET['p'] ?? 1) );
    $limit   = 20;
    $total   = LicenseManager::total();
    $licenses = LicenseManager::all( $limit, ($page-1)*$limit );

    // مجموعه‌ی کلیدهای لایسنسی که در پرداخت‌های موفق ثبت شده‌اند → برای تشخیص لایسنس دستی
    $paid_license_keys  = [];
    $paid_license_dates = [];
    $db_data_tmp = Database::all_data();
    foreach ( $db_data_tmp['payments'] ?? [] as $p ) {
        if ( ( $p['status'] ?? '' ) === 'paid' && ! empty( $p['license_key'] ) ) {
            $paid_license_keys[ $p['license_key'] ] = true;
            if ( ! empty( $p['created_at'] ) ) {
                $paid_license_dates[ $p['license_key'] ] = $p['created_at'];
            }
        }
    }
    foreach ( $licenses as &$lic_ref ) {
        $lic_ref['is_manual']     = ! isset( $paid_license_keys[ $lic_ref['license_key'] ] );
        $lic_ref['purchased_at']  = $paid_license_dates[ $lic_ref['license_key'] ] ?? null;
    }
    unset( $lic_ref );

    $plugin_labels = [ 'wccp' => 'BAGET — ویرایشگر صفحه پرداخت' ];
    if ( defined('LS_PLUGIN_LABELS') && is_array(LS_PLUGIN_LABELS) ) {
        $plugin_labels = array_merge( $plugin_labels, LS_PLUGIN_LABELS );
    }

    $detail_key         = $_GET['detail'] ?? null;
    $detail_activations = $detail_key ? LicenseManager::activations_of($detail_key) : [];

    // لیست محصولات از config برای dropdown
    $products_list = [ 'wccp' => 'wccp' ];
    if ( defined('LS_PLUGIN_LABELS') && is_array(LS_PLUGIN_LABELS) ) {
        foreach ( LS_PLUGIN_LABELS as $slug => $label ) {
            $products_list[ $slug ] = $slug . ' — ' . $label;
        }
    }

    // کوپن‌ها
    $coupons      = Database::coupon_all();
    $coupons_total = Database::coupon_count();

    // ویرایش کوپن
    $edit_coupon = null;
    if ( isset( $_GET['coupon_edit'] ) ) {
        $edit_coupon = Database::coupon_find_by_id( $_GET['coupon_edit'] );
    }

    // ─── بارگذاری خریدها ─────────────────────────────────────────
    $db_data  = Database::all_data();
    $payments = array_reverse( $db_data['payments'] ?? [] );
}

// تولید کد پیشنهادی برای فرم
$suggested_code = CouponManager::generate_code(8);

?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>پنل لایسنس — webakery.ir</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Vazirmatn',Tahoma,Arial,sans-serif;background:#f1f5f9;color:#1e293b;font-size:14px;line-height:1.6}
.wrap{max-width:1180px;margin:0 auto;padding:28px 18px 60px}

/* ── هدر ── */
.topbar{background:linear-gradient(135deg,#7c3aed 0%,#4f46e5 55%,#2563eb 100%);border-radius:18px;padding:26px 30px;
  color:#fff;margin-bottom:22px;box-shadow:0 10px 32px rgba(76,29,149,.22);position:relative;overflow:hidden;
  display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px}
.topbar::before{content:'';position:absolute;top:-70px;left:-50px;width:220px;height:220px;border-radius:50%;
  background:rgba(255,255,255,.14);filter:blur(10px)}
.topbar h1{position:relative;font-size:19px;font-weight:800;display:flex;align-items:center;gap:10px}
.topbar h1 .ic{font-size:26px}
.topbar .sub{position:relative;font-size:12px;opacity:.82;font-weight:400;margin-top:4px}

h2{font-size:15px}
.card{background:#fff;border-radius:16px;box-shadow:0 2px 14px rgba(15,23,42,.06);border:1px solid #eef2f7;
  padding:22px 24px;margin-bottom:22px}
.card h2{font-size:15px;font-weight:700;margin-bottom:16px;color:#1e293b;border-bottom:1px solid #f1f5f9;
  padding-bottom:12px;display:flex;align-items:center;gap:8px}
label{display:block;font-size:12px;font-weight:600;color:#64748b;margin-bottom:6px}
input[type=text],input[type=email],input[type=password],input[type=date],input[type=number],select,textarea{
  width:100%;padding:10px 13px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:13px;font-family:inherit;
  background:#fbfcfe;transition:border-color .15s,box-shadow .15s;color:#1e293b}
input:focus,select:focus,textarea:focus{outline:none;border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.12);background:#fff}
textarea{resize:vertical;min-height:44px}
.row{display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr auto;gap:14px;align-items:end}
.row-4{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:14px;align-items:end}
.row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;align-items:end}
.row-2{display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:end}

.btn{display:inline-flex;align-items:center;gap:5px;padding:10px 20px;border-radius:10px;border:none;cursor:pointer;
  font-size:13px;font-weight:700;text-decoration:none;font-family:inherit;transition:transform .12s,box-shadow .12s,background .15s}
.btn:active{transform:translateY(1px)}
.btn-primary{background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;box-shadow:0 6px 16px rgba(124,58,237,.32)}
.btn-primary:hover{box-shadow:0 8px 20px rgba(124,58,237,.44)}
.btn-danger{background:#fef2f2;color:#dc2626;border:1.5px solid #fecaca}
.btn-danger:hover{background:#fee2e2}
.btn-sm{padding:6px 13px;font-size:12px;border-radius:8px}
.btn-success{background:#ecfdf5;color:#059669;border:1.5px solid #a7f3d0}
.btn-success:hover{background:#d1fae5}
.btn-warning{background:#fffbeb;color:#b45309;border:1.5px solid #fde68a}
.btn-secondary{background:#f1f5f9;color:#475569;border:1.5px solid #e2e8f0}
.btn-secondary:hover{background:#e2e8f0}

.tabs{display:flex;gap:6px;margin-bottom:22px;background:#e9edf5;padding:6px;border-radius:14px;flex-wrap:wrap;width:fit-content}
.tab{padding:9px 20px;border-radius:10px;border:none;background:none;cursor:pointer;font-family:inherit;
  font-size:13px;font-weight:600;color:#64748b;transition:background .15s,color .15s}
.tab:hover{color:#4f46e5}
.tab.active{background:#fff;color:#7c3aed;box-shadow:0 2px 8px rgba(15,23,42,.08)}

table{width:100%;border-collapse:collapse}
th,td{padding:12px 14px;text-align:right;border-bottom:1px solid #f1f5f9;font-size:13px}
th{background:#f8fafc;font-weight:700;color:#64748b;font-size:11px;letter-spacing:.02em}
tr:hover td{background:#f8fafc}
tbody tr:last-child td{border-bottom:none}

.badge{display:inline-block;padding:3px 11px;border-radius:99px;font-size:11px;font-weight:700}
.badge-active,.badge-paid{background:#dcfce7;color:#166534}
.badge-revoked,.badge-failed{background:#fee2e2;color:#b91c1c}
.badge-expired{background:#fef3c7;color:#92400e}
.badge-pending{background:#e0f2fe;color:#0369a1}
.badge-disabled{background:#f1f5f9;color:#64748b}

.key-code{font-family:Consolas,monospace;font-size:12px;color:#6d28d9;background:#f5f3ff;padding:4px 9px;
  border-radius:7px;border:1px solid #e9d5ff}

.msg{padding:13px 18px;border-radius:12px;margin-bottom:18px;font-size:13px;font-weight:500}
.msg-info{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}
.msg-success{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
.msg-error{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}

.login-wrap{max-width:380px;margin:90px auto}
.login-wrap.card{padding:32px 28px;text-align:center}
.login-wrap .ic{font-size:42px;margin-bottom:6px}

.pager{display:flex;gap:8px;margin-top:16px;justify-content:center}
.hint{font-size:11.5px;color:#94a3b8;margin-top:4px;display:block}

.coupon-preview{background:#f5f3ff;border:1px dashed #c4b5fd;border-radius:10px;padding:12px 14px;margin-top:12px;font-size:12.5px;color:#4c1d95}
.coupon-preview b{color:#7c3aed}
.copy-link{cursor:pointer;color:#7c3aed;text-decoration:underline;font-size:12px;font-weight:600}

@media(max-width:768px){
  .row,.row-4{grid-template-columns:1fr 1fr}
  .row-3{grid-template-columns:1fr}
  .topbar{padding:20px}
}
</style>
</head>
<body>
<div class="wrap">
<div class="topbar">
  <div>
    <h1><span class="ic">🔑</span> پنل مدیریت لایسنس</h1>
    <div class="sub">webakery.ir — مدیریت لایسنس‌ها، کدهای تخفیف و خریدها</div>
  </div>
</div>

<?php if ( ! $authed ): ?>
<div class="login-wrap card">
    <div class="ic">🛡️</div>
    <h2 style="justify-content:center;border-bottom:none;padding-bottom:6px;margin-bottom:6px">ورود به پنل</h2>
    <p style="color:#94a3b8;font-size:12.5px;margin-bottom:20px">برای مدیریت لایسنس‌ها وارد شوید</p>
    <?php if ( !empty($login_error) ): ?>
        <div class="msg msg-error"><?= $login_error ?></div>
    <?php endif; ?>
    <form method="post" style="text-align:right">
        <div style="margin-bottom:14px"><label>نام کاربری</label><input type="text" name="u" autofocus autocomplete="username"></div>
        <div style="margin-bottom:18px"><label>رمز عبور</label><input type="password" name="p" autocomplete="current-password"></div>
        <button type="submit" class="btn btn-primary" name="login" value="1" style="width:100%;justify-content:center">ورود</button>
    </form>
    <p style="color:#94a3b8;font-size:11.5px;margin-top:14px;line-height:1.7">
        اگر رمز را فراموش کردید:
        <code dir="ltr">tools/reset-admin.php?key=RESET_NOW_2026</code>
        — بعد از ریست همان فایل را حذف کنید.
    </p>
</div>

<?php else: ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div class="tabs">
        <button class="tab <?= $tab==='licenses'?'active':'' ?>" onclick="location='?tab=licenses'">📋 لایسنس‌ها (<?= $total ?>)</button>
        <button class="tab <?= $tab==='coupons'?'active':'' ?>" onclick="location='?tab=coupons'">🎟️ کد تخفیف (<?= $coupons_total ?>)</button>
        <button class="tab <?= $tab==='payments'?'active':'' ?>" onclick="location='?tab=payments'">💳 خریدها (<?= count($payments) ?>)</button>
        <button class="tab <?= $tab==='account'?'active':'' ?>" onclick="location='?tab=account'">👤 تنظیمات حساب</button>
    </div>
    <a href="?logout=1" class="btn btn-sm btn-secondary">خروج</a>
</div>

<?php if ( $msg ): ?><div class="msg msg-<?= htmlspecialchars($msg_type) ?>"><?= $msg ?></div><?php endif; ?>

<?php if ( $tab === 'licenses' ): ?>

<!-- فرم ایجاد لایسنس -->
<div class="card">
    <h2>➕ ایجاد لایسنس دستی (ایمیل + دامنه)</h2>
    <form method="post">
        <div class="row">
            <div>
                <label>ایمیل خریدار *</label>
                <input type="email" name="email" required placeholder="user@example.com">
            </div>
            <div>
                <label>پلاگین (slug)</label>
                <select name="product">
                    <?php foreach ( $products_list as $slug => $lbl ): ?>
                    <option value="<?= htmlspecialchars($slug) ?>" <?= ($slug==='wccp')?'selected':'' ?>><?= htmlspecialchars($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>دامنه (اختیاری)</label>
                <input type="text" name="domain" placeholder="example.com — برای فعال‌سازی خودکار">
                <span class="hint">با پر کردن این فیلد، لایسنس همانجا روی این دامنه فعال می‌شود.</span>
            </div>
            <div>
                <label>یادداشت</label>
                <input type="text" name="note" placeholder="نام خریدار، شماره سفارش...">
            </div>
            <div>
                <label>انقضا (خالی = مادام‌العمر)</label>
                <input type="date" name="expires">
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-start">
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;margin-bottom:0"><input type="checkbox" name="send_email" value="1" checked> ارسال ایمیل به مشتری</label>
                <button class="btn btn-primary" name="create">ایجاد</button>
            </div>
        </div>
    </form>
</div>

<!-- جزئیات دامنه‌های فعال -->
<?php if ( $detail_key ): ?>
<div class="card">
    <h2>📡 دامنه‌های فعال: <?= htmlspecialchars($detail_key) ?></h2>
    <?php if ( $detail_activations ): ?>
    <table>
        <tr><th>دامنه</th><th>IP</th><th>تاریخ فعال‌سازی</th><th>عملیات</th></tr>
        <?php foreach ( $detail_activations as $a ): ?>
        <tr>
            <td><?= htmlspecialchars($a['domain']) ?></td>
            <td style="color:#9ca3af"><?= htmlspecialchars($a['ip'] ?? '—') ?></td>
            <td><?= $a['activated_at'] ?></td>
            <td>
                <a class="btn btn-sm btn-danger" href="?tab=licenses&remove_domain=<?= urlencode($a['domain']) ?>&key=<?= urlencode($detail_key) ?>"
                   onclick="return confirm('جدا کردن این دامنه؟')">جدا کردن</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
        <p style="color:#9ca3af">هنوز روی هیچ دامنه‌ای فعال نشده.</p>
    <?php endif; ?>
    <div style="margin-top:12px"><a href="?tab=licenses" class="btn btn-sm btn-secondary">← بازگشت</a></div>
</div>
<?php endif; ?>

<!-- لیست لایسنس‌ها -->
<div class="card">
    <h2>📋 لیست لایسنس‌ها</h2>
    <table>
        <tr><th>کلید لایسنس</th><th>ایمیل</th><th>محصول</th><th>وضعیت</th><th>منبع</th><th>تاریخ خرید</th><th>دامنه‌ها</th><th>انقضا</th><th>یادداشت</th><th>عملیات</th></tr>
        <?php foreach ( $licenses as $l ):
            $status = $l['status'];
            if ( $status === 'active' && $l['expires_at'] && strtotime($l['expires_at']) < time() ) $status = 'expired';
            $badge_map  = ['active'=>'badge-active','revoked'=>'badge-revoked','expired'=>'badge-expired'];
            $status_map = ['active'=>'فعال','revoked'=>'باطل','expired'=>'منقضی'];
            $slug       = $l['product'] ?? 'wccp';
            $pname      = $plugin_labels[ $slug ] ?? strtoupper( $slug );
        ?>
        <tr>
            <td><span class="key-code"><?= htmlspecialchars($l['license_key']) ?></span></td>
            <td><?= htmlspecialchars($l['email']) ?></td>
            <td>
                <div style="font-weight:600;color:#1e293b;font-size:12px;line-height:1.5"><?= htmlspecialchars($pname) ?></div>
                <span style="background:#ede9fe;color:#6c63ff;padding:2px 8px;border-radius:99px;font-size:10px;margin-top:4px;display:inline-block"><?= htmlspecialchars($slug) ?></span>
            </td>
            <td><span class="badge <?= $badge_map[$status] ?? '' ?>"><?= $status_map[$status] ?? $status ?></span></td>
            <td>
                <?php if ( ! empty( $l['is_manual'] ) ): ?>
                    <span class="badge badge-expired" title="ساخته‌شده توسط ادمین">✋ دستی</span>
                <?php else: ?>
                    <span class="badge badge-active">💳 خرید</span>
                <?php endif; ?>
            </td>
            <td style="font-size:12px;color:#6b7280;white-space:nowrap">
                <?php if ( ! empty( $l['purchased_at'] ) ): ?>
                    <?= htmlspecialchars( $l['purchased_at'] ) ?>
                <?php elseif ( ! empty( $l['is_manual'] ) ): ?>
                    <span style="color:#9ca3af">—</span>
                    <?php if ( ! empty( $l['created_at'] ) ): ?>
                        <div style="font-size:10px;color:#d1d5db;margin-top:2px">صدور: <?= htmlspecialchars( $l['created_at'] ) ?></div>
                    <?php endif; ?>
                <?php else: ?>
                    <?= htmlspecialchars( $l['created_at'] ?? '—' ) ?>
                <?php endif; ?>
            </td>
            <td>
                <a href="?tab=licenses&detail=<?= urlencode($l['license_key']) ?>" style="color:#6c63ff;text-decoration:none">
                    <?= $l['activation_count'] ?> دامنه
                </a>
            </td>
            <td><?= $l['expires_at'] ?: '♾' ?></td>
            <td style="color:#6b7280;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($l['note'] ?? '') ?></td>
            <td style="white-space:nowrap">
                <?php if ( $l['status'] === 'active' ): ?>
                    <a class="btn btn-sm btn-danger" href="?tab=licenses&revoke=<?= urlencode($l['license_key']) ?>">باطل</a>
                <?php else: ?>
                    <a class="btn btn-sm btn-success" href="?tab=licenses&restore=<?= urlencode($l['license_key']) ?>">بازیابی</a>
                <?php endif; ?>
                <a class="btn btn-sm btn-danger" href="?tab=licenses&delete=<?= urlencode($l['license_key']) ?>&confirm=1"
                   onclick="return confirm('حذف دائمی؟')">حذف</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php $pages = ceil( $total / $limit ); if ( $pages > 1 ): ?>
    <div class="pager">
        <?php for ( $i=1; $i<=$pages; $i++ ): ?>
        <a href="?tab=licenses&p=<?= $i ?>" class="btn btn-sm <?= $i===$page?'btn-primary':'' ?>"
           style="<?= $i===$page?'':'background:#f3f4f6;color:#374151' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php elseif ( $tab === 'coupons' ): ?>

<!-- فرم ایجاد/ویرایش کد تخفیف -->
<div class="card">
    <h2><?= $edit_coupon ? '✏️ ویرایش کد تخفیف' : '🎟️ ایجاد کد تخفیف جدید' ?></h2>
    <form method="post">
        <?php if ( $edit_coupon ): ?>
            <input type="hidden" name="coupon_id" value="<?= htmlspecialchars($edit_coupon['id']) ?>">
            <input type="hidden" name="coupon_update" value="1">
        <?php else: ?>
            <input type="hidden" name="coupon_create" value="1">
        <?php endif; ?>

        <div class="row-4">
            <div>
                <label>کد تخفیف *</label>
                <input type="text" name="coupon_code" required
                       value="<?= $edit_coupon ? htmlspecialchars($edit_coupon['code']) : htmlspecialchars($suggested_code) ?>"
                       style="direction:ltr;text-align:left;font-family:monospace;text-transform:uppercase">
                <span class="hint">با حروف بزرگ. پیشنهاد: <span class="copy-link" onclick="document.querySelector('[name=coupon_code]').value='<?= htmlspecialchars($suggested_code) ?>'"><?= htmlspecialchars($suggested_code) ?></span></span>
            </div>
            <div>
                <label>نوع تخفیف</label>
                <select name="coupon_type" id="coupon_type">
                    <option value="percent" <?= ( $edit_coupon && $edit_coupon['type']==='percent' ) ? 'selected' : '' ?>>درصدی (٪)</option>
                    <option value="fixed" <?= ( $edit_coupon && $edit_coupon['type']==='fixed' ) ? 'selected' : '' ?>>مبلغ ثابت (ریال)</option>
                </select>
            </div>
            <div>
                <label>مقدار تخفیف *</label>
                <input type="number" name="coupon_value" min="1" required
                       value="<?= $edit_coupon ? htmlspecialchars($edit_coupon['value']) : '10' ?>">
                <span class="hint" id="value_hint">مثلاً ۲۰ یعنی ۲۰٪ تخفیف</span>
            </div>
            <div>
                <label>محصول قابل استفاده</label>
                <select name="coupon_product">
                    <option value="all" <?= ( $edit_coupon && $edit_coupon['product']==='all' ) ? 'selected' : '' ?>>همه محصولات</option>
                    <?php foreach ( $products_list as $slug => $lbl ): ?>
                    <option value="<?= htmlspecialchars($slug) ?>" <?= ( $edit_coupon && $edit_coupon['product']===$slug ) ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="row-4" style="margin-top:12px">
            <div>
                <label>حداکثر استفاده (۰ = نامحدود)</label>
                <input type="number" name="coupon_max_uses" min="0"
                       value="<?= $edit_coupon ? htmlspecialchars($edit_coupon['max_uses'] ?? 0) : '0' ?>">
            </div>
            <div>
                <label>حداقل مبلغ سفارش (ریال — ۰ = بدون شرط)</label>
                <input type="number" name="coupon_min_amount" min="0"
                       value="<?= $edit_coupon ? htmlspecialchars($edit_coupon['min_amount'] ?? 0) : '0' ?>">
                <span class="hint">مثلاً ۵۰۰۰۰۰۰ ریال = ۵۰۰,۰۰۰ تومان</span>
            </div>
            <div>
                <label>تاریخ انقضا (خالی = بدون انقضا)</label>
                <input type="date" name="coupon_expires"
                       value="<?= $edit_coupon && !empty($edit_coupon['expires_at']) ? htmlspecialchars($edit_coupon['expires_at']) : '' ?>">
            </div>
            <div>
                <label>یادداشت</label>
                <input type="text" name="coupon_note" placeholder="مثلاً: کمپین تابستانه"
                       value="<?= $edit_coupon ? htmlspecialchars($edit_coupon['note'] ?? '') : '' ?>">
            </div>
        </div>

        <?php if ( $edit_coupon ): ?>
        <div style="margin-top:12px">
            <label>وضعیت</label>
            <select name="coupon_status">
                <option value="active" <?= ( $edit_coupon['status'] ?? 'active' )==='active' ? 'selected' : '' ?>>فعال</option>
                <option value="disabled" <?= ( $edit_coupon['status'] ?? '' )==='disabled' ? 'selected' : '' ?>>غیرفعال</option>
            </select>
        </div>
        <?php endif; ?>

        <div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap">
            <button class="btn btn-primary" type="submit"><?= $edit_coupon ? 'ذخیره تغییرات' : 'ایجاد کد تخفیف' ?></button>
            <?php if ( $edit_coupon ): ?>
                <a href="?tab=coupons" class="btn btn-secondary">انصراف</a>
            <?php endif; ?>
        </div>

        <div class="coupon-preview" id="coupon_preview"></div>
    </form>

    <script>
    (function(){
        var typeSel = document.getElementById('coupon_type');
        var valInp  = document.querySelector('[name=coupon_value]');
        var prodInp = document.querySelector('[name=coupon_product]');
        var hint    = document.getElementById('value_hint');
        var preview = document.getElementById('coupon_preview');
        function fmtToman(rial){ return (rial/10).toLocaleString('fa-IR') + ' تومان'; }
        function refresh(){
            var t = typeSel.value;
            if ( t === 'percent' ) {
                hint.textContent = 'مثلاً ۲۰ یعنی ۲۰٪ تخفیف';
            } else {
                hint.textContent = 'مبلغ ثابت به ریال — مثلاً ۱۰۰۰۰۰۰ = ۱۰۰,۰۰۰ تومان';
            }
            var v = parseInt(valInp.value, 10) || 0;
            var sampleBase = 1990000; // 199,000 تومان به عنوان نمونه (قیمت BAGET)
            var discount = t === 'percent' ? Math.round(sampleBase * v / 100) : v;
            if ( discount > sampleBase ) discount = sampleBase;
            var final = sampleBase - discount;
            preview.innerHTML = '📊 نمونه: برای سفارش <b>' + fmtToman(sampleBase) + '</b> ' +
                '→ تخفیف <b>' + fmtToman(discount) + '</b> ' +
                '→ قابل پرداخت <b>' + fmtToman(final) + '</b> ' +
                '(محصول انتخابی: ' + prodInp.options[prodInp.selectedIndex].text + ')';
        }
        typeSel.addEventListener('change', refresh);
        valInp.addEventListener('input', refresh);
        prodInp.addEventListener('change', refresh);
        refresh();
    })();
    </script>
</div>

<!-- لیست کدهای تخفیف -->
<div class="card">
    <h2>🎟️ لیست کدهای تخفیف</h2>
    <?php if ( $coupons ): ?>
    <table>
        <tr>
            <th>کد</th>
            <th>نوع</th>
            <th>مقدار</th>
            <th>محصول</th>
            <th>استفاده</th>
            <th>انقضا</th>
            <th>وضعیت</th>
            <th>یادداشت</th>
            <th>عملیات</th>
        </tr>
        <?php foreach ( $coupons as $c ):
            $is_active = ( $c['status'] ?? 'active' ) === 'active';
            $is_expired = ! empty( $c['expires_at'] ) && strtotime( $c['expires_at'] ) < time();
            $is_exhausted = (int)( $c['max_uses'] ?? 0 ) > 0 && (int)( $c['used_count'] ?? 0 ) >= (int)( $c['max_uses'] ?? 0 );
        ?>
        <tr>
            <td><span class="key-code" style="text-transform:uppercase"><?= htmlspecialchars($c['code']) ?></span></td>
            <td>
                <?php if ( $c['type'] === 'percent' ): ?>
                    <span class="badge badge-active">درصدی</span>
                <?php else: ?>
                    <span class="badge badge-pending">مبلغ ثابت</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ( $c['type'] === 'percent' ): ?>
                    <?= (int)$c['value'] ?>٪
                <?php else: ?>
                    <?= number_format((int)$c['value']/10) ?> تومان
                <?php endif; ?>
            </td>
            <td>
                <?php if ( ( $c['product'] ?? 'all' ) === 'all' ): ?>
                    <span style="color:#6b7280;font-size:12px">همه</span>
                <?php else: ?>
                    <span style="background:#ede9fe;color:#6c63ff;padding:2px 8px;border-radius:99px;font-size:11px"><?= htmlspecialchars($c['product']) ?></span>
                <?php endif; ?>
            </td>
            <td style="font-size:12px;color:#6b7280">
                <?= (int)($c['used_count'] ?? 0) ?>/<?= (int)($c['max_uses'] ?? 0) > 0 ? (int)$c['max_uses'] : '∞' ?>
                <?php if ( $is_exhausted ): ?>
                    <span class="badge badge-expired" style="margin-right:4px">تکمیل</span>
                <?php endif; ?>
            </td>
            <td style="font-size:12px;color:#6b7280">
                <?= !empty($c['expires_at']) ? htmlspecialchars($c['expires_at']) : '∞' ?>
                <?php if ( $is_expired ): ?>
                    <span class="badge badge-expired" style="margin-right:4px">منقضی</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ( $is_active && ! $is_expired && ! $is_exhausted ): ?>
                    <span class="badge badge-active">فعال</span>
                <?php elseif ( ! $is_active ): ?>
                    <span class="badge badge-disabled">غیرفعال</span>
                <?php else: ?>
                    <span class="badge badge-expired">غیرقابل استفاده</span>
                <?php endif; ?>
            </td>
            <td style="color:#6b7280;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($c['note'] ?? '') ?></td>
            <td style="white-space:nowrap">
                <a class="btn btn-sm btn-secondary" href="?tab=coupons&coupon_edit=<?= urlencode($c['id']) ?>">ویرایش</a>
                <a class="btn btn-sm <?= $is_active ? 'btn-warning' : 'btn-success' ?>" href="?tab=coupons&coupon_toggle=<?= urlencode($c['id']) ?>">
                    <?= $is_active ? 'غیرفعال' : 'فعال' ?>
                </a>
                <a class="btn btn-sm btn-danger" href="?tab=coupons&coupon_delete=<?= urlencode($c['id']) ?>"
                   onclick="return confirm('حذف این کد تخفیف؟')">حذف</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
        <p style="color:#9ca3af;text-align:center;padding:20px">هنوز هیچ کد تخفیفی ساخته نشده.</p>
    <?php endif; ?>
</div>

<!-- راهنما -->
<div class="card">
    <h2>💡 راهنمای استفاده</h2>
    <div style="font-size:13px;color:#374151;line-height:2">
        <p>• کد تخفیف در صفحه پرداخت <code>/license-server/pay/?plugin=...</code> نمایش داده می‌شود و مشتری می‌تواند قبل از پرداخت کد را وارد کند.</p>
        <p>• نوع <b>درصدی</b>: مقدار بین ۱ تا ۱۰۰ — مثلاً ۲۰ یعنی ۲۰٪ تخفیف.</p>
        <p>• نوع <b>مبلغ ثابت</b>: مقدار به <b>ریال</b> وارد می‌شود — مثلاً ۵۰۰۰۰۰ = ۵۰,۰۰۰ تومان تخفیف.</p>
        <p>• «حداکثر استفاده» ۰ یعنی نامحدود. هر بار پرداخت موفق با این کد، شمارنده یکی اضافه می‌شود.</p>
        <p>• «حداقل مبلغ سفارش» به ریال وارد شود — برای کدهایی که فقط روی خریدهای بالای یک مبلغ مشخص اعمال شوند.</p>
        <p>• اگر محصول خاصی انتخاب شود، کد فقط برای همان پلاگین کار می‌کند.</p>
    </div>
</div>

<?php elseif ( $tab === 'payments' ): ?>

<!-- جدول خریدها -->
<div class="card">
    <h2>💳 تاریخچه خریدها</h2>
    <?php if ( $payments ): ?>
    <table>
        <tr><th>تاریخ خرید</th><th>ایمیل</th><th>دامنه</th><th>محصول</th><th>مبلغ (تومان)</th><th>کد تخفیف</th><th>وضعیت</th><th>کلید لایسنس</th></tr>
        <?php foreach ( $payments as $p ):
            $badge_p = ['paid'=>'badge-paid','failed'=>'badge-failed','pending'=>'badge-pending'][$p['status']] ?? '';
            $status_p = ['paid'=>'موفق','failed'=>'ناموفق','pending'=>'در انتظار'][$p['status']] ?? $p['status'];
            $pay_slug  = $p['plugin'] ?? '—';
            $pay_pname = $plugin_labels[ $pay_slug ] ?? $pay_slug;
        ?>
        <tr>
            <td style="color:#6b7280;font-size:12px"><?= htmlspecialchars($p['created_at'] ?? '—') ?></td>
            <td><?= htmlspecialchars($p['email'] ?? '—') ?></td>
            <td><?= htmlspecialchars($p['domain'] ?? '—') ?></td>
            <td>
                <div style="font-weight:600;font-size:12px"><?= htmlspecialchars($pay_pname) ?></div>
                <span style="background:#ede9fe;color:#6c63ff;padding:2px 8px;border-radius:99px;font-size:10px;margin-top:3px;display:inline-block"><?= htmlspecialchars($pay_slug) ?></span>
            </td>
            <td><?= number_format((int)($p['amount'] ?? 0) / 10) ?></td>
            <td>
                <?php if ( ! empty( $p['coupon_code'] ) ): ?>
                    <span class="key-code"><?= htmlspecialchars($p['coupon_code']) ?></span>
                    <?php if ( ! empty( $p['coupon_discount'] ) ): ?>
                        <div style="font-size:10px;color:#9ca3af;margin-top:2px">تخفیف: <?= number_format((int)$p['coupon_discount']/10) ?> ت</div>
                    <?php endif; ?>
                <?php else: ?>
                    <span style="color:#d1d5db">—</span>
                <?php endif; ?>
            </td>
            <td><span class="badge <?= $badge_p ?>"><?= $status_p ?></span></td>
            <td>
                <?php if ( $p['license_key'] ): ?>
                    <span class="key-code"><?= htmlspecialchars($p['license_key']) ?></span>
                <?php else: ?>
                    <span style="color:#d1d5db">—</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
        <p style="color:#9ca3af;text-align:center;padding:20px">هنوز هیچ خریدی ثبت نشده.</p>
    <?php endif; ?>
</div>

<?php elseif ( $tab === 'account' ): ?>

<!-- تنظیمات حساب ادمین -->
<div class="card" style="max-width:520px">
    <h2>👤 تغییر نام کاربری و رمز عبور مدیر</h2>
    <p style="color:#94a3b8;font-size:12.5px;margin-bottom:18px">
        نام کاربری فعلی مدیر:
        <strong style="color:#1e293b;direction:ltr;display:inline-block"><?= htmlspecialchars( AdminAuth::username() ) ?></strong>
    </p>
    <p style="color:#64748b;font-size:12px;margin-bottom:18px;line-height:1.7;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px">
        با ذخیره، رمز در <code>data/admin-auth.json</code> به‌روز می‌شود و در صورت امکان
        <code>ADMIN_USER</code> / <code>ADMIN_PASS</code> داخل <code>config.php</code> هم هم‌سان می‌شود
        تا ورود قطع نشود. بقیه تنظیمات config (دیتابیس، قیمت، گوگل، …) دست نخورده می‌ماند.
    </p>
    <form method="post" autocomplete="off">
        <div style="margin-bottom:16px">
            <label>رمز عبور فعلی *</label>
            <input type="password" name="current_pass" required autocomplete="current-password">
            <span class="hint">همان رمزی که الان با آن وارد پنل شده‌اید (یا رمز فعلی config.php).</span>
        </div>

        <hr style="border:none;border-top:1px dashed #e2e8f0;margin:18px 0">

        <div style="margin-bottom:16px">
            <label>نام کاربری جدید مدیر (اختیاری)</label>
            <input type="text" name="new_username" placeholder="خالی بگذارید = بدون تغییر" style="direction:ltr;text-align:left" autocomplete="off">
        </div>
        <div style="margin-bottom:16px">
            <label>رمز عبور جدید مدیر (اختیاری)</label>
            <input type="password" name="new_pass" placeholder="خالی بگذارید = بدون تغییر" minlength="6" autocomplete="new-password">
        </div>
        <div style="margin-bottom:20px">
            <label>تکرار رمز عبور جدید</label>
            <input type="password" name="confirm_pass" placeholder="فقط اگر رمز جدید وارد کردید" autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary" name="save_account" value="1" style="width:100%;justify-content:center">💾 ذخیره تغییرات حساب مدیر</button>
    </form>
    <p style="color:#94a3b8;font-size:11.5px;margin-top:14px;line-height:1.7">
        اگر رمز را فراموش کرده‌اید و وارد نمی‌شوید، از ابزار اضطراری
        <code>tools/reset-admin.php?key=RESET_NOW_2026</code> استفاده کنید و بعد همان فایل را حذف کنید.
    </p>
</div>

<?php endif; ?>
<?php endif; ?>
</div>
</body>
</html>
