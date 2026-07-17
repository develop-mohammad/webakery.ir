<?php
defined( 'ABSPATH' ) || exit;
$s = NM_Settings::all();
$days = NM_Jalali::weekday_names();
$closed = array_map( 'intval', (array) $s['closed_weekdays'] );
?>
<form method="post" class="nm-panel-card">
	<?php wp_nonce_field( 'nm_settings' ); ?>
	<input type="hidden" name="nm_save_settings" value="1" />

	<h3>عمومی</h3>
	<div class="nm-fields-admin">
		<label>نام کسب‌وکار<input type="text" name="settings[business_name]" value="<?php echo esc_attr( $s['business_name'] ); ?>" class="widefat" /></label>
		<label>برچسب واحد پول<input type="text" name="settings[currency_label]" value="<?php echo esc_attr( $s['currency_label'] ); ?>" class="widefat" /></label>
		<label>قیمت پیش‌فرض (تومان)<input type="number" name="settings[default_price]" value="<?php echo esc_attr( $s['default_price'] ); ?>" class="widefat" /></label>
		<label>مدت پیش‌فرض (دقیقه)<input type="number" name="settings[default_duration]" min="5" max="300" value="<?php echo esc_attr( $s['default_duration'] ); ?>" class="widefat" /></label>
		<label>حداقل مدت<input type="number" name="settings[min_duration]" value="<?php echo esc_attr( $s['min_duration'] ); ?>" class="widefat" /></label>
		<label>حداکثر مدت (تا ۵ ساعت)<input type="number" name="settings[max_duration]" value="<?php echo esc_attr( $s['max_duration'] ); ?>" class="widefat" /></label>
		<label>وقفه بین رزروها<input type="number" name="settings[buffer_minutes]" value="<?php echo esc_attr( $s['buffer_minutes'] ); ?>" class="widefat" /></label>
		<label>گام اسلات (دقیقه)<input type="number" name="settings[slot_step]" value="<?php echo esc_attr( $s['slot_step'] ); ?>" class="widefat" /></label>
	</div>

	<h3>تقویم شمسی و تعطیلی</h3>
	<p>روزهای تعطیل هفتگی:</p>
	<div class="nm-check-row">
		<?php foreach ( $days as $i => $name ) : ?>
			<label><input type="checkbox" name="settings[closed_weekdays][]" value="<?php echo (int) $i; ?>" <?php checked( in_array( $i, $closed, true ) ); ?> /> <?php echo esc_html( $name ); ?></label>
		<?php endforeach; ?>
	</div>
	<label><input type="checkbox" name="settings[block_holidays]" value="1" <?php checked( ! empty( $s['block_holidays'] ) ); ?> /> مسدود کردن تعطیلات رسمی ایران</label>

	<h3>ظاهر</h3>
	<div class="nm-fields-admin">
		<label>رنگ اصلی<input type="color" name="settings[primary_color]" value="<?php echo esc_attr( $s['primary_color'] ); ?>" /></label>
		<label>رنگ ثانویه<input type="color" name="settings[accent_color]" value="<?php echo esc_attr( $s['accent_color'] ); ?>" /></label>
	</div>

	<h3>فیلدها و آپلود</h3>
	<label><input type="checkbox" name="settings[require_email]" value="1" <?php checked( ! empty( $s['require_email'] ) ); ?> /> ایمیل اجباری</label>
	<label><input type="checkbox" name="settings[require_city]" value="1" <?php checked( ! empty( $s['require_city'] ) ); ?> /> شهر اجباری</label>
	<label><input type="checkbox" name="settings[require_gender]" value="1" <?php checked( ! empty( $s['require_gender'] ) ); ?> /> جنسیت اجباری</label>
	<label><input type="checkbox" name="settings[enable_photo]" value="1" <?php checked( ! empty( $s['enable_photo'] ) ); ?> /> ارسال عکس</label>
	<label><input type="checkbox" name="settings[enable_voice]" value="1" <?php checked( ! empty( $s['enable_voice'] ) ); ?> /> ارسال ویس</label>
	<label>حداکثر حجم آپلود (مگابایت)<input type="number" name="settings[max_upload_mb]" value="<?php echo esc_attr( $s['max_upload_mb'] ); ?>" class="widefat" /></label>

	<h3>متن تشکر (قابل سفارشی‌سازی + لینک)</h3>
	<p class="nm-muted">متغیرها: {booking_code} {jalali_date} {start_time} {end_time} {customer_name} {price} {site_url} {invoice_no}</p>
	<textarea name="settings[thank_you_text]" class="widefat" rows="8"><?php echo esc_textarea( $s['thank_you_text'] ); ?></textarea>

	<h3>اعلان‌ها</h3>
	<label><input type="checkbox" name="settings[notify_email]" value="1" <?php checked( ! empty( $s['notify_email'] ) ); ?> /> ایمیل پس از تکمیل</label>
	<label><input type="checkbox" name="settings[notify_sms]" value="1" <?php checked( ! empty( $s['notify_sms'] ) ); ?> /> پیامک پس از تکمیل (پرو)</label>
	<label>ایمیل ادمین<input type="email" name="settings[admin_email]" value="<?php echo esc_attr( $s['admin_email'] ); ?>" class="widefat" /></label>

	<p style="margin-top:20px"><button class="button button-primary button-hero">ذخیره تنظیمات</button></p>
</form>
