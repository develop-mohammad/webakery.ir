<?php
defined( 'ABSPATH' ) || exit;
/** @var array $s */
/** @var bool $saved */
$providers = WBL_SMS::providers();
$pages     = get_pages( array( 'sort_column' => 'post_title' ) );
$roles     = wp_roles()->get_names();
$tab       = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore
?>
<div class="wrap wbl-admin" dir="rtl">
	<h1>ورود آسان — لاگین پیامکی و جیمیل</h1>
	<p class="wbl-admin-lead">ورود کاربران با شماره موبایل (OTP) و حساب جیمیل. شورت‌کد: <code>[webakery_login]</code></p>

	<?php if ( $saved ) : ?>
		<div class="notice notice-success is-dismissible"><p>تنظیمات ذخیره شد.</p></div>
	<?php endif; ?>

	<nav class="nav-tab-wrapper">
		<a class="nav-tab <?php echo 'general' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-login&tab=general' ) ); ?>">عمومی</a>
		<a class="nav-tab <?php echo 'appearance' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-login&tab=appearance' ) ); ?>">ظاهر / قالب</a>
		<a class="nav-tab <?php echo 'sms' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-login&tab=sms' ) ); ?>">پیامک</a>
		<a class="nav-tab <?php echo 'google' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-login&tab=google' ) ); ?>">جیمیل / گوگل</a>
		<a class="nav-tab <?php echo 'license' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=wb-licenses' ) ); ?>">لایسنس</a>
	</nav>

	<form method="post" class="wbl-admin-form">
		<?php wp_nonce_field( 'wbl_settings' ); ?>
		<input type="hidden" name="wbl_save_settings" value="1" />

		<?php if ( 'google' === $tab ) : ?>
			<table class="form-table" role="presentation">
				<tr>
					<th>فعال‌سازی ورود با جیمیل</th>
					<td><label><input type="checkbox" name="settings[enable_google]" value="1" <?php checked( ! empty( $s['enable_google'] ) ); ?> /> نمایش دکمه ورود با Google</label></td>
				</tr>
				<tr>
					<th><label for="g_cid">Client ID</label></th>
					<td><input id="g_cid" type="text" class="large-text" dir="ltr" name="settings[google_client_id]" value="<?php echo esc_attr( $s['google_client_id'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="g_sec">Client Secret</label></th>
					<td><input id="g_sec" type="password" class="large-text" dir="ltr" name="settings[google_client_secret]" value="<?php echo esc_attr( $s['google_client_secret'] ); ?>" autocomplete="new-password" /></td>
				</tr>
				<tr>
					<th>Redirect URI</th>
					<td>
						<code dir="ltr"><?php echo esc_html( WBL_Google::redirect_uri() ); ?></code>
						<p class="description">این آدرس را در Google Cloud Console → Credentials → Authorized redirect URIs ثبت کنید.</p>
					</td>
				</tr>
			</table>

		<?php elseif ( 'appearance' === $tab ) : ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="tpl">قالب صفحه</label></th>
					<td>
						<select id="tpl" name="settings[template_layout]" class="regular-text">
							<?php foreach ( WBL_Settings::layouts() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['template_layout'], $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">اسپلیت = پنل برند + موكاپ گوشی OTP + فرم. در المنتور هم قابل‌override است.</p>
					</td>
				</tr>
				<tr>
					<th><label for="anim">استایل انیمیشن</label></th>
					<td>
						<select id="anim" name="settings[animation_style]" class="regular-text">
							<?php foreach ( WBL_Settings::animations() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['animation_style'], $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">iOS: اسپرینگ و sheet نرم · تلگرام: حباب پیام و اسلاید سریع · ترکیبی: هر دو.</p>
					</td>
				</tr>
				<tr>
					<th>موكاپ گوشی</th>
					<td><label><input type="checkbox" name="settings[show_phone_visual]" value="1" <?php checked( ! empty( $s['show_phone_visual'] ) ); ?> /> نمایش گوشی OTP در قالب اسپلیت</label></td>
				</tr>
				<tr>
					<th><label for="bh">تیتر پنل برند</label></th>
					<td><input id="bh" type="text" class="large-text" name="settings[brand_headline]" value="<?php echo esc_attr( $s['brand_headline'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="bt">متن پنل برند</label></th>
					<td><input id="bt" type="text" class="large-text" name="settings[brand_text]" value="<?php echo esc_attr( $s['brand_text'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="fcolor">رنگ اکسنت</label></th>
					<td><input id="fcolor" type="color" name="settings[primary_color]" value="<?php echo esc_attr( $s['primary_color'] ); ?>" /></td>
				</tr>
				<tr>
					<th>رنگ پنل برند</th>
					<td>
						<input type="color" name="settings[panel_color_a]" value="<?php echo esc_attr( $s['panel_color_a'] ); ?>" /> شروع
						<input type="color" name="settings[panel_color_b]" value="<?php echo esc_attr( $s['panel_color_b'] ); ?>" /> میانی
					</td>
				</tr>
				<tr>
					<th>شیشه</th>
					<td>
						بلور <input type="number" min="6" max="40" name="settings[glass_blur]" value="<?php echo esc_attr( $s['glass_blur'] ); ?>" /> px —
						گردی <input type="number" min="8" max="40" name="settings[glass_radius]" value="<?php echo esc_attr( $s['glass_radius'] ); ?>" /> px
					</td>
				</tr>
				<tr>
					<th><label for="ftitle">عنوان فرم</label></th>
					<td><input id="ftitle" type="text" class="regular-text" name="settings[form_title]" value="<?php echo esc_attr( $s['form_title'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="fsub">زیرعنوان فرم</label></th>
					<td><input id="fsub" type="text" class="large-text" name="settings[form_subtitle]" value="<?php echo esc_attr( $s['form_subtitle'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="fph">placeholder موبایل</label></th>
					<td><input id="fph" type="text" class="regular-text" name="settings[phone_placeholder]" value="<?php echo esc_attr( $s['phone_placeholder'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="ccss">CSS سفارشی</label></th>
					<td>
						<textarea id="ccss" name="settings[custom_css]" class="large-text code" rows="6" dir="ltr" placeholder=".wbl-box{ }"><?php echo esc_textarea( $s['custom_css'] ); ?></textarea>
						<p class="description">برای ریزتنظیم ظاهر. شورت‌کد نمونه: <code>[webakery_login layout="split" animation="telegram"]</code></p>
					</td>
				</tr>
			</table>

		<?php elseif ( 'sms' === $tab ) : ?>
			<table class="form-table" role="presentation">
				<tr>
					<th>فعال‌سازی ورود با موبایل</th>
					<td><label><input type="checkbox" name="settings[enable_phone]" value="1" <?php checked( ! empty( $s['enable_phone'] ) ); ?> /> ارسال کد OTP به موبایل</label></td>
				</tr>
				<tr>
					<th><label for="sms_provider">سرویس‌دهنده</label></th>
					<td>
						<select id="sms_provider" name="settings[sms_provider]" class="regular-text">
							<?php foreach ( $providers as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['sms_provider'], $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr class="wbl-melipayamak-only">
					<th><label for="sms_user">نام کاربری ملی‌پیامک</label></th>
					<td><input id="sms_user" type="text" class="regular-text" dir="ltr" name="settings[sms_username]" value="<?php echo esc_attr( $s['sms_username'] ); ?>" /></td>
				</tr>
				<tr class="wbl-melipayamak-only">
					<th><label for="sms_pass">رمز عبور ملی‌پیامک</label></th>
					<td><input id="sms_pass" type="password" class="regular-text" dir="ltr" name="settings[sms_password]" value="<?php echo esc_attr( $s['sms_password'] ); ?>" autocomplete="new-password" /></td>
				</tr>
				<tr class="wbl-api-key-row">
					<th><label for="sms_key">API Key</label></th>
					<td>
						<input id="sms_key" type="text" class="large-text" dir="ltr" name="settings[sms_api_key]" value="<?php echo esc_attr( $s['sms_api_key'] ); ?>" />
						<p class="description">برای کاوه‌نگار، IPPanel و قاصدک.</p>
					</td>
				</tr>
				<tr>
					<th><label for="sms_sender">خط ارسال / Sender</label></th>
					<td><input id="sms_sender" type="text" class="regular-text" dir="ltr" name="settings[sms_sender]" value="<?php echo esc_attr( $s['sms_sender'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="sms_pattern">کد پترن / قالب</label></th>
					<td>
						<input id="sms_pattern" type="text" class="regular-text" dir="ltr" name="settings[sms_pattern]" value="<?php echo esc_attr( $s['sms_pattern'] ); ?>" />
						<p class="description">ملی‌پیامک: bodyId پترن خدماتی. کاوه‌نگار: نام قالب Verify. IPPanel: کد پترن. خالی = ارسال متنی.</p>
					</td>
				</tr>
				<tr>
					<th><label for="sms_pvar">نام متغیر پترن</label></th>
					<td><input id="sms_pvar" type="text" class="regular-text" dir="ltr" name="settings[sms_pattern_var]" value="<?php echo esc_attr( $s['sms_pattern_var'] ); ?>" placeholder="code" /></td>
				</tr>
				<tr>
					<th><label for="sms_msg">متن پیامک</label></th>
					<td>
						<textarea id="sms_msg" name="settings[sms_message]" class="large-text" rows="3"><?php echo esc_textarea( $s['sms_message'] ); ?></textarea>
						<p class="description">از <code>{code}</code> برای جای‌گذاری کد استفاده کنید (ارسال متنی).</p>
					</td>
				</tr>
				<tr>
					<th>طول کد / اعتبار</th>
					<td>
						<input type="number" min="4" max="8" name="settings[otp_length]" value="<?php echo esc_attr( $s['otp_length'] ); ?>" /> رقم —
						اعتبار <input type="number" min="60" max="600" name="settings[otp_ttl]" value="<?php echo esc_attr( $s['otp_ttl'] ); ?>" /> ثانیه —
						فاصله ارسال مجدد <input type="number" min="30" max="300" name="settings[otp_resend_wait]" value="<?php echo esc_attr( $s['otp_resend_wait'] ); ?>" /> ثانیه
					</td>
				</tr>
				<tr>
					<th>محدودیت‌ها</th>
					<td>
						حداکثر تلاش: <input type="number" min="3" max="20" name="settings[otp_max_attempts]" value="<?php echo esc_attr( $s['otp_max_attempts'] ); ?>" />
						— سقف روزانه هر شماره: <input type="number" min="1" max="50" name="settings[otp_daily_limit]" value="<?php echo esc_attr( $s['otp_daily_limit'] ); ?>" />
					</td>
				</tr>
				<tr>
					<th>تست ارسال</th>
					<td>
						<input type="tel" id="wbl-test-phone" class="regular-text" dir="ltr" placeholder="09123456789" />
						<button type="button" class="button" id="wbl-test-sms">ارسال پیامک تست</button>
						<span id="wbl-test-result"></span>
					</td>
				</tr>
			</table>

		<?php else : ?>
			<table class="form-table" role="presentation">
				<tr>
					<th>ثبت‌نام خودکار</th>
					<td><label><input type="checkbox" name="settings[auto_register]" value="1" <?php checked( ! empty( $s['auto_register'] ) ); ?> /> اگر کاربر نبود، حساب جدید بساز</label></td>
				</tr>
				<tr>
					<th><label for="def_role">نقش پیش‌فرض</label></th>
					<td>
						<select id="def_role" name="settings[default_role]">
							<?php foreach ( $roles as $rk => $rl ) : ?>
								<option value="<?php echo esc_attr( $rk ); ?>" <?php selected( $s['default_role'], $rk ); ?>><?php echo esc_html( translate_user_role( $rl ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="redir">آدرس بعد از ورود</label></th>
					<td>
						<input id="redir" type="url" class="large-text" dir="ltr" name="settings[redirect_after]" value="<?php echo esc_attr( $s['redirect_after'] ); ?>" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>" />
						<p class="description">خالی = حساب کاربری ووکامرس (در صورت وجود) یا صفحه اصلی.</p>
					</td>
				</tr>
				<tr>
					<th><label for="login_page">صفحه ورود</label></th>
					<td>
						<select id="login_page" name="settings[login_page_id]">
							<option value="0">— انتخاب کنید —</option>
							<?php foreach ( $pages as $p ) : ?>
								<option value="<?php echo (int) $p->ID; ?>" <?php selected( (int) $s['login_page_id'], (int) $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">صفحه‌ای که شورت‌کد <code>[webakery_login]</code> در آن قرار دارد.</p>
					</td>
				</tr>
				<tr>
					<th>جایگزینی wp-login</th>
					<td><label><input type="checkbox" name="settings[replace_wp_login]" value="1" <?php checked( ! empty( $s['replace_wp_login'] ) ); ?> /> هدایت /wp-login.php به صفحه ورود بالا</label></td>
				</tr>
				<tr>
					<th>ظاهر</th>
					<td><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-login&tab=appearance' ) ); ?>">تنظیم قالب، انیمیشن و رنگ‌ها →</a></td>
				</tr>
			</table>
		<?php endif; ?>

		<?php if ( 'license' !== $tab ) : ?>
			<p class="submit"><button type="submit" class="button button-primary">ذخیره تنظیمات</button></p>
		<?php endif; ?>
	</form>

	<div class="wbl-admin-help">
		<h2>راهنمای سریع</h2>
		<ol>
			<li><strong>المنتور:</strong> ویجت «ورود آسان» را از دسته وب‌آکری بکشید (ظاهر مینیمال؛ عنوان را با Heading المنتور بگذارید).</li>
			<li>یا شورت‌کد <code>[webakery_login]</code> / بدون عنوان: <code>[webakery_login show_title="0"]</code></li>
			<li>در تب پیامک، پنل ملی‌پیامک (یا کاوه‌نگار / IPPanel / قاصدک) را وصل کنید.</li>
			<li>برای جیمیل، OAuth Client در Google Cloud بسازید و Redirect URI را ثبت کنید.</li>
		</ol>
	</div>
</div>
