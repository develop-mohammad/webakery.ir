<?php
defined( 'ABSPATH' ) || exit;

$s    = WBCN_Settings::get();
$next = wp_next_scheduled( WBCN_Cron::DAILY );
$poll = wp_next_scheduled( WBCN_Cron::POLL );
$hook = WBCN_Settings::webhook_url( $s );
$types = (array) $s['post_types'];
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wbcn-settings">
	<?php wp_nonce_field( 'wbcn_save_settings' ); ?>
	<input type="hidden" name="action" value="wbcn_save_settings">

	<div class="wbcn-grid">
		<div class="wbcn-card">
			<h2>اتصال ربات</h2>
			<ol class="wbcn-steps">
				<li>در تلگرام به <code>@BotFather</code> بروید → <code>/newbot</code> → توکن را کپی کنید.</li>
				<li>ربات را در کانال <strong>ادمین</strong> کنید (ارسال پیام).</li>
				<li>یوزرنیم کانال را با @ وارد کنید، یا Chat ID عددی (مثلاً <code>-100…</code>).</li>
				<li>«ارسال تست» را بزنید.</li>
			</ol>
			<p>
				<label class="wbcn-label">Bot Token</label>
				<input type="text" class="regular-text" dir="ltr" name="bot_token" value="<?php echo esc_attr( $s['bot_token'] ); ?>" placeholder="123456:ABC-DEF..." autocomplete="off">
			</p>
			<p>
				<label class="wbcn-label">کانال (@username یا Chat ID)</label>
				<input type="text" class="regular-text" dir="ltr" name="channel" value="<?php echo esc_attr( $s['channel'] ); ?>" placeholder="@mychannel">
			</p>
			<p>
				<label class="wbcn-label">آیدی تلگرام مدیرها (برای /idea و /send) — با ویرگول</label>
				<input type="text" class="regular-text" dir="ltr" name="admin_ids" value="<?php echo esc_attr( $s['admin_ids'] ); ?>" placeholder="123456789">
				<span class="wbcn-hint">آیدی را از <code>@userinfobot</code> بگیرید.</span>
			</p>
		</div>

		<div class="wbcn-card">
			<h2>هاست ایران (پروکسی)</h2>
			<p class="wbcn-hint">اگر <code>api.telegram.org</code> از سرور باز نمی‌شود، HTTP/SOCKS پروکسی بگذارید.</p>
			<label class="wbcn-check">
				<input type="hidden" name="proxy_enabled" value="0">
				<input type="checkbox" name="proxy_enabled" value="1" <?php checked( ! empty( $s['proxy_enabled'] ) ); ?>>
				پروکسی برای درخواست‌های تلگرام
			</label>
			<p>
				<label class="wbcn-label">میزبان</label>
				<input type="text" dir="ltr" name="proxy_host" value="<?php echo esc_attr( $s['proxy_host'] ); ?>" placeholder="127.0.0.1">
			</p>
			<p>
				<label class="wbcn-label">پورت</label>
				<input type="text" dir="ltr" name="proxy_port" value="<?php echo esc_attr( $s['proxy_port'] ); ?>" placeholder="1080">
			</p>
			<p>
				<label class="wbcn-label">نام کاربری پروکسی (اختیاری)</label>
				<input type="text" dir="ltr" name="proxy_user" value="<?php echo esc_attr( $s['proxy_user'] ); ?>">
			</p>
			<p>
				<label class="wbcn-label">رمز پروکسی (خالی = بدون تغییر)</label>
				<input type="password" dir="ltr" name="proxy_pass" value="" autocomplete="new-password">
			</p>
		</div>
	</div>

	<div class="wbcn-grid">
		<div class="wbcn-card">
			<h2>ارسال خودکار مطلب جدید</h2>
			<label class="wbcn-check">
				<input type="hidden" name="auto_post" value="0">
				<input type="checkbox" name="auto_post" value="1" <?php checked( ! empty( $s['auto_post'] ) ); ?>>
				با انتشار، به کانال برود
			</label>
			<label class="wbcn-check">
				<input type="hidden" name="auto_photo" value="0">
				<input type="checkbox" name="auto_photo" value="1" <?php checked( ! empty( $s['auto_photo'] ) ); ?>>
				تصویر شاخص را هم بفرست
			</label>
			<label class="wbcn-check">
				<input type="hidden" name="auto_excerpt" value="0">
				<input type="checkbox" name="auto_excerpt" value="1" <?php checked( ! empty( $s['auto_excerpt'] ) ); ?>>
				خلاصه مطلب در کپشن
			</label>
			<label class="wbcn-check">
				<input type="hidden" name="signature" value="0">
				<input type="checkbox" name="signature" value="1" <?php checked( ! empty( $s['signature'] ) ); ?>>
				امضای @کانال ته پست
			</label>
			<label class="wbcn-check">
				<input type="hidden" name="disable_preview" value="0">
				<input type="checkbox" name="disable_preview" value="1" <?php checked( ! empty( $s['disable_preview'] ) ); ?>>
				پیش‌نمایش لینک تلگرام خاموش
			</label>
			<p class="wbcn-label">نوع محتوا</p>
			<label class="wbcn-check"><input type="checkbox" name="post_types[]" value="post" <?php checked( in_array( 'post', $types, true ) ); ?>> نوشته</label>
			<label class="wbcn-check"><input type="checkbox" name="post_types[]" value="page" <?php checked( in_array( 'page', $types, true ) ); ?>> برگه</label>
			<label class="wbcn-check"><input type="checkbox" name="post_types[]" value="product" <?php checked( in_array( 'product', $types, true ) ); ?>> محصول ووکامرس</label>
		</div>

		<div class="wbcn-card">
			<h2>ایده روزانه و ربات اعضا</h2>
			<label class="wbcn-check">
				<input type="hidden" name="daily_ideas" value="0">
				<input type="checkbox" name="daily_ideas" value="1" <?php checked( ! empty( $s['daily_ideas'] ) ); ?>>
				هر روز یک ایده ارزشمند از مطالب قدیمی
			</label>
			<p>
				<label class="wbcn-label">ساعت ارسال (زمان سایت)</label>
				<input type="number" name="daily_hour" min="0" max="23" value="<?php echo (int) $s['daily_hour']; ?>">
			</p>
			<p class="wbcn-hint">اجرای بعدی ایده روزانه: <strong><?php echo $next ? esc_html( date_i18n( 'Y/m/d H:i', $next ) ) : 'خاموش'; ?></strong></p>

			<p>
				<label class="wbcn-label">پیام /start ربات</label>
				<textarea name="welcome" rows="3"><?php echo esc_textarea( $s['welcome'] ); ?></textarea>
			</p>
			<p>
				<label class="wbcn-label">متن دکمه عضویت</label>
				<input type="text" name="join_label" value="<?php echo esc_attr( $s['join_label'] ); ?>">
			</p>

			<label class="wbcn-check">
				<input type="hidden" name="polling" value="0">
				<input type="checkbox" name="polling" value="1" <?php checked( ! empty( $s['polling'] ) ); ?>>
				خواندن پیام ربات با cron (اگر وب‌هوک از ایران به سایت نمی‌رسد)
			</label>
			<p class="wbcn-hint">polling بعدی: <strong><?php echo $poll ? esc_html( date_i18n( 'Y/m/d H:i', $poll ) ) : 'خاموش'; ?></strong></p>
			<p>
				<label class="wbcn-label">آدرس وب‌هوک</label>
				<input type="text" class="large-text" dir="ltr" readonly value="<?php echo esc_attr( $hook ); ?>">
			</p>
		</div>
	</div>

	<p>
		<button type="submit" class="button button-primary">ذخیره تنظیمات</button>
	</p>
</form>

<div class="wbcn-bar">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'wbcn_test' ); ?>
		<input type="hidden" name="action" value="wbcn_test">
		<button class="button button-primary">ارسال تست به کانال</button>
	</form>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'wbcn_webhook' ); ?>
		<input type="hidden" name="action" value="wbcn_webhook">
		<input type="hidden" name="webhook_act" value="set">
		<button class="button">ثبت وب‌هوک</button>
	</form>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'wbcn_webhook' ); ?>
		<input type="hidden" name="action" value="wbcn_webhook">
		<input type="hidden" name="webhook_act" value="delete">
		<button class="button">حذف وب‌هوک</button>
	</form>
</div>
