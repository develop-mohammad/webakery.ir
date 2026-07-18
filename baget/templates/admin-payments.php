<?php
defined( 'ABSPATH' ) || exit;
/** @var string $tab */

$settings = \WCCP\Payments::settings();
$enabled  = \WCCP\Payments::zarinpal_enabled();
?>
<div class="wrap wccp-wrap">
	<div class="wccp-topbar">
		<div>
			<h1>Baget — تنظیمات پرداخت لینک مستقیم</h1>
			<p class="wccp-muted">برای اینکه دکمه «ادامه و پرداخت» در لینک‌های /pay/… کار کند، مرچنت‌کد زرین‌پال لازم است.</p>
		</div>
		<div class="wccp-topbar-actions">
			<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=wccp_product' ) ); ?>">لینک‌های پرداخت</a>
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
	settings_errors( 'wccp_payments' );
	?>

	<div class="wccp-howto">
		<div class="wccp-howto-step"><span>۱</span><div><strong>مرچنت زرین‌پال</strong><p>کد ۳۶ کاراکتری پنل زرین‌پال</p></div></div>
		<div class="wccp-howto-step"><span>۲</span><div><strong>قیمت لینک</strong><p>در ویرایش لینک پرداخت مبلغ بگذارید</p></div></div>
		<div class="wccp-howto-step"><span>۳</span><div><strong>تست پرداخت</strong><p>روی /pay/… دکمه ادامه و پرداخت</p></div></div>
		<div class="wccp-howto-step"><span>۴</span><div><strong>جایگزین</strong><p>اگر مرچنت نباشد، ووکامرس استفاده می‌شود</p></div></div>
	</div>

	<div class="wccp-tpl-grid">
		<div class="wccp-tpl-form-card">
			<header>
				<strong>درگاه زرین‌پال</strong>
				<span class="wccp-tag <?php echo $enabled ? 'type' : 'required'; ?>">
					<?php echo $enabled ? 'فعال' : 'مرچنت لازم است'; ?>
				</span>
			</header>
			<form class="wccp-tpl-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'wccp_save_payments' ); ?>
				<input type="hidden" name="action" value="wccp_save_payments" />

				<label>
					<strong>مرچنت‌کد زرین‌پال</strong>
					<input type="text" name="zarinpal_merchant" class="widefat" dir="ltr"
						value="<?php echo esc_attr( $settings['zarinpal_merchant'] ); ?>"
						placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" />
					<small class="description">از پنل زرین‌پال → درگاه‌ها کپی کنید (۳۶ کاراکتر UUID).</small>
				</label>

				<label class="wccp-check-row">
					<input type="checkbox" name="sandbox" value="1" <?php checked( ! empty( $settings['sandbox'] ) ); ?> />
					<span>حالت آزمایشی (Sandbox) زرین‌پال</span>
				</label>

				<p>
					<button type="submit" class="button button-primary">ذخیره تنظیمات پرداخت</button>
				</p>
			</form>
		</div>

		<div class="wccp-tpl-list-card">
			<header><strong>راهنما</strong></header>
			<div style="padding:16px;line-height:1.9;color:#334155;font-size:13px">
				<p><strong>چرا صفحه خالی admin-post می‌آمد؟</strong><br>
				فرم لینک پرداخت هندلر نداشت — در نسخه جدید رفع شده است.</p>
				<p><strong>آیا مرچنت لازم است؟</strong><br>
				بله، برای پرداخت مستقیم زرین‌پال. اگر مرچنت نباشد و ووکامرس + درگاه فعال داشته باشید، به صفحه پرداخت ووکامرس می‌رود.</p>
				<p><strong>مبلغ:</strong> قیمت لینک به <b>تومان</b> است و هنگام ارسال به زرین‌پال به ریال تبدیل می‌شود.</p>
			</div>
		</div>
	</div>
</div>
