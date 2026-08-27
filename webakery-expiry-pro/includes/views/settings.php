<?php
defined( 'ABSPATH' ) || exit;
$edition = ( defined( 'WBE_EDITION' ) && 'pro' === WBE_EDITION ) ? 'پرو' : 'رایگان';
$opt     = WBE_Settings::OPTION;
$sms_msg = isset( $_GET['wbe_sms'] ) ? sanitize_key( wp_unslash( $_GET['wbe_sms'] ) ) : '';
?>
<div class="wrap wbe-wrap" dir="rtl">
	<h1>تنظیمات انقضای کالا <span class="wbe-ver"><?php echo esc_html( WBE_VERSION . ' — ' . $edition ); ?></span></h1>
	<p class="wbe-sub">سازنده: <a href="https://webakery.ir" target="_blank" rel="noopener">webakery.ir</a> — محمد حاجی مهدیخانی</p>

	<?php if ( 'sms_ok' === $sms_msg ) : ?>
		<div class="notice notice-success is-dismissible"><p>پیامک آزمایشی ارسال شد.</p></div>
	<?php elseif ( 'sms_err' === $sms_msg ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( isset( $_GET['wbe_sms_err'] ) ? wp_unslash( $_GET['wbe_sms_err'] ) : 'ارسال پیامک ناموفق بود.' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'wbe_settings_group' ); ?>
		<h2>نمایش و تقویم</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">تقویم پیش‌فرض همه محصولات</th>
				<td>
					<label><input type="radio" name="<?php echo esc_attr( $opt ); ?>[calendar]" value="jalali" <?php checked( $s['calendar'], 'jalali' ); ?> /> شمسی</label>
					&nbsp;&nbsp;
					<label><input type="radio" name="<?php echo esc_attr( $opt ); ?>[calendar]" value="gregorian" <?php checked( $s['calendar'], 'gregorian' ); ?> /> میلادی</label>
				</td>
			</tr>
			<tr>
				<th scope="row">نمایش در فروشگاه</th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[show_near_price]" value="1" <?php checked( $s['show_near_price'], 1 ); ?> /> زیر قیمت محصول</label><br>
					<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[show_in_description]" value="1" <?php checked( $s['show_in_description'], 1 ); ?> /> فیلد تاریخ انقضا در توضیحات محصول</label>
				</td>
			</tr>
		</table>

		<h2>هشدار انقضای نزدیک</h2>
		<p class="description">آلارم پیشخوان همیشه کار می‌کند. ایمیل روزانه راحت‌ترین روش بدون پنل پیامک است. پیامک اختیاری است.</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">بازه هشدار (روز)</th>
				<td>
					فوری:
					<input type="number" min="0" max="365" class="small-text" name="<?php echo esc_attr( $opt ); ?>[alert_soon_days]" value="<?php echo esc_attr( $s['alert_soon_days'] ); ?>" />
					یک ماه:
					<input type="number" min="1" max="365" class="small-text" name="<?php echo esc_attr( $opt ); ?>[alert_month_days]" value="<?php echo esc_attr( $s['alert_month_days'] ); ?>" />
					دو ماه:
					<input type="number" min="1" max="730" class="small-text" name="<?php echo esc_attr( $opt ); ?>[alert_two_month_days]" value="<?php echo esc_attr( $s['alert_two_month_days'] ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row">پیشخوان</th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[dash_alarm]" value="1" <?php checked( $s['dash_alarm'], 1 ); ?> /> نوار هشدار بالای صفحات پیشخوان</label><br>
					<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[dash_widget]" value="1" <?php checked( $s['dash_widget'], 1 ); ?> /> ویجت در صفحهٔ خانه پیشخوان</label>
				</td>
			</tr>
			<tr>
				<th scope="row">ایمیل روزانه</th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[email_alert]" value="1" <?php checked( $s['email_alert'], 1 ); ?> /> ارسال خلاصه روزانه</label><br>
					<input type="email" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[email_to]" value="<?php echo esc_attr( $s['email_to'] ); ?>" placeholder="خالی = ایمیل مدیر سایت" />
				</td>
			</tr>
			<tr>
				<th scope="row">پیامک روزانه</th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[sms_alert]" value="1" <?php checked( $s['sms_alert'], 1 ); ?> /> ارسال پیامک به مدیر</label><br>
					<input type="text" class="regular-text" dir="ltr" name="<?php echo esc_attr( $opt ); ?>[sms_phone]" value="<?php echo esc_attr( $s['sms_phone'] ); ?>" placeholder="0912xxxxxxx" /><br>
					<select name="<?php echo esc_attr( $opt ); ?>[sms_provider]">
						<?php foreach ( WBE_SMS::providers() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['sms_provider'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p>
						نام کاربری:
						<input type="text" name="<?php echo esc_attr( $opt ); ?>[sms_username]" value="<?php echo esc_attr( $s['sms_username'] ); ?>" />
						رمز:
						<input type="password" name="<?php echo esc_attr( $opt ); ?>[sms_password]" value="<?php echo esc_attr( $s['sms_password'] ); ?>" autocomplete="new-password" />
					</p>
					<p>
						API Key:
						<input type="text" class="regular-text" dir="ltr" name="<?php echo esc_attr( $opt ); ?>[sms_api_key]" value="<?php echo esc_attr( $s['sms_api_key'] ); ?>" />
						خط ارسال:
						<input type="text" dir="ltr" name="<?php echo esc_attr( $opt ); ?>[sms_sender]" value="<?php echo esc_attr( $s['sms_sender'] ); ?>" />
					</p>
				</td>
			</tr>
		</table>
		<?php submit_button( 'ذخیره تنظیمات' ); ?>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'wbe_test_sms' ); ?>
		<input type="hidden" name="action" value="wbe_test_sms" />
		<?php submit_button( 'ارسال پیامک آزمایشی', 'secondary', 'submit', false ); ?>
	</form>
</div>
