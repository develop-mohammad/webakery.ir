<?php
defined( 'ABSPATH' ) || exit;
$s = NM_Settings::all();
$days = NM_Jalali::weekday_names();
$months = NM_Jalali::month_names();
$closed = array_map( 'intval', (array) $s['closed_weekdays'] );
$active_months = array_map( 'intval', (array) ( $s['active_months'] ?? range( 1, 12 ) ) );
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

	<h3>بازه تاریخ رزرو (شمسی)</h3>
	<p class="nm-muted">اگر خالی بگذارید: از امروز تا چند ماه جلو باز است. تاریخ‌ها باید سال جاری (۱۴۰۵) باشند؛ اگر بازهٔ قدیمی (مثلاً ۱۴۰۴) بماند، افزونه خودکار از امروز حساب می‌کند.</p>
	<div class="nm-fields-admin">
		<label>از تاریخ (مثال ۱۴۰۵/۰۴/۰۱)<input type="text" name="settings[booking_from]" value="<?php echo esc_attr( $s['booking_from'] ?? '' ); ?>" class="widefat nm-jalali-input" placeholder="خالی = از امروز" /></label>
		<label>تا تاریخ (مثال ۱۴۰۵/۰۷/۳۱)<input type="text" name="settings[booking_until]" value="<?php echo esc_attr( $s['booking_until'] ?? '' ); ?>" class="widefat nm-jalali-input" placeholder="خالی = طبق ماه‌های جلو" /></label>
		<label>اگر «تا تاریخ» خالی است — چند ماه جلو؟<input type="number" name="settings[booking_months_ahead]" min="1" max="24" value="<?php echo esc_attr( $s['booking_months_ahead'] ?? 3 ); ?>" class="widefat" /></label>
	</div>

	<h3>ماه‌های فعال سال شمسی</h3>
	<div class="nm-check-row">
		<?php foreach ( $months as $i => $name ) : $m = $i + 1; ?>
			<label><input type="checkbox" name="settings[active_months][]" value="<?php echo (int) $m; ?>" <?php checked( in_array( $m, $active_months, true ) ); ?> /> <?php echo esc_html( $name ); ?></label>
		<?php endforeach; ?>
	</div>

	<h3>تقویم شمسی و تعطیلی</h3>
	<p>روزهای <strong>بستهٔ هفتگی</strong> (مثلاً اگر جمعه کار می‌کنید، تیک جمعه را بردارید):</p>
	<div class="nm-check-row">
		<?php foreach ( $days as $i => $name ) : ?>
			<label><input type="checkbox" name="settings[closed_weekdays][]" value="<?php echo (int) $i; ?>" <?php checked( in_array( $i, $closed, true ) ); ?> /> <?php echo esc_html( $name ); ?></label>
		<?php endforeach; ?>
	</div>
	<label><input type="checkbox" name="settings[block_holidays]" value="1" <?php checked( ! empty( $s['block_holidays'] ) ); ?> /> مسدود کردن فقط تعطیلات رسمی ایران (نه روزهای عادی)</label>
	<p class="nm-muted">روزهای «بسته» به‌خاطر تعطیل هفتگی یا نبودن ساعت کاری هستند؛ با تعطیل رسمی فرق دارند.</p>

	<h3>پرداخت</h3>
	<div class="nm-fields-admin">
		<p class="nm-muted">اگر مرچنت‌کد زیبال نداشته باشید، افزونه می‌تواند از درگاه ووکامرس سایت شما استفاده کند.</p>
		<label>درگاه
			<select name="settings[payment_gateway]" class="widefat">
				<option value="auto" <?php selected( ($s['payment_gateway'] ?? 'auto'), 'auto' ); ?>>خودکار (اگر مرچنت زیبال بود زیبال، وگرنه ووکامرس)</option>
				<option value="woocommerce" <?php selected( $s['payment_gateway'] ?? '', 'woocommerce' ); ?>>فقط ووکامرس</option>
				<option value="zibal" <?php selected( $s['payment_gateway'] ?? '', 'zibal' ); ?>>فقط زیبال مستقیم</option>
			</select>
		</label>
		<label>مرچنت‌کد زیبال (اختیاری)<input type="text" name="settings[zibal_merchant]" value="<?php echo esc_attr( $s['zibal_merchant'] ?? '' ); ?>" class="widefat" dir="ltr" placeholder="اگر خالی باشد از ووکامرس استفاده می‌شود" /></label>
	</div>

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
