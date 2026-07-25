<?php
/**
 * پرداخت اضطراری چت باکس — بدون وابستگی به config.php خراب
 * آدرس: https://webakery.ir/license-server/updates/chat-pay.php
 *
 * بعد از درست شدن config.php این فایل را حذف کنید و از /pay/ استفاده کنید.
 */
ini_set( 'display_errors', '1' );
ini_set( 'display_startup_errors', '1' );
error_reporting( E_ALL );

header( 'Content-Type: text/html; charset=utf-8' );

$ROOT = dirname( __DIR__ );

/*
 * عمداً config.php را لود نمی‌کنیم — اگر خراب باشد کل صفحه ۵۰۰ می‌شود.
 * درگاه و آدرس به‌صورت ثابت اینجاست (همان مقادیر سرور).
 */
if ( ! defined( 'ZIBAL_MERCHANT' ) ) {
	define( 'ZIBAL_MERCHANT', 'fc6fd44c-0e7d-4693-ae42-f7ccc29116d9' );
}
if ( ! defined( 'LS_BASE_URL' ) ) {
	define( 'LS_BASE_URL', 'https://webakery.ir' );
}

$PLANS = [
	'1m'   => [ 'months' => 1, 'price' => 1500000, 'label' => 'ماهانه', 'hint' => '۱۵۰,۰۰۰ تومان' ],
	'3m'   => [ 'months' => 3, 'price' => 3500000, 'label' => '۳ ماهه', 'hint' => '۳۵۰,۰۰۰ تومان', 'badge' => 'پیشنهادی' ],
	'life' => [ 'months' => 0, 'price' => 7990000, 'label' => 'دائمی', 'hint' => '۷۹۹,۰۰۰ تومان' ],
];

try {
	require_once $ROOT . '/includes/Database.php';
	require_once $ROOT . '/includes/LicenseManager.php';
} catch ( Throwable $e ) {
	http_response_code( 500 );
	echo '<pre style="direction:rtl;font-family:Tahoma">خطا در بارگذاری LicenseManager/Database:\n'
		. htmlspecialchars( $e->getMessage() ) . "\n"
		. htmlspecialchars( $e->getFile() . ':' . $e->getLine() )
		. '</pre>';
	exit;
}

$MERCHANT = ZIBAL_MERCHANT;
$SELF     = rtrim( LS_BASE_URL, '/' ) . '/license-server/updates/chat-pay.php';
$plugin   = 'webakery-chat';
$plan     = preg_replace( '/[^a-z0-9_-]/i', '', $_GET['plan'] ?? $_POST['plan'] ?? '3m' );
if ( ! isset( $PLANS[ $plan ] ) ) {
	$plan = '3m';
}
$domain_get = trim( $_GET['domain'] ?? $_POST['domain'] ?? '' );
$return_get = trim( $_GET['return'] ?? $_POST['return_url'] ?? '' );
$error      = '';

function zibal_post_chat( $url, $data ) {
	$ch = curl_init( $url );
	curl_setopt( $ch, CURLOPT_POST, true );
	curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $data ) );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json' ] );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_TIMEOUT, 20 );
	$body = curl_exec( $ch );
	curl_close( $ch );
	$decoded = json_decode( $body ?: '{}', true );
	return is_array( $decoded ) ? $decoded : [];
}

