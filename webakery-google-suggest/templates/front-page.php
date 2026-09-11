<?php
defined( 'ABSPATH' ) || exit;
/** @var bool $licensed */
/** @var bool $logged */
$public = ! empty( WBGS_Plugin::settings()['front_public'] );
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>سجست‌یاب گوگل</title>
	<?php wp_head(); ?>
</head>
<body class="wbgs-app wbgs-app-google">
	<div class="wbgs-app-shell">
		<?php if ( $logged ) : ?>
			<p class="wbgs-app-user wbgs-g-user">
				<?php echo esc_html( wp_get_current_user()->display_name ); ?>
				<a href="<?php echo esc_url( wp_logout_url( WBGS_Frontend::url() ) ); ?>">خروج</a>
			</p>
		<?php endif; ?>

		<?php if ( ! $logged && ! $public ) : ?>
			<section class="wbgs-login-panel">
				<h2>ورود برای استفاده از ابزار</h2>
				<?php
				if ( WBGS_Frontend::has_easy_login() ) {
					echo WBL_Frontend::shortcode( // phpcs:ignore WordPress.Security.EscapeOutput
						array(
							'redirect' => WBGS_Frontend::url(),
							'title'    => 'ورود به سجست‌یاب',
							'subtitle' => 'موبایل یا جیمیل',
						)
					);
				} else {
					wp_login_form( array( 'redirect' => WBGS_Frontend::url() ) );
				}
				?>
			</section>
		<?php elseif ( ! $licensed ) : ?>
			<div class="wbgs-card"><p>لایسنس سجست‌یاب فعال نیست.</p></div>
		<?php else : ?>
			<?php include WBGS_PATH . 'templates/google-home.php'; ?>
		<?php endif; ?>
	</div>
	<?php wp_footer(); ?>
</body>
</html>
