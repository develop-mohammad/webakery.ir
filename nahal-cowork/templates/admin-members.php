<?php
defined( 'ABSPATH' ) || exit;
$q       = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore
$rows    = NCK_Members::search( $q, 80, 0 );
$t       = NCK_Jalali::today();
$labels  = NCK_Shifts::labels();
?>
<form method="get" class="nck-search">
	<input type="hidden" name="page" value="<?php echo esc_attr( NCK_MENU ); ?>" />
	<input type="hidden" name="tab" value="members" />
	<input type="search" name="s" value="<?php echo esc_attr( $q ); ?>" placeholder="نام یا موبایل" />
	<button class="button">جستجو</button>
</form>

<table class="widefat striped nck-table">
	<thead>
		<tr>
			<th>نام</th>
			<th>موبایل</th>
			<th>وضعیت</th>
			<th>صبح این ماه</th>
			<th>عصر این ماه</th>
			<th></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( ! $rows ) : ?>
			<tr><td colspan="6">عضوی پیدا نشد.</td></tr>
		<?php else : ?>
			<?php foreach ( $rows as $row ) : ?>
				<?php $snap = NCK_Subscriptions::snapshot( (int) $row['id'], $t['y'], $t['m'] ); ?>
				<tr>
					<td><?php echo esc_html( NCK_Contract::title_label( $row['honorific'] ) . ' ' . $row['full_name'] ); ?></td>
					<td dir="ltr"><?php echo esc_html( $row['phone'] ); ?></td>
					<td>
						<span class="nck-pill <?php echo 'active' === $row['status'] ? 'nck-pill-on' : 'nck-pill-off'; ?>">
							<?php echo 'active' === $row['status'] ? 'فعال' : 'غیرفعال'; ?>
						</span>
					</td>
					<td>
						<?php
						if ( $snap['morning']['has_plan'] ) {
							echo esc_html( NCK_Jalali::fa_digits( $snap['morning']['used'] . ' از ' . $snap['morning']['quota'] ) );
						} else {
							echo '—';
						}
						?>
					</td>
					<td>
						<?php
						if ( $snap['evening']['has_plan'] ) {
							echo esc_html( NCK_Jalali::fa_digits( $snap['evening']['used'] . ' از ' . $snap['evening']['quota'] ) );
						} else {
							echo '—';
						}
						?>
					</td>
					<td>
						<button type="button" class="button button-small" data-nck-status="<?php echo esc_attr( (string) $row['id'] ); ?>" data-to="<?php echo 'active' === $row['status'] ? 'inactive' : 'active'; ?>">
							<?php echo 'active' === $row['status'] ? 'غیرفعال کردن' : 'فعال کردن'; ?>
						</button>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>
<?php unset( $labels ); ?>
