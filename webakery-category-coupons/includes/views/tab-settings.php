<?php
defined( 'ABSPATH' ) || exit;

$settings = wp_parse_args( get_option( 'wbcc_settings', array() ), array(
	'default_prefix'  => 'OFF',
	'cleanup_expired' => 0,
	'cleanup_days'    => 7,
	'delete_data'     => 0,
) );

$log  = get_option( 'wbcc_log', array() );
$next = wp_next_scheduled( WBCC_Cron::HOOK );
?>

<div class="wbcc-grid wbcc-grid-2">

	<div class="wbcc-card-box">
		<h3>تنظیمات کلی</h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'wbcc_save_settings' ); ?>
			<input type="hidden" name="action" value="wbcc_save_settings">

			<p>
				<label class="wbcc-label">پیشوند پیش‌فرض کد (وقتی کمپین پیشوند ندارد)</label>
				<input type="text" name="default_prefix" dir="ltr" maxlength="12"
				       value="<?php echo esc_attr( $settings['default_prefix'] ); ?>">
			</p>

			<label class="wbcc-check">
				<input type="hidden" name="cleanup_expired" value="0">
				<input type="checkbox" name="cleanup_expired" value="1" <?php checked( ! empty( $settings['cleanup_expired'] ) ); ?>>
				<span>پاک‌سازی خودکار کدهای منقضی‌شده افزونه</span>
			</label>

			<p>
				<label class="wbcc-label">پاک‌سازی چند روز بعد از انقضا</label>
				<input type="number" name="cleanup_days" min="0" max="365" value="<?php echo (int) $settings['cleanup_days']; ?>">
			</p>

			<label class="wbcc-check">
				<input type="hidden" name="delete_data" value="0">
				<input type="checkbox" name="delete_data" value="1" <?php checked( ! empty( $settings['delete_data'] ) ); ?>>
				<span>با حذف افزونه، کمپین‌ها و تنظیمات هم پاک شود</span>
			</label>

			<p><button type="submit" class="button button-primary">ذخیره تنظیمات</button></p>
		</form>

		<hr>
		<p class="wbcc-hint-block">
			اجرای بعدی زمان‌بند خودکار:
			<strong><?php echo $next ? esc_html( WBCC_Date::format_long( $next ) ) : 'زمان‌بندی نشده'; ?></strong>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'wbcc_settings_action' ); ?>
			<input type="hidden" name="action" value="wbcc_cleanup">
			<button type="submit" class="button">پاک‌سازی همین حالا</button>
		</form>
	</div>

	<div class="wbcc-card-box">
		<h3>راهنمای سریع</h3>
		<ol class="wbcc-guide">
			<li>در تب «کمپین‌های تخفیف» یک کمپین بسازید.</li>
			<li>دسته‌بندی محصولات را تیک بزنید (مثلاً «لوازم خانگی»).</li>
			<li>بازه تخفیف را بگذارید: از <strong>۴۰</strong> تا <strong>۵۰</strong> با پله <strong>۵</strong>.</li>
			<li>روی «ساخت کد» بزنید؛ کدها با درصد تصادفی همان بازه ساخته می‌شوند.</li>
			<li>برای ساخت مداوم، «ساخت خودکار زمان‌بندی‌شده» را روشن کنید.</li>
			<li>برای اینکه مشتری خودش کد بگیرد، شورت‌کد
				<code dir="ltr">[webakery_coupon campaign="1"]</code> را در برگه بگذارید یا ویجت المنتور «کد تخفیف دسته‌بندی» را اضافه کنید.</li>
		</ol>

		<h3>آخرین اجراهای خودکار</h3>
		<?php if ( ! is_array( $log ) || ! $log ) : ?>
			<p class="wbcc-muted">هنوز اجرای خودکاری ثبت نشده است.</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr><th>زمان</th><th>کمپین</th><th>نتیجه</th></tr></thead>
				<tbody>
				<?php foreach ( array_slice( $log, 0, 10 ) as $row ) : ?>
					<tr>
						<td><?php echo esc_html( WBCC_Date::format_long( $row['time'] ) ); ?></td>
						<td><?php echo esc_html( $row['campaign'] ); ?></td>
						<td><?php echo esc_html( $row['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

</div>
