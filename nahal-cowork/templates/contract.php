<?php
defined( 'ABSPATH' ) || exit;

$ctx      = NCK_Frontend::preview_context();
$s        = $ctx['s'];
$intro    = $ctx['intro'];
$preamble = $ctx['preamble'];
$sections = $ctx['sections'];
$notice   = $ctx['notice'];
$hours    = NCK_Settings::hours();
?>
<div class="nck-root" dir="rtl" data-nck="contract">
	<?php if ( $notice ) : ?>
		<div class="nck-banner"><?php echo esc_html( $notice ); ?></div>
	<?php endif; ?>

	<form class="nck-form nck-wizard" data-nck-sign data-nck-wizard novalidate>
		<header class="nck-paper-head nck-wizard-brand">
			<div class="nck-mark" aria-hidden="true">
				<svg viewBox="0 0 48 48" width="42" height="42"><path fill="currentColor" d="M24 4c1.2 6 3 10 8 14-6 1-10 4-12 10-2-6-6-9-12-10 5-4 6.8-8 8-14 2 5 4 8 8 10z"/><path fill="currentColor" opacity=".55" d="M24 28c2 6 5 10 12 14-8-1-14 1-16 8-2-7-8-9-16-8 7-4 10-8 12-14 2 4 4 6 8 8z"/></svg>
			</div>
			<div>
				<p class="nck-kicker"><?php echo esc_html( $s['org_name'] ); ?></p>
				<h2 class="nck-title">قرارداد فضای کار اشتراکی</h2>
				<p class="nck-tag"><?php echo esc_html( $s['org_tagline'] ); ?></p>
			</div>
		</header>

		<?php include NCK_PATH . 'templates/wizard-head.php'; ?>

		<div class="nck-alert nck-alert-error" data-nck-error hidden></div>
		<div class="nck-alert nck-alert-ok" data-nck-ok hidden></div>

		<section class="nck-step" data-nck-step data-nck-step-label="مشخصات و اشتراک">
			<div class="nck-grid">
				<div class="nck-field">
					<label for="nck-honorific">عنوان</label>
					<select id="nck-honorific" name="honorific">
						<option value="mr">آقای</option>
						<option value="ms">خانم</option>
					</select>
				</div>
				<div class="nck-field nck-field-wide">
					<label for="nck-name">نام و نام خانوادگی</label>
					<input id="nck-name" name="name" type="text" autocomplete="name" required placeholder="مثال: سارا محمدی" />
				</div>
				<div class="nck-field">
					<label for="nck-phone">شماره تماس</label>
					<input id="nck-phone" name="phone" type="tel" dir="ltr" inputmode="numeric" autocomplete="tel" required placeholder="09123456789" />
				</div>
				<?php include NCK_PATH . 'templates/cowork-plans.php'; ?>
			</div>
		</section>

		<section class="nck-step" data-nck-step data-nck-step-label="امضا" hidden>
			<?php
			$nck_sign_title = 'امضا';
			$nck_sign_party = 'امضای عضو';
			include NCK_PATH . 'templates/sign-pad.php';
			?>
		</section>

		<section class="nck-step" data-nck-step data-nck-step-label="مفاد قرارداد" hidden>
			<p class="nck-form-help">متن قرارداد با اطلاعات شما پر شده است. آن را بخوانید و فایل را دانلود کنید.</p>
			<?php include NCK_PATH . 'templates/contract-review.php'; ?>
			<p class="nck-review-actions">
				<button type="button" class="nck-btn" data-nck-download-review>دانلود قرارداد</button>
			</p>
			<label class="nck-agree">
				<input type="checkbox" name="agree" value="1" required />
				مفاد قرارداد را خواندم و می‌پذیرم.
			</label>
		</section>

		<?php
		$nck_pay_id       = 'nck-cowork-pay';
		$nck_pay_amount   = '';
		$nck_pay_required = true;
		$nck_pay_from     = '';
		include NCK_PATH . 'templates/pay-step.php';
		?>

		<nav class="nck-wizard-nav">
			<button type="button" class="nck-btn-ghost" data-nck-prev>مرحله قبل</button>
			<button type="button" class="nck-btn" data-nck-next>مرحله بعد</button>
			<button type="submit" class="nck-btn" data-nck-submit hidden>ثبت و پرداخت</button>
		</nav>
	</form>

	<div class="nck-done" data-nck-done hidden>
		<p class="nck-done-title">قرارداد شما ثبت شد.</p>
		<p data-nck-done-msg></p>
		<p>
			<a class="nck-btn" data-nck-print-link href="#" target="_blank" rel="noopener">دانلود و چاپ قرارداد</a>
		</p>
	</div>
</div>
