<?php
defined( 'ABSPATH' ) || exit;
/** @var string $tab */

$settings = \WCCP\Payments::settings();
$enabled  = \WCCP\Payments::zarinpal_enabled();
$co       = class_exists( '\\WCCP\\CheckoutPage' ) ? \WCCP\CheckoutPage::status() : array();
$pages    = class_exists( '\\WCCP\\CheckoutPage' ) ? \WCCP\CheckoutPage::pages_for_select() : array();
$convert  = ! empty( $co['page_id'] )
	? wp_nonce_url(
		admin_url( 'admin-post.php?action=wccp_convert_classic_checkout&page_id=' . (int) $co['page_id'] ),
		'wccp_convert_classic_checkout'
	)
	: '';
?>
<div class="wrap wccp-wrap">
	<div class="wccp-topbar">
		<div>
			<h1>Baget — تنظیمات پرداخت</h1>
			<p class="wccp-muted">مرچنت زرین‌پال برای لینک‌های /pay/… و تنظیم صفحه پرداخت ووکامرس (بدون شناسه ثابت).</p>
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
		<div class="wccp-howto-step"><span>۱</span><div><strong>صفحه پرداخت</strong><p>صفحه واقعی را انتخاب کنید — شناسه ثابت لازم نیست</p></div></div>
		<div class="wccp-howto-step"><span>۲</span><div><strong>چک‌اوت کلاسیک</strong><p>بلاک گوتنبرگ با فیلدهای Baget سازگار نیست</p></div></div>
		<div class="wccp-howto-step"><span>۳</span><div><strong>مرچنت زرین‌پال</strong><p>برای لینک‌های مستقیم /pay/…</p></div></div>
		<div class="wccp-howto-step"><span>۴</span><div><strong>تست</strong><p>صفحه پرداخت فروشگاه + لینک /pay/…</p></div></div>
	</div>

	<?php if ( ! empty( $co ) ) : ?>
	<div class="wccp-tpl-form-card" style="margin-bottom:18px">
		<header>
			<strong>صفحه پرداخت ووکامرس</strong>
			<?php if ( ! empty( $co['has_block'] ) ) : ?>
				<span class="wccp-tag required">گوتنبرگ / بلاک</span>
			<?php elseif ( ! empty( $co['has_shortcode'] ) ) : ?>
				<span class="wccp-tag type">کلاسیک ✓</span>
			<?php else : ?>
				<span class="wccp-tag">نامشخص</span>
			<?php endif; ?>
		</header>
		<div style="padding:14px 16px;line-height:1.8;font-size:13px;color:#334155">
			<p style="margin:0 0 10px">
				صفحه فعلی:
				<strong><?php echo $co['page_id'] ? esc_html( $co['title'] . ' (شناسه ' . (int) $co['page_id'] . ')' ) : 'پیدا نشد'; ?></strong>
				<?php if ( ! empty( $co['permalink'] ) ) : ?>
					· <a href="<?php echo esc_url( $co['permalink'] ); ?>" target="_blank" rel="noopener">مشاهده</a>
				<?php endif; ?>
			</p>
			<?php if ( ! empty( $co['wc_mismatch'] ) || ! empty( $co['wc_missing'] ) ) : ?>
				<p style="margin:0 0 10px;color:#b45309">
					شناسه ثبت‌شده در ووکامرس:
					<strong><?php echo (int) $co['wc_page_id']; ?></strong>
					— با صفحه واقعی یکی نیست (قبلاً ممکن است ۷ بوده باشد). از فرم زیر صفحه درست را بزنید و «همگام با ووکامرس» را فعال کنید.
				</p>
			<?php endif; ?>
			<?php if ( ! empty( $co['has_block'] ) && $convert ) : ?>
				<p style="margin:0 0 12px;color:#b91c1c">
					این صفحه با بلاک Checkout گوتنبرگ ساخته شده؛ فیلدهای سفارشی Baget روی بلاک اعمال نمی‌شوند.
					<a class="button button-primary" href="<?php echo esc_url( $convert ); ?>">تبدیل همین صفحه به چک‌اوت کلاسیک</a>
				</p>
			<?php endif; ?>
		</div>
		<form class="wccp-tpl-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="border-top:1px solid #e2e8f0;padding-top:14px">
			<?php wp_nonce_field( 'wccp_fix_checkout_page' ); ?>
			<input type="hidden" name="action" value="wccp_fix_checkout_page" />

			<label>
				<strong>انتخاب صفحه پرداخت</strong>
				<select name="checkout_page_id" class="widefat">
					<option value="0">خودکار (ووکامرس / جستجوی محتوا)</option>
					<?php foreach ( $pages as $pid => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $pid ); ?>" <?php selected( (int) $co['override_id'], (int) $pid ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<small class="description">اگر صفحه را دوباره با گوتنبرگ ساختید و شناسه عوض شد، همان صفحه جدید را اینجا انتخاب کنید.</small>
			</label>

			<label class="wccp-check-row">
				<input type="checkbox" name="force_classic" value="1" <?php checked( ! empty( $co['force_classic'] ) ); ?> />
				<span>اجبار چک‌اوت کلاسیک (جایگزینی بلاک گوتنبرگ با <code>[woocommerce_checkout]</code>)</span>
			</label>

			<label class="wccp-check-row">
				<input type="checkbox" name="sync_wc_page" value="1" checked />
				<span>همگام‌سازی شناسه با تنظیمات ووکامرس (WooCommerce → پیشرفته → صفحه پرداخت)</span>
			</label>

			<p>
				<button type="submit" class="button button-primary">ذخیره صفحه پرداخت</button>
			</p>
		</form>
	</div>
	<?php endif; ?>

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
				<p><strong>چرا بعد از گوتنبرگ کار نکرد؟</strong><br>
				Baget فیلدها را با API کلاسیک ووکامرس تغییر می‌دهد. بلاک Checkout گوتنبرگ آن فیلترها را نمی‌خواند. با «تبدیل به کلاسیک» یا تیک اجبار کلاسیک درست می‌شود.</p>
				<p><strong>شناسه صفحه عوض شده؟</strong><br>
				دیگر به شناسه ثابت (مثل ۷) وابسته نیستید — صفحه را از لیست بالا انتخاب و با ووکامرس همگام کنید.</p>
				<p><strong>مرچنت زرین‌پال:</strong> برای لینک‌های مستقیم /pay/…. مبلغ به تومان است و به ریال تبدیل می‌شود.</p>
			</div>
		</div>
	</div>
</div>
