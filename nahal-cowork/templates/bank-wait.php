<?php
defined( 'ABSPATH' ) || exit;
$url = isset( $nck_bank_url ) ? (string) $nck_bank_url : '';
if ( $url === '' ) {
	return;
}
$safe = esc_url( $url );
header( 'Content-Type: text/html; charset=UTF-8' );
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta http-equiv="refresh" content="0;url=<?php echo esc_attr( $url ); ?>" />
	<title>انتقال به درگاه بانک</title>
	<style>
		body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f6f4ef;color:#222;font-family:Tahoma,Arial,sans-serif}
		.nck-bank-wait{text-align:center;padding:24px}
		.nck-bank-wait p{margin:0 0 12px;font-size:1.05rem;line-height:1.8}
		.nck-bank-wait a{color:#1f5c4a}
	</style>
</head>
<body>
	<div class="nck-bank-wait">
		<p>سفارش ثبت شد. در حال انتقال به درگاه بانک…</p>
		<p><a href="<?php echo $safe; ?>">اگر منتقل نشدید اینجا را بزنید</a></p>
	</div>
	<script>window.location.replace(<?php echo wp_json_encode( $url ); ?>);</script>
</body>
</html>
