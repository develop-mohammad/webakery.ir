<?php
/**
 * ریست اضطراری یوزرنیم/رمز مدیر — بدون نیاز به رمز فعلی.
 *
 * استفاده:
 * 1) این فایل را روی سرور در مسیر license-server/tools/ بگذارید
 * 2) در مرورگر باز کنید:
 *    https://webakery.ir/license-server/tools/reset-admin.php?key=RESET_NOW_2026
 * 3) یوزر و پسورد جدید را ذخیره کنید
 * 4) همین فایل را از سرور حذف کنید
 *
 * نکته: فقط وقتی data/admin-auth.json یا ADMIN_* در config قابل استفاده نیست.
 */
session_start();

$RESET_KEY = 'RESET_NOW_2026';

if ( ! isset( $_GET['key'] ) || ! hash_equals( $RESET_KEY, (string) $_GET['key'] ) ) {
	http_response_code( 403 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo "Forbidden\n";
	echo "آدرس را با ?key=RESET_NOW_2026 باز کنید، بعد از ریست این فایل را حذف کنید.\n";
	exit;
}

$config = dirname( __DIR__ ) . '/config.php';
if ( is_readable( $config ) ) {
	require_once $config;
}
require_once dirname( __DIR__ ) . '/includes/AdminAuth.php';

$msg      = '';
$msg_type = '';

if ( isset( $_POST['do_reset'] ) ) {
	$user = trim( (string) ( $_POST['username'] ?? '' ) );
	$pass = (string) ( $_POST['password'] ?? '' );
	$confirm = (string) ( $_POST['confirm'] ?? '' );

	if ( $user === '' || ! preg_match( '/^[A-Za-z0-9._@-]{3,64}$/', $user ) ) {
		$msg = 'نام کاربری معتبر نیست (۳ تا ۶۴ کاراکتر: حروف، عدد، . _ @ -).';
		$msg_type = 'error';
	} elseif ( strlen( $pass ) < 6 ) {
		$msg = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
		$msg_type = 'error';
	} elseif ( $pass !== $confirm ) {
		$msg = 'رمز و تکرار آن یکسان نیستند.';
		$msg_type = 'error';
	} else {
		$path = dirname( __DIR__ ) . '/data/admin-auth.json';
		$dir  = dirname( $path );
		if ( ! is_dir( $dir ) ) {
			@mkdir( $dir, 0755, true );
		}
		$payload = array(
			'username'      => $user,
			'password_hash' => password_hash( $pass, PASSWORD_DEFAULT ),
			'updated_at'    => gmdate( 'c' ),
			'reset_via'     => 'tools/reset-admin.php',
		);
		$json = json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		$ok   = $json !== false && file_put_contents( $path, $json . "\n", LOCK_EX ) !== false;
		if ( $ok ) {
			@chmod( $path, 0640 );
			$msg = 'ریست شد. الان با یوزرنیم/رمز جدید وارد /license-server/admin/ شوید و این فایل tools/reset-admin.php را حذف کنید.';
			$msg_type = 'success';
		} else {
			$msg = 'نوشتن data/admin-auth.json ناموفق بود. مجوز پوشه data را روی 755/775 بگذارید.';
			$msg_type = 'error';
		}
	}
}

$current_user = class_exists( 'AdminAuth' ) ? AdminAuth::username() : ( defined( 'ADMIN_USER' ) ? ADMIN_USER : 'admin' );
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ریست حساب مدیر</title>
<style>
body{font-family:Tahoma,sans-serif;background:#f1f5f9;margin:0;padding:24px;color:#0f172a}
.card{max-width:440px;margin:40px auto;background:#fff;border-radius:12px;padding:24px;box-shadow:0 8px 24px rgba(15,23,42,.08)}
h1{font-size:18px;margin:0 0 8px}
p{color:#64748b;font-size:13px;line-height:1.7}
label{display:block;margin:14px 0 6px;font-size:13px;font-weight:700}
input{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;font:inherit}
button{margin-top:18px;width:100%;padding:12px;border:0;border-radius:8px;background:#2563eb;color:#fff;font:inherit;font-weight:700;cursor:pointer}
.msg{padding:10px 12px;border-radius:8px;margin:12px 0;font-size:13px}
.ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
.err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.warn{background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;font-size:12.5px;margin-top:16px;padding:10px 12px;border-radius:8px}
code{background:#f8fafc;padding:1px 5px;border-radius:4px}
</style>
</head>
<body>
<div class="card">
	<h1>ریست اضطراری حساب مدیر</h1>
	<p>یوزرنیم فعلی (خوانده‌شده از سیستم): <strong dir="ltr"><?php echo htmlspecialchars( $current_user ); ?></strong></p>
	<?php if ( $msg ) : ?>
		<div class="msg <?php echo $msg_type === 'success' ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars( $msg ); ?></div>
	<?php endif; ?>
	<form method="post">
		<label>نام کاربری جدید مدیر</label>
		<input type="text" name="username" required dir="ltr" value="<?php echo htmlspecialchars( $current_user ); ?>">
		<label>رمز عبور جدید</label>
		<input type="password" name="password" required minlength="6" autocomplete="new-password">
		<label>تکرار رمز عبور جدید</label>
		<input type="password" name="confirm" required minlength="6" autocomplete="new-password">
		<button type="submit" name="do_reset" value="1">ذخیره و ریست</button>
	</form>
	<div class="warn">
		بعد از موفقیت، فایل <code>tools/reset-admin.php</code> را از هاست حذف کنید.
		این صفحه فقط با کلید <code>?key=RESET_NOW_2026</code> باز می‌شود.
	</div>
</div>
</body>
</html>