/* ─── callback ─── */
if ( isset( $_GET['zibal_cb'] ) ) {
	$track_id = trim( $_GET['trackId'] ?? '' );
	$success  = (int) ( $_GET['success'] ?? 0 );
	if ( ! $track_id ) {
		exit( 'trackId missing' );
	}
	$pay = Database::payment_find( $track_id );
	if ( ! $pay ) {
		exit( 'payment not found' );
	}
	if ( ( $pay['status'] ?? '' ) === 'paid' && ! empty( $pay['license_key'] ) ) {
		$key = $pay['license_key'];
	} elseif ( $success !== 1 ) {
		Database::payment_update( $track_id, [ 'status' => 'failed' ] );
		exit( 'پرداخت ناموفق بود. <a href="' . htmlspecialchars( $SELF ) . '">تلاش مجدد</a>' );
	} else {
		$verify = zibal_post_chat(
			'https://gateway.zibal.ir/v1/verify',
			[
				'merchant' => $MERCHANT,
				'trackId'  => (int) $track_id,
			]
		);
		$code = (int) ( $verify['result'] ?? 0 );
		if ( $code !== 100 && $code !== 201 ) {
			exit( 'verify failed: ' . $code );
		}
		$months = (int) ( $pay['months'] ?? 0 );
		$pplan  = (string) ( $pay['plan'] ?? '' );
		if ( $months > 0 ) {
			$lic = LicenseManager::create_or_extend_subscription( $pay['email'], $plugin, $months, $pay['domain'] ?? '', 'اشتراک ' . $pplan );
		} else {
			$lic = LicenseManager::create_or_upgrade_lifetime( $pay['email'], $plugin, $pay['domain'] ?? '', 'لایسنس دائمی' );
		}
		Database::payment_update( $track_id, [ 'status' => 'paid', 'license_key' => $lic['license_key'] ] );
		$key = $lic['license_key'];
		$exp = $lic['expires_at'] ?? null;
	}

	$html = '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>پرداخت موفق</title>'
		. '<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">'
		. '<style>body{font-family:Vazirmatn,Tahoma;background:#f8fafc;padding:40px}.card{max-width:480px;margin:auto;background:#fff;border-radius:16px;padding:28px;box-shadow:0 4px 24px rgba(0,0,0,.08)}.key{background:#f0fdf4;border:1px solid #86efac;padding:12px;border-radius:10px;direction:ltr;font-weight:bold}</style></head><body><div class="card">'
		. '<h2>لایسنس فعال شد ✅</h2>'
		. '<div class="key">' . htmlspecialchars( $key ) . '</div>';
	if ( ! empty( $exp ) ) {
		$html .= '<p>اعتبار تا: <strong>' . htmlspecialchars( $exp ) . '</strong></p>';
	} else {
		$html .= '<p>♾ لایسنس مادام‌العمر</p>';
	}
	if ( ! empty( $pay['return_url'] ) ) {
		$ru  = $pay['return_url'];
		$sep = strpos( $ru, '?' ) !== false ? '&' : '?';
		$ru .= $sep . http_build_query(
			[
				'wccp_activate' => '1',
				'wccp_key'      => $key,
				'wbl_product'   => $plugin,
			]
		);
		$html .= '<p>در حال انتقال برای فعال‌سازی...</p><script>setTimeout(function(){location=' . json_encode( $ru ) . '},3000)</script>';
	}
	$html .= '</div></body></html>';
	echo $html;
	exit;
}

/* ─── POST start payment ─── */
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
	$email  = filter_var( trim( $_POST['email'] ?? '' ), FILTER_VALIDATE_EMAIL );
	$domain = trim( $_POST['domain'] ?? '' );
	$plan   = preg_replace( '/[^a-z0-9_-]/i', '', $_POST['plan'] ?? '3m' );
	if ( ! isset( $PLANS[ $plan ] ) ) {
		$plan = '3m';
	}
	$return_get = trim( $_POST['return_url'] ?? '' );
	if ( ! $email || $domain === '' ) {
		$error = 'ایمیل یا دامنه معتبر نیست.';
	} else {
		$amount = (int) $PLANS[ $plan ]['price'];
		$months = (int) $PLANS[ $plan ]['months'];
		$cb     = $SELF . '?zibal_cb=1';
		$resp   = zibal_post_chat(
			'https://gateway.zibal.ir/v1/request',
			[
				'merchant'    => $MERCHANT,
				'amount'      => $amount,
				'callbackUrl' => $cb,
				'description' => 'چت باکس (' . $PLANS[ $plan ]['label'] . ') — ' . $domain,
			]
		);
		if ( (int) ( $resp['result'] ?? -1 ) !== 100 ) {
			$error = 'خطای درگاه: ' . ( $resp['message'] ?? ( $resp['result'] ?? '?' ) );
		} else {
			$track = (string) $resp['trackId'];
			Database::payment_insert(
				[
					'track_id'    => $track,
					'plugin'      => $plugin,
					'email'       => $email,
					'domain'      => LicenseManager::clean_domain( $domain ),
					'return_url'  => $return_get,
					'amount'      => $amount,
					'base_amount' => $amount,
					'plan'        => $plan,
					'months'      => $months,
					'status'      => 'pending',
					'license_key' => null,
					'created_at'  => date( 'Y-m-d H:i:s' ),
				]
			);
			header( 'Location: https://gateway.zibal.ir/start/' . $track );
			exit;
		}
	}
}

