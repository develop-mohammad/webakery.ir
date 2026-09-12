<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap wbe-wrap" dir="rtl">
	<h1>لایسنس انقضای کالا پرو</h1>
	<p class="wbe-sub">قیمت: ۸۰۰٬۰۰۰ تومان — سازنده webakery.ir، محمد حاجی مهدیخانی. شماره لایسنس به صورت <code dir="ltr">WEBAKE-XXXX-XXXX-XXXX-XXXX</code> است.</p>
	<p class="wbe-sub">امکانات نسخه پرو با رایگان یکسان است و بدون لایسنس هم کار می‌کند. لایسنس برای به‌روزرسانی خودکار و پشتیبانی است.</p>
	<?php
	if ( class_exists( 'WB_License', false ) ) {
		echo WB_License::render_box( WBE_PRODUCT ); // phpcs:ignore WordPress.Security.EscapeOutput
	} else {
		echo '<div class="notice notice-error"><p>کلاینت لایسنس در دسترس نیست.</p></div>';
	}
	?>
</div>
