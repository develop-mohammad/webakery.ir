<?php
defined( 'ABSPATH' ) || exit;
/** @var array $form */
$steps = NCK_Forms::display_steps( $form );
$need_sign = ! empty( $form['require_signature'] );
?>
<div class="nck-root" dir="rtl" data-nck="form">
	<form class="nck-form nck-wizard" data-nck-sign-form data-nck-wizard novalidate>
		<input type="hidden" name="form_id" value="<?php echo esc_attr( $form['id'] ); ?>" />
		<input type="hidden" name="form_slug" value="<?php echo esc_attr( $form['slug'] ); ?>" />
		<header class="nck-paper-head nck-wizard-brand">
			<div class="nck-mark" aria-hidden="true">
				<svg viewBox="0 0 48 48" width="42" height="42"><path fill="currentColor" d="M24 4c1.2 6 3 10 8 14-6 1-10 4-12 10-2-6-6-9-12-10 5-4 6.8-8 8-14 2 5 4 8 8 10z"/></svg>
			</div>
			<div>
				<?php if ( $form['kicker'] !== '' ) : ?>
					<p class="nck-kicker"><?php echo esc_html( $form['kicker'] ); ?></p>
				<?php endif; ?>
				<h2 class="nck-title"><?php echo esc_html( $form['title'] ); ?></h2>
				<?php if ( $form['tagline'] !== '' ) : ?>
					<p class="nck-tag"><?php echo esc_html( $form['tagline'] ); ?></p>
				<?php endif; ?>
			</div>
		</header>

		<?php include NCK_PATH . 'templates/wizard-head.php'; ?>

		<div class="nck-alert nck-alert-error" data-nck-error hidden></div>
		<div class="nck-alert nck-alert-ok" data-nck-ok hidden></div>

		<?php foreach ( $steps as $i => $step ) : ?>
			<section class="nck-step" data-nck-step data-nck-step-label="<?php echo esc_attr( $step['label'] ); ?>"<?php echo 0 === $i ? '' : ' hidden'; ?>>
				<?php if ( ! empty( $form['payment']['enabled'] ) && ! empty( $form['payment']['amount'] ) && 'پرداخت' === $step['label'] ) : ?>
					<p class="nck-note">مبلغ این فرم: <?php echo esc_html( NCK_Hall::format_money( (int) $form['payment']['amount'] ) ); ?></p>
				<?php endif; ?>
				<div class="nck-grid nck-grid-hall">
					<?php
					foreach ( isset( $step['fields'] ) ? $step['fields'] : array() as $field ) {
						$wide = in_array( $field['type'], array( 'textarea', 'note', 'checkbox', 'radio', 'payment_method' ), true );
						if ( $wide ) {
							echo '<div class="nck-field-wide">';
						}
						NCK_Forms::render_field( $field, $form );
						if ( $wide ) {
							echo '</div>';
						}
					}
					?>
				</div>
			</section>
		<?php endforeach; ?>

		<section class="nck-step" data-nck-step data-nck-step-label="تأیید و ثبت" hidden>
			<label class="nck-agree">
				<input type="checkbox" name="agree" value="1" required />
				اطلاعات واردشده صحیح است و ثبت این فرم را می‌پذیرم.
			</label>
			<?php if ( $need_sign ) : ?>
				<?php
				$nck_sign_title = 'امضا';
				$nck_sign_party = 'امضا';
				include NCK_PATH . 'templates/sign-pad.php';
				?>
			<?php endif; ?>
			<?php if ( $form['slogan'] !== '' ) : ?>
				<p class="nck-slogan"><?php echo esc_html( $form['slogan'] ); ?></p>
			<?php endif; ?>
		</section>

		<nav class="nck-wizard-nav">
			<button type="button" class="nck-btn-ghost" data-nck-prev>مرحله قبل</button>
			<button type="button" class="nck-btn" data-nck-next>مرحله بعد</button>
			<button type="submit" class="nck-btn" data-nck-submit hidden>ثبت فرم</button>
		</nav>
	</form>

	<div class="nck-done" data-nck-done hidden>
		<p class="nck-done-title">فرم ثبت شد.</p>
		<p data-nck-done-msg></p>
		<p>
			<a class="nck-btn" data-nck-print-link href="#" target="_blank" rel="noopener">مشاهده و چاپ فرم</a>
		</p>
	</div>
</div>
