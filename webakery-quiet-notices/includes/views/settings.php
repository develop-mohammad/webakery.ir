<?php
defined( 'ABSPATH' ) || exit;
$opt = WBQN_Plugin::OPTION;
?>
<div class="wrap wbqn-wrap" dir="rtl">
	<h1>حذف نوتیف پیشخوان <small style="font-weight:400;color:#64748b">v<?php echo esc_html( WBQN_VERSION ); ?></small></h1>
	<p>سازنده: <a href="https://webakery.ir" target="_blank" rel="noopener">webakery.ir</a> — نوتیفیکیشن‌های شلوغ افزونه‌ها را در پیشخوان خاموش می‌کند.</p>

	<div class="wbqn-card">
		<form method="post" action="options.php">
			<?php settings_fields( 'wbqn_group' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th>فعال‌سازی</th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[enabled]" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?> />
							خاموش کردن نوتیف‌ها
						</label>
					</td>
				</tr>
				<tr>
					<th>محدوده</th>
					<td>
						<select name="<?php echo esc_attr( $opt ); ?>[scope]">
							<option value="all_admin" <?php selected( $s['scope'] ?? 'all_admin', 'all_admin' ); ?>>همه صفحات پیشخوان</option>
							<option value="dashboard_only" <?php selected( $s['scope'] ?? '', 'dashboard_only' ); ?>>فقط داشبورد (صفحه اصلی)</option>
						</select>
					</td>
				</tr>
				<tr>
					<th>حالت</th>
					<td>
						<select name="<?php echo esc_attr( $opt ); ?>[mode]">
							<option value="all" <?php selected( $s['mode'], 'all' ); ?>>همه اعلان‌ها (پیشنهادی)</option>
							<option value="plugins" <?php selected( $s['mode'], 'plugins' ); ?>>فقط افزونه‌ها — هسته وردپرس بماند</option>
							<option value="non_core" <?php selected( $s['mode'], 'non_core' ); ?>>همه غیر‌هسته‌ای</option>
						</select>
					</td>
				</tr>
				<tr>
					<th>اعمال برای</th>
					<td>
						<select name="<?php echo esc_attr( $opt ); ?>[hide_for]">
							<option value="all_caps" <?php selected( $s['hide_for'], 'all_caps' ); ?>>همه کاربران پیشخوان</option>
							<option value="only_editors" <?php selected( $s['hide_for'], 'only_editors' ); ?>>فقط ویرایشگرها (نه مدیرکل)</option>
							<option value="everyone" <?php selected( $s['hide_for'], 'everyone' ); ?>>همه</option>
						</select>
					</td>
				</tr>
				<tr>
					<th>استثناها</th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[keep_errors]" value="1" <?php checked( ! empty( $s['keep_errors'] ) ); ?> /> نگه داشتن پیام‌های خطا (قرمز)</label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[hide_settings_errors]" value="1" <?php checked( ! empty( $s['hide_settings_errors'] ) ); ?> /> مخفی کردن پیام‌های تنظیمات و راهنما (مثل «لطفاً پرونده‌ای را برگزینید»)</label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[hide_update_nags]" value="1" <?php checked( ! empty( $s['hide_update_nags'] ) ); ?> /> مخفی کردن نَگ آپدیت وردپرس</label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[hide_wc_nags]" value="1" <?php checked( ! empty( $s['hide_wc_nags'] ) ); ?> /> مخفی کردن اعلان‌های ووکامرس</label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[css_fallback]" value="1" <?php checked( ! empty( $s['css_fallback'] ) ); ?> /> پشتیبان CSS (برای نوتیف‌های سرسخت)</label>
					</td>
				</tr>
			</table>

			<?php submit_button( 'ذخیره تنظیمات' ); ?>
		</form>
	</div>

	<div class="wbqn-card">
		<h2>نمایش موقت نوتیف‌ها</h2>
		<p>اگر گاهی لازم است نوتیف‌ها را ببینید، این لینک را باز کنید:</p>
		<p><a class="button" href="<?php echo esc_url( admin_url( 'index.php?wbqn_show=1' ) ); ?>">نمایش موقت داشبورد</a></p>
	</div>
</div>
<style>
.wbqn-wrap{max-width:820px}
.wbqn-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;margin:16px 0;box-shadow:0 8px 24px rgba(15,23,42,.04)}
.wbqn-card h2{margin-top:0}
</style>
