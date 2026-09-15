<?php
defined( 'ABSPATH' ) || exit;
/** @var string $tab */

$settings = \WCCP\QuickBuy::settings();
$wc_ok    = class_exists( 'WooCommerce' );
?>
<div class="wrap wccp-wrap">
	<div class="wccp-topbar">
		<div>
			<h1>Baget — خرید سریع</h1>
			<p class="wccp-muted">با زدن دکمه خرید، مشتری مستقیم به صفحه پرداخت می‌رود و همان‌جا مشخصات را وارد می‌کند. سبد فعلی خالی نمی‌شود.</p>
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
	settings_errors( 'wccp_quick_buy' );
	?>

	<?php if ( ! $wc_ok ) : ?>
		<div class="notice notice-warning"><p>ووکامرس فعال نیست. این قابلیت روی فروشگاه ووکامرس کار می‌کند.</p></div>
	<?php endif; ?>

	<div class="wccp-howto">
		<div class="wccp-howto-step"><span>۱</span><div><strong>دکمه خرید</strong><p>در محصول یا فروشگاه</p></div></div>
		<div class="wccp-howto-step"><span>۲</span><div><strong>سبد فعلی</strong><p>محصول اضافه می‌شود، سبد خالی نمی‌شود</p></div></div>
		<div class="wccp-howto-step"><span>۳</span><div><strong>صفحه پرداخت</strong><p>مشتری مشخصات را وارد می‌کند</p></div></div>
		<div class="wccp-howto-step"><span>۴</span><div><strong>درگاه</strong><p>ادامه پرداخت ووکامرس</p></div></div>
	</div>

	<div class="wccp-tpl-grid">
		<div class="wccp-tpl-form-card">
			<header>
				<strong>تنظیمات خرید سریع</strong>
				<span class="wccp-tag <?php echo ! empty( $settings['enabled'] ) ? 'type' : 'required'; ?>">
					<?php echo ! empty( $settings['enabled'] ) ? 'فعال' : 'خاموش'; ?>
				</span>
			</header>
			<form class="wccp-tpl-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'wccp_save_quick_buy' ); ?>
				<input type="hidden" name="action" value="wccp_save_quick_buy" />

				<label class="wccp-check-row">
					<input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
					<span>خرید سریع فعال باشد (رد شدن از صفحه سبد)</span>
				</label>

				<label>
					<strong>متن دکمه خرید</strong>
					<input type="text" name="button_text" class="widefat"
						value="<?php echo esc_attr( $settings['button_text'] ); ?>"
						placeholder="خرید" />
					<small class="description">روی محصول ساده اعمال می‌شود. محصول متغیر در لیست همان «انتخاب گزینه‌ها» می‌ماند.</small>
				</label>

				<label class="wccp-check-row">
					<input type="checkbox" name="redirect_cart" value="1" <?php checked( ! empty( $settings['redirect_cart'] ) ); ?> />
					<span>اگر کسی وارد صفحه سبد شد، به پرداخت هدایت شود (وقتی سبد خالی نیست)</span>
				</label>

				<p>
					<button type="submit" class="button button-primary">ذخیره تنظیمات خرید سریع</button>
				</p>
			</form>
		</div>

		<div class="wccp-tpl-list-card">
			<header><strong>راهنما</strong></header>
			<div style="padding:16px;line-height:1.9;color:#334155;font-size:13px">
				<p><strong>چه چیزی عوض می‌شود؟</strong><br>
				به‌جای «افزودن به سبد → صفحه سبد → پرداخت»، مشتری با یک کلیک به صفحه پرداخت می‌رسد.</p>
				<p><strong>سبد چندمحصولی</strong><br>
				سبد خالی نمی‌شود. اگر از قبل چیزی در سبد باشد، محصول جدید هم کنارش می‌ماند.</p>
				<p><strong>فیلدهای مشخصات</strong><br>
				همان قالب فیلدهای Baget در صفحه پرداخت نمایش داده می‌شود.</p>
			</div>
		</div>
	</div>
</div>
