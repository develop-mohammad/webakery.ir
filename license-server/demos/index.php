<?php
/**
 * صفحه محیط تست / دمو زنده افزونه‌های webakery.ir
 *
 * آدرس: https://webakery.ir/license-server/demos/
 *
 * برای محیط زنده، ساب‌دامین demo.webakery.ir را طبق LIVE-DEMO-FA.txt بسازید.
 * اگر هنوز آماده نیست، دکمه «ورود به محیط تست» مخفی می‌ماند مگر LIVE_DEMO_URL ست شود.
 */
header( 'Content-Type: text/html; charset=utf-8' );

/** آدرس وردپرس تست زنده — بعد از ساخت ساب‌دامین همین را عوض کنید */
$LIVE_DEMO_URL = 'https://demo.webakery.ir';

/** اگر هنوز وردپرس دمو بالا نیست، false بگذار تا دکمه غیرفعال شود */
$LIVE_DEMO_READY = true;

$demos = [
	'hesabdar' => [
		'file'     => 'hesabdar-demo.zip',
		'name'     => 'Hesabdar — حسابدار',
		'desc'     => 'پرتال حسابدار، سفارش و گزارش ووکامرس',
		'icon'     => '📊',
		'price'    => '۷۹۹,۰۰۰ تومان',
		'buy'      => 'https://webakery.ir/license-server/pay/?plugin=hesabdar',
		'live'     => 'https://demo.webakery.ir/wp-admin/',
		'live_hint'=> 'ورود پیشخوان → منوی Hesabdar / پرتال حسابدار',
	],
	'nobat-man' => [
		'file'     => 'nobat-man-demo.zip',
		'name'     => 'نوبت من',
		'desc'     => 'رزرو نوبت با تقویم شمسی و نسخه پرو',
		'icon'     => '📅',
		'price'    => '۵۹۹,۰۰۰ تومان',
		'buy'      => 'https://webakery.ir/license-server/pay/?plugin=nobat-man',
		'live'     => 'https://demo.webakery.ir/?page_id=nobat', // بعداً با شورت‌کد واقعی عوض شود
		'live_hint'=> 'صفحه رزرو نوبت روی سایت دمو',
	],
	'access-levels' => [
		'file'     => 'access-levels-demo.zip',
		'name'     => 'Barbari — مدیریت دسترسی',
		'desc'     => 'کنترل منو و افزونه‌های پیشخوان',
		'icon'     => '🔐',
		'price'    => '۹۹,۹۹۹ تومان',
		'buy'      => 'https://webakery.ir/license-server/pay/?plugin=access-levels',
		'live'     => 'https://demo.webakery.ir/wp-admin/admin.php?page=access-levels',
		'live_hint'=> 'پیشخوان دمو → Barbari',
	],
	'wccp' => [
		'file'     => 'baget-demo.zip',
		'name'     => 'Baget — فیلدهای پرداخت',
		'desc'     => 'ویرایش فیلدهای checkout ووکامرس',
		'icon'     => '🛒',
		'price'    => '۱۹۹,۰۰۰ تومان',
		'buy'      => 'https://webakery.ir/license-server/pay/?plugin=wccp',
		'live'     => 'https://demo.webakery.ir/checkout/',
		'live_hint'=> 'صفحه پرداخت فروشگاه دمو',
	],
	'webakery-chat' => [
		'file'     => 'webakery-chat-box-demo.zip',
		'name'     => 'چت باکس',
		'desc'     => 'ویجت چت، صندوق پیام، اعلان تلگرام/واتساپ',
		'icon'     => '💬',
		'price'    => 'از ۱۵۰,۰۰۰ تومان',
		'buy'      => 'https://webakery.ir/license-server/pay/?plugin=webakery-chat',
		'live'     => 'https://demo.webakery.ir/',
		'live_hint'=> 'ویجت چت پایین سایت دمو',
	],
];

$focus = isset( $_GET['plugin'] ) ? preg_replace( '/[^a-z0-9\-]/', '', (string) $_GET['plugin'] ) : '';
if ( $focus === 'baget' ) {
	$focus = 'wccp';
}

function demo_size( string $file ): string {
	$path = __DIR__ . '/' . $file;
	if ( ! is_readable( $path ) ) {
		return '—';
	}
	$b = filesize( $path );
	if ( $b < 1024 ) {
		return $b . ' B';
	}
	if ( $b < 1024 * 1024 ) {
		return round( $b / 1024 ) . ' KB';
	}
	return round( $b / ( 1024 * 1024 ), 1 ) . ' MB';
}

