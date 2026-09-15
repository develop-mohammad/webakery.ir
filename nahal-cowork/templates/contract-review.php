<?php
defined( 'ABSPATH' ) || exit;
?>
<article class="nck-paper nck-paper-nested" data-nck-review>
	<p class="nck-intro"><?php echo esc_html( $intro ); ?></p>
	<p class="nck-preamble" data-nck-preamble data-tpl="<?php echo esc_attr( $s['contract_preamble'] ); ?>"><?php echo esc_html( $preamble ); ?></p>
	<div class="nck-review-meta">
		<p>اشتراک: <strong data-nck-live="package" data-blank="—">—</strong></p>
		<p>شیفت: <strong data-nck-live="shift" data-blank="—">—</strong></p>
	</div>
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
	<div class="nck-sign-plate nck-sign-plate-review">
		<p class="nck-sign-plate-kicker">امضای عضو</p>
		<div class="nck-sign-plate-stage">
			<img class="nck-sign-plate-ink" data-nck-review-ink alt="امضا" hidden />
			<span class="nck-sign-plate-empty" data-nck-review-empty>امضا از مرحله قبل اینجاست.</span>
			<span class="nck-sign-plate-line" aria-hidden="true"></span>
		</div>
		<div class="nck-sign-plate-meta">
			<span>امضای عضو</span>
			<strong data-nck-live="name">…</strong>
		</div>
	</div>
</article>
