<?php
defined( 'ABSPATH' ) || exit;
global $wpdb;
$bookings_table = $wpdb->prefix . 'nm_bookings';
$total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table}" );
$paid    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table} WHERE payment_status='paid'" );
$pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table} WHERE status='pending'" );
$revenue = (int) $wpdb->get_var( "SELECT COALESCE(SUM(price),0) FROM {$bookings_table} WHERE payment_status='paid'" );
$recent  = NM_Booking::query( array( 'limit' => 8 ) );
$today   = NM_Jalali::today();
?>
<div class="nm-stats">
	<div class="nm-stat"><span>کل رزروها</span><strong><?php echo number_format_i18n( $total ); ?></strong></div>
	<div class="nm-stat"><span>پرداخت‌شده</span><strong><?php echo number_format_i18n( $paid ); ?></strong></div>
	<div class="nm-stat"><span>در انتظار</span><strong><?php echo number_format_i18n( $pending ); ?></strong></div>
	<div class="nm-stat accent"><span>درآمد (تومان)</span><strong><?php echo number_format_i18n( $revenue ); ?></strong></div>
</div>

<div class="nm-grid-2">
	<div class="nm-panel-card">
		<h3>امروز شمسی</h3>
		<p class="nm-big"><?php echo esc_html( NM_Jalali::format( $today['y'], $today['m'], $today['d'] ) ); ?></p>
		<p class="nm-muted"><?php echo esc_html( NM_Jalali::weekday_names()[ NM_Jalali::weekday( $today['y'], $today['m'], $today['d'] ) ] . ' — ' . NM_Jalali::month_names()[ $today['m'] - 1 ] ); ?></p>
		<?php $h = NM_Holidays::is_holiday( $today['y'], $today['m'], $today['d'] ); if ( $h ) : ?>
			<span class="nm-pill warn">تعطیل: <?php echo esc_html( $h ); ?></span>
		<?php endif; ?>
	</div>
	<div class="nm-panel-card">
		<h3>شروع سریع</h3>
		<ul class="nm-checklist">
			<li>شورت‌کد <code>[nobat_man]</code> را در برگه قرار دهید</li>
			<li>ساعات کاری و قیمت را تنظیم کنید</li>
			<li>ووکامرس را برای پرداخت فعال کنید</li>
			<li>برای متخصصین متعدد لایسنس پرو بگیرید</li>
		</ul>
	</div>
</div>

<div class="nm-panel-card">
	<h3>آخرین رزروها</h3>
	<table class="nm-table">
		<thead><tr><th>کد</th><th>مشتری</th><th>تاریخ</th><th>مبلغ</th><th>وضعیت</th></tr></thead>
		<tbody>
		<?php if ( empty( $recent ) ) : ?>
			<tr><td colspan="5">هنوز رزروی ثبت نشده است.</td></tr>
		<?php else : foreach ( $recent as $r ) : ?>
			<tr>
				<td><code><?php echo esc_html( $r->booking_code ); ?></code></td>
				<td><?php echo esc_html( $r->customer_name ); ?><br><small><?php echo esc_html( $r->customer_phone ); ?></small></td>
				<td><?php echo esc_html( $r->jalali_date . ' ' . substr( $r->start_time, 0, 5 ) ); ?></td>
				<td><?php echo esc_html( NM_Settings::format_price( $r->price ) ); ?></td>
				<td><span class="nm-pill"><?php echo esc_html( $r->status ); ?></span></td>
			</tr>
		<?php endforeach; endif; ?>
		</tbody>
	</table>
</div>
