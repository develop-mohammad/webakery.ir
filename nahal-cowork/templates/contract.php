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

		<section class="nck-step" data-nck-step data-nck-step-label="متن قرارداد">
			<article class="nck-paper nck-paper-nested">
				<p class="nck-intro"><?php echo esc_html( $intro ); ?></p>
				<p class="nck-preamble" data-nck-preamble data-tpl="<?php echo esc_attr( $s['contract_preamble'] ); ?>"><?php echo esc_html( $preamble ); ?></p>
				<?php foreach ( $sections as $sec ) : ?>
					<section class="nck-section">
						<h3><?php echo esc_html( $sec['title'] ); ?></h3>
						<?php foreach ( preg_split( '/\n+/', $sec['body'] ) as $p ) : ?>
							<?php if ( trim( $p ) !== '' ) : ?>
								<p><?php echo esc_html( $p ); ?></p>
							<?php endif; ?>
						<?php endforeach; ?>
					</section>
				<?php endforeach; ?>
				<div class="nck-hours" aria-hidden="true">
					<span>صبح <?php echo esc_html( NCK_Jalali::fa_digits( $hours['morning_start'] . '–' . $hours['morning_end'] ) ); ?></span>
					<span>عصر <?php echo esc_html( NCK_Jalali::fa_digits( $hours['evening_start'] . '–' . $hours['evening_end'] ) ); ?></span>
				</div>
			</article>
		</section>

		<section class="nck-step" data-nck-step data-nck-step-label="مشخصات و اشتراک" hidden>
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
				<div class="nck-field nck-field-wide">
					<span class="nck-label">نوع اشتراک</span>
					<div class="nck-plans">
						<label class="nck-plan"><input type="radio" name="plan" value="morning" required /> شیفت صبح</label>
						<label class="nck-plan"><input type="radio" name="plan" value="evening" /> شیفت عصر</label>
						<label class="nck-plan"><input type="radio" name="plan" value="both" /> هر دو شیفت (دو اشتراک)</label>
					</div>
				</div>
			</div>
		</section>

		<section class="nck-step" data-nck-step data-nck-step-label="امضا" hidden>
			<?php
			$nck_sign_title = 'امضا';
			$nck_sign_party = 'امضای عضو';
			include NCK_PATH . 'templates/sign-pad.php';
			?>
			<label class="nck-agree">
				<input type="checkbox" name="agree" value="1" required />
				مفاد قرارداد را خواندم و می‌پذیرم.
			</label>
		</section>

		<?php
		$nck_pay_id       = 'nck-cowork-pay';
		$nck_pay_amount   = ! empty( $s['cowork_fee'] ) ? (string) $s['cowork_fee'] : '';
		$nck_pay_required = ! empty( $s['cowork_fee'] );
		$nck_pay_from     = '';
		include NCK_PATH . 'templates/pay-step.php';
		?>

		<nav class="nck-wizard-nav">
			<button type="button" class="nck-btn-ghost" data-nck-prev>مرحله قبل</button>
			<button type="button" class="nck-btn" data-nck-next>مرحله بعد</button>
			<button type="submit" class="nck-btn" data-nck-submit hidden>ثبت قرارداد</button>
		</nav>
	</form>

	<div class="nck-done" data-nck-done hidden>
		<p class="nck-done-title">قرارداد شما ثبت شد.</p>
		<p data-nck-done-msg></p>
		<p>
			<a class="nck-btn" data-nck-print-link href="#" target="_blank" rel="noopener">مشاهده و چاپ قرارداد</a>
		</p>
	</div>
</div>
