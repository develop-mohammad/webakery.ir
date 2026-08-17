<?php
defined( 'ABSPATH' ) || exit;

$campaigns   = WBCC_Campaigns::all();
$campaign_id = isset( $_GET['campaign'] ) ? (int) $_GET['campaign'] : 0;
$paged       = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
$per_page    = 30;

$data  = WBCC_Generator::list_coupons( array(
	'campaign' => $campaign_id,
	'paged'    => $paged,
	'limit'    => $per_page,
) );
$pages = (int) ceil( $data['total'] / $per_page );
?>

<div class="wbcc-bar">
	<form method="get" class="wbcc-inline-form">
		<input type="hidden" name="page" value="<?php echo esc_attr( WBCC_MENU ); ?>">
		<input type="hidden" name="tab" value="coupons">
		<select name="campaign">
			<option value="0">همه کمپین‌ها</option>
			<?php foreach ( $campaigns as $c ) : ?>
				<option value="<?php echo (int) $c['id']; ?>" <?php selected( $campaign_id, $c['id'] ); ?>>
					<?php echo esc_html( $c['name'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button">فیلتر</button>
	</form>

	<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wbcc_export&campaign=' . $campaign_id ), 'wbcc_coupon_action' ) ); ?>">
		دریافت خروجی CSV
	</a>
	<span class="wbcc-bar-hint">مجموع: <?php echo esc_html( WBCC_Date::fa_digits( $data['total'] ) ); ?> کد ساخته‌شده توسط افزونه</span>
</div>

<?php if ( ! $data['items'] ) : ?>

	<div class="wbcc-empty">
		<div class="wbcc-empty-ic">🧾</div>
		<h2>هنوز کدی ساخته نشده</h2>
		<p>از تب «کمپین‌های تخفیف» روی دکمه «ساخت کد» بزنید.</p>
	</div>

<?php else : ?>

	<table class="widefat striped wbcc-table">
		<thead>
			<tr>
				<th>کد تخفیف</th>
				<th>کمپین</th>
				<th>مقدار</th>
				<th>دسته‌بندی‌ها</th>
				<th>انقضا</th>
				<th>مصرف</th>
				<th>منبع</th>
				<th>عملیات</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $data['items'] as $item ) :
			$campaign = $campaigns[ $item['campaign'] ] ?? null;
			$expired  = $item['expires'] && $item['expires'] < time();
			$sources  = array( 'manual' => 'دستی', 'auto' => 'خودکار', 'public' => 'دریافت مشتری' );
			?>
			<tr>
				<td>
					<code class="wbcc-code-cell" dir="ltr"><?php echo esc_html( $item['code'] ); ?></code>
					<button type="button" class="button-link wbcc-copy-btn" data-code="<?php echo esc_attr( $item['code'] ); ?>">کپی</button>
				</td>
				<td><?php echo $campaign ? esc_html( $campaign['name'] ) : '<span class="wbcc-muted">حذف‌شده</span>'; ?></td>
				<td class="wbcc-amount">
					<?php
					echo esc_html( WBCC_Date::fa_digits( WBCC_Campaigns::trim_zeros( $item['amount'] ) ) );
					echo 'percent' === $item['type'] ? '٪' : '';
					?>
				</td>
				<td><?php echo esc_html( implode( '، ', WBCC_Campaigns::category_names( $item['categories'], 3 ) ) ); ?></td>
				<td>
					<?php if ( ! $item['expires'] ) : ?>
						<span class="wbcc-muted">بدون انقضا</span>
					<?php else : ?>
						<span class="<?php echo $expired ? 'wbcc-expired' : ''; ?>">
							<?php echo esc_html( WBCC_Date::format( $item['expires'] ) ); ?>
							<?php echo $expired ? '(منقضی)' : ''; ?>
						</span>
					<?php endif; ?>
				</td>
				<td>
					<?php
					echo esc_html( WBCC_Date::fa_digits( $item['usage'] ) );
					echo ' / ';
					echo $item['limit'] ? esc_html( WBCC_Date::fa_digits( $item['limit'] ) ) : '∞';
					?>
				</td>
				<td><?php echo esc_html( $sources[ $item['source'] ] ?? $item['source'] ); ?></td>
				<td class="wbcc-actions">
					<a class="button" href="<?php echo esc_url( get_edit_post_link( $item['id'] ) ); ?>">ویرایش در ووکامرس</a>
					<a class="button wbcc-danger"
					   href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wbcc_delete_coupon&id=' . $item['id'] . '&campaign=' . $campaign_id ), 'wbcc_coupon_action' ) ); ?>"
					   onclick="return confirm('این کد تخفیف حذف شود؟')">حذف</a>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $pages > 1 ) : ?>
		<div class="tablenav"><div class="tablenav-pages">
			<?php
			echo paginate_links( array(
				'base'    => admin_url( 'admin.php?page=' . WBCC_MENU . '&tab=coupons&campaign=' . $campaign_id . '&paged=%#%' ),
				'format'  => '',
				'current' => $paged,
				'total'   => $pages,
			) ); // phpcs:ignore WordPress.Security.EscapeOutput
			?>
		</div></div>
	<?php endif; ?>

<?php endif; ?>
