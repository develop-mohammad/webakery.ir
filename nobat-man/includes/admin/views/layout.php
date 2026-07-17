<?php
defined( 'ABSPATH' ) || exit;
$pro = NM_Pro::is_active();
$s = NM_Settings::all();
?>
<div class="wrap nm-admin" dir="rtl">
	<div class="nm-admin-shell">
		<aside class="nm-admin-nav">
			<div class="nm-brand">
				<div class="nm-brand-mark">ن</div>
				<div>
					<strong>نوبت من</strong>
					<span>سازنده: webakery.ir · v<?php echo esc_html( NM_VERSION ); ?></span>
				</div>
			</div>
			<nav>
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a class="nm-nav-item<?php echo $tab === $key ? ' is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=nobat-man&tab=' . $key ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<div class="nm-nav-foot">
				<?php if ( $pro ) : ?>
					<span class="nm-pill ok">پرو فعال</span>
				<?php else : ?>
					<span class="nm-pill warn">آزمایشی / رایگان</span>
				<?php endif; ?>
				<span class="nm-muted" style="color:#94a3b8;font-size:12px">نسخه <?php echo esc_html( NM_VERSION ); ?></span>
				<a href="https://webakery.ir" target="_blank" rel="noopener">سازنده: webakery.ir</a>
			</div>
		</aside>

		<main class="nm-admin-main">
			<header class="nm-admin-top">
				<div>
					<h1><?php echo esc_html( $tabs[ $tab ] ?? 'نوبت من' ); ?></h1>
					<p>شورت‌کد: <code>[nobat_man]</code> · قیمت پرو: ۵۹۹,۰۰۰ تومان · سازنده: <a href="https://webakery.ir" target="_blank" rel="noopener">webakery.ir</a> · v<?php echo esc_html( NM_VERSION ); ?></p>
				</div>
				<a class="nm-btn-soft" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank">مشاهده سایت</a>
			</header>

			<?php settings_errors( 'nm' ); ?>

			<?php
			$view = NM_PATH . 'includes/admin/views/tab-' . $tab . '.php';
			if ( is_readable( $view ) ) {
				include $view;
			} else {
				echo '<div class="nm-panel-card">این بخش به‌زودی تکمیل می‌شود.</div>';
			}
			?>
		</main>
	</div>
</div>
