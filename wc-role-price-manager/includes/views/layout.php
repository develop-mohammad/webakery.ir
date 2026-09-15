<?php
defined( 'ABSPATH' ) || exit;
/** @var string $tab */
/** @var array $tabs */
?>
<div class="wrap wrpm-wrap" dir="rtl">
	<h1>نقش‌قیمت <small class="wrpm-muted">v<?php echo esc_html( WRPM_VERSION ); ?></small></h1>
	<p class="wrpm-muted">مدیریت نقش‌های کاربری و قیمت‌گذاری بر اساس نقش در ووکامرس · سازنده: <a href="https://webakery.ir" target="_blank" rel="noopener">webakery.ir</a></p>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $key => $label ) : ?>
			<a class="nav-tab<?php echo $tab === $key ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-role-price-manager&tab=' . $key ) ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<?php settings_errors( 'wrpm' ); ?>

	<?php
	$view = WRPM_PATH . 'includes/views/tab-' . $tab . '.php';
	if ( is_readable( $view ) ) {
		include $view;
	}
	?>
</div>
