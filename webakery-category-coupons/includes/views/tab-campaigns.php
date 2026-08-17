<?php
defined( 'ABSPATH' ) || exit;

$editing = isset( $_GET['edit'] ) ? sanitize_text_field( wp_unslash( $_GET['edit'] ) ) : '';

if ( '' !== $editing ) {
	$campaign = 'new' === $editing ? WBCC_Campaigns::defaults() : WBCC_Campaigns::get( (int) $editing );
	if ( $campaign ) {
		include WBCC_PATH . 'includes/views/campaign-form.php';
		return;
	}
}

$campaigns = WBCC_Campaigns::all();
?>

<div class="wbcc-bar">
	<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . WBCC_MENU . '&tab=campaigns&edit=new' ) ); ?>">
		+ کمپین تخفیف جدید
	</a>
	<span class="wbcc-bar-hint">هر کمپین = یک دسته‌بندی (یا چند دسته) + بازه درصد تخفیف + قوانین مصرف</span>
</div>

<?php if ( ! $campaigns ) : ?>

	<div class="wbcc-empty">
		<div class="wbcc-empty-ic">🎟️</div>
		<h2>هنوز کمپینی نساخته‌اید</h2>
		<p>
			یک کمپین بسازید، دسته‌بندی محصول را انتخاب کنید و بازه تخفیف را مثلاً روی <strong>۴۰ تا ۵۰ درصد</strong> بگذارید؛
			افزونه هر بار یک کد یکتا با درصد تصادفی در همان بازه می‌سازد.
		</p>
		<a class="button button-primary button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=' . WBCC_MENU . '&tab=campaigns&edit=new' ) ); ?>">
			ساخت اولین کمپین
		</a>
	</div>

<?php else : ?>

	<table class="widefat striped wbcc-table">
		<thead>
			<tr>
				<th>کمپین</th>
				<th>دسته‌بندی‌ها</th>
				<th>تخفیف</th>
				<th>انقضا</th>
				<th>خودکار</th>
				<th>کدها</th>
				<th>عملیات</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $campaigns as $c ) :
			$cats  = WBCC_Campaigns::category_names( WBCC_Campaigns::ids( $c['categories'] ), 3 );
			$count = WBCC_Generator::list_coupons( array( 'campaign' => $c['id'], 'limit' => 1 ) )['total'];
			$next  = WBCC_Cron::next_run( $c );
			?>
			<tr>
				<td>
					<strong><?php echo esc_html( $c['name'] ); ?></strong>
					<span class="wbcc-id">#<?php echo (int) $c['id']; ?></span><br>
					<?php if ( empty( $c['enabled'] ) ) : ?>
						<span class="wbcc-pill wbcc-pill-off">غیرفعال</span>
					<?php else : ?>
						<span class="wbcc-pill wbcc-pill-on">فعال</span>
					<?php endif; ?>
					<?php if ( ! empty( $c['public_enabled'] ) ) : ?>
						<span class="wbcc-pill wbcc-pill-info">دریافت توسط مشتری</span>
					<?php endif; ?>
				</td>
				<td><?php echo $cats ? esc_html( implode( '، ', $cats ) ) : '<span class="wbcc-muted">انتخاب نشده</span>'; ?></td>
				<td class="wbcc-amount"><?php echo esc_html( WBCC_Date::fa_digits( WBCC_Campaigns::amount_label( $c ) ) ); ?></td>
				<td>
					<?php echo (int) $c['expires_days'] > 0
						? esc_html( WBCC_Date::fa_digits( $c['expires_days'] ) ) . ' روز'
						: '<span class="wbcc-muted">بدون انقضا</span>'; ?>
				</td>
				<td>
					<?php if ( ! empty( $c['auto_enabled'] ) ) : ?>
						<?php echo esc_html( WBCC_Cron::interval_label( $c['auto_interval'] ) ); ?>
						· <?php echo esc_html( WBCC_Date::fa_digits( $c['auto_count'] ) ); ?> کد<br>
						<span class="wbcc-muted">اجرای بعدی: <?php echo esc_html( WBCC_Date::format_long( $next ) ); ?></span>
					<?php else : ?>
						<span class="wbcc-muted">خاموش</span>
					<?php endif; ?>
				</td>
				<td>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . WBCC_MENU . '&tab=coupons&campaign=' . $c['id'] ) ); ?>">
						<?php echo esc_html( WBCC_Date::fa_digits( $count ) ); ?> کد
					</a>
				</td>
				<td class="wbcc-actions">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wbcc-inline-form">
						<?php wp_nonce_field( 'wbcc_campaign_action' ); ?>
						<input type="hidden" name="action" value="wbcc_generate">
						<input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
						<input type="number" name="count" min="1" max="500" value="<?php echo (int) $c['batch_count']; ?>" class="wbcc-mini">
						<button type="submit" class="button button-primary">ساخت کد</button>
					</form>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . WBCC_MENU . '&tab=campaigns&edit=' . $c['id'] ) ); ?>">ویرایش</a>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wbcc_toggle_campaign&id=' . $c['id'] . '&enabled=' . ( empty( $c['enabled'] ) ? 1 : 0 ) ), 'wbcc_campaign_action' ) ); ?>">
						<?php echo empty( $c['enabled'] ) ? 'فعال‌سازی' : 'غیرفعال'; ?>
					</a>
					<a class="button wbcc-danger" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wbcc_delete_campaign&id=' . $c['id'] ), 'wbcc_campaign_action' ) ); ?>"
					   onclick="return confirm('این کمپین حذف شود؟ کدهای ساخته‌شده حذف نمی‌شوند.')">حذف</a>
					<?php if ( ! empty( $c['public_enabled'] ) ) : ?>
						<code class="wbcc-shortcode">[webakery_coupon campaign="<?php echo (int) $c['id']; ?>"]</code>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

<?php endif; ?>
