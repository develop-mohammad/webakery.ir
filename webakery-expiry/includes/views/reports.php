<?php
defined( 'ABSPATH' ) || exit;

$sort_url = function ( $key ) use ( $sort, $dir ) {
	$next = ( $sort === $key && 'desc' === $dir ) ? 'asc' : 'desc';
	$args = array_merge(
		$_GET, // phpcs:ignore WordPress.Security.NonceVerification
		array(
			'page'     => 'webakery-expiry',
			'wbe_sort' => $key,
			'wbe_dir'  => $next,
		)
	);
	return esc_url( admin_url( 'admin.php?' . http_build_query( $args ) ) );
};

$cats = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
	)
);
if ( is_wp_error( $cats ) ) {
	$cats = array();
}

$export_base = wp_nonce_url( admin_url( 'admin-post.php?action=wbe_export' ), 'wbe_export' );
$qs          = $_GET; // phpcs:ignore WordPress.Security.NonceVerification
unset( $qs['page'] );
$export_xls = $export_base . '&format=xls&' . http_build_query( $qs );
$export_csv = $export_base . '&format=csv&' . http_build_query( $qs );
?>
<div class="wrap wbe-wrap" dir="rtl">
	<h1>گزارش انقضا و موجودی</h1>
	<p class="wbe-sub">فقط محصولاتی که بچ برایشان تنظیم شده. خروجی اکسل فارسی و راست‌چین است.</p>

	<form method="get" class="wbe-filters">
		<input type="hidden" name="page" value="webakery-expiry" />
		<input type="search" name="s" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="جستجوی محصول یا SKU" />
		<select name="wbe_cat">
			<option value="0">همه دسته‌بندی‌ها</option>
			<?php foreach ( $cats as $c ) : ?>
				<option value="<?php echo (int) $c->term_id; ?>" <?php selected( $filters['category'], (int) $c->term_id ); ?>><?php echo esc_html( $c->name ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="text" name="wbe_brand" value="<?php echo esc_attr( $filters['brand'] ); ?>" placeholder="برند" />
		<label class="wbe-check">
			<input type="checkbox" name="wbe_near" value="1" <?php checked( $filters['near'], 1 ); ?> />
			نزدیک به انقضا
		</label>
		<button type="submit" class="button">اعمال فیلتر</button>
		<a class="button button-primary" href="<?php echo esc_url( $export_xls ); ?>">خروجی اکسل</a>
		<a class="button" href="<?php echo esc_url( $export_csv ); ?>">CSV</a>
	</form>

	<table class="widefat striped wbe-report">
		<thead>
			<tr>
				<th><a href="<?php echo $sort_url( 'name' ); ?>">محصول</a></th>
				<th>دسته</th>
				<th>برند</th>
				<th><a href="<?php echo $sort_url( 'expiry' ); ?>">انقضا</a></th>
				<th><a href="<?php echo $sort_url( 'days' ); ?>">روز مانده</a></th>
				<th><a href="<?php echo $sort_url( 'price' ); ?>">قیمت</a></th>
				<th><a href="<?php echo $sort_url( 'stock' ); ?>">موجودی</a></th>
				<th>رزرو</th>
				<th><a href="<?php echo $sort_url( 'sold_qty' ); ?>">فروش</a></th>
				<th><a href="<?php echo $sort_url( 'sold_amt' ); ?>">مبلغ فروش</a></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="10">محصول تنظیم‌شده‌ای مطابق فیلتر پیدا نشد.</td></tr>
			<?php else : ?>
				<?php foreach ( $rows as $r ) : ?>
					<tr class="wbe-row--<?php echo esc_attr( $r['status'] ); ?>">
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( $r['id'] ) ); ?>"><?php echo esc_html( $r['name'] ); ?></a>
							<?php if ( $r['sku'] ) : ?><div class="wbe-muted"><?php echo esc_html( $r['sku'] ); ?></div><?php endif; ?>
						</td>
						<td><?php echo esc_html( $r['category'] ? $r['category'] : '—' ); ?></td>
						<td><?php echo esc_html( $r['brand'] ); ?></td>
						<td><?php echo esc_html( $r['expiry_fa'] ); ?></td>
						<td><?php echo null === $r['days'] ? '—' : esc_html( (string) $r['days'] ); ?></td>
						<td><?php echo esc_html( (string) $r['price'] ); ?></td>
						<td><?php echo esc_html( (string) $r['stock'] ); ?></td>
						<td><?php echo esc_html( (string) $r['reserves'] ); ?></td>
						<td><?php echo esc_html( (string) $r['sold_qty'] ); ?></td>
						<td><?php echo esc_html( (string) $r['sold_amt'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
