<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="nm-panel-card">
	<h3>خروجی لیست خرید / رزروها</h3>
	<?php if ( ! NM_Pro::is_active() ) : ?>
		<p>خروجی CSV در نسخه پرو فعال است.</p>
	<?php else : ?>
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin-post.php?action=nm_export_csv' ) ); ?>">دانلود CSV</a>
	<?php endif; ?>
	<p class="nm-muted">برای گزارش مالی کامل می‌توانید از ووکامرس + افزونه حسابدار webakery استفاده کنید.</p>
</div>
