<?php
defined( 'ABSPATH' ) || exit;
/** @var string $tab */
/** @var array $tabs */
/** @var bool $saved */
/** @var bool $deleted */
/** @var string $err */
/** @var array $projects */
/** @var int $pid */
/** @var array|null $project */
/** @var array $domains */
/** @var array $keywords */
/** @var array $s */
/** @var bool $usable */
/** @var string $device */
?>
<div class="wrap did-wrap" dir="rtl">
	<h1 class="did-h1">
		دیدبانی
		<span class="did-ver">v<?php echo esc_html( DID_VERSION ); ?></span>
	</h1>
	<p class="did-sub">رتبهٔ کلیدواژه در گوگل و بینگ · بک‌لینک رقبا · webakery.ir</p>

	<?php if ( $saved ) : ?>
		<div class="notice notice-success is-dismissible"><p>ذخیره شد.</p></div>
	<?php endif; ?>
	<?php if ( $deleted ) : ?>
		<div class="notice notice-success is-dismissible"><p>پروژه حذف شد.</p></div>
	<?php endif; ?>
	<?php if ( 'license' === $err ) : ?>
		<div class="notice notice-error"><p>برای ذخیرهٔ پروژه، لایسنس یا دورهٔ آزمایشی باید فعال باشد.</p></div>
	<?php endif; ?>
	<?php if ( ! $usable ) : ?>
		<div class="notice notice-warning"><p>دوره آزمایشی یا لایسنس فعال نیست. رتبه‌یابی قفل است. از تب لایسنس کلید را وارد کنید.</p></div>
	<?php endif; ?>

	<nav class="nav-tab-wrapper did-tabs">
		<?php foreach ( $tabs as $key => $label ) : ?>
			<?php
			$href = admin_url( 'admin.php?page=didbani&tab=' . $key );
			if ( $pid ) {
				$href = add_query_arg( 'project', $pid, $href );
			}
			?>
			<a class="nav-tab<?php echo $tab === $key ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( $href ); ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( $projects && 'license' !== $tab ) : ?>
		<form class="did-switcher" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="didbani" />
			<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>" />
			<label for="did-project-select">پروژه</label>
			<select id="did-project-select" name="project" onchange="this.form.submit()">
				<?php foreach ( $projects as $p ) : ?>
					<option value="<?php echo (int) $p['id']; ?>" <?php selected( $pid, (int) $p['id'] ); ?>>
						<?php echo esc_html( $p['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</form>
	<?php endif; ?>

	<?php
	$view = DID_PATH . 'templates/tab-' . $tab . '.php';
	if ( is_readable( $view ) ) {
		include $view;
	}
	?>
</div>
