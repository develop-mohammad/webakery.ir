<?php
defined( 'ABSPATH' ) || exit;
$t     = NCK_Jalali::today();
$today = NCK_Jalali::format( $t['y'], $t['m'], $t['d'] );
$label = NCK_Jalali::format_long( $t['y'], $t['m'], $t['d'] );
$slots = NCK_Hall::time_slots();
$start = '16:00';
$end   = '20:00';
$wd    = array( 'ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج' );
?>
<div class="nck-field nck-field-wide">
	<span class="nck-label" id="nck-hall-date-label">تاریخ برگزاری</span>
	<div
		class="nck-cal"
		data-nck-cal
		data-today="<?php echo esc_attr( $today ); ?>"
		data-today-y="<?php echo esc_attr( (string) $t['y'] ); ?>"
		data-today-m="<?php echo esc_attr( (string) $t['m'] ); ?>"
		data-today-d="<?php echo esc_attr( (string) $t['d'] ); ?>"
	>
		<button type="button" class="nck-cal-trigger" data-nck-cal-open aria-labelledby="nck-hall-date-label" aria-haspopup="dialog">
			<span data-nck-cal-label><?php echo esc_html( $label ); ?></span>
		</button>
		<input id="nck-hall-date" name="event_date" type="hidden" required value="<?php echo esc_attr( $today ); ?>" />
		<div class="nck-cal-pop" data-nck-cal-pop hidden role="dialog" aria-label="تقویم شمسی">
			<div class="nck-cal-nav">
				<button type="button" data-nck-cal-prev>ماه قبل</button>
				<strong data-nck-cal-month><?php echo esc_html( NCK_Jalali::month_names()[ (int) $t['m'] - 1 ] . ' ' . NCK_Jalali::fa_digits( $t['y'] ) ); ?></strong>
				<button type="button" data-nck-cal-next>ماه بعد</button>
			</div>
			<div class="nck-cal-weekdays">
				<?php foreach ( $wd as $w ) : ?>
					<span><?php echo esc_html( $w ); ?></span>
				<?php endforeach; ?>
			</div>
			<div class="nck-cal-grid" data-nck-cal-grid></div>
		</div>
	</div>
</div>
<div class="nck-field nck-field-wide">
	<span class="nck-label">ساعت اجاره</span>
	<p class="nck-form-help">ساعت را روی رول بگردانید. فقط ۹ تا ۱۳ و ۱۶ تا ۲۲ قابل انتخاب است.</p>
	<div class="nck-time-rolls" data-nck-time-rolls>
		<div class="nck-roll" data-nck-roll="start">
			<p class="nck-roll-caption">از ساعت</p>
			<div class="nck-roll-frame" data-nck-roll-frame tabindex="0">
				<ul class="nck-roll-list">
					<?php foreach ( $slots as $slot ) : ?>
						<li data-value="<?php echo esc_attr( $slot ); ?>"<?php echo $slot === $start ? ' class="is-on"' : ''; ?>><?php echo esc_html( NCK_Jalali::fa_digits( $slot ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<div class="nck-roll" data-nck-roll="end">
			<p class="nck-roll-caption">تا ساعت</p>
			<div class="nck-roll-frame" data-nck-roll-frame tabindex="0">
				<ul class="nck-roll-list">
					<?php foreach ( $slots as $slot ) : ?>
						<li data-value="<?php echo esc_attr( $slot ); ?>"<?php echo $slot === $end ? ' class="is-on"' : ''; ?>><?php echo esc_html( NCK_Jalali::fa_digits( $slot ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<input id="nck-hall-start" name="start_hour" type="hidden" required value="<?php echo esc_attr( $start ); ?>" />
		<input id="nck-hall-end" name="end_hour" type="hidden" required value="<?php echo esc_attr( $end ); ?>" />
	</div>
</div>
