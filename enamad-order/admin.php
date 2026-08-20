<?php
/**
 * پنل داخلی enamad-order — فهرست سفارش‌ها و فاکتور جامع برای اقدام اینماد.
 */
session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Invoice.php';

$ADMIN_USER = defined( 'EO_ADMIN_USER' ) ? EO_ADMIN_USER : 'admin';
$ADMIN_PASS = defined( 'EO_ADMIN_PASS' ) ? EO_ADMIN_PASS : 'change-this-password';

if ( isset( $_POST['login'] ) ) {
	$u = (string) ( $_POST['u'] ?? '' );
	$p = (string) ( $_POST['p'] ?? '' );
	if ( hash_equals( $ADMIN_USER, $u ) && hash_equals( $ADMIN_PASS, $p ) ) {
		$_SESSION['eo_auth'] = true;
	} else {
		$login_error = 'نام کاربری یا رمز عبور اشتباه است.';
	}
}
if ( isset( $_GET['logout'] ) ) {
	session_destroy();
	header( 'Location: ?' );
	exit;
}

$authed = ! empty( $_SESSION['eo_auth'] );

if ( $authed && isset( $_GET['mark_sent'] ) ) {
	EO_Database::update_by_code( (string) $_GET['mark_sent'], [ 'enamad_sent' => true ] );
	header( 'Location: ?view=' . urlencode( (string) $_GET['mark_sent'] ) );
	exit;
}

$view_code   = $_GET['view'] ?? null;
$view_order  = $view_code ? EO_Database::find_by_code( (string) $view_code ) : null;

$page  = max( 1, (int) ( $_GET['p'] ?? 1 ) );
$limit = 25;
$total = $authed ? EO_Database::total() : 0;
$paid_count = $authed ? EO_Database::count_by_status( 'paid' ) : 0;
$orders = $authed ? EO_Database::all( $limit, ( $page - 1 ) * $limit ) : [];

