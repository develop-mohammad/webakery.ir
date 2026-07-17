<?php
defined( 'ABSPATH' ) || exit;
global $wpdb;
$sp = (int) ( $_GET['specialist_id'] ?? 0 );
$names = NM_Jalali::weekday_names();
$rows = $wpdb->get_results( $wpdb->prepare(
	'SELECT * FROM ' . $wpdb->prefix . 'nm_schedules WHERE specialist_id = %d ORDER BY weekday',
	$sp
), OBJECT_K );
$by = array();
foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'nm_schedules WHERE specialist_id = %d', $sp ) ) as $r ) {
	$by[ (int) $r->weekday ] = $r;
}
$specialists = $wpdb->get_results( 'SELECT id, name FROM ' . NM_Specialist::table() . ' ORDER BY name' );
?>
<div class="nm-panel-card">
	<form method="get" class="nm-inline-form">
		<input type="hidden" name="page" value="nobat-man" />
		<input type="hidden" name="tab" value="schedule" />
		<label>متخصص
			<select name="specialist_id" onchange="this.form.submit()">
				<option value="0">پیش‌فرض عمومی</option>
				<?php foreach ( $specialists as $s ) : ?>
					<option value="<?php echo (int) $s->id; ?>" <?php selected( $sp, $s->id ); ?>><?php echo esc_html( $s->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
	</form>

	<form method="post">
		<?php wp_nonce_field( 'nm_schedule' ); ?>
		<input type="hidden" name="nm_save_schedule" value="1" />
		<input type="hidden" name="specialist_id" value="<?php echo (int) $sp; ?>" />
		<table class="nm-table">
			<thead><tr><th>روز هفته (شمسی)</th><th>فعال</th><th>از</th><th>تا</th></tr></thead>
			<tbody>
			<?php for ( $d = 0; $d < 7; $d++ ) :
				$r = $by[ $d ] ?? null;
				$start = $r ? substr( $r->start_time, 0, 5 ) : '09:00';
				$end   = $r ? substr( $r->end_time, 0, 5 ) : '17:00';
				?>
				<tr>
					<td><?php echo esc_html( $names[ $d ] ); ?></td>
					<td><input type="checkbox" name="day[<?php echo $d; ?>][active]" value="1" <?php checked( (bool) $r ); ?> /></td>
					<td><input type="time" name="day[<?php echo $d; ?>][start]" value="<?php echo esc_attr( $start ); ?>" /></td>
					<td><input type="time" name="day[<?php echo $d; ?>][end]" value="<?php echo esc_attr( $end ); ?>" /></td>
				</tr>
			<?php endfor; ?>
			</tbody>
		</table>
		<p class="nm-muted">جمعه و تعطیلات رسمی در تنظیمات قابل مسدودسازی هستند. تقویم کاملاً شمسی است.</p>
		<button class="button button-primary">ذخیره برنامه</button>
	</form>
</div>
