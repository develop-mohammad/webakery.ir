<?php
/**
 * صفحه دانلود نسخه دمو افزونه‌های webakery.ir
 * آدرس عمومی:
 *   https://webakery.ir/license-server/demos/
 *   https://webakery.ir/license-server/demos/?plugin=hesabdar
 */
header( 'Content-Type: text/html; charset=utf-8' );

$demos = [
	'hesabdar' => [
		'file'  => 'hesabdar-demo.zip',
		'name'  => 'Hesabdar — حسابدار',
		'desc'  => 'پرتال حسابدار، سفارش و گزارش ووکامرس',
		'icon'  => '📊',
		'price' => '۷۹۹,۰۰۰ تومان',
		'buy'   => 'https://webakery.ir/license-server/pay/?plugin=hesabdar',
	],
	'nobat-man' => [
		'file'  => 'nobat-man-demo.zip',
		'name'  => 'نوبت من',
		'desc'  => 'رزرو نوبت با تقویم شمسی و نسخه پرو',
		'icon'  => '📅',
		'price' => '۵۹۹,۰۰۰ تومان',
		'buy'   => 'https://webakery.ir/license-server/pay/?plugin=nobat-man',
	],
	'access-levels' => [
		'file'  => 'access-levels-demo.zip',
		'name'  => 'Barbari — مدیریت دسترسی',
		'desc'  => 'کنترل منو و افزونه‌های پیشخوان',
		'icon'  => '🔐',
		'price' => '۹۹,۹۹۹ تومان',
		'buy'   => 'https://webakery.ir/license-server/pay/?plugin=access-levels',
	],
	'wccp' => [
		'file'  => 'baget-demo.zip',
		'name'  => 'Baget — فیلدهای پرداخت',
		'desc'  => 'ویرایش فیلدهای checkout ووکامرس',
		'icon'  => '🛒',
		'price' => '۱۹۹,۰۰۰ تومان',
		'buy'   => 'https://webakery.ir/license-server/pay/?plugin=wccp',
	],
	'webakery-chat' => [
		'file'  => 'webakery-chat-box-demo.zip',
		'name'  => 'چت باکس',
		'desc'  => 'ویجت چت، صندوق پیام، اعلان تلگرام/واتساپ',
		'icon'  => '💬',
		'price' => 'از ۱۵۰,۰۰۰ تومان',
		'buy'   => 'https://webakery.ir/license-server/pay/?plugin=webakery-chat',
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
<title>دانلود نسخه دمو — webakery.ir</title>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:Tahoma,Vazirmatn,sans-serif;background:#f1f5f9;color:#0f172a}
.wrap{max-width:880px;margin:0 auto;padding:28px 16px 60px}
.hero{background:linear-gradient(135deg,#0284c7,#0369a1);color:#fff;border-radius:20px;padding:28px 24px;margin-bottom:22px;box-shadow:0 12px 32px rgba(3,105,161,.25)}
.hero h1{margin:0 0 8px;font-size:24px}
.hero p{margin:0;opacity:.95;line-height:1.7;font-size:14px}
.grid{display:grid;gap:14px}
.card{background:#fff;border-radius:16px;padding:18px 18px 16px;box-shadow:0 4px 18px rgba(15,23,42,.06);border:1px solid #e2e8f0}
.card.focus{border-color:#38bdf8;box-shadow:0 8px 28px rgba(14,165,233,.18)}
.row{display:flex;gap:14px;align-items:flex-start}
.icon{width:48px;height:48px;border-radius:14px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0}
.meta{flex:1;min-width:0}
.meta h2{margin:0 0 4px;font-size:17px}
.meta p{margin:0;color:#64748b;font-size:13px;line-height:1.6}
.price{font-size:12px;color:#0369a1;font-weight:700;margin-top:6px}
.actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 14px;border-radius:10px;text-decoration:none;font-size:13px;font-weight:700;border:0;cursor:pointer}
.btn-demo{background:#0284c7;color:#fff}
.btn-demo:hover{background:#0369a1;color:#fff}
.btn-buy{background:#fff;color:#0f172a;border:1.5px solid #cbd5e1}
.btn-buy:hover{border-color:#0284c7;color:#0284c7}
.btn[disabled],.btn.disabled{opacity:.45;pointer-events:none}
.note{margin-top:18px;background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:14px 16px;font-size:13px;line-height:1.8;color:#92400e}
.foot{margin-top:22px;text-align:center;font-size:12px;color:#64748b}
.foot a{color:#0369a1}
.miss{color:#b91c1c;font-size:12px;margin-top:8px}
.size{font-size:11px;color:#94a3b8;font-weight:500}
</style>
</head>
<body>
<div class="wrap">
	<div class="hero">
		<h1>🧪 نسخه دمو افزونه‌های وب‌آکری</h1>
		<p>
			قبل از خرید، ZIP دمو را دانلود و روی وردپرس تست نصب کنید.
			لایسنس لازم نیست — همه امکانات برای آزمایش باز است.
		</p>
	</div>

	<div class="grid">
		<?php foreach ( $demos as $slug => $d ) :
			$ok   = demo_exists( $d['file'] );
			$href = $ok ? './' . rawurlencode( $d['file'] ) : '#';
			$cls  = ( $focus && $focus === $slug ) ? 'card focus' : 'card';
			?>
			<div class="<?php echo $cls; ?>" id="<?php echo htmlspecialchars( $slug ); ?>">
				<div class="row">
					<div class="icon"><?php echo $d['icon']; ?></div>
					<div class="meta">
						<h2><?php echo htmlspecialchars( $d['name'] ); ?></h2>
						<p><?php echo htmlspecialchars( $d['desc'] ); ?></p>
						<div class="price">نسخه کامل: <?php echo htmlspecialchars( $d['price'] ); ?></div>
						<div class="actions">
							<?php if ( $ok ) : ?>
								<a class="btn btn-demo" href="<?php echo htmlspecialchars( $href ); ?>" download>
									⬇️ دانلود دمو
									<span class="size">(<?php echo htmlspecialchars( demo_size( $d['file'] ) ); ?>)</span>
								</a>
							<?php else : ?>
								<span class="btn btn-demo disabled">فایل دمو هنوز آپلود نشده</span>
							<?php endif; ?>
							<a class="btn btn-buy" href="<?php echo htmlspecialchars( $d['buy'] ); ?>">خرید نسخه کامل</a>
						</div>
						<?php if ( ! $ok ) : ?>
							<div class="miss">فایل <code><?php echo htmlspecialchars( $d['file'] ); ?></code> را داخل همین پوشه demos آپلود کنید.</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="note">
		<strong>نحوه نصب دمو:</strong>
		۱) ZIP را دانلود کنید ←
		۲) وردپرس → افزونه‌ها → افزودن → بارگذاری افزونه ←
		۳) نصب و فعال‌سازی ←
		۴) بنر آبی «نسخه دمو» را می‌بینید و می‌توانید همه امکانات را تست کنید.<br>
		بعد از رضایت، نسخه دمو را حذف کنید و نسخه کامل + لایسنس را نصب کنید.
	</div>

	<div class="foot">
		<a href="https://webakery.ir">webakery.ir</a>
		·
		<a href="https://webakery.ir/license-server/portal/">پورتال مشتری</a>
	</div>
</div>
</body>
</html>
