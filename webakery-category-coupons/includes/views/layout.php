<?php
defined( 'ABSPATH' ) || exit;
/** @var string $tab */
/** @var array $tabs */
?>
<div class="wrap wbcc-wrap" dir="rtl">
	<h1 class="wbcc-h1">
		🎟️ کد تخفیف دسته‌بندی
		<span class="wbcc-ver">v<?php echo esc_html( WBCC_VERSION ); ?></span>
	</h1>
	<p class="wbcc-sub">
		برای هر دسته‌بندی محصولات، کد تخفیف با درصد دلخواه (مثلاً ۴۰ تا ۵۰ درصد) بساز — دستی یا خودکار ·
		سازنده: <a href="https://webakery.ir" target="_blank" rel="noopener">webakery.ir</a>
	</p>

	<nav class="nav-tab-wrapper wbcc-tabs">
		<?php foreach ( $tabs as $key => $label ) : ?>
			<a class="nav-tab<?php echo $tab === $key ? ' nav-tab-active' : ''; ?>"
			   href="<?php echo esc_url( admin_url( 'admin.php?page=' . WBCC_MENU . '&tab=' . $key ) ); ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php WBCC_Admin::notice(); ?>

	<?php if ( ! WBCC_Plugin::woo_available() ) : ?>
		<div class="notice notice-error inline"><p>ووکامرس فعال نیست؛ ساخت کد تخفیف ممکن نیست.</p></div>
	<?php endif; ?>

	<?php
	$view = WBCC_PATH . 'includes/views/tab-' . $tab . '.php';
	if ( is_readable( $view ) ) {
		include $view;
	}
	?>
</div>