// فرم
ob_start();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>خرید چت باکس</title>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}body{font-family:Vazirmatn,Tahoma;background:linear-gradient(160deg,#f8fafc,#eef2ff);margin:0;padding:28px 16px}
.card{max-width:520px;margin:0 auto;background:#fff;border-radius:16px;padding:28px;box-shadow:0 4px 24px rgba(0,0,0,.08)}
h1{font-size:18px;margin:0 0 6px}.sub{color:#6b7280;font-size:13px;margin-bottom:18px}
.plans{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:16px}
label.plan{border:1.5px solid #e5e7eb;border-radius:12px;padding:12px 8px;text-align:center;cursor:pointer;position:relative}
label.plan:has(input:checked){border-color:#6c63ff;background:#f5f3ff}
label.plan input{display:none}.badge{position:absolute;top:-8px;left:6px;background:#ef4444;color:#fff;font-size:10px;padding:2px 6px;border-radius:99px}
.price{color:#6c63ff;font-weight:800;font-size:14px;margin-top:6px}
.field{margin-bottom:12px}label.l{display:block;font-size:13px;font-weight:700;margin-bottom:4px}
input[type=email],input[type=text]{width:100%;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-family:inherit;direction:ltr;text-align:left}
.btn{width:100%;padding:14px;border:0;border-radius:10px;background:#6c63ff;color:#fff;font-family:inherit;font-weight:800;font-size:15px;cursor:pointer}
.err{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;padding:10px;border-radius:8px;margin-bottom:12px;font-size:13px}
.note{margin-top:14px;font-size:11px;color:#94a3b8;text-align:center}
</style>
</head>
<body>
<div class="card">
	<h1>💬 چت باکس — خرید لایسنس</h1>
	<p class="sub">ماهانه ۱۵۰ / ۳ ماهه ۳۵۰ / دائمی ۷۹۹ هزار تومان</p>
	<?php if ( $error ) : ?><div class="err"><?php echo htmlspecialchars( $error ); ?></div><?php endif; ?>
	<form method="post">
		<input type="hidden" name="return_url" value="<?php echo htmlspecialchars( $return_get ); ?>">
		<div class="plans">
			<?php foreach ( $PLANS as $id => $p ) : ?>
			<label class="plan">
				<?php if ( ! empty( $p['badge'] ) ) : ?><span class="badge"><?php echo htmlspecialchars( $p['badge'] ); ?></span><?php endif; ?>
				<input type="radio" name="plan" value="<?php echo htmlspecialchars( $id ); ?>" <?php echo $plan === $id ? 'checked' : ''; ?>>
				<div><?php echo htmlspecialchars( $p['label'] ); ?></div>
				<div class="price"><?php echo htmlspecialchars( $p['hint'] ); ?></div>
			</label>
			<?php endforeach; ?>
		</div>
		<div class="field">
			<label class="l">ایمیل</label>
			<input type="email" name="email" required placeholder="name@example.com">
		</div>
		<div class="field">
			<label class="l">دامنه سایت</label>
			<input type="text" name="domain" required value="<?php echo htmlspecialchars( $domain_get ); ?>" placeholder="example.com">
		</div>
		<button class="btn" type="submit">پرداخت با زیبال 💳</button>
	</form>
	<p class="note">صفحه اضطراری — بعد از رفع config.php از مسیر اصلی /license-server/pay/ استفاده کنید.</p>
</div>
</body>
</html>
<?php
echo ob_get_clean();
