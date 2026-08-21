<?php
/**
 * ریست اضطراری یوزرنیم/رمز مدیر — مستقل (بدون وابستگی به AdminAuth جدید)
 *
 * استفاده:
 * https://YOUR-DOMAIN/license-server/tools/reset-admin.php?key=RESET_NOW_2026
 *
 * بعد از موفقیت، همین فایل را از سرور حذف کنید.
 */
declare(strict_types=1);

// نمایش خطا برای تشخیص 500 (بعد از ریست این فایل را حذف کنید)
ini_set( 'display_errors', '1' );
error_reporting( E_ALL );

$RESET_KEY = 'RESET_NOW_2026';

if ( ! isset( $_GET['key'] ) || ! hash_equals( $RESET_KEY, (string) $_GET['key'] ) ) {
	http_response_code( 403 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo "Forbidden\n";
	echo "آدرس را با ?key=RESET_NOW_2026 باز کنید.\n";
	exit;
}

$root        = dirname( __DIR__ );
$config_path = $root . '/config.php';
$data_dir    = $root . '/data';
$auth_path   = $data_dir . '/admin-auth.json';

$msg      = '';
$msg_type = '';
$current_user = 'admin';

if ( is_readable( $config_path ) ) {
	// فقط ثابت‌ها را بخوان؛ از require خودداری می‌کنیم تا خطای config باعث 500 نشود
	$cfg_raw = (string) file_get_contents( $config_path );
	if ( preg_match( "/define\(\s*'ADMIN_USER'\s*,\s*'([^']*)'\s*\)/", $cfg_raw, $m ) ) {
		$current_user = $m[1];
	} elseif ( preg_match( '/define\(\s*"ADMIN_USER"\s*,\s*"([^"]*)"\s*\)/', $cfg_raw, $m ) ) {
		$current_user = $m[1];
	}
}
if ( is_readable( $auth_path ) ) {
	$auth = json_decode( (string) file_get_contents( $auth_path ), true );
	if ( is_array( $auth ) && ! empty( $auth['username'] ) ) {
		$current_user = (string) $auth['username'];
	}
}

/**
 * به‌روزرسانی ADMIN_USER / ADMIN_PASS در config.php در صورت امکان.
 */
function ls_sync_config( string $config_path, string $username, string $password ): string {
	if ( ! is_readable( $config_path ) ) {
		return ' (config.php خوانده نشد)';
	}
	if ( ! is_writable( $config_path ) ) {
		return ' (config.php قابل نوشتن نیست — فقط admin-auth.json به‌روز شد)';
	}

	$content = file_get_contents( $config_path );
	if ( $content === false || $content === '' ) {
		return ' (خواندن config.php ناموفق)';
	}

	$original = $content;
	$user_php = "'" . str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), $username ) . "'";
	$pass_php = "'" . str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), $password ) . "'";

	$content2 = preg_replace(
		"/define\(\s*'ADMIN_USER'\s*,\s*'[^']*'\s*\)/",
		"define( 'ADMIN_USER', {$user_php} )",
		$content,
		1
	);
	$content2 = preg_replace(
		"/define\(\s*'ADMIN_PASS'\s*,\s*'[^']*'\s*\)/",
		"define( 'ADMIN_PASS', {$pass_php} )",
		is_string( $content2 ) ? $content2 : $content,
		1
	);

	if ( ! is_string( $content2 ) || $content2 === $original ) {
		return ' (الگوی ADMIN_* در config پیدا نشد — فقط admin-auth.json به‌روز شد)';
	}

	if ( file_put_contents( $config_path, $content2, LOCK_EX ) === false ) {
		return ' (نوشتن config.php ناموفق — فقط admin-auth.json به‌روز شد)';
	}

	return ' + config.php هم‌سان شد';
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
	$user    = trim( (string) ( $_POST['username'] ?? '' ) );
	$pass    = (string) ( $_POST['password'] ?? '' );
	$confirm = (string) ( $_POST['confirm'] ?? '' );

	if ( $user === '' || ! preg_match( '/^[A-Za-z0-9._@-]{3,64}$/', $user ) ) {
		$msg      = 'نام کاربری معتبر نیست (۳ تا ۶۴ کاراکتر: حروف، عدد، . _ @ -).';
		$msg_type = 'error';
	} elseif ( strlen( $pass ) < 6 ) {
		$msg      = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
		$msg_type = 'error';
	} elseif ( $pass !== $confirm ) {
		$msg      = 'رمز و تکرار آن یکسان نیستند.';
		$msg_type = 'error';
	} else {
		if ( ! is_dir( $data_dir ) ) {
			@mkdir( $data_dir, 0755, true );
		}
		if ( ! is_dir( $data_dir ) || ! is_writable( $data_dir ) ) {
			$msg      = 'پوشه data قابل نوشتن نیست. مجوز آن را 755 یا 775 کنید: ' . $data_dir;
			$msg_type = 'error';
		} else {
			$payload = array(
				'username'      => $user,
				'password_hash' => password_hash( $pass, PASSWORD_DEFAULT ),
				'updated_at'    => gmdate( 'c' ),
				'reset_via'     => 'tools/reset-admin.php',
			);
			$json = json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
			$ok   = $json !== false && file_put_contents( $auth_path, $json . "\n", LOCK_EX ) !== false;
			if ( $ok ) {
				@chmod( $auth_path, 0640 );
				$sync     = ls_sync_config( $config_path, $user, $pass );
				$msg      = 'ریست موفق شد. الان با یوزر/رمز جدید وارد /license-server/admin/ شوید.' . $sync . ' — بعد این فایل tools/reset-admin.php را حذف کنید.';
				$msg_type = 'success';
				$current_user = $user;
			} else {
				$msg      = 'نوشتن data/admin-auth.json ناموفق بود. مجوز پوشه data را بررسی کنید.';
				$msg_type = 'error';
			}
		}
	}
}
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
.info{font-size:12px;color:#64748b;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 10px;margin-top:10px;direction:ltr;text-align:left}
</style>
</head>
<body>
<div class="card">
	<h1>ریست اضطراری حساب مدیر</h1>
	<p>یوزرنیم فعلی: <strong dir="ltr"><?php echo htmlspecialchars( $current_user, ENT_QUOTES, 'UTF-8' ); ?></strong></p>
	<?php if ( $msg ) : ?>
		<div class="msg <?php echo $msg_type === 'success' ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars( $msg, ENT_QUOTES, 'UTF-8' ); ?></div>
	<?php endif; ?>
	<form method="post">
		<label>نام کاربری جدید</label>
		<input type="text" name="username" required dir="ltr" value="<?php echo htmlspecialchars( $current_user, ENT_QUOTES, 'UTF-8' ); ?>">
		<label>رمز عبور جدید</label>
		<input type="password" name="password" required minlength="6" autocomplete="new-password">
		<label>تکرار رمز عبور جدید</label>
		<input type="password" name="confirm" required minlength="6" autocomplete="new-password">
		<button type="submit">ذخیره و ریست</button>
	</form>
	<div class="warn">
		بعد از موفقیت، فایل <code>tools/reset-admin.php</code> را از هاست حذف کنید.
	</div>
	<div class="info">
		auth: <?php echo htmlspecialchars( $auth_path, ENT_QUOTES, 'UTF-8' ); ?><br>
		writable data: <?php echo is_dir( $data_dir ) && is_writable( $data_dir ) ? 'yes' : 'no'; ?><br>
		php: <?php echo htmlspecialchars( PHP_VERSION, ENT_QUOTES, 'UTF-8' ); ?>
	</div>
</div>
</body>
</html>
