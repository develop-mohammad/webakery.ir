<?php
defined( 'ABSPATH' ) || exit;
$q = sanitize_text_field( $_GET['s'] ?? '' );
$rows = NM_Booking::query( array( 'search' => $q, 'limit' => 100 ) );
?>
<div class="nm-panel-card">
	<form method="get" class="nm-inline-form">
		<input type="hidden" name="page" value="nobat-man" />
		<input type="hidden" name="tab" value="bookings" />
		<input type="search" name="s" value="<?php echo esc_attr( $q ); ?>" placeholder="جستجو نام / تلفن / کد" />
		<button class="button button-primary">جستجو</button>
	</form>
	<table class="nm-table">
		<thead>
			<tr>
				<th>کد</th><th>مشتری</th><th>تاریخ شمسی</th><th>مبلغ</th><th>وضعیت</th><th>پرداخت</th><th>عملیات</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $rows as $r ) : ?>
			<tr>
				<td><code><?php echo esc_html( $r->booking_code ); ?></code></td>
				<td>
					<strong><?php echo esc_html( $r->customer_name ); ?></strong><br>
					<small><?php echo esc_html( $r->customer_phone . ' · ' . $r->customer_city ); ?></small><br>
					<small><?php echo esc_html( $r->problem_category ); ?></small>
				</td>
				<td><?php echo esc_html( $r->jalali_date ); ?><br><small><?php echo esc_html( substr( $r->start_time, 0, 5 ) . '–' . substr( $r->end_time, 0, 5 ) ); ?></small></td>
				<td><?php echo esc_html( NM_Settings::format_price( $r->price ) ); ?></td>
				<td><?php echo esc_html( $r->status ); ?></td>
				<td><?php echo esc_html( $r->payment_status ); ?></td>
				<td class="nm-ops">
					<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=nm_print_invoice&id=' . $r->id ) ); ?>" target="_blank">فاکتور</a>
					<?php if ( $r->order_id ) : ?>
						<a href="<?php echo esc_url( admin_url( 'post.php?post=' . (int) $r->order_id . '&action=edit' ) ); ?>">سفارش</a>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		<?php if ( empty( $rows ) ) : ?><tr><td colspan="7">موردی نیست.</td></tr><?php endif; ?>
		</tbody>
	</table>
</div>
