<?php
defined( 'ABSPATH' ) || exit;
$s = NCK_Settings::all();
?>
<form method="post" class="nck-settings">
	<?php wp_nonce_field( 'nck_settings' ); ?>
	<input type="hidden" name="nck_save_settings" value="1" />

	<h2>مجموعه</h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="org_name">نام مجموعه</label></th>
			<td><input id="org_name" class="regular-text" type="text" name="settings[org_name]" value="<?php echo esc_attr( $s['org_name'] ); ?>" /></td>
		</tr>
		<tr>
			<th><label for="org_tagline">شعار</label></th>
			<td><input id="org_tagline" class="regular-text" type="text" name="settings[org_tagline]" value="<?php echo esc_attr( $s['org_tagline'] ); ?>" /></td>
		</tr>
		<tr>
			<th><label for="accent">رنگ برگ نهال</label></th>
			<td><input id="accent" type="color" name="settings[accent]" value="<?php echo esc_attr( $s['accent'] ); ?>" /></td>
		</tr>
		<tr>
			<th><label for="event_notice">اطلاع رویداد</label></th>
			<td>
				<textarea id="event_notice" class="large-text" rows="3" name="settings[event_notice]"><?php echo esc_textarea( $s['event_notice'] ); ?></textarea>
				<p class="description">اگر فضای کار به‌خاطر برنامه فرهنگی جابه‌جا شود، همین متن روی فرم قرارداد و پورتال دیده می‌شود.</p>
			</td>
		</tr>
	</table>

	<h2>شیفت و سهمیه</h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="shifts_per_month">شیفت در ماه</label></th>
			<td><input id="shifts_per_month" type="number" min="1" max="62" name="settings[shifts_per_month]" value="<?php echo esc_attr( (string) $s['shifts_per_month'] ); ?>" /></td>
		</tr>
		<tr>
			<th>صبح</th>
			<td>
				<input type="text" dir="ltr" name="settings[morning_start]" value="<?php echo esc_attr( $s['morning_start'] ); ?>" />
				الی
				<input type="text" dir="ltr" name="settings[morning_end]" value="<?php echo esc_attr( $s['morning_end'] ); ?>" />
			</td>
		</tr>
		<tr>
			<th>عصر</th>
			<td>
				<input type="text" dir="ltr" name="settings[evening_start]" value="<?php echo esc_attr( $s['evening_start'] ); ?>" />
				الی
				<input type="text" dir="ltr" name="settings[evening_end]" value="<?php echo esc_attr( $s['evening_end'] ); ?>" />
			</td>
		</tr>
		<tr>
			<th><label for="grace_minutes">دقایق پیش از شروع</label></th>
			<td><input id="grace_minutes" type="number" min="0" max="60" name="settings[grace_minutes]" value="<?php echo esc_attr( (string) $s['grace_minutes'] ); ?>" /></td>
		</tr>
		<tr>
			<th>تعطیل رسمی</th>
			<td>
				<select name="settings[official_policy]">
					<option value="morning" <?php selected( $s['official_policy'], 'morning' ); ?>>فقط شیفت صبح تعطیل</option>
					<option value="full" <?php selected( $s['official_policy'], 'full' ); ?>>هر دو شیفت تعطیل</option>
					<option value="none" <?php selected( $s['official_policy'], 'none' ); ?>>بدون تأثیر خودکار</option>
				</select>
			</td>
		</tr>
		<tr>
			<th><label for="full_close_dates">تعطیلی کامل</label></th>
			<td>
				<textarea id="full_close_dates" class="large-text" rows="5" name="settings[full_close_dates]" placeholder="1404/06/21 | برنامه ویژه"><?php echo esc_textarea( $s['full_close_dates'] ); ?></textarea>
				<p class="description">هر خط یک تاریخ شمسی. اختیاری بعد از | توضیح بگذارید.</p>
			</td>
		</tr>
	</table>

	<h2>اجاره سالن</h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="hall_org">طرف اول قرارداد</label></th>
			<td><input id="hall_org" class="regular-text" type="text" name="settings[hall_org]" value="<?php echo esc_attr( $s['hall_org'] ); ?>" /></td>
		</tr>
		<tr>
			<th><label for="hall_signer">امضاکننده مجموعه</label></th>
			<td><input id="hall_signer" class="regular-text" type="text" name="settings[hall_signer]" value="<?php echo esc_attr( $s['hall_signer'] ); ?>" /></td>
		</tr>
		<tr>
			<th><label for="hall_list">فهرست سالن‌ها</label></th>
			<td>
				<textarea id="hall_list" class="large-text" rows="4" name="settings[hall_list]"><?php echo esc_textarea( $s['hall_list'] ); ?></textarea>
				<p class="description">هر خط یک سالن. در فرم اجاره به‌صورت پیشنهاد نمایش داده می‌شود.</p>
			</td>
		</tr>
		<tr>
			<th>ویدئو پروژکتور</th>
			<td>
				<input type="text" dir="ltr" name="settings[projector_price]" value="<?php echo esc_attr( (string) $s['projector_price'] ); ?>" />
				تومان به ازای
				<input type="number" min="15" max="600" name="settings[projector_minutes]" value="<?php echo esc_attr( (string) $s['projector_minutes'] ); ?>" />
				دقیقه
			</td>
		</tr>
	</table>

	<h2>ووکامرس و حسابدار</h2>
	<p class="description">هر فرمی که مبلغ پرداخت داشته باشد، به‌صورت سفارش ووکامرس ثبت می‌شود و در افزونه حسابدار هم دیده می‌شود. اگر ووکامرس خاموش باشد، خود فرم همچنان ذخیره می‌شود.</p>
	<table class="form-table" role="presentation">
		<tr>
			<th>همگام‌سازی سفارش</th>
			<td>
				<label>
					<input type="hidden" name="settings[wc_sync]" value="0" />
					<input type="checkbox" name="settings[wc_sync]" value="1" <?php checked( ! empty( $s['wc_sync'] ) ); ?> />
					ثبت خودکار سفارش در ووکامرس / حسابدار
				</label>
			</td>
		</tr>
		<tr>
			<th><label for="cowork_fee">شهریه فضای کار (تومان)</label></th>
			<td>
				<input id="cowork_fee" type="text" dir="ltr" name="settings[cowork_fee]" value="<?php echo esc_attr( (string) $s['cowork_fee'] ); ?>" />
				<p class="description">اگر بیشتر از صفر باشد، بعد از امضای قرارداد فضای کار یک سفارش ساخته می‌شود. صفر یعنی بدون سفارش.</p>
			</td>
		</tr>
		<tr>
			<th><label for="learner_fee">شهریه پیش‌فرض پذیرش (تومان)</label></th>
			<td>
				<input id="learner_fee" type="text" dir="ltr" name="settings[learner_fee]" value="<?php echo esc_attr( (string) $s['learner_fee'] ); ?>" />
				<p class="description">اگر در فرم پذیرش مبلغ خالی بماند، همین عدد برای سفارش استفاده می‌شود.</p>
			</td>
		</tr>
	</table>

	<h2>متن قرارداد فضای کار</h2>
	<p class="description">جایگاه‌ها: <code>{{org}}</code> <code>{{title}}</code> <code>{{name}}</code> <code>{{phone}}</code> <code>{{shifts}}</code> <code>{{morning}}</code> <code>{{evening}}</code> <code>{{plan}}</code> <code>{{date}}</code>. بخش‌ها با خط <code>---</code> جدا می‌شوند؛ خط اول هر بخش عنوان است.</p>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="contract_intro">مقدمه</label></th>
			<td><textarea id="contract_intro" class="large-text" rows="2" name="settings[contract_intro]"><?php echo esc_textarea( $s['contract_intro'] ); ?></textarea></td>
		</tr>
		<tr>
			<th><label for="contract_preamble">طرفین</label></th>
			<td><textarea id="contract_preamble" class="large-text" rows="2" name="settings[contract_preamble]"><?php echo esc_textarea( $s['contract_preamble'] ); ?></textarea></td>
		</tr>
		<tr>
			<th><label for="contract_sections">مواد قرارداد</label></th>
			<td><textarea id="contract_sections" class="large-text" rows="18" name="settings[contract_sections]"><?php echo esc_textarea( $s['contract_sections'] ); ?></textarea></td>
		</tr>
	</table>

	<?php submit_button( 'ذخیره تنظیمات' ); ?>
</form>
