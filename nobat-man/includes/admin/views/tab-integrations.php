<?php
defined( 'ABSPATH' ) || exit;
$s = NM_Settings::all();
?>
<div class="nm-panel-card">
	<h3>ووکامرس</h3>
	<p><?php echo class_exists( 'WooCommerce' ) ? '✅ ووکامرس فعال است.' : '⚠️ ووکامرس نصب نیست — پرداخت آنلاین غیرفعال.'; ?></p>
	<form method="post">
		<?php wp_nonce_field( 'nm_settings' ); ?>
		<input type="hidden" name="nm_save_settings" value="1" />
		<label>شناسه محصول رزرو (خودکار ساخته می‌شود)<input type="number" name="settings[wc_product_id]" value="<?php echo esc_attr( $s['wc_product_id'] ); ?>" class="widefat" /></label>
		<p><button class="button button-primary">ذخیره</button></p>
	</form>
</div>

<div class="nm-panel-card">
	<h3>پیامک ایرانی (پرو)</h3>
	<form method="post">
		<?php wp_nonce_field( 'nm_settings' ); ?>
		<input type="hidden" name="nm_save_settings" value="1" />
		<label>سرویس‌دهنده
			<select name="settings[sms_provider]" class="widefat">
				<option value="ippanel" <?php selected( $s['sms_provider'], 'ippanel' ); ?>>IPPanel</option>
				<option value="kavenegar" <?php selected( $s['sms_provider'], 'kavenegar' ); ?>>کاوه‌نگار</option>
				<option value="melipayamak" <?php selected( $s['sms_provider'], 'melipayamak' ); ?>>ملی‌پیامک</option>
			</select>
		</label>
		<label>API Key / Username<input type="text" name="settings[sms_api_key]" value="<?php echo esc_attr( $s['sms_api_key'] ); ?>" class="widefat" /></label>
		<label>خط ارسال / Password<input type="text" name="settings[sms_sender]" value="<?php echo esc_attr( $s['sms_sender'] ); ?>" class="widefat" /></label>
		<label>کد پترن (اختیاری)<input type="text" name="settings[sms_pattern]" value="<?php echo esc_attr( $s['sms_pattern'] ); ?>" class="widefat" /></label>
		<p><button class="button button-primary">ذخیره پیامک</button></p>
	</form>
</div>

<div class="nm-panel-card">
	<h3>گوگل کلندر (پرو)</h3>
	<form method="post">
		<?php wp_nonce_field( 'nm_settings' ); ?>
		<input type="hidden" name="nm_save_settings" value="1" />
		<label>Client ID<input type="text" name="settings[google_client_id]" value="<?php echo esc_attr( $s['google_client_id'] ); ?>" class="widefat" /></label>
		<label>Client Secret<input type="text" name="settings[google_client_secret]" value="<?php echo esc_attr( $s['google_client_secret'] ); ?>" class="widefat" /></label>
		<p>Refresh Token: <code><?php echo $s['google_refresh_token'] ? 'ثبت شده ✓' : 'ثبت نشده'; ?></code></p>
		<p>
			<button class="button button-primary">ذخیره</button>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin-post.php?action=nm_google_oauth' ) ); ?>">اتصال OAuth گوگل</a>
		</p>
	</form>
</div>

<div class="nm-panel-card">
	<h3>حسابدار</h3>
	<p>پس از پرداخت موفق، رویداد <code>hesabdar_external_sale</code> برای همگام‌سازی مالی ارسال می‌شود و متای سفارش ووکامرس علامت‌گذاری می‌گردد.</p>
</div>
