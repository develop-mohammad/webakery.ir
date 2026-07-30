<?php
defined( 'ABSPATH' ) || exit;
/** @var array $s */
/** @var bool $show_phone */
/** @var bool $show_google */
/** @var string $error */
/** @var string $redirect */
?>
<div class="wbl-box" data-wbl-root <?php echo $redirect ? 'data-redirect="' . esc_attr( $redirect ) . '"' : ''; ?>>
	<div class="wbl-head">
		<h2 class="wbl-title"><?php echo esc_html( $s['form_title'] ); ?></h2>
		<?php if ( ! empty( $s['form_subtitle'] ) ) : ?>
			<p class="wbl-sub"><?php echo esc_html( $s['form_subtitle'] ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( $error ) : ?>
		<div class="wbl-alert wbl-alert-error" role="alert"><?php echo esc_html( $error ); ?></div>
	<?php endif; ?>

	<div class="wbl-alert wbl-alert-error" data-wbl-error hidden></div>
	<div class="wbl-alert wbl-alert-ok" data-wbl-ok hidden></div>

	<?php if ( $show_google ) : ?>
		<a class="wbl-btn wbl-btn-google" href="<?php echo esc_url( ! empty( $google_url ) ? $google_url : WBL_Google::auth_url( $redirect ) ); ?>">
			<svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3 0 5.8 1.1 7.9 3l5.7-5.7C34.2 6.1 29.4 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.2-.1-2.3-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 16 19 12 24 12c3 0 5.8 1.1 7.9 3l5.7-5.7C34.2 6.1 29.4 4 24 4 16.3 4 9.6 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.3 35.9 26.8 37 24 37c-5.3 0-9.7-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-1.3 3.7-4.6 6.4-8.6 7.1l.1.1 6.2 5.2C36.1 39.9 44 34 44 24c0-1.2-.1-2.3-.4-3.5z"/></svg>
			ورود با جیمیل / گوگل
		</a>
		<?php if ( $show_phone ) : ?>
			<div class="wbl-divider"><span>یا</span></div>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $show_phone ) : ?>
		<form class="wbl-form" data-wbl-step="phone" autocomplete="off">
			<label class="wbl-label" for="wbl-phone">شماره موبایل</label>
			<input
				id="wbl-phone"
				class="wbl-input"
				type="tel"
				name="phone"
				inputmode="numeric"
				dir="ltr"
				placeholder="<?php echo esc_attr( $s['phone_placeholder'] ); ?>"
				required
			/>
			<button type="submit" class="wbl-btn wbl-btn-primary" data-wbl-send>
				ارسال کد تأیید
			</button>
		</form>

		<form class="wbl-form" data-wbl-step="code" hidden autocomplete="one-time-code">
			<p class="wbl-hint" data-wbl-hint></p>
			<label class="wbl-label" for="wbl-code">کد تأیید</label>
			<input
				id="wbl-code"
				class="wbl-input wbl-input-code"
				type="text"
				name="code"
				inputmode="numeric"
				dir="ltr"
				maxlength="8"
				placeholder="•••••"
				required
			/>
			<button type="submit" class="wbl-btn wbl-btn-primary" data-wbl-verify>
				ورود
			</button>
			<button type="button" class="wbl-link" data-wbl-resend disabled>ارسال مجدد کد</button>
			<button type="button" class="wbl-link wbl-link-muted" data-wbl-back>تغییر شماره</button>
		</form>
	<?php endif; ?>

	<?php if ( ! $show_phone && ! $show_google ) : ?>
		<p class="wbl-hint">هیچ روش ورودی فعال نیست. از تنظیمات افزونه فعال کنید.</p>
	<?php endif; ?>
</div>