?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>پنل سفارش‌های اینماد — webakery.ir</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Vazirmatn',Tahoma,Arial,sans-serif;background:#f1f5f9;color:#1e293b;font-size:14px;line-height:1.6}
.wrap{max-width:1080px;margin:0 auto;padding:26px 18px 60px}
.topbar{background:linear-gradient(135deg,#7c3aed 0%,#4f46e5 55%,#2563eb 100%);border-radius:18px;padding:24px 28px;color:#fff;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;box-shadow:0 10px 30px rgba(76,29,149,.22)}
.topbar h1{font-size:18px;font-weight:800}
.topbar .sub{font-size:12px;opacity:.85;margin-top:4px}
.topbar a{color:#fff;text-decoration:none;background:rgba(255,255,255,.15);padding:8px 16px;border-radius:9px;font-size:12.5px;font-weight:700}
.card{background:#fff;border-radius:16px;box-shadow:0 2px 14px rgba(15,23,42,.06);border:1px solid #eef2f7;padding:22px 24px;margin-bottom:20px}
.card h2{font-size:15px;font-weight:700;margin-bottom:14px;border-bottom:1px solid #f1f5f9;padding-bottom:10px}
table{width:100%;border-collapse:collapse}
th,td{padding:11px 12px;text-align:right;border-bottom:1px solid #f1f5f9;font-size:12.5px}
th{background:#f8fafc;font-weight:700;color:#64748b;font-size:11px}
tr:hover td{background:#f8fafc}
.badge{display:inline-block;padding:3px 11px;border-radius:99px;font-size:11px;font-weight:700}
.badge-paid{background:#dcfce7;color:#166534}
.badge-pending{background:#e0f2fe;color:#0369a1}
.badge-failed{background:#fee2e2;color:#b91c1c}
.btn{display:inline-flex;align-items:center;gap:5px;padding:8px 16px;border-radius:9px;border:none;cursor:pointer;font-size:12.5px;font-weight:700;text-decoration:none;font-family:inherit}
.btn-primary{background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff}
.btn-secondary{background:#f1f5f9;color:#475569}
.login-wrap{max-width:380px;margin:90px auto}
.login-wrap.card{text-align:center;padding:32px 28px}
input[type=text],input[type=password]{width:100%;padding:11px 14px;border:1.5px solid #e2e8f0;border-radius:10px;font-family:inherit;margin-bottom:14px}
.msg-error{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:13px}
.stats{display:flex;gap:14px;margin-bottom:20px;flex-wrap:wrap}
.stat{flex:1;min-width:140px;background:#fff;border-radius:14px;padding:16px 18px;box-shadow:0 2px 12px rgba(15,23,42,.05)}
.stat .n{font-size:22px;font-weight:800;color:#4f46e5}
.stat .l{font-size:11.5px;color:#64748b;margin-top:2px}
.invoice-table{width:100%;border-collapse:collapse;margin-top:14px}
.invoice-table td{padding:9px 4px;font-size:12.5px;border-bottom:1px solid #f1f5f9}
.invoice-table td:first-child{color:#64748b;width:45%}
.invoice-table td:last-child{color:#1e293b;font-weight:700}
@media print{.no-print{display:none !important}body{background:#fff}}
</style>
</head>
<body>
<div class="wrap">
<div class="topbar no-print">
	<div><h1>🛡️ پنل داخلی سفارش‌های اینماد</h1><div class="sub">فاکتور جامع برای اقدام دریافت نماد اعتماد الکترونیکی</div></div>
	<?php if ( $authed ) : ?><a href="?logout=1">خروج</a><?php endif; ?>
</div>
<?php if ( $authed && ! $view_order ) : ?>
<div class="card no-print" style="background:#eef2ff;border-color:#c7d2fe">
	<div style="font-size:12.5px;color:#4338ca;line-height:1.8">
		<b>لینک قابل اشتراک فرم:</b>
		<span style="direction:ltr;display:inline-block;font-weight:700"><?= htmlspecialchars( defined( 'EO_PUBLIC_URL' ) && EO_PUBLIC_URL !== '' ? rtrim( EO_PUBLIC_URL, '/' ) . '/' : ( rtrim( defined( 'EO_BASE_URL' ) ? EO_BASE_URL : 'https://webakery.ir', '/' ) . '/enamad-order/' ) ) ?></span>
		<div style="margin-top:6px;color:#64748b">این لینک را در تلگرام و اینستاگرام بفرستید. بعد از پرداخت، فاکتور جامع همین‌جا برای اقدام اینماد آماده می‌شود.</div>
	</div>
</div>
<?php endif; ?>

<?php if ( ! $authed ) : ?>
<div class="login-wrap card">
	<div style="font-size:38px;margin-bottom:6px">🔐</div>
	<h2 style="border-bottom:none;justify-content:center">ورود به پنل</h2>
	<?php if ( ! empty( $login_error ) ) : ?><div class="msg-error"><?= htmlspecialchars( $login_error ) ?></div><?php endif; ?>
	<form method="post" style="text-align:right;margin-top:14px">
		<input type="text" name="u" placeholder="نام کاربری" autofocus>
		<input type="password" name="p" placeholder="رمز عبور">
		<button class="btn btn-primary" name="login" style="width:100%;justify-content:center">ورود</button>
	</form>
</div>

<?php elseif ( $view_order ) : ?>

<div class="card no-print">
	<a href="?" class="btn btn-secondary">← بازگشت به فهرست</a>
	<button class="btn btn-primary" onclick="window.print()" style="margin-right:8px">🖨️ چاپ فاکتور جامع</button>
	<?php if ( empty( $view_order['enamad_sent'] ) ) : ?>
		<a href="?mark_sent=<?= urlencode( $view_order['order_code'] ) ?>" class="btn" style="background:#ecfdf5;color:#059669">✅ ثبت شد: ارسال به اینماد</a>
	<?php else : ?>
		<span class="badge badge-paid">✅ به اینماد ارسال شده</span>
	<?php endif; ?>
</div>

<div class="card">
	<h2>🧾 فاکتور جامع داخلی — سفارش <?= htmlspecialchars( $view_order['order_code'] ) ?></h2>
	<?= eo_internal_invoice_html( $view_order ) ?>
</div>

<?php else : ?>

<div class="stats">
	<div class="stat"><div class="n"><?= $total ?></div><div class="l">کل سفارش‌ها</div></div>
	<div class="stat"><div class="n"><?= $paid_count ?></div><div class="l">پرداخت‌شده</div></div>
</div>

<div class="card">
	<h2>📋 فهرست سفارش‌ها</h2>
	<?php if ( $orders ) : ?>
	<table>
		<tr><th>تاریخ</th><th>کد سفارش</th><th>نام متقاضی</th><th>کسب‌وکار</th><th>وب‌سایت</th><th>وضعیت</th><th>اینماد</th><th></th></tr>
		<?php foreach ( $orders as $o ) :
			$badge = [ 'paid' => 'badge-paid', 'pending' => 'badge-pending', 'failed' => 'badge-failed' ][ $o['status'] ?? '' ] ?? '';
			$status_fa = [ 'paid' => 'پرداخت‌شده', 'pending' => 'در انتظار', 'failed' => 'ناموفق' ][ $o['status'] ?? '' ] ?? ( $o['status'] ?? '—' );
		?>
		<tr>
			<td style="color:#94a3b8;white-space:nowrap"><?= htmlspecialchars( $o['created_at'] ?? '' ) ?></td>
			<td><?= htmlspecialchars( $o['order_code'] ?? '' ) ?></td>
			<td><?= htmlspecialchars( $o['full_name'] ?? '' ) ?></td>
			<td><?= htmlspecialchars( $o['business_name'] ?? '' ) ?></td>
			<td style="direction:ltr;text-align:right"><?= htmlspecialchars( $o['website'] ?? '' ) ?></td>
			<td><span class="badge <?= $badge ?>"><?= htmlspecialchars( $status_fa ) ?></span></td>
			<td><?= empty( $o['enamad_sent'] ) ? '<span style="color:#94a3b8">—</span>' : '✅' ?></td>
			<td><a href="?view=<?= urlencode( $o['order_code'] ?? '' ) ?>" class="btn btn-secondary">مشاهده</a></td>
		</tr>
		<?php endforeach; ?>
	</table>
	<?php $pages = (int) ceil( $total / $limit ); if ( $pages > 1 ) : ?>
	<div style="margin-top:14px;display:flex;gap:6px">
		<?php for ( $i = 1; $i <= $pages; $i++ ) : ?>
			<a href="?p=<?= $i ?>" class="btn <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?>"><?= $i ?></a>
		<?php endfor; ?>
	</div>
	<?php endif; ?>
	<?php else : ?>
		<p style="color:#94a3b8;text-align:center;padding:20px">هنوز هیچ سفارشی ثبت نشده.</p>
	<?php endif; ?>
</div>

<?php endif; ?>
</div>
</body>
</html>
