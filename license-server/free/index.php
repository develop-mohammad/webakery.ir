<?php
/**
 * دانلود رایگان افزونه‌های webakery
 * https://webakery.ir/license-server/free/
 */
header( 'Content-Type: text/html; charset=utf-8' );

$items = array(
	array(
		'file'  => 'webakery-gateway-pricing.zip',
		'name'  => 'قیمت‌گذاری درگاه | Gateway Pricing',
		'desc'  => 'کارمزد درگاه قسطی + تخفیف نقدی زیبال/زرین‌پال — سازگار با اسنپ‌پی و ترب‌پی',
		'ver'   => '1.0.0',
		'icon'  => '💳',
	),
);

function free_size( $file ) {
	$path = __DIR__ . '/' . $file;
	if ( ! is_readable( $path ) ) {
		return '—';
	}
	$b = filesize( $path );
	return $b < 1024 * 1024 ? round( $b / 1024 ) . ' KB' : round( $b / ( 1024 * 1024 ), 1 ) . ' MB';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>دانلود رایگان افزونه‌ها — webakery.ir</title>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:Tahoma,Vazirmatn,sans-serif;background:#f8fafc;color:#0f172a}
.wrap{max-width:720px;margin:0 auto;padding:28px 16px 50px}
.hero{background:linear-gradient(135deg,#0d9488,#0f766e);color:#fff;border-radius:18px;padding:24px;margin-bottom:18px}
.hero h1{margin:0 0 8px;font-size:22px}
.hero p{margin:0;opacity:.95;line-height:1.7;font-size:14px}
.card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:16px;margin-bottom:12px;box-shadow:0 4px 14px rgba(15,23,42,.05)}
.row{display:flex;gap:12px;align-items:flex-start}
.icon{width:48px;height:48px;border-radius:12px;background:#f0fdfa;display:flex;align-items:center;justify-content:center;font-size:24px}
h2{margin:0 0 4px;font-size:16px}
p{margin:0;color:#64748b;font-size:13px;line-height:1.6}
.meta{font-size:12px;color:#0f766e;margin-top:6px}
.btn{display:inline-flex;margin-top:12px;padding:10px 14px;border-radius:10px;background:#0d9488;color:#fff;text-decoration:none;font-weight:700;font-size:13px}
.btn:hover{background:#0f766e;color:#fff}
.miss{color:#b91c1c;font-size:12px;margin-top:8px}
.foot{margin-top:20px;text-align:center;font-size:12px;color:#64748b}
.foot a{color:#0f766e}
</style>
</head>
<body>
<div class="wrap">
	<div class="hero">
		<h1>⬇️ دانلود رایگان افزونه‌های وب‌آکری</h1>
		<p>بدون لایسنس — نصب مستقیم روی وردپرس</p>
	</div>

	<?php foreach ( $items as $it ) :
		$ok = is_readable( __DIR__ . '/' . $it['file'] );
		?>
		<div class="card">
			<div class="row">
				<div class="icon"><?php echo $it['icon']; ?></div>
				<div>
					<h2><?php echo htmlspecialchars( $it['name'] ); ?></h2>
					<p><?php echo htmlspecialchars( $it['desc'] ); ?></p>
					<div class="meta">نسخه <?php echo htmlspecialchars( $it['ver'] ); ?> · <?php echo htmlspecialchars( free_size( $it['file'] ) ); ?> · رایگان</div>
					<?php if ( $ok ) : ?>
						<a class="btn" href="./<?php echo rawurlencode( $it['file'] ); ?>" download>دانلود ZIP</a>
					<?php else : ?>
						<div class="miss">فایل هنوز روی هاست آپلود نشده: <?php echo htmlspecialchars( $it['file'] ); ?></div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	<?php endforeach; ?>

	<div class="foot">
		<a href="https://webakery.ir">webakery.ir</a>
	</div>
</div>
</body>
</html>
