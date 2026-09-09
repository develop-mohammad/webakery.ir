<?php
defined( 'ABSPATH' ) || exit;
/** @var string $tab */
/** @var array $tabs */
/** @var array $settings */
/** @var bool $licensed */
?>
<div class="wrap wbgs-wrap" dir="rtl">
	<h1 class="wbgs-h1">
		سجست‌یاب گوگل
		<span class="wbgs-ver">v<?php echo esc_html( WBGS_VERSION ); ?></span>
	</h1>
	<p class="wbgs-sub">
		پیشنهادهای واقعی Autocomplete گوگل را با فاصله و حرف‌گردانی الفبا جمع می‌کند —
		سازنده: <a href="https://webakery.ir" target="_blank" rel="noopener">webakery.ir</a>
	</p>

	<nav class="nav-tab-wrapper wbgs-tabs">
		<?php foreach ( $tabs as $key => $label ) : ?>
			<a class="nav-tab<?php echo $tab === $key ? ' nav-tab-active' : ''; ?>"
			   href="<?php echo esc_url( admin_url( 'admin.php?page=' . WBGS_MENU . '&tab=' . $key ) ); ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( ! empty( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>تنظیمات ذخیره شد.</p></div>
	<?php endif; ?>

	<?php if ( ! $licensed ) : ?>
		<div class="notice notice-warning"><p>دوره آزمایشی یا لایسنس فعال نیست. استخراج قفل است. از زبانه لایسنس اقدام کنید.</p></div>
	<?php endif; ?>

	<?php if ( 'extract' === $tab ) : ?>
		<?php include WBGS_PATH . 'templates/extract-form.php'; ?>
	<?php elseif ( 'settings' === $tab ) : ?>
		<form class="wbgs-card wbgs-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wbgs_save_settings" />
			<?php wp_nonce_field( 'wbgs_save_settings' ); ?>
			<h2>صفحهٔ جدا روی سایت</h2>
			<p class="wbgs-hint">آدرس را اینجا بگذارید. آن بخش از سایت قالب معمولی را نشان نمی‌دهد؛ اول ورود با موبایل یا جیمیل (افزونه ورود آسان)، بعد استخراج سجست.</p>
			<p>
				<label class="wbgs-check">
					<input type="checkbox" name="front_enabled" value="1" <?php checked( ! empty( $settings['front_enabled'] ) ); ?> />
					فعال بودن صفحهٔ عمومی
				</label>
			</p>
			<p>
				<label class="wbgs-check">
					<input type="checkbox" name="front_public" value="1" <?php checked( ! empty( $settings['front_public'] ) ); ?> />
					ابزار رایگان بدون ورود (شورت‌کد و صفحهٔ جدا)
				</label>
			</p>
			<p class="wbgs-hint">شورت‌کد صفحهٔ گوگل‌مانند: <code>[webakery_suggest]</code></p>
			<p>
				<label class="wbgs-label" for="wbgs-slug">آدرس صفحه (اسلاگ)</label>
				<input id="wbgs-slug" class="wbgs-input wbgs-input-sm" type="text" name="front_slug" value="<?php echo esc_attr( $settings['front_slug'] ); ?>" dir="ltr" />
			</p>
			<?php if ( WBGS_Frontend::url() ) : ?>
				<p class="wbgs-hint">لینک: <a href="<?php echo esc_url( WBGS_Frontend::url() ); ?>" target="_blank" rel="noopener"><code dir="ltr"><?php echo esc_html( WBGS_Frontend::url() ); ?></code></a>
					— بعد از ذخیره، یک‌بار «پیوندهای یکتا» را در تنظیمات وردپرس ذخیره کنید اگر صفحه ۴۰۴ شد.</p>
			<?php endif; ?>
			<?php if ( ! class_exists( 'WBL_Plugin' ) ) : ?>
				<p class="wbgs-admin-hint">افزونه «ورود آسان» نصب نیست. تا نصب نشود، روی صفحهٔ عمومی فرم ورود معمولی وردپرس می‌آید نه موبایل/جیمیل.</p>
			<?php endif; ?>

			<h2>زبان و کشور گوگل</h2>
			<p class="wbgs-hint">این‌ها همان پارامترهای Autocomplete گوگل هستند (مثل سرچ از ایران).</p>

			<p>
				<label class="wbgs-label" for="wbgs-hl">زبان (hl)</label>
				<input id="wbgs-hl" class="wbgs-input wbgs-input-sm" type="text" name="hl" value="<?php echo esc_attr( $settings['hl'] ); ?>" dir="ltr" />
			</p>
			<p>
				<label class="wbgs-label" for="wbgs-gl">کشور (gl)</label>
				<input id="wbgs-gl" class="wbgs-input wbgs-input-sm" type="text" name="gl" value="<?php echo esc_attr( $settings['gl'] ); ?>" dir="ltr" />
			</p>
			<p>
				<label class="wbgs-label" for="wbgs-delay">فاصله بین درخواست‌ها (میلی‌ثانیه)</label>
				<input id="wbgs-delay" class="wbgs-input wbgs-input-sm" type="number" name="delay_ms" min="150" max="2000" step="50" value="<?php echo esc_attr( (string) $settings['delay_ms'] ); ?>" dir="ltr" />
			</p>
			<h2>میزان سرچ دقیق (Google Ads Keyword Planner)</h2>
			<p class="wbgs-hint">عدد ماهانه فقط از خودِ گوگل ادز خوانده می‌شود. اگر وصل نباشد، ستون سرچ خالی می‌ماند — هیچ عددی ساخته نمی‌شود.</p>
			<p>
				<label class="wbgs-label">Developer token</label>
				<input class="wbgs-input" type="password" name="ads_developer_token" value="" autocomplete="off" placeholder="<?php echo $settings['ads_developer_token'] ? '•••• ذخیره شده' : ''; ?>" />
			</p>
			<p>
				<label class="wbgs-label">OAuth Client ID</label>
				<input class="wbgs-input" type="text" name="ads_client_id" value="<?php echo esc_attr( $settings['ads_client_id'] ); ?>" dir="ltr" />
			</p>
			<p>
				<label class="wbgs-label">OAuth Client Secret</label>
				<input class="wbgs-input" type="password" name="ads_client_secret" value="" autocomplete="off" placeholder="<?php echo $settings['ads_client_secret'] ? '•••• ذخیره شده' : ''; ?>" />
			</p>
			<p>
				<label class="wbgs-label">Refresh token</label>
				<input class="wbgs-input" type="password" name="ads_refresh_token" value="" autocomplete="off" placeholder="<?php echo $settings['ads_refresh_token'] ? '•••• ذخیره شده' : ''; ?>" />
			</p>
			<p>
				<label class="wbgs-label">Customer ID (اعداد)</label>
				<input class="wbgs-input wbgs-input-sm" type="text" name="ads_customer_id" value="<?php echo esc_attr( $settings['ads_customer_id'] ); ?>" dir="ltr" />
			</p>
			<p>
				<label class="wbgs-label">Login customer ID (اختیاری، MCC)</label>
				<input class="wbgs-input wbgs-input-sm" type="text" name="ads_login_customer_id" value="<?php echo esc_attr( $settings['ads_login_customer_id'] ); ?>" dir="ltr" />
			</p>
			<?php submit_button( 'ذخیره تنظیمات' ); ?>
		</form>
	<?php else : ?>
		<div class="wbgs-card">
			<?php
			if ( class_exists( 'WB_License' ) ) {
				echo WB_License::render_box( WBGS_PRODUCT ); // phpcs:ignore WordPress.Security.EscapeOutput
			} else {
				echo '<p>کلاینت لایسنس در دسترس نیست.</p>';
			}
			?>
		</div>
	<?php endif; ?>
</div>
