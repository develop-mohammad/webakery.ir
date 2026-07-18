<?php
defined( 'ABSPATH' ) || exit;
/** @var string $tab */
/** @var array $templates */
/** @var array $edit */
/** @var string $edit_key */
$templates = \WCCP\Templates::all();
$edit_key  = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : ''; // phpcs:ignore
$edit      = $edit_key && isset( $templates[ $edit_key ] ) && empty( $templates[ $edit_key ]['builtin'] )
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
	);
?>
<div class="wrap wccp-wrap">
	<div class="wccp-topbar">
		<div>
			<h1>Baget — قالب صفحه پرداخت</h1>
			<p class="wccp-muted">قالب آماده بسازید یا انتخاب کنید؛ بعد برای هر محصول آنلاین قالب را جداگانه تعیین کنید.</p>
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

	<div class="wccp-tpl-grid">
		<section class="wccp-tpl-list-card">
			<header>
				<strong>قالب‌های موجود</strong>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wccp&tab=templates' ) ); ?>">+ افزودن قالب</a>
			</header>
			<div class="wccp-tpl-cards">
				<?php foreach ( $templates as $key => $tpl ) : ?>
					<div class="wccp-tpl-card" style="--p:<?php echo esc_attr( $tpl['primary'] ); ?>;--bg:<?php echo esc_attr( $tpl['background'] ); ?>">
						<div class="wccp-tpl-swatch" style="background:linear-gradient(135deg,<?php echo esc_attr( $tpl['primary'] ); ?>,<?php echo esc_attr( $tpl['background'] ); ?>)"></div>
						<div class="wccp-tpl-meta">
							<strong><?php echo esc_html( $tpl['label'] ); ?></strong>
							<code dir="ltr"><?php echo esc_html( $key ); ?></code>
							<span class="wccp-tag <?php echo ! empty( $tpl['builtin'] ) ? 'default' : 'custom'; ?>">
								<?php echo ! empty( $tpl['builtin'] ) ? 'پیش‌فرض' : 'سفارشی'; ?>
							</span>
						</div>
						<div class="wccp-tpl-actions">
							<?php if ( empty( $tpl['builtin'] ) ) : ?>
								<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=wccp&tab=templates&edit=' . rawurlencode( $key ) ) ); ?>">ویرایش</a>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm('این قالب حذف شود؟');">
									<?php wp_nonce_field( 'wccp_delete_template' ); ?>
									<input type="hidden" name="action" value="wccp_delete_template" />
									<input type="hidden" name="key" value="<?php echo esc_attr( $key ); ?>" />
									<button type="submit" class="button button-small">حذف</button>
								</form>
							<?php else : ?>
								<span class="wccp-muted">قابل ویرایش نیست</span>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="wccp-tpl-form-card">
			<header>
				<strong><?php echo $edit_key ? 'ویرایش قالب سفارشی' : 'افزودن قالب جدید'; ?></strong>
			</header>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wccp-tpl-form">
				<?php wp_nonce_field( 'wccp_save_template' ); ?>
				<input type="hidden" name="action" value="wccp_save_template" />
				<input type="hidden" name="key" value="<?php echo esc_attr( $edit_key ); ?>" />

				<label>نام قالب
					<input type="text" name="label" class="widefat" required value="<?php echo esc_attr( $edit['label'] ); ?>" placeholder="مثلاً: قالب فروش ویژه" />
				</label>

				<div class="wccp-color-grid">
					<label>رنگ اصلی <input type="color" name="primary" value="<?php echo esc_attr( $edit['primary'] ); ?>" /></label>
					<label>پس‌زمینه <input type="color" name="background" value="<?php echo esc_attr( $edit['background'] ); ?>" /></label>
					<label>کارت <input type="color" name="card" value="<?php echo esc_attr( $edit['card'] ); ?>" /></label>
					<label>متن <input type="color" name="text" value="<?php echo esc_attr( $edit['text'] ); ?>" /></label>
					<label>متن کم‌رنگ <input type="color" name="muted" value="<?php echo esc_attr( $edit['muted'] ); ?>" /></label>
					<label>متن دکمه <input type="color" name="button_text" value="<?php echo esc_attr( $edit['button_text'] ); ?>" /></label>
				</div>

				<label>شعاع گوشه (px)
					<input type="number" min="0" max="40" name="radius" value="<?php echo esc_attr( (string) $edit['radius'] ); ?>" class="small-text" />
				</label>

				<label>چیدمان
					<select name="layout" class="widefat">
						<option value="card" <?php selected( $edit['layout'], 'card' ); ?>>کارت کلاسیک</option>
						<option value="minimal" <?php selected( $edit['layout'], 'minimal' ); ?>>مینیمال</option>
						<option value="cover" <?php selected( $edit['layout'], 'cover' ); ?>>تمام‌صفحه (Cover)</option>
					</select>
				</label>

				<p class="wccp-muted">بعد از ذخیره، در ویرایش هر محصول آنلاین می‌توانید این قالب را انتخاب کنید.</p>
				<button type="submit" class="button button-primary button-large"><?php echo $edit_key ? 'به‌روزرسانی قالب' : 'افزودن قالب'; ?></button>
				<?php if ( $edit_key ) : ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wccp&tab=templates' ) ); ?>">انصراف</a>
				<?php endif; ?>
			</form>
		</section>
	</div>
</div>
