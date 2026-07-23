<?php
defined( 'ABSPATH' ) || exit;
$s = WBCB_Settings::get();
?>
<div class="wrap wbcb-wrap wbcb-settings-wrap" dir="rtl">
	<h1>تنظیمات چت باکس</h1>

	<?php settings_errors( 'wbcb' ); ?>

	<form method="post" action="options.php" class="wbcb-settings-form">
		<?php settings_fields( 'wbcb_group' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">فعال‌سازی</th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( WBCB_Settings::OPTION ); ?>[enabled]" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?> /> نمایش ویجت چت در سایت</label>
				</td>
			</tr>
			<tr>
				<th scope="row">عنوان ویجت</th>
				<td><input type="text" class="regular-text" name="<?php echo esc_attr( WBCB_Settings::OPTION ); ?>[title]" value="<?php echo esc_attr( $s['title'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row">زیرعنوان</th>
				<td><input type="text" class="regular-text" name="<?php echo esc_attr( WBCB_Settings::OPTION ); ?>[subtitle]" value="<?php echo esc_attr( $s['subtitle'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row">پیام خوش‌آمد</th>
				<td><textarea class="large-text" rows="3" name="<?php echo esc_attr( WBCB_Settings::OPTION ); ?>[welcome]"><?php echo esc_textarea( $s['welcome'] ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row">Placeholder ورودی</th>
				<td><input type="text" class="regular-text" name="<?php echo esc_attr( WBCB_Settings::OPTION ); ?>[placeholder]" value="<?php echo esc_attr( $s['placeholder'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row">رنگ اصلی</th>
				<td><input type="color" name="<?php echo esc_attr( WBCB_Settings::OPTION ); ?>[primary]" value="<?php echo esc_attr( $s['primary'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row">موقعیت</th>
				<td>
					<select name="<?php echo esc_attr( WBCB_Settings::OPTION ); ?>[position]">
						<option value="left" <?php selected( $s['position'], 'left' ); ?>>پایین چپ (RTL)</option>
						<option value="right" <?php selected( $s['position'], 'right' ); ?>>پایین راست</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">نمایش در</th>
				<td>
					<select name="<?php echo esc_attr( WBCB_Settings::OPTION ); ?>[show_on]">
						<option value="all" <?php selected( $s['show_on'], 'all' ); ?>>همه صفحات</option>
						<option value="front" <?php selected( $s['show_on'], 'front' ); ?>>فقط صفحه اصلی</option>
						<option value="shop" <?php selected( $s['show_on'], 'shop' ); ?>>فروشگاه ووکامرس</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">قبل از چت</th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( WBCB_Settings::OPTION ); ?>[ask_name]" value="1" <?php checked( ! empty( $s['ask_name'] ) ); ?> /> پرسیدن نام</label><br>
					<label><input type="checkbox" name="<?php echo esc_attr( WBCB_Settings::OPTION ); ?>[ask_email]" value="1" <?php checked( ! empty( $s['ask_email'] ) ); ?> /> پرسیدن ایمیل</label>
				</td>
			</tr>
			<tr>
				<th scope="row">مدیران</th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( WBCB_Settings::OPTION ); ?>[hide_logged_in_admins]" value="1" <?php checked( ! empty( $s['hide_logged_in_admins'] ) ); ?> /> ویجت برای مدیر لاگین‌شده نمایش داده نشود</label>
				</td>
			</tr>
			<tr>
				<th scope="row">اعلان ایمیل</th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( WBCB_Settings::OPTION ); ?>[email_notify]" value="1" <?php checked( ! empty( $s['email_notify'] ) ); ?> /> ایمیل هنگام پیام جدید</label><br>
					<input type="email" class="regular-text" placeholder="خالی = ایمیل مدیر سایت" name="<?php echo esc_attr( WBCB_Settings::OPTION ); ?>[email_to]" value="<?php echo esc_attr( $s['email_to'] ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row">پاسخ خودکار</th>
				<td>
					<textarea class="large-text" rows="2" name="<?php echo esc_attr( WBCB_Settings::OPTION ); ?>[auto_reply]" placeholder="مثلاً: ممنون، به زودی پاسخ می‌دهیم."><?php echo esc_textarea( $s['auto_reply'] ); ?></textarea>
					<p class="description">یک‌بار بعد از اولین پیام بازدیدکننده (اگر هنوز پاسخ ادمین نبود).</p>
				</td>
			</tr>
			<tr>
				<th scope="row">ساعات کاری</th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( WBCB_Settings::OPTION ); ?>[business_hours_enabled]" value="1" <?php checked( ! empty( $s['business_hours_enabled'] ) ); ?> /> فقط در بازه آنلاین باشیم</label><br>
					<input type="text" class="small-text" name="<?php echo esc_attr( WBCB_Settings::OPTION ); ?>[business_hours]" value="<?php echo esc_attr( $s['business_hours'] ); ?>" placeholder="9-18" />
					<p class="description">فرمت ساعت ۲۴ساعته مثلاً 9-18</p>
					<textarea class="large-text" rows="2" name="<?php echo esc_attr( WBCB_Settings::OPTION ); ?>[offline_note]"><?php echo esc_textarea( $s['offline_note'] ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row">لینک‌های سریع</th>
				<td>
					<label>واتساپ (فقط عدد)<br><input type="text" class="regular-text" dir="ltr" name="<?php echo esc_attr( WBCB_Settings::OPTION ); ?>[whatsapp]" value="<?php echo esc_attr( $s['whatsapp'] ); ?>" placeholder="98912xxxxxxx" /></label><br><br>
					<label>تلگرام (یوزرنیم بدون @)<br><input type="text" class="regular-text" dir="ltr" name="<?php echo esc_attr( WBCB_Settings::OPTION ); ?>[telegram]" value="<?php echo esc_attr( $s['telegram'] ); ?>" /></label>
				</td>
			</tr>
		</table>

		<?php submit_button( 'ذخیره تنظیمات' ); ?>
	</form>
</div>
