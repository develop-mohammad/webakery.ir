<?php
defined( 'ABSPATH' ) || exit;
/** @var bool $licensed */
/** @var bool $logged */
?><!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>سجست‌یاب گوگل</title>
	<?php wp_head(); ?>
</head>
<body class="wbgs-app">
	<div class="wbgs-app-shell">
		<header class="wbgs-app-head">
			<div>
				<h1>سجست‌یاب گوگل</h1>
				<p>پیشنهادهای واقعی Autocomplete گوگل · webakery.ir</p>
			</div>
			<?php if ( $logged ) : ?>
				<p class="wbgs-app-user">
					<?php echo esc_html( wp_get_current_user()->display_name ); ?>
					<a href="<?php echo esc_url( wp_logout_url( WBGS_Frontend::url() ) ); ?>">خروج</a>
				</p>
			<?php endif; ?>
		</header>

		<?php if ( ! $logged ) : ?>
			<section class="wbgs-login-panel">
				<h2>ورود برای استفاده از ابزار</h2>
				<p class="wbgs-hint">با شماره موبایل یا حساب جیمیل وارد شوید. بعد از ورود، استخراج سجست در همین صفحه باز می‌شود.</p>
				<?php
				if ( WBGS_Frontend::has_easy_login() ) {
					echo WBL_Frontend::shortcode( // phpcs:ignore WordPress.Security.EscapeOutput
						array(
							'redirect'   => WBGS_Frontend::url(),
							'title'      => 'ورود به سجست‌یاب',
							'subtitle'   => 'موبایل یا جیمیل',
						)
					);
				} else {
					echo '<div class="wbgs-card">';
					if ( current_user_can( 'manage_options' ) ) {
						echo '<p class="wbgs-admin-hint">برای ورود با موبایل و جیمیل، افزونه «ورود آسان» را نصب و فعال کنید. تا آن زمان فرم ورود وردپرس نمایش داده می‌شود.</p>';
					}
					wp_login_form(
						array(
							'redirect'       => WBGS_Frontend::url(),
							'label_username' => 'نام کاربری یا ایمیل',
							'label_password' => 'رمز عبور',
							'label_log_in'   => 'ورود',
							'remember'       => true,
						)
					);
					echo '</div>';
				}
				?>
			</section>
		<?php elseif ( ! $licensed ) : ?>
			<div class="wbgs-card">
				<p>لایسنس یا دوره آزمایشی سجست‌یاب فعال نیست. مدیر سایت باید از پیشخوان افزونه را فعال کند.</p>
			</div>
		<?php else : ?>
			<?php include WBGS_PATH . 'templates/extract-form.php'; ?>
		<?php endif; ?>
	</div>
	<?php wp_footer(); ?>
</body>
</html>