function demo_exists( string $file ): bool {
	return is_readable( __DIR__ . '/' . $file );
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>محیط تست افزونه‌ها — webakery.ir</title>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:Tahoma,Vazirmatn,sans-serif;background:#f1f5f9;color:#0f172a}
.wrap{max-width:900px;margin:0 auto;padding:28px 16px 60px}
.hero{background:linear-gradient(135deg,#0f766e,#0e7490);color:#fff;border-radius:20px;padding:28px 24px;margin-bottom:18px;box-shadow:0 12px 32px rgba(15,118,110,.25)}
.hero h1{margin:0 0 8px;font-size:24px}
.hero p{margin:0;opacity:.95;line-height:1.75;font-size:14px}
.live-box{background:#fff;border:2px solid #14b8a6;border-radius:16px;padding:18px;margin-bottom:18px;box-shadow:0 6px 20px rgba(20,184,166,.12)}
.live-box h2{margin:0 0 8px;font-size:18px;color:#0f766e}
.live-box p{margin:0 0 12px;color:#334155;font-size:13.5px;line-height:1.7}
.creds{background:#f0fdfa;border-radius:10px;padding:10px 12px;font-size:13px;margin-bottom:12px;direction:ltr;text-align:left}
.creds code{background:#ccfbf1;padding:2px 6px;border-radius:6px}
.grid{display:grid;gap:14px}
.card{background:#fff;border-radius:16px;padding:18px;box-shadow:0 4px 18px rgba(15,23,42,.06);border:1px solid #e2e8f0}
.card.focus{border-color:#2dd4bf;box-shadow:0 8px 28px rgba(45,212,191,.18)}
.row{display:flex;gap:14px;align-items:flex-start}
.icon{width:48px;height:48px;border-radius:14px;background:#ccfbf1;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0}
.meta{flex:1;min-width:0}
.meta h2{margin:0 0 4px;font-size:17px}
.meta p{margin:0;color:#64748b;font-size:13px;line-height:1.6}
.hint{font-size:12px;color:#0f766e;margin-top:6px}
.actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 14px;border-radius:10px;text-decoration:none;font-size:13px;font-weight:700;border:0}
.btn-live{background:#0d9488;color:#fff}
.btn-live:hover{background:#0f766e;color:#fff}
.btn-demo{background:#0284c7;color:#fff}
.btn-buy{background:#fff;color:#0f172a;border:1.5px solid #cbd5e1}
.btn-buy:hover{border-color:#0d9488;color:#0d9488}
.btn.disabled,.btn[disabled]{opacity:.45;pointer-events:none}
.note{margin-top:18px;background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:14px 16px;font-size:13px;line-height:1.8;color:#92400e}
.foot{margin-top:22px;text-align:center;font-size:12px;color:#64748b}
.foot a{color:#0f766e}
.size{font-size:11px;color:#94a3b8;font-weight:500}
</style>
</head>
<body>
<div class="wrap">
	<div class="hero">
		<h1>🧪 محیط تست زنده افزونه‌های وب‌آکری</h1>
		<p>
			اینجا می‌توانید قبل از خرید، افزونه را <strong>روی سایت تست</strong> ببینید و با آن کار کنید.
			نیازی به نصب روی سایت خودتان نیست.
		</p>
	</div>

	<div class="live-box">
		<h2>ورود به سایت تست</h2>
		<p>
			یک وردپرس جدا روی <strong>demo.webakery.ir</strong> برای آزمایش همه افزونه‌ها.
			داده تست است؛ آزادانه کلیک و تنظیم کنید.
		</p>
		<div class="creds">
			Admin: <code>demo</code> &nbsp;|&nbsp; Password: <code>Demo@12345</code>
			<br><span style="font-size:11px;color:#64748b">(بعد از ساخت سایت دمو، همین رمز را در وردپرس ست کنید — یا عوض کنید و اینجا هم به‌روز کنید)</span>
		</div>
		<div class="actions" style="margin-top:0">
			<?php if ( $LIVE_DEMO_READY ) : ?>
				<a class="btn btn-live" href="<?php echo htmlspecialchars( $LIVE_DEMO_URL ); ?>" target="_blank" rel="noopener">🚀 باز کردن سایت تست</a>
				<a class="btn btn-buy" href="<?php echo htmlspecialchars( rtrim( $LIVE_DEMO_URL, '/' ) . '/wp-admin/' ); ?>" target="_blank" rel="noopener">ورود به پیشخوان</a>
			<?php else : ?>
				<span class="btn btn-live disabled">سایت تست هنوز آماده نیست</span>
			<?php endif; ?>
		</div>
	</div>

	<div class="grid">
		<?php foreach ( $demos as $slug => $d ) :
			$ok   = demo_exists( $d['file'] );
			$href = $ok ? './' . rawurlencode( $d['file'] ) : '#';
			$cls  = ( $focus && $focus === $slug ) ? 'card focus' : 'card';
			$live = $d['live'] ?? $LIVE_DEMO_URL;
			?>
			<div class="<?php echo $cls; ?>" id="<?php echo htmlspecialchars( $slug ); ?>">
				<div class="row">
					<div class="icon"><?php echo $d['icon']; ?></div>
					<div class="meta">
						<h2><?php echo htmlspecialchars( $d['name'] ); ?></h2>
						<p><?php echo htmlspecialchars( $d['desc'] ); ?></p>
						<div class="hint"><?php echo htmlspecialchars( $d['live_hint'] ?? '' ); ?></div>
						<div class="actions">
							<?php if ( $LIVE_DEMO_READY ) : ?>
								<a class="btn btn-live" href="<?php echo htmlspecialchars( $live ); ?>" target="_blank" rel="noopener">کار با دمو در سایت تست</a>
							<?php endif; ?>
							<?php if ( $ok ) : ?>
								<a class="btn btn-demo" href="<?php echo htmlspecialchars( $href ); ?>" download>
									⬇️ ZIP دمو
									<span class="size">(<?php echo htmlspecialchars( demo_size( $d['file'] ) ); ?>)</span>
								</a>
							<?php endif; ?>
							<a class="btn btn-buy" href="<?php echo htmlspecialchars( $d['buy'] ); ?>">خرید نسخه کامل</a>
						</div>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="note">
		<strong>تفاوت دو نوع دمو:</strong><br>
		• <strong>سایت تست (پیشنهادی):</strong> مشتری وارد demo.webakery.ir می‌شود و همان‌جا کار می‌کند.<br>
		• <strong>ZIP دمو:</strong> برای کسانی که می‌خواهند روی وردپرس خودشان نصب و تست کنند.<br>
		راهنمای ساخت سایت تست: فایل <code>LIVE-DEMO-FA.txt</code> داخل همین پوشه.
	</div>

	<div class="foot">
		<a href="https://webakery.ir">webakery.ir</a>
		·
		<a href="https://webakery.ir/license-server/portal/">پورتال مشتری</a>
	</div>
</div>
</body>
</html>
