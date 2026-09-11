<?php
defined( 'ABSPATH' ) || exit;
$reports = class_exists( 'WBGS_Reports' ) ? WBGS_Reports::admin_flat() : array();
?>
<section class="wbgs-card">
	<h2>گزارش‌های ذخیره‌شده</h2>
	<p class="wbgs-hint">هر استخراج را می‌توان ذخیره کرد و بعداً باز کرد یا با یک عبارت دیگر مقایسه کرد. این‌ها کیورد ساختگی نیستند؛ همان پیشنهادهای گوگل‌اند.</p>
	<?php if ( ! $reports ) : ?>
		<p class="wbgs-empty">هنوز گزارشی ذخیره نشده. بعد از استخراج، «ذخیره گزارش» را بزنید.</p>
	<?php else : ?>
		<table class="widefat striped wbgs-report-table">
			<thead>
				<tr>
					<th>عبارت پایه</th>
					<th>تعداد</th>
					<th>تاریخ</th>
					<th>صاحب</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $reports as $report ) : ?>
					<tr>
						<td><?php echo esc_html( $report['seed'] ); ?></td>
						<td><?php echo esc_html( (string) (int) $report['count'] ); ?></td>
						<td><?php echo esc_html( $report['created'] ? wp_date( 'Y/m/d H:i', (int) $report['created'] ) : '—' ); ?></td>
						<td dir="ltr"><?php echo 0 === strpos( (string) $report['actor'], 'u' ) ? 'کاربر' : 'مهمان'; ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('حذف شود؟');">
								<input type="hidden" name="action" value="wbgs_delete_report" />
								<input type="hidden" name="id" value="<?php echo esc_attr( $report['id'] ); ?>" />
								<?php wp_nonce_field( 'wbgs_delete_report' ); ?>
								<button type="submit" class="button-link-delete">حذف</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</section>
