<?php
defined( 'ABSPATH' ) || exit;

$settings = WRPM_Pricing::settings();
$roles    = WRPM_Roles::priceable_roles();
?>
<div class="wrpm-card">
	<?php if ( ! WC_Role_Price_Manager::licensed() ) : ?>
		<div class="wrpm-notice warn">اعمال قیمت نقش‌محور و مخفی‌سازی قیمت به لایسنس فعال (یا دوره آزمایشی) نیاز دارد.</div>
	<?php endif; ?>

	<h2>تخفیف درصدی سراسری</h2>
	<p class="wrpm-muted">اگر روی محصول قیمت اختصاصی نقش نگذارید، این درصد از قیمت پایه کم می‌شود. قیمت اختصاصی محصول اولویت دارد.</p>

	<form method="post">
		<?php wp_nonce_field( 'wrpm_save_settings' ); ?>
		<input type="hidden" name="wrpm_save_settings" value="1" />

		<table class="wrpm-table">
			<thead>
				<tr>
					<th>نقش</th>
					<th>تخفیف٪</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $roles as $slug => $label ) :
					$pct = $settings['global_discounts'][ $slug ] ?? '';
					?>
					<tr>
						<td><?php echo esc_html( $label ); ?> <code dir="ltr"><?php echo esc_html( $slug ); ?></code></td>
						<td>
							<input type="number" name="global_discounts[<?php echo esc_attr( $slug ); ?>]" value="<?php echo esc_attr( $pct ); ?>" min="0" max="100" step="0.1" style="width:100px" dir="ltr" placeholder="0" />
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<hr class="wrpm-hr" />

		<h2>مخفی‌کردن قیمت</h2>
		<p class="wrpm-muted">به‌جای قیمت، پیام زیر نمایش داده می‌شود.</p>

		<label class="wrpm-check">
			<input type="checkbox" name="hide_price_guests" value="1" <?php checked( ! empty( $settings['hide_price_guests'] ) ); ?> />
			مخفی‌کردن قیمت برای مهمانان (کاربران واردنشده)
		</label>

		<p><strong>مخفی برای این نقش‌ها:</strong></p>
		<div class="wrpm-checks">
			<?php foreach ( $roles as $slug => $label ) : ?>
				<label class="wrpm-check">
					<input type="checkbox" name="hide_price_roles[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, (array) $settings['hide_price_roles'], true ) ); ?> />
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endforeach; ?>
		</div>

		<label class="wrpm-field">
			<span>پیام جایگزین قیمت</span>
			<input type="text" class="regular-text" name="hide_price_message" value="<?php echo esc_attr( $settings['hide_price_message'] ); ?>" />
		</label>

		<p><button type="submit" class="button button-primary">ذخیره تنظیمات</button></p>
	</form>
</div>
