<?php
defined( 'ABSPATH' ) || exit;
$s = WBCB_Settings::get();
$o = WBCB_Settings::OPTION;
?>
<div class="wrap wbcb-wrap wbcb-settings-wrap" dir="rtl">
	<h1>تنظیمات چت باکس</h1>

	<?php settings_errors( 'wbcb' ); ?>

	<div class="notice notice-info" style="padding:12px 14px;margin:12px 0 18px">
		<p style="margin:0 0 8px"><strong>اگر چت‌باکس روی سایت دیده نمی‌شود، این‌ها را چک کنید:</strong></p>
		<ol style="margin:0;padding-right:18px;line-height:1.9">
			<li>تیک «نمایش ویجت چت در سایت» روشن باشد.</li>
			<li>اگر با اکانت مدیر لاگین هستید و تیک «ویجت برای مدیر…» روشن است، ویجت را نمی‌بینید — تیک را بردارید یا با حالت ناشناس سایت را باز کنید.</li>
			<li>صفحه <a href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-chat-box-license' ) ); ?>">خرید و لایسنس</a> فعال باشد (دوره آزمایشی ۳ روزه یا لایسنس خریداری‌شده).</li>
			<li>گزینه «نمایش در» روی «همه صفحات» باشد.</li>
		</ol>
	</div>

	<form method="post" action="options.php" class="wbcb-settings-form">
		<?php settings_fields( 'wbcb_group' ); ?>

		<h2 class="title">ظاهر ویجت</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">فعال‌سازی</th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( $o ); ?>[enabled]" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?> /> نمایش ویجت چت در سایت</label>
				</td>
			</tr>
			<tr>
				<th scope="row">عنوان ویجت</th>
				<td><input type="text" class="regular-text" name="<?php echo esc_attr( $o ); ?>[title]" value="<?php echo esc_attr( $s['title'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row">زیرعنوان</th>
				<td><input type="text" class="regular-text" name="<?php echo esc_attr( $o ); ?>[subtitle]" value="<?php echo esc_attr( $s['subtitle'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row">پیام خوش‌آمد</th>
				<td><textarea class="large-text" rows="3" name="<?php echo esc_attr( $o ); ?>[welcome]"><?php echo esc_textarea( $s['welcome'] ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row">Placeholder ورودی</th>
				<td><input type="text" class="regular-text" name="<?php echo esc_attr( $o ); ?>[placeholder]" value="<?php echo esc_attr( $s['placeholder'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row">رنگ اصلی</th>
				<td><input type="color" name="<?php echo esc_attr( $o ); ?>[primary]" value="<?php echo esc_attr( $s['primary'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row">موقعیت</th>
				<td>
					<select name="<?php echo esc_attr( $o ); ?>[position]">
						<option value="left" <?php selected( $s['position'], 'left' ); ?>>پایین چپ (RTL)</option>
						<option value="right" <?php selected( $s['position'], 'right' ); ?>>پایین راست</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">نمایش در</th>
				<td>
					<select name="<?php echo esc_attr( $o ); ?>[show_on]">
						<option value="all" <?php selected( $s['show_on'], 'all' ); ?>>همه صفحات</option>
						<option value="front" <?php selected( $s['show_on'], 'front' ); ?>>فقط صفحه اصلی</option>
						<option value="shop" <?php selected( $s['show_on'], 'shop' ); ?>>فروشگاه ووکامرس</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">قبل از چت</th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( $o ); ?>[ask_name]" value="1" <?php checked( ! empty( $s['ask_name'] ) ); ?> /> پرسیدن نام</label><br>
					<label><input type="checkbox" name="<?php echo esc_attr( $o ); ?>[ask_email]" value="1" <?php checked( ! empty( $s['ask_email'] ) ); ?> /> پرسیدن ایمیل</label>
				</td>
			</tr>
			<tr>
				<th scope="row">مدیران</th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( $o ); ?>[hide_logged_in_admins]" value="1" <?php checked( ! empty( $s['hide_logged_in_admins'] ) ); ?> /> ویجت برای مدیر لاگین‌شده نمایش داده نشود</label>
					<p class="description" style="color:#b45309">اگر این تیک روشن باشد، وقتی خودتان لاگین هستید چت را روی سایت نمی‌بینید. برای تست، تیک را بردارید یا سایت را در پنجره ناشناس باز کنید.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">پاسخ خودکار</th>
				<td>
					<textarea class="large-text" rows="2" name="<?php echo esc_attr( $o ); ?>[auto_reply]" placeholder="مثلاً: ممنون، به زودی پاسخ می‌دهیم."><?php echo esc_textarea( $s['auto_reply'] ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row">ساعات کاری</th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( $o ); ?>[business_hours_enabled]" value="1" <?php checked( ! empty( $s['business_hours_enabled'] ) ); ?> /> فقط در بازه آنلاین باشیم</label><br>
					<input type="text" class="small-text" name="<?php echo esc_attr( $o ); ?>[business_hours]" value="<?php echo esc_attr( $s['business_hours'] ); ?>" placeholder="9-18" />
					<p class="description">فرمت ساعت ۲۴ساعته مثلاً 9-18</p>
					<textarea class="large-text" rows="2" name="<?php echo esc_attr( $o ); ?>[offline_note]"><?php echo esc_textarea( $s['offline_note'] ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row">لینک سریع برای مشتری</th>
				<td>
					<label>واتساپ (نمایش در ویجت)<br><input type="text" class="regular-text" dir="ltr" name="<?php echo esc_attr( $o ); ?>[whatsapp]" value="<?php echo esc_attr( $s['whatsapp'] ); ?>" placeholder="98912xxxxxxx" /></label><br><br>
					<label>تلگرام یوزرنیم (نمایش در ویجت)<br><input type="text" class="regular-text" dir="ltr" name="<?php echo esc_attr( $o ); ?>[telegram]" value="<?php echo esc_attr( $s['telegram'] ); ?>" placeholder="username" /></label>
					<p class="description">این‌ها فقط لینک زیر ویجت برای مشتری هستند — برای اعلان به خودتان بخش زیر را پر کنید.</p>
				</td>
			</tr>
		</table>

		<hr>
		<h2 class="title">اعلان به شما (ایمیل / تلگرام / واتساپ)</h2>
		<p class="description">وقتی بازدیدکننده پیام جدید بفرستد، این اعلان‌ها برای شما ارسال می‌شود.</p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">ایمیل</th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( $o ); ?>[email_notify]" value="1" <?php checked( ! empty( $s['email_notify'] ) ); ?> /> ایمیل هنگام پیام جدید</label><br>
					<input type="email" class="regular-text" placeholder="خالی = ایمیل مدیر سایت" name="<?php echo esc_attr( $o ); ?>[email_to]" value="<?php echo esc_attr( $s['email_to'] ); ?>" />
					<p><button type="button" class="button wbcb-test-notify" data-channel="email">ارسال تست ایمیل</button></p>
				</td>
			</tr>

			<tr>
				<th scope="row">تلگرام (Bot)</th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( $o ); ?>[tg_notify]" value="1" <?php checked( ! empty( $s['tg_notify'] ) ); ?> /> اعلان پیام جدید به تلگرام</label>
					<div class="wbcb-help-box">
						<strong>راه‌اندازی سریع:</strong>
						<ol>
							<li>در تلگرام به <code>@BotFather</code> بروید → <code>/newbot</code> → توکن را کپی کنید.</li>
							<li>یک‌بار به ربات خودتان پیام بدهید (مثلاً /start).</li>
							<li>Chat ID را از <code>@userinfobot</code> بگیرید (عدد مثبت برای شخصی، منفی برای گروه).</li>
							<li>توکن و Chat ID را ذخیره کنید و «تست تلگرام» را بزنید.</li>
						</ol>
					</div>
					<p>
						<label>Bot Token<br>
							<input type="text" class="regular-text" dir="ltr" name="<?php echo esc_attr( $o ); ?>[tg_bot_token]" value="<?php echo esc_attr( $s['tg_bot_token'] ); ?>" placeholder="123456:ABC-DEF..." />
						</label>
					</p>
					<p>
						<label>Chat ID<br>
							<input type="text" class="regular-text" dir="ltr" name="<?php echo esc_attr( $o ); ?>[tg_chat_id]" value="<?php echo esc_attr( $s['tg_chat_id'] ); ?>" placeholder="مثلاً 123456789" />
						</label>
					</p>
					<p><button type="button" class="button button-primary wbcb-test-notify" data-channel="telegram">ارسال تست تلگرام</button></p>
				</td>
			</tr>

			<tr>
				<th scope="row">واتساپ</th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( $o ); ?>[wa_notify]" value="1" <?php checked( ! empty( $s['wa_notify'] ) ); ?> /> اعلان پیام جدید به واتساپ</label>
					<p>
						<label>شماره دریافت‌کننده (با کد کشور، بدون +)<br>
							<input type="text" class="regular-text" dir="ltr" name="<?php echo esc_attr( $o ); ?>[wa_notify_phone]" value="<?php echo esc_attr( $s['wa_notify_phone'] ); ?>" placeholder="98912xxxxxxx" />
						</label>
					</p>
					<p>
						<label>ارائه‌دهنده<br>
							<select name="<?php echo esc_attr( $o ); ?>[wa_provider]" id="wbcb-wa-provider">
								<option value="callmebot" <?php selected( $s['wa_provider'], 'callmebot' ); ?>>CallMeBot (رایگان شخصی)</option>
								<option value="ultramsg" <?php selected( $s['wa_provider'], 'ultramsg' ); ?>>Ultramsg (حرفه‌ای)</option>
							</select>
						</label>
					</p>

					<div class="wbcb-wa-callmebot wbcb-help-box">
						<strong>CallMeBot — رایگان برای اعلان شخصی:</strong>
						<ol>
							<li>از واتساپ به شماره <code>+34 644 66 92 45</code> پیام دهید:</li>
							<li><code>I allow callmebot to send me messages</code></li>
							<li>API Key را که برایتان می‌فرستد اینجا وارد کنید.</li>
							<li>جزئیات: <a href="https://www.callmebot.com/blog/free-api-whatsapp-messages/" target="_blank" rel="noopener">callmebot.com</a></li>
						</ol>
						<label>API Key<br>
							<input type="text" class="regular-text" dir="ltr" name="<?php echo esc_attr( $o ); ?>[wa_callmebot_key]" value="<?php echo esc_attr( $s['wa_callmebot_key'] ); ?>" />
						</label>
					</div>

					<div class="wbcb-wa-ultramsg wbcb-help-box" style="margin-top:12px">
						<strong>Ultramsg (اختیاری — اگر حساب دارید):</strong>
						<p>
							<label>Instance ID<br>
								<input type="text" class="regular-text" dir="ltr" name="<?php echo esc_attr( $o ); ?>[wa_ultramsg_instance]" value="<?php echo esc_attr( $s['wa_ultramsg_instance'] ); ?>" placeholder="instanceXXXX" />
							</label>
						</p>
						<p>
							<label>Token<br>
								<input type="text" class="regular-text" dir="ltr" name="<?php echo esc_attr( $o ); ?>[wa_ultramsg_token]" value="<?php echo esc_attr( $s['wa_ultramsg_token'] ); ?>" />
							</label>
						</p>
					</div>

					<p><button type="button" class="button button-primary wbcb-test-notify" data-channel="whatsapp">ارسال تست واتساپ</button></p>
				</td>
			</tr>
		</table>

		<p>
			<button type="button" class="button wbcb-test-notify" data-channel="all">تست همه کانال‌های فعال</button>
			<span id="wbcb-test-result" class="wbcb-test-result" aria-live="polite"></span>
		</p>

		<?php submit_button( 'ذخیره تنظیمات' ); ?>
	</form>
</div>
