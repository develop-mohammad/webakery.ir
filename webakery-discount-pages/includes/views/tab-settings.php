<?php
defined( 'ABSPATH' ) || exit;

$settings = wp_parse_args(
	get_option( 'wdp_settings', array() ),
	array(
		'url_base'    => 'discount',
		'show_badge'  => 1,
		'delete_data' => 0,
	)
);
?>

<div class="wdp-grid wdp-grid-2">

	<div class="wdp-card-box">
		<h3>تنظیمات آدرس و نمایش</h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'wdp_save_settings' ); ?>
			<input type="hidden" name="action" value="wdp_save_settings">

			<p>
				<label class="wdp-label">پیشوند آدرس صفحه‌های تخفیف</label>
				<input type="text" name="url_base" dir="ltr" value="<?php echo esc_attr( $settings['url_base'] ); ?>">
			</p>
			<p class="wdp-hint-block">
				مثال: با مقدار <code dir="ltr">discount</code> آدرس صفحه‌ها می‌شود
				<code dir="ltr"><?php echo esc_html( home_url( '/discount/your-page/' ) ); ?></code>
			</p>

			<label class="wdp-check">
				<input type="hidden" name="show_badge" value="0">
				<input type="checkbox" name="show_badge" value="1" <?php checked( ! empty( $settings['show_badge'] ) ); ?>>
				<span>نشان «٪ تخفیف» روی محصولات (فروشگاه و صفحه محصول) نمایش داده شود</span>
			</label>
			<p class="wdp-hint-block">
				اگر خودِ صفحه تخفیف بازه‌اش را نشان می‌دهد (مثلاً «۵۰ تا ۶۰٪») و نیازی به تکرار درصد
				روی هر محصول نیست، این گزینه را خاموش کنید.
			</p>

			<label class="wdp-check">
				<input type="hidden" name="delete_data" value="0">
				<input type="checkbox" name="delete_data" value="1" <?php checked( ! empty( $settings['delete_data'] ) ); ?>>
				<span>با حذف افزونه، صفحه‌های تخفیف و تنظیمات هم پاک شوند</span>
			</label>

			<p><button type="submit" class="button button-primary">ذخیره تنظیمات</button></p>
			<p class="wdp-hint">بعد از تغییر پیشوند آدرس، پیوندهای یکتای وردپرس خودکار تازه‌سازی می‌شوند.</p>
		</form>
	</div>

	<div class="wdp-card-box">
		<h3>نکات فنی</h3>
		<ul class="wdp-guide">
			<li>موتور تشخیص، تخفیف را از تفاوت «قیمت اصلی» و «قیمت فروش» ووکامرس محاسبه می‌کند.</li>
			<li>برای محصولات متغیر (رنگ/سایز و ...)، کمترین قیمت متغیرها ملاک تخفیف است.</li>
			<li>تخفیف زمان‌بندی‌شده ووکامرس (تاریخ شروع/پایان) هم پشتیبانی می‌شود؛ افزونه هر ساعت صفحه‌ها را بازبینی می‌کند.</li>
			<li>هر صفحه تخفیف می‌تواند به یک یا چند دسته‌بندی محصول ووکامرس محدود شود (اختیاری)؛
				اگر محدود نشود، برای همه دسته‌بندی‌ها باز است.</li>
			<li>اگر بازه دو صفحه با هم هم‌پوشانی داشته باشد، ترتیب برنده شدن این‌طور است: اولویت بیشتر ←
				صفحه‌ای که به دسته‌بندی محصول محدود شده (نسبت به صفحه عمومی) ← بازه باریک‌تر.</li>
			<li>هر محصول همیشه فقط در یک صفحه تخفیف قرار می‌گیرد؛ با تغییر تخفیف یا تغییر دسته‌بندی محصول،
				از صفحه قبلی خودکار خارج و به صفحه درست منتقل می‌شود.</li>
		</ul>
	</div>

</div>
