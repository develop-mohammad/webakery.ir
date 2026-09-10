<?php
defined( 'ABSPATH' ) || exit;
/** @var bool $licensed */
/** @var bool $logged */
/** @var bool $public */
/** @var string $extra_class */
$public      = isset( $public ) ? $public : ! empty( WBGS_Plugin::settings()['front_public'] );
$extra_class = isset( $extra_class ) ? $extra_class : '';
$wrap        = 'wbgs-embed';
if ( $extra_class !== '' ) {
	$wrap .= ' ' . $extra_class;
}
?>
<div class="<?php echo esc_attr( $wrap ); ?>" dir="rtl">
	<?php if ( ! $licensed ) : ?>
		<div class="wbgs-card wbgs-locked">
			<h2>استخراج قفل است</h2>
			<p>برای استخراج، لایسنس را فعال کنید یا دوره آزمایشی را استفاده کنید.</p>
		</div>
	<?php elseif ( ! $logged && ! $public ) : ?>
		<div class="wbgs-card">
			<h2>ورود برای استفاده از سجست‌یاب</h2>
			<p class="wbgs-hint">مالک سایت ورود را الزامی کرده است. با شماره موبایل یا جیمیل وارد شوید.</p>
			<?php
			if ( WBGS_Frontend::has_easy_login() ) {
				echo WBL_Frontend::shortcode( // phpcs:ignore WordPress.Security.EscapeOutput
					array(
						'redirect' => get_permalink() ? get_permalink() : WBGS_Frontend::url(),
						'title'    => 'ورود به سجست‌یاب',
						'subtitle' => 'موبایل یا جیمیل',
					)
				);
			} else {
				wp_login_form(
					array(
						'redirect' => get_permalink() ? get_permalink() : home_url( '/' ),
					)
				);
			}
			?>
		</div>
	<?php else : ?>
		<?php include WBGS_PATH . 'templates/google-home.php'; ?>
	<?php endif; ?>
</div>
