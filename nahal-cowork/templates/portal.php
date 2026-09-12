<?php
defined( 'ABSPATH' ) || exit;
$s      = NCK_Settings::all();
$notice = $s['event_notice'];
$hours  = NCK_Settings::hours();
?>
<div class="nck-root nck-portal" dir="rtl" data-nck="portal">
	<div class="nck-portal-card">
		<p class="nck-kicker"><?php echo esc_html( $s['org_name'] ); ?></p>
		<h2 class="nck-title">پورتال عضو</h2>
		<p class="nck-tag">باقی‌مانده شیفت‌ها و ثبت حضور</p>

		<?php if ( $notice ) : ?>
			<div class="nck-banner"><?php echo esc_html( $notice ); ?></div>
		<?php endif; ?>

		<div class="nck-hours-line">
			<span>صبح <?php echo esc_html( NCK_Jalali::fa_digits( $hours['morning_start'] . ' الی ' . $hours['morning_end'] ) ); ?></span>
			<span>عصر <?php echo esc_html( NCK_Jalali::fa_digits( $hours['evening_start'] . ' الی ' . $hours['evening_end'] ) ); ?></span>
		</div>

		<div class="nck-alert nck-alert-error" data-nck-error hidden></div>
		<div class="nck-alert nck-alert-ok" data-nck-ok hidden></div>

		<form class="nck-form nck-form-compact" data-nck-lookup>
			<div class="nck-field">
				<label for="nck-portal-phone">شماره موبایل قرارداد</label>
				<input id="nck-portal-phone" name="phone" type="tel" dir="ltr" inputmode="numeric" required placeholder="09123456789" />
			</div>
			<button type="submit" class="nck-btn" data-nck-lookup-btn>مشاهده اشتراک</button>
		</form>

		<div class="nck-member" data-nck-member hidden>
			<p class="nck-member-name" data-nck-member-name></p>
			<p class="nck-member-month" data-nck-month></p>
			<div class="nck-shift-cards" data-nck-cards></div>
			<div class="nck-portal-actions">
				<button type="button" class="nck-btn" data-nck-checkin hidden>ثبت حضور شیفت جاری</button>
				<a class="nck-link-btn" data-nck-print href="#" target="_blank" rel="noopener" hidden>چاپ قرارداد</a>
			</div>
		</div>
	</div>
</div>
