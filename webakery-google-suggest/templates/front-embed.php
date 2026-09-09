<?php
defined( 'ABSPATH' ) || exit;
/** @var bool $licensed */
/** @var bool $logged */
?>
<div class="wbgs-embed" dir="rtl">
	<?php if ( ! $logged ) : ?>
		<div class="wbgs-card">
			<h2>ورود برای استفاده از سجست‌یاب</h2>
			<p class="wbgs-hint">با شماره موبایل یا جیمیل وارد شوید.</p>
			<?php
			if ( WBGS_Frontend::has_easy_login() ) {
				echo WBL_Frontend::shortcode( // phpcs:ignore WordPress.Security.EscapeOutput
					array(
						'redirect' => get_permalink() ? get_permalink() : WBGS_Frontend::url(),
						'title'    => 'ورود به سجست‌یاب',
						'subtitle' => 'موبایل یا جیمیل',
					)
				);
			} else {
				wp_login_form(
					array(
						'redirect' => get_permalink() ? get_permalink() : home_url( '/' ),
					)
				);
			}
			?>
		</div>
	<?php elseif ( ! $licensed ) : ?>
		<div class="wbgs-card"><p>لایسنس سجست‌یاب فعال نیست.</p></div>
	<?php else : ?>
		<?php include WBGS_PATH . 'templates/extract-form.php'; ?>
	<?php endif; ?>
</div>
