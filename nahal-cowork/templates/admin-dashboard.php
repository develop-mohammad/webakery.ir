<?php
defined( 'ABSPATH' ) || exit;
$t      = NCK_Jalali::today();
$now    = NCK_Jalali::now();
$g      = $now->format( 'Y-m-d' );
$counts = NCK_Attendance::today_counts( $g );
$slot   = NCK_Shifts::current_slot( $now, NCK_Settings::hours() );
$ctx    = NCK_Settings::holiday_context( $t['y'], $t['m'], $t['d'] );
$morn   = NCK_Shifts::day_status( 'morning', $t['y'], $t['m'], $t['d'], $ctx );
$eve    = NCK_Shifts::day_status( 'evening', $t['y'], $t['m'], $t['d'], $ctx );
$labels = NCK_Shifts::labels();
$recent = NCK_Attendance::recent( 12 );
$export = wp_nonce_url( admin_url( 'admin.php?page=' . NCK_MENU . '&nck_export=attendance' ), 'nck_export' );
?>
<div class="nck-dash">
	<div class="nck-cards">
		<div class="nck-card">
			<span>اعضای فعال</span>
			<strong><?php echo esc_html( NCK_Jalali::fa_digits( NCK_Members::count( 'active' ) ) ); ?></strong>
		</div>
		<div class="nck-card">
			<span>قرارداد فضای کار این ماه</span>
			<strong><?php echo esc_html( NCK_Jalali::fa_digits( NCK_Contracts::count_month( $t['y'], $t['m'], 'cowork' ) ) ); ?></strong>
		</div>
		<div class="nck-card">
			<span>اجاره سالن این ماه</span>
			<strong><?php echo esc_html( NCK_Jalali::fa_digits( NCK_Contracts::count_month( $t['y'], $t['m'], 'hall' ) ) ); ?></strong>
		</div>
		<div class="nck-card">
			<span>حضور صبح امروز</span>
			<strong><?php echo esc_html( NCK_Jalali::fa_digits( $counts[ NCK_Shifts::MORNING ] ) ); ?></strong>
		</div>
		<div class="nck-card">
			<span>حضور عصر امروز</span>
			<strong><?php echo esc_html( NCK_Jalali::fa_digits( $counts[ NCK_Shifts::EVENING ] ) ); ?></strong>
		</div>
	</div>

	<div class="nck-panel">
		<p><strong>امروز:</strong> <?php echo esc_html( NCK_Jalali::format_long( $t['y'], $t['m'], $t['d'] ) ); ?>
			— شیفت جاری: <?php echo $slot ? esc_html( $labels[ $slot ] ) : 'خارج از ساعات کاری'; ?>
		</p>
		<p>
			صبح: <?php echo ! empty( $morn['ok'] ) ? 'باز' : 'تعطیل' . ( $morn['title'] ? ' — ' . esc_html( $morn['title'] ) : '' ); ?>
			|
			عصر: <?php echo ! empty( $eve['ok'] ) ? 'باز' : 'تعطیل' . ( $eve['title'] ? ' — ' . esc_html( $eve['title'] ) : '' ); ?>
		</p>
		<p>
			<a class="button" href="<?php echo esc_url( $export ); ?>">خروجی CSV حضور این ماه</a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=' . NCK_MENU . '&nck_export=members' ), 'nck_export' ) ); ?>">خروجی CSV اعضا</a>
		</p>
	</div>

	<h2>آخرین حضورها</h2>
	<table class="widefat striped nck-table">
		<thead>
			<tr>
				<th>عضو</th>
				<th>موبایل</th>
				<th>شیفت</th>
				<th>تاریخ</th>
				<th>منبع</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! $recent ) : ?>
				<tr><td colspan="5">هنوز حضوری ثبت نشده است.</td></tr>
			<?php else : ?>
				<?php foreach ( $recent as $row ) : ?>
					<?php
					$j = NCK_Jalali::from_g_date( $row['shift_date'] );
					$d = $j ? NCK_Jalali::format_long( $j['y'], $j['m'], $j['d'] ) : $row['shift_date'];
					?>
					<tr>
						<td><?php echo esc_html( $row['full_name'] ); ?></td>
						<td dir="ltr"><?php echo esc_html( $row['phone'] ); ?></td>
						<td><?php echo esc_html( isset( $labels[ $row['shift_type'] ] ) ? $labels[ $row['shift_type'] ] : $row['shift_type'] ); ?></td>
						<td><?php echo esc_html( $d ); ?></td>
						<td><?php echo 'admin' === $row['source'] ? 'پیشخوان' : 'سایت'; ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
