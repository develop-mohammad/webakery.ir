<?php
defined( 'ABSPATH' ) || exit;

$cats = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
	)
);
if ( is_wp_error( $cats ) ) {
	$cats = array();
}

$modes    = WBE_Admin_Bulk::mode_labels();
$ph_date  = ( 'jalali' === $calendar ) ? '۱۴۰۵/۰۶/۰۵' : '2026/08/27';
$action   = admin_url( 'admin-post.php' );
?>
<div class="wrap wbe-wrap wbe-bulk-wrap" dir="rtl">
	<h1>ویرایش گروهی قیمت تخفیف و جشنواره</h1>
	<p class="wbe-sub">فقط محصولاتی که بچ انقضا دارند. تغییرات روی <strong>بچ فعال</strong> اعمال می‌شود؛ رزرو دست نمی‌خورد. اگر هم درصد تخفیف و هم مبلغ جشنواره را بگذارید، مبلغ جشنواره اولویت دارد.</p>

	<?php if ( $updated || $skipped ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $updated ); ?> محصول به‌روز شد<?php echo $skipped ? ' — ' . (int) $skipped . ' محصول بدون بچ رد شد' : ''; ?>.</p></div>
	<?php endif; ?>
	<?php if ( ! empty( $empty ) ) : ?>
		<div class="notice notice-warning is-dismissible"><p>چیزی برای اعمال نبود. محصول را تیک بزنید و حداقل یک فیلد (درصد، مبلغ جشنواره یا تاریخ) را پر کنید.</p></div>
	<?php endif; ?>

	<form method="get" class="wbe-filters">
		<input type="hidden" name="page" value="webakery-expiry-bulk" />
		<input type="search" name="s" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="جستجوی محصول یا SKU" />
		<select name="wbe_cat">
			<option value="0">همه دسته‌بندی‌ها</option>
			<?php foreach ( $cats as $c ) : ?>
				<option value="<?php echo (int) $c->term_id; ?>" <?php selected( $filters['category'], (int) $c->term_id ); ?>><?php echo esc_html( $c->name ); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button">اعمال فیلتر</button>
	</form>

	<form method="post" action="<?php echo esc_url( $action ); ?>" class="wbe-bulk-form">
		<input type="hidden" name="action" value="wbe_bulk_apply" />
		<input type="hidden" name="s" value="<?php echo esc_attr( $filters['q'] ); ?>" />
		<input type="hidden" name="wbe_cat" value="<?php echo (int) $filters['category']; ?>" />
		<?php wp_nonce_field( 'wbe_bulk' ); ?>

		<div class="wbe-bulk-toolbar">
			<div class="wbe-bulk-field">
				<label for="wbe_regular_mode">قیمت اصلی</label>
				<select id="wbe_regular_mode" name="wbe_regular_mode" class="wbe-bulk-mode">
					<?php foreach ( $modes as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="text" name="wbe_regular_value" class="wbe-bulk-value" placeholder="مبلغ یا درصد" dir="ltr" />
			</div>
			<div class="wbe-bulk-field">
				<label for="wbe_discount">قیمت با تخفیف — درصد</label>
				<input type="text" id="wbe_discount" name="wbe_discount" placeholder="مثلاً ۲۰" dir="ltr" />
			</div>
			<div class="wbe-bulk-field">
				<label for="wbe_sale_mode">قیمت جشنواره — مبلغ</label>
				<select id="wbe_sale_mode" name="wbe_sale_mode" class="wbe-bulk-mode">
					<?php foreach ( $modes as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="text" name="wbe_sale_value" class="wbe-bulk-value" placeholder="مبلغ فروش" dir="ltr" />
			</div>
			<div class="wbe-bulk-field">
				<label for="wbe_sale_from">جشنواره از</label>
				<input type="text" id="wbe_sale_from" name="wbe_sale_from" placeholder="<?php echo esc_attr( $ph_date ); ?>" dir="ltr" />
			</div>
			<div class="wbe-bulk-field">
				<label for="wbe_sale_to">جشنواره تا</label>
				<input type="text" id="wbe_sale_to" name="wbe_sale_to" placeholder="<?php echo esc_attr( $ph_date ); ?>" dir="ltr" />
			</div>
			<label class="wbe-check">
				<input type="checkbox" name="wbe_clear_sale" value="1" />
				حذف تخفیف و بازه جشنواره
			</label>
			<div class="wbe-bulk-actions">
				<button type="submit" class="button button-primary" name="wbe_bulk_mode" value="selected">اعمال روی انتخاب‌شده‌ها</button>
				<button type="submit" class="button" name="wbe_bulk_mode" value="rows">اعمال ردیف‌های پرشده</button>
			</div>
		</div>

		<table class="widefat striped wbe-report wbe-bulk-table">
			<thead>
				<tr>
					<td class="check-column"><input type="checkbox" id="wbe-bulk-check-all" /></td>
					<th>محصول</th>
					<th>قیمت اصلی</th>
					<th>تخفیف ٪</th>
					<th>قیمت با تخفیف</th>
					<th>جشنواره از / تا</th>
					<th>انقضا</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="7">محصول تنظیم‌شده‌ای مطابق فیلتر پیدا نشد.</td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $r ) : ?>
						<tr class="<?php echo empty( $r['has_active'] ) ? 'wbe-row--empty' : ''; ?>">
							<th class="check-column">
								<input type="checkbox" class="wbe-bulk-id" name="ids[]" value="<?php echo (int) $r['id']; ?>" />
							</th>
							<td>
								<a href="<?php echo esc_url( get_edit_post_link( $r['id'] ) ); ?>"><?php echo esc_html( $r['name'] ); ?></a>
								<?php if ( $r['sku'] ) : ?><div class="wbe-muted"><?php echo esc_html( $r['sku'] ); ?></div><?php endif; ?>
								<?php if ( empty( $r['has_active'] ) ) : ?><div class="wbe-muted">بدون بچ فعال</div><?php endif; ?>
							</td>
							<td>
								<input type="text" class="small-text" name="wbe_row[<?php echo (int) $r['id']; ?>][regular]" value="" placeholder="<?php echo esc_attr( $r['regular'] ); ?>" dir="ltr" />
							</td>
							<td>
								<input type="text" class="small-text" name="wbe_row[<?php echo (int) $r['id']; ?>][discount]" value="" placeholder="<?php echo esc_attr( (string) $r['discount'] ); ?>" dir="ltr" />
							</td>
							<td>
								<input type="text" class="small-text" name="wbe_row[<?php echo (int) $r['id']; ?>][sale]" value="" placeholder="<?php echo esc_attr( $r['sale'] ); ?>" dir="ltr" />
							</td>
							<td>
								<input type="text" class="small-text" name="wbe_row[<?php echo (int) $r['id']; ?>][from]" value="" placeholder="<?php echo esc_attr( $r['from_fa'] ? $r['from_fa'] : $ph_date ); ?>" dir="ltr" />
								<input type="text" class="small-text" name="wbe_row[<?php echo (int) $r['id']; ?>][to]" value="" placeholder="<?php echo esc_attr( $r['to_fa'] ? $r['to_fa'] : $ph_date ); ?>" dir="ltr" />
							</td>
							<td><?php echo $r['expiry'] ? esc_html( $r['expiry'] ) : '—'; ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<p class="wbe-muted">برای مقادیر متفاوت در هر محصول، فیلد همان ردیف را پر کنید و «اعمال ردیف‌های پرشده» را بزنید. برای یک تغییر روی چند محصول، تیک بزنید و نوار بالا را پر کنید.</p>
	</form>
</div>
