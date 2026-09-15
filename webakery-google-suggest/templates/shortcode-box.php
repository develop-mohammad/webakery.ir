<?php
defined( 'ABSPATH' ) || exit;
$code = WBGS_Frontend::primary_shortcode();
?>
<section class="wbgs-card wbgs-shortcode-box" dir="<?php echo esc_attr( WBGS_Plugin::html_dir() ); ?>">
	<h2>قرار دادن در صفحه</h2>
	<p class="wbgs-hint">این شورت‌کد را در هر برگه یا نوشته بگذارید تا بازدیدکننده‌ها (نه فقط مدیر) سجست‌یاب را روی همان صفحه استفاده کنند.</p>
	<div class="wbgs-shortcode-row">
		<code id="wbgs-shortcode-text" dir="ltr"><?php echo esc_html( $code ); ?></code>
		<button type="button" class="button" id="wbgs-copy-shortcode" data-shortcode="<?php echo esc_attr( $code ); ?>">کپی</button>
	</div>
	<p class="wbgs-hint">نام مستعار: <code dir="ltr">[wbgs_suggest]</code> — همان ابزار.</p>
	<ol class="wbgs-shortcode-steps">
		<li>برگه‌ها ← افزودن برگه</li>
		<li>شورت‌کد <code dir="ltr"><?php echo esc_html( $code ); ?></code> را بچسبانید</li>
		<li>انتشار. بازدیدکننده‌ها همان صفحه را باز می‌کنند و جستجو می‌کنند</li>
	</ol>
	<p class="wbgs-hint">در المنتور: ویجت Shortcode را اضافه کنید و <code dir="ltr"><?php echo esc_html( $code ); ?></code> را وارد کنید.</p>
	<p class="wbgs-hint">اگر صفحهٔ جدا (<code dir="ltr">/sajest/</code>) هم روشن است و ۴۰۴ شد، یک‌بار تنظیمات ← پیوندهای یکتا را ذخیره کنید.</p>
</section>
<script>
(function () {
	var btn = document.getElementById('wbgs-copy-shortcode');
	if (!btn) return;
	btn.addEventListener('click', function () {
		var text = btn.getAttribute('data-shortcode') || '[webakery_suggest]';
		var prev = btn.textContent;
		function done(ok) {
			btn.textContent = ok ? 'کپی شد' : 'کپی نشد';
			setTimeout(function () { btn.textContent = prev; }, 1600);
		}
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(function () { done(true); }).catch(function () { done(false); });
			return;
		}
		var ta = document.createElement('textarea');
		ta.value = text;
		document.body.appendChild(ta);
		ta.select();
		try { done(document.execCommand('copy')); } catch (e) { done(false); }
		document.body.removeChild(ta);
	});
})();
</script>
