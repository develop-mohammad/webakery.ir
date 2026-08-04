<?php
defined( 'ABSPATH' ) || exit;

$custom   = WRPM_Roles::get_custom_roles();
$wp_roles = wp_roles()->roles;
$bases    = array();
foreach ( $wp_roles as $slug => $role ) {
	if ( 'administrator' === $slug ) {
		continue;
	}
	$bases[ $slug ] = translate_user_role( $role['name'] );
}
?>
<div class="wrpm-card">
	<?php if ( ! WC_Role_Price_Manager::licensed() ) : ?>
		<div class="wrpm-notice warn">برای اعمال کامل امکانات، لایسنس را از تب «لایسنس» فعال کنید. در دوره آزمایشی هم کار می‌کند.</div>
	<?php endif; ?>

	<h2>افزودن نقش جدید</h2>
	<p class="wrpm-muted">نقش‌هایی مثل «عمده‌فروش»، «همکار» یا «نماینده» بسازید و بعد روی هر محصول قیمت جداگانه بگذارید.</p>

	<form method="post" class="wrpm-form">
		<?php wp_nonce_field( 'wrpm_add_role' ); ?>
		<input type="hidden" name="wrpm_add_role" value="1" />
		<div class="wrpm-grid-form">
			<label>
				<span>عنوان نمایشی</span>
				<input type="text" name="role_label" required placeholder="مثلاً عمده‌فروش" />
			</label>
			<label>
				<span>شناسه انگلیسی</span>
				<input type="text" name="role_slug" required pattern="[a-z0-9_\-]+" placeholder="wholesaler" dir="ltr" />
			</label>
			<label>
				<span>بر پایهٔ نقش</span>
				<select name="role_base">
					<?php foreach ( $bases as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"<?php selected( $slug, 'customer' ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>
		<p><button type="submit" class="button button-primary">افزودن نقش</button></p>
	</form>
</div>

<div class="wrpm-card">
	<h2>نقش‌های ساخته‌شده با این افزونه</h2>
	<?php if ( empty( $custom ) ) : ?>
		<p class="wrpm-muted">هنوز نقش سفارشی نساخته‌اید.</p>
	<?php else : ?>
		<table class="wrpm-table">
			<thead>
				<tr>
					<th>عنوان</th>
					<th>شناسه</th>
					<th>پایه</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $custom as $slug => $data ) : ?>
					<tr>
						<td><?php echo esc_html( $data['label'] ?? $slug ); ?></td>
						<td><code dir="ltr"><?php echo esc_html( $slug ); ?></code></td>
						<td><?php echo esc_html( $bases[ $data['base'] ?? '' ] ?? ( $data['base'] ?? '—' ) ); ?></td>
						<td>
							<a class="button button-small button-link-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wc-role-price-manager&tab=roles&wrpm_delete_role=' . rawurlencode( $slug ) ), 'wrpm_delete_role_' . $slug ) ); ?>" onclick="return confirm('این نقش حذف شود؟');">حذف</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<div class="wrpm-card">
	<h2>همهٔ نقش‌های سایت</h2>
	<ul class="wrpm-role-list">
		<?php foreach ( $wp_roles as $slug => $role ) : ?>
			<li>
				<strong><?php echo esc_html( translate_user_role( $role['name'] ) ); ?></strong>
				<code dir="ltr"><?php echo esc_html( $slug ); ?></code>
				<?php if ( isset( $custom[ $slug ] ) ) : ?>
					<span class="wrpm-badge">نقش‌قیمت</span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
