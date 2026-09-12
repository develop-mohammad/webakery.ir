<?php
defined( 'ABSPATH' ) || exit;
$t        = NCK_Jalali::today();
$today    = NCK_Jalali::format( $t['y'], $t['m'], $t['d'] );
$label    = NCK_Jalali::format_long( $t['y'], $t['m'], $t['d'] );
$bookable = NCK_Hall::bookable_slots();
$start    = '16:00';
$end      = '17:30';
$wd       = array( 'ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج' );
?>
<div class="nck-field nck-field-wide">
	<span class="nck-label" id="nck-hall-date-label">تاریخ برگزاری</span>
	<p class="nck-form-help">تقویم ماهانه را ورق بزنید. روزهای نقطه‌دار رزرو دارند؛ با انتخاب روز، ساعت‌های پر را می‌بینید و بعد یک نوبت ۹۰ دقیقه‌ای خالی را انتخاب کنید.</p>
	<div
		class="nck-cal nck-cal-month"
		data-nck-cal
		data-today="<?php echo esc_attr( $today ); ?>"
		data-today-y="<?php echo esc_attr( (string) $t['y'] ); ?>"
		data-today-m="<?php echo esc_attr( (string) $t['m'] ); ?>"
		data-today-d="<?php echo esc_attr( (string) $t['d'] ); ?>"
	>
		<p class="nck-cal-picked" data-nck-cal-label><?php echo esc_html( $label ); ?></p>
		<input id="nck-hall-date" name="event_date" type="hidden" required value="<?php echo esc_attr( $today ); ?>" />
		<div class="nck-cal-board" data-nck-cal-board role="group" aria-labelledby="nck-hall-date-label">
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
			<p class="nck-cal-status" data-nck-cal-status hidden></p>
		</div>
		<div class="nck-cal-busy" data-nck-cal-busy>
			<p class="nck-cal-busy-title">ساعت‌های پر این روز</p>
			<ul data-nck-cal-busy-list hidden></ul>
			<p class="nck-cal-busy-empty" data-nck-cal-busy-empty>برای این روز رزروی ثبت نشده؛ می‌توانید نوبت آزاد را انتخاب کنید.</p>
		</div>
	</div>
</div>
<div class="nck-field nck-field-wide">
	<label for="nck-hall-slot">نوبت ۹۰ دقیقه‌ای</label>
	<p class="nck-form-help">هر رزرو ۹۰ دقیقه است. نوبت صبح از ۹ تا ۱۳ با ۵۰ درصد تخفیف اجاره فضا است؛ نوبت‌های پر قابل انتخاب نیستند.</p>
	<select id="nck-hall-slot" class="nck-slot-select" name="nck_slot" data-nck-hall-slot required>
		<optgroup label="صبح — ۵۰٪ تخفیف">
			<?php foreach ( $bookable as $slot ) : ?>
				<?php
				if ( empty( $slot['morning'] ) ) {
					continue;
				}
				$val = $slot['start'] . '-' . $slot['end'];
				?>
				<option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( NCK_Jalali::fa_digits( $slot['start'] ) . ' تا ' . NCK_Jalali::fa_digits( $slot['end'] ) ); ?></option>
			<?php endforeach; ?>
		</optgroup>
		<optgroup label="عصر">
			<?php foreach ( $bookable as $slot ) : ?>
				<?php
				if ( ! empty( $slot['morning'] ) ) {
					continue;
				}
				$val = $slot['start'] . '-' . $slot['end'];
				$sel = ( $slot['start'] === $start && $slot['end'] === $end );
				?>
				<option value="<?php echo esc_attr( $val ); ?>"<?php echo $sel ? ' selected' : ''; ?>><?php echo esc_html( NCK_Jalali::fa_digits( $slot['start'] ) . ' تا ' . NCK_Jalali::fa_digits( $slot['end'] ) ); ?></option>
			<?php endforeach; ?>
		</optgroup>
	</select>
	<input id="nck-hall-start" name="start_hour" type="hidden" required value="<?php echo esc_attr( $start ); ?>" />
	<input id="nck-hall-end" name="end_hour" type="hidden" required value="<?php echo esc_attr( $end ); ?>" />
</div>
