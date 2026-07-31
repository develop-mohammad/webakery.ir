<?php
defined( 'ABSPATH' ) || exit;
/**
 * @var array  $s
 * @var bool   $show_phone
 * @var bool   $show_google
 * @var string $error
 * @var string $redirect
 * @var bool   $show_title
 * @var string $uid
 * @var string $layout
 * @var string $animation
 * @var bool   $show_phone_visual
 */

$layout            = isset( $layout ) ? $layout : WBL_Settings::get( 'template_layout', 'split' );
$animation         = isset( $animation ) ? $animation : WBL_Settings::get( 'animation_style', 'hybrid' );
$show_phone_visual = isset( $show_phone_visual ) ? (bool) $show_phone_visual : (bool) WBL_Settings::get( 'show_phone_visual', 1 );
if ( 'split' === $layout && ! $show_phone_visual ) {
	// هنوز اسپلیت با کپی برند، بدون گوشی.
}

$shell_class = 'wbl-shell wbl-layout-' . sanitize_html_class( $layout ) . ' wbl-anim-' . sanitize_html_class( $animation );
?>
<div class="<?php echo esc_attr( $shell_class ); ?>" data-wbl-shell data-layout="<?php echo esc_attr( $layout ); ?>" data-anim="<?php echo esc_attr( $animation ); ?>">
<?php if ( 'split' === $layout ) : ?>
	<div class="wbl-layout-split">
		<section class="wbl-visual" aria-label="برند ورود آسان">
			<span class="wbl-brand-mark"><span class="dot"></span> ورود آسان</span>
			<div class="wbl-visual-stage">
				<?php if ( $show_phone_visual ) : ?>
				<div class="wbl-phone-scene" aria-hidden="true">
					<span class="wbl-phone-ring wbl-phone-ring-a"></span>
					<span class="wbl-phone-ring wbl-phone-ring-b"></span>
					<span class="wbl-phone-glow"></span>
					<div class="wbl-phone">
						<div class="wbl-phone-screen">
							<div class="wbl-phone-notch"></div>
							<div class="wbl-sms">
								<div class="wbl-sms-from"><strong>ورود آسان</strong><span>الان</span></div>
								<p class="wbl-sms-body">کد ورود شما:</p>
								<span class="wbl-sms-code">۴۸۲۹۱</span>
							</div>
							<div class="wbl-phone-bars"><i></i><i></i><i></i></div>
						</div>
					</div>
				</div>
				<?php endif; ?>
				<div class="wbl-visual-copy">
					<h2><?php echo esc_html( $s['brand_headline'] ); ?></h2>
					<p><?php echo esc_html( $s['brand_text'] ); ?></p>
				</div>
			</div>
			<p class="wbl-visual-foot">webakery.ir · امن با OTP و Google</p>
		</section>
		<section class="wbl-split-form">
			<?php include WBL_PATH . 'templates/login-form.php'; ?>
		</section>
	</div>
<?php elseif ( 'centered' === $layout ) : ?>
	<div class="wbl-layout-centered">
		<?php include WBL_PATH . 'templates/login-form.php'; ?>
	</div>
<?php else : ?>
	<div class="wbl-layout-form">
		<?php include WBL_PATH . 'templates/login-form.php'; ?>
	</div>
<?php endif; ?>
</div>
