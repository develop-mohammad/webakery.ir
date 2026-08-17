<?php
defined( 'ABSPATH' ) || exit;
/** @var string $tab */
/** @var array $tabs */
?>
<div class="wrap wdp-wrap" dir="rtl">
	<h1 class="wdp-h1">
		🏷️ صفحه‌های تخفیف هوشمند
		<span class="wdp-ver">v<?php echo esc_html( WDP_VERSION ); ?></span>
	</h1>
	<p class="wdp-sub">
		برای هر بازه تخفیف (مثلاً ۲۰ تا ۳۰ درصد) یک صفحه با URL اختصاصی بساز؛ محصولاتی که الان همان‌قدر تخفیف دارند خودکار در همان صفحه نمایش داده می‌شوند و با تغییر تخفیف محصول، خودکار به صفحه درست منتقل می‌شوند ·
		سازنده: <a href="https://webakery.ir" target="_blank" rel="noopener">webakery.ir</a>
	</p>

	<nav class="nav-tab-wrapper wdp-tabs">
		<?php foreach ( $tabs as $key => $label ) : ?>
			<a class="nav-tab<?php echo $tab === $key ? ' nav-tab-active' : ''; ?>"
			   href="<?php echo esc_url( admin_url( 'admin.php?page=' . WDP_MENU . '&tab=' . $key ) ); ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php WDP_Admin::notice(); ?>

	<?php if ( ! WDP_Plugin::woo_available() ) : ?>
		<div class="notice notice-error inline"><p>ووکامرس فعال نیست؛ صفحه‌های تخفیف کار نمی‌کنند.</p></div>
	<?php endif; ?>

	<?php
	$view = WDP_PATH . 'includes/views/tab-' . $tab . '.php';
	if ( is_readable( $view ) ) {
		include $view;
	}
	?>
</div>
