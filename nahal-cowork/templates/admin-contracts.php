<?php
defined( 'ABSPATH' ) || exit;
$q    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore
$kind = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : ''; // phpcs:ignore
if ( ! in_array( $kind, array( 'cowork', 'hall' ), true ) ) {
	$kind = '';
}
$rows = NCK_Contracts::search( $q, 60, $kind );
?>
<form method="get" class="nck-search">
	<input type="hidden" name="page" value="<?php echo esc_attr( NCK_MENU ); ?>" />
	<input type="hidden" name="tab" value="contracts" />
	<select name="kind">
		<option value="" <?php selected( $kind, '' ); ?>>همه قراردادها</option>
		<option value="cowork" <?php selected( $kind, 'cowork' ); ?>>فضای کار</option>
		<option value="hall" <?php selected( $kind, 'hall' ); ?>>اجاره سالن</option>
	</select>
	<input type="search" name="s" value="<?php echo esc_attr( $q ); ?>" placeholder="نام، موبایل یا کد ملی" />
	<button class="button">جستجو</button>
</form>

<table class="widefat striped nck-table">
	<thead>
		<tr>
			<th>طرف دوم</th>
			<th>موبایل</th>
			<th>نوع</th>
			<th>جزئیات</th>
			<th>تاریخ امضا</th>
			<th></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( ! $rows ) : ?>
			<tr><td colspan="6">قراردادی ثبت نشده است.</td></tr>
		<?php else : ?>
			<?php foreach ( $rows as $row ) : ?>
				<?php
				$is_hall = 'hall' === NCK_Contracts::kind_of( $row );
				$title   = $is_hall ? NCK_Hall::title_label( $row['honorific'] ) : NCK_Contract::title_label( $row['honorific'] );
				?>
				<tr>
					<td><?php echo esc_html( $title . ' ' . $row['full_name'] ); ?></td>
					<td dir="ltr"><?php echo esc_html( $row['phone'] ); ?></td>
					<td><?php echo esc_html( NCK_Contracts::kind_label( NCK_Contracts::kind_of( $row ) ) ); ?></td>
					<td><?php echo esc_html( NCK_Contracts::plan_label( $row ) ); ?></td>
					<td><?php echo esc_html( $row['signed_at'] ); ?></td>
					<td>
						<?php if ( 'signed' === $row['status'] ) : ?>
							<a class="button button-small" href="<?php echo esc_url( NCK_Contracts::print_url( $row['print_token'] ) ); ?>" target="_blank" rel="noopener">چاپ</a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>
