<?php
defined( 'ABSPATH' ) || exit;
$now     = NCK_Jalali::now();
$t       = NCK_Jalali::today();
$g       = $now->format( 'Y-m-d' );
$today   = NCK_Attendance::for_date( $g );
$members = NCK_Members::search( '', 200, 0 );
$labels  = NCK_Shifts::labels();
?>
<div class="nck-panel">
	<h2>ثبت حضور دستی</h2>
	<p class="description">تاریخ را شمسی وارد کنید؛ مثلاً <?php echo esc_html( NCK_Jalali::format( $t['y'], $t['m'], $t['d'] ) ); ?></p>
	<form class="nck-admin-form" data-nck-admin-checkin>
		<select name="member_id" required>
			<option value="">انتخاب عضو</option>
			<?php foreach ( $members as $m ) : ?>
				<option value="<?php echo esc_attr( (string) $m['id'] ); ?>"><?php echo esc_html( $m['full_name'] . ' — ' . $m['phone'] ); ?></option>
			<?php endforeach; ?>
		</select>
		<select name="shift" required>
			<option value="morning">صبح</option>
			<option value="evening">عصر</option>
		</select>
		<input type="text" name="date" dir="ltr" value="<?php echo esc_attr( NCK_Jalali::format( $t['y'], $t['m'], $t['d'] ) ); ?>" />
		<button class="button button-primary">ثبت حضور</button>
	</form>
	<p class="nck-admin-msg" data-nck-admin-msg hidden></p>
</div>

<h2>حضور امروز</h2>
<table class="widefat striped nck-table">
	<thead>
		<tr>
			<th>عضو</th>
			<th>موبایل</th>
			<th>شیفت</th>
			<th>ساعت</th>
		</tr>
	</thead>
	<tbody>
		<?php if ( ! $today ) : ?>
			<tr><td colspan="4">هنوز کسی حضور ثبت نکرده است.</td></tr>
		<?php else : ?>
			<?php foreach ( $today as $row ) : ?>
				<tr>
					<td><?php echo esc_html( $row['full_name'] ); ?></td>
					<td dir="ltr"><?php echo esc_html( $row['phone'] ); ?></td>
					<td><?php echo esc_html( isset( $labels[ $row['shift_type'] ] ) ? $labels[ $row['shift_type'] ] : $row['shift_type'] ); ?></td>
					<td><?php echo esc_html( $row['checked_in_at'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>
