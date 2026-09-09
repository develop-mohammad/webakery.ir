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
		<div class="wbgs-grid">
			<section class="wbgs-card">
				<h2>عبارت پایه</h2>
				<p class="wbgs-hint">مثل «کفش». ابزار همان عبارت را با فاصله و حروف الفبا از گوگل می‌پرسد؛ چیزی از خودش نمی‌سازد.</p>

				<label class="wbgs-label" for="wbgs-seed">عبارت</label>
				<input id="wbgs-seed" class="wbgs-input" type="text" dir="auto" placeholder="کفش" <?php disabled( ! $licensed ); ?> />

				<fieldset class="wbgs-modes" <?php disabled( ! $licensed ); ?>>
					<legend>روش‌ها</legend>
					<label><input type="checkbox" name="wbgs-mode" value="space" checked /> فاصله قبل و بعد</label>
					<label><input type="checkbox" name="wbgs-mode" value="alphabet" checked /> حرف‌گردانی فارسی (ا تا ی)</label>
					<label><input type="checkbox" name="wbgs-mode" value="latin" /> حروف انگلیسی a–z</label>
					<label><input type="checkbox" name="wbgs-mode" value="digits" /> ارقام ۰–۹</label>
					<label><input type="checkbox" name="wbgs-mode" value="modifiers" /> پیشوندهای رایج (خرید، قیمت، …)</label>
				</fieldset>

				<div class="wbgs-actions">
					<button type="button" class="button button-primary" id="wbgs-start" <?php disabled( ! $licensed ); ?>>استخراج از گوگل</button>
					<button type="button" class="button" id="wbgs-stop" hidden>توقف</button>
				</div>

				<div class="wbgs-progress" id="wbgs-progress" hidden>
					<div class="wbgs-progress-bar" id="wbgs-progress-bar"></div>
					<p class="wbgs-progress-text" id="wbgs-progress-text"></p>
				</div>
				<p class="wbgs-status" id="wbgs-status" role="status"></p>
			</section>

			<section class="wbgs-card">
				<div class="wbgs-results-head">
					<h2>نتایج</h2>
					<span class="wbgs-count" id="wbgs-count">۰ عبارت</span>
				</div>
				<div class="wbgs-actions">
					<button type="button" class="button" id="wbgs-copy" disabled>کپی همه</button>
					<button type="button" class="button" id="wbgs-csv" disabled>دانلود CSV</button>
					<button type="button" class="button" id="wbgs-txt" disabled>دانلود TXT</button>
				</div>
				<ol class="wbgs-list" id="wbgs-list"></ol>
				<p class="wbgs-empty" id="wbgs-empty">هنوز چیزی استخراج نشده.</p>
			</section>
		</div>
	<?php elseif ( 'settings' === $tab ) : ?>
		<form class="wbgs-card wbgs-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wbgs_save_settings" />
			<?php wp_nonce_field( 'wbgs_save_settings' ); ?>
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
