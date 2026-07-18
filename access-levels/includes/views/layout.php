<?php
defined( 'ABSPATH' ) || exit;
/** @var string $tab */
/** @var array $tabs */
?>
<div class="wrap al-wrap" dir="rtl">
	<h1>Barbari <small class="al-muted">v<?php echo esc_html( AL_VERSION ); ?></small></h1>
	<p class="al-muted">کنترل دسترسی کاربران به افزونه‌ها و بخش‌های پیشخوان · سازنده: <a href="https://webakery.ir" target="_blank" rel="noopener">webakery.ir</a></p>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $key => $label ) : ?>
			<a class="nav-tab<?php echo $tab === $key ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=access-levels&tab=' . $key ) ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<?php settings_errors( 'al' ); ?>

	<?php
	$view = AL_PATH . 'includes/views/tab-' . $tab . '.php';
	if ( is_readable( $view ) ) {
		include $view;
	}
	?>
</div>
