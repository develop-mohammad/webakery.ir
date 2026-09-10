<?php
defined( 'ABSPATH' ) || exit;
/** @var string $tab */
/** @var array $tabs */
?>
<div class="wrap nck-wrap" dir="rtl">
	<h1 class="nck-h1">
		نهال — قراردادها
		<span class="nck-ver">v<?php echo esc_html( NCK_VERSION ); ?></span>
	</h1>
	<p class="nck-sub">
		فضای کار اشتراکی و اجاره سالن ·
		<code>[nahal_contract]</code>
		<code>[nahal_hall]</code>
		<code>[nahal_portal]</code>
		· سازنده: <a href="https://webakery.ir" target="_blank" rel="noopener">webakery.ir</a>
	</p>

	<nav class="nav-tab-wrapper nck-tabs">
		<?php foreach ( $tabs as $key => $label ) : ?>
			<a class="nav-tab<?php echo $tab === $key ? ' nav-tab-active' : ''; ?>"
			   href="<?php echo esc_url( admin_url( 'admin.php?page=' . NCK_MENU . '&tab=' . $key ) ); ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php NCK_Admin::notice(); ?>

	<?php
	$view = NCK_PATH . 'templates/admin-' . $tab . '.php';
	if ( is_readable( $view ) ) {
		include $view;
	}
	?>
</div>
