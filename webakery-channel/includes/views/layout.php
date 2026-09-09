<?php
defined( 'ABSPATH' ) || exit;
/** @var string $tab */
/** @var array $tabs */
$s = WBCN_Settings::get();
?>
<div class="wrap wbcn-wrap" dir="rtl">
	<h1 class="wbcn-h1">
		کانال‌یار
		<span class="wbcn-ver">v<?php echo esc_html( WBCN_VERSION ); ?></span>
	</h1>
	<p class="wbcn-sub">
		از مطالب سایت، ایده رشد و پست آماده برای کانال تلگرام بسازید ·
		سازنده: <a href="https://webakery.ir" target="_blank" rel="noopener">webakery.ir</a>
		· شورت‌کد: <code>[webakery_channel]</code>
	</p>

	<nav class="nav-tab-wrapper wbcn-tabs">
		<?php foreach ( $tabs as $key => $label ) : ?>
			<a class="nav-tab<?php echo $tab === $key ? ' nav-tab-active' : ''; ?>"
			   href="<?php echo esc_url( admin_url( 'admin.php?page=' . WBCN_MENU . '&tab=' . $key ) ); ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php WBCN_Admin::notice(); ?>

	<?php if ( ! WBCN_Plugin::licensed() ) : ?>
		<div class="notice notice-warning inline"><p>دوره آزمایشی یا لایسنس فعال نیست. از تب لایسنس فعال کنید — پیش‌نمایش ایده همچنان کار می‌کند.</p></div>
	<?php endif; ?>

	<?php
	$view = WBCN_PATH . 'includes/views/tab-' . $tab . '.php';
	if ( is_readable( $view ) ) {
		include $view;
	}
	?>
</div>
