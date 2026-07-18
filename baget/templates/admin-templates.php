<?php
defined( 'ABSPATH' ) || exit;

$templates   = \WCCP\Templates::all();
$all_fields  = \WCCP\CustomFields::merged_with_defaults();
$edit_key    = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : ''; // phpcs:ignore
$is_edit     = ( $edit_key && isset( $templates[ $edit_key ] ) );
$edit        = $is_edit
	? $templates[ $edit_key ]
	: array(
		'label'       => '',
		'primary'     => '#6d28d9',
		'background'  => '#f5f3ff',
		'card'        => '#ffffff',
		'text'        => '#0f172a',
		'muted'       => '#64748b',
		'button_text' => '#ffffff',
		'radius'      => '16',
		'layout'      => 'card',
		'fields'      => \WCCP\Templates::default_fields(),
		'builtin'     => false,
	);
$edit_fields = \WCCP\Templates::sanitize_fields( $edit['fields'] ?? \WCCP\Templates::default_fields() );
if ( empty( $edit_fields ) ) {
	$edit_fields = \WCCP\Templates::default_fields();
}
?>
<div class="wrap wccp-wrap">
	<div class="wccp-topbar">
		<div>
			<h1>Baget — افزودن قالب</h1>
			<p class="wccp-muted">دو قالب پیش‌فرض: <strong>محصولات دیجیتال</strong> و <strong>محصولات فیزیکی</strong>. می‌توانید قالب سفارشی جدید بسازید و بعد در «محصولات فروشگاه» روی هر محصول اعمال کنید.</p>
		</div>
	</div>

	<?php include WCCP_PATH . 'templates/admin-tabs.php'; ?>

	<?php
	$errors = get_transient( 'settings_errors' );
	if ( is_array( $errors ) ) {
		global $wp_settings_errors;
		$wp_settings_errors = $errors;
		delete_transient( 'settings_errors' );
	}
	settings_errors( 'wccp_templates' );
	?>

	<div class="wccp-howto" style="margin-bottom:16px">
		<div class="wccp-howto-step"><span>۱</span><div><strong>قالب بسازید/ویرایش کنید</strong><p>دیجیتال / فیزیکی / سفارشی</p></div></div>
		<div class="wccp-howto-step"><span>۲</span><div><strong>فیلدها را تیک بزنید</strong><p>سوالات همان قالب</p></div></div>
		<div class="wccp-howto-step"><span>۳</span><div><strong>ذخیره قالب</strong><p>نام قابل ویرایش است</p></div></div>
		<div class="wccp-howto-step"><span>۴</span><div><strong>روی محصول فروشگاه</strong><p>تب محصولات فروشگاه</p></div></div>
	</div>

	<div class="wccp-tpl-grid">
		<section class="wccp-tpl-list-card">
			<header>
				<strong>قالب‌های موجود</strong>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wccp&tab=templates' ) ); ?>">+ افزودن قالب</a>
			</header>
			<div class="wccp-tpl-cards">
				<?php foreach ( $templates as $key => $tpl ) :
					$fc = count( \WCCP\Templates::sanitize_fields( $tpl['fields'] ?? array() ) );
					?>
					<div class="wccp-tpl-card <?php echo ( $edit_key === $key ) ? 'is-selected' : ''; ?>">
						<div class="wccp-tpl-swatch" style="background:linear-gradient(135deg,<?php echo esc_attr( $tpl['primary'] ); ?>,<?php echo esc_attr( $tpl['background'] ); ?>)"></div>
						<div class="wccp-tpl-meta">
							<strong><?php echo esc_html( $tpl['label'] ); ?></strong>
							<code dir="ltr"><?php echo esc_html( $key ); ?></code>
							<span class="wccp-tag <?php echo ! empty( $tpl['builtin'] ) && empty( \WCCP\Templates::custom()[ $key ] ) ? 'default' : 'custom'; ?>">
								<?php echo ! empty( $tpl['builtin'] ) && empty( \WCCP\Templates::custom()[ $key ] ) ? 'پیش‌فرض' : 'سفارشی/ویرایش‌شده'; ?>
							</span>
							<span class="wccp-tag type"><?php echo esc_html( (string) $fc ); ?> فیلد</span>
						</div>
						<div class="wccp-tpl-actions">
							<a class="button button-small button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wccp&tab=templates&edit=' . rawurlencode( $key ) ) ); ?>">ویرایش</a>
							<?php if ( isset( \WCCP\Templates::custom()[ $key ] ) ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm('این قالب/تغییرات حذف شود؟');">
									<?php wp_nonce_field( 'wccp_delete_template' ); ?>
									<input type="hidden" name="action" value="wccp_delete_template" />
									<input type="hidden" name="key" value="<?php echo esc_attr( $key ); ?>" />
									<button type="submit" class="button button-small"><?php echo ! empty( $tpl['builtin'] ) ? 'بازنشانی' : 'حذف'; ?></button>
								</form>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="wccp-tpl-form-card">
			<header>
				<strong><?php echo $is_edit ? 'ویرایش قالب: ' . esc_html( $edit['label'] ) : 'افزودن قالب جدید'; ?></strong>
			</header>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wccp-tpl-form">
				<?php wp_nonce_field( 'wccp_save_template' ); ?>
				<input type="hidden" name="action" value="wccp_save_template" />
				<input type="hidden" name="key" value="<?php echo esc_attr( $is_edit ? $edit_key : '' ); ?>" />

				<label><strong>نام قالب</strong>
					<input type="text" name="label" class="widefat" required value="<?php echo esc_attr( $edit['label'] ); ?>" placeholder="مثلاً: اردو / فروش ویژه" />
				</label>

				<div class="wccp-color-grid">
					<label>رنگ اصلی <input type="color" name="primary" value="<?php echo esc_attr( $edit['primary'] ?: '#6d28d9' ); ?>" /></label>
					<label>پس‌زمینه <input type="color" name="background" value="<?php echo esc_attr( $edit['background'] ?: '#f5f3ff' ); ?>" /></label>
					<label>کارت <input type="color" name="card" value="<?php echo esc_attr( $edit['card'] ?: '#ffffff' ); ?>" /></label>
					<label>متن <input type="color" name="text" value="<?php echo esc_attr( $edit['text'] ?: '#0f172a' ); ?>" /></label>
					<label>متن کم‌رنگ <input type="color" name="muted" value="<?php echo esc_attr( $edit['muted'] ?: '#64748b' ); ?>" /></label>
					<label>متن دکمه <input type="color" name="button_text" value="<?php echo esc_attr( $edit['button_text'] ?: '#ffffff' ); ?>" /></label>
				</div>

				<label>شعاع گوشه (px)
					<input type="number" min="0" max="40" name="radius" value="<?php echo esc_attr( (string) ( $edit['radius'] ?? '16' ) ); ?>" class="small-text" />
				</label>

				<label>چیدمان
					<select name="layout" class="widefat">
						<option value="card" <?php selected( $edit['layout'] ?? '', 'card' ); ?>>کارت کلاسیک</option>
						<option value="minimal" <?php selected( $edit['layout'] ?? '', 'minimal' ); ?>>مینیمال</option>
						<option value="cover" <?php selected( $edit['layout'] ?? '', 'cover' ); ?>>تمام‌صفحه (Cover)</option>
					</select>
				</label>

				<div class="wccp-tpl-fields-box">
					<strong>فیلدهای اختصاصی این قالب</strong>
					<p class="wccp-muted">هر فیلدی که تیک بخورد، وقتی این قالب روی محصول اعمال شود در فرم پرداخت می‌آید.</p>
					<div class="wccp-tpl-fields-list">
						<?php foreach ( $all_fields as $fkey => $fdef ) : ?>
							<label class="wccp-tpl-field-check">
								<input type="checkbox" name="fields[]" value="<?php echo esc_attr( $fkey ); ?>" <?php checked( in_array( $fkey, $edit_fields, true ) ); ?> />
								<span>
									<strong><?php echo esc_html( $fdef['label'] ?? $fkey ); ?></strong>
									<small><?php echo esc_html( \WCCP\Fields::type_label( $fdef['type'] ?? 'text' ) ); ?> — <code dir="ltr"><?php echo esc_html( $fkey ); ?></code></small>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<p class="wccp-muted">بعد از ذخیره: <strong>محصولات فروشگاه → انتخاب قالب برای هر محصول</strong> یا لینک پرداخت آنلاین.</p>
				<button type="submit" class="button button-primary button-large"><?php echo $is_edit ? 'ذخیره تغییرات قالب' : 'افزودن قالب'; ?></button>
				<?php if ( $is_edit ) : ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wccp&tab=templates' ) ); ?>">انصراف</a>
				<?php endif; ?>
			</form>
		</section>
	</div>
</div>
