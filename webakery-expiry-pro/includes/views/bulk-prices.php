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

$modes   = WBE_Admin_Bulk::mode_labels();
$ph_date = ( 'jalali' === $calendar ) ? '۱۴۰۵/۰۶/۰۵' : '2026/08/27';
$action  = admin_url( 'admin-post.php' );
$count   = is_array( $rows ) ? count( $rows ) : 0;
?>
<div class="wrap wbe-wrap wbe-bulk-wrap" dir="rtl">
	<h1>ویرایش گروهی محصول</h1>
	<p class="wbe-sub">قیمت اصلی، تخفیف، جشنواره، موجودی و انقضا در یک جدول. فقط بچ فعال عوض می‌شود. ذخیره تکه‌تکه است تا سایت سنگین نشود.</p>

	<div id="wbe-bulk-notice" hidden class="notice is-dismissible"></div>
	<?php if ( $updated || $skipped ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $updated ); ?> محصول به‌روز شد<?php echo $skipped ? ' — ' . (int) $skipped . ' محصول بدون بچ رد شد' : ''; ?>.</p></div>
	<?php endif; ?>
	<?php if ( ! empty( $empty ) ) : ?>
		<div class="notice notice-warning is-dismissible"><p>چیزی برای اعمال نبود. محصول را تیک بزنید یا سلولی را عوض کنید.</p></div>
	<?php endif; ?>

	<form method="get" class="wbe-filters">
		<input type="hidden" name="page" value="webakery-expiry-bulk" />
		<input type="search" name="s" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="جستجوی سرور: نام یا SKU" />
		<select name="wbe_cat">
			<option value="0">همه دسته‌بندی‌ها</option>
			<?php foreach ( $cats as $c ) : ?>
				<option value="<?php echo (int) $c->term_id; ?>" <?php selected( $filters['category'], (int) $c->term_id ); ?>><?php echo esc_html( $c->name ); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button">اعمال فیلتر</button>
		<input type="search" id="wbe-bulk-live" placeholder="فیلتر آنی همین صفحه" />
		<span class="wbe-muted" id="wbe-bulk-count"><?php echo (int) $count; ?> محصول</span>
	</form>

	<form method="post" action="<?php echo esc_url( $action ); ?>" class="wbe-bulk-form" id="wbe-bulk-form">
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
				<label for="wbe_discount">تخفیف ٪</label>
				<input type="text" id="wbe_discount" name="wbe_discount" placeholder="مثلاً ۲۰" dir="ltr" />
			</div>
			<div class="wbe-bulk-field">
				<label for="wbe_sale_mode">قیمت جشنواره</label>
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
			<div class="wbe-bulk-field">
				<label for="wbe_stock_mode">موجودی</label>
				<select id="wbe_stock_mode" name="wbe_stock_mode" class="wbe-bulk-mode">
					<?php foreach ( $modes as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="text" name="wbe_stock_value" class="wbe-bulk-value" placeholder="تعداد" dir="ltr" />
			</div>
			<div class="wbe-bulk-field">
				<label for="wbe_expiry">تاریخ انقضا</label>
				<input type="text" id="wbe_expiry" name="wbe_expiry" placeholder="<?php echo esc_attr( $ph_date ); ?>" dir="ltr" />
			</div>
			<div class="wbe-bulk-field">
				<label for="wbe_round">گرد کردن درصد</label>
				<select id="wbe_round" name="wbe_round">
					<option value="">بدون گرد کردن</option>
					<option value="round">نزدیک‌ترین</option>
					<option value="ceil">به بالا</option>
					<option value="floor">به پایین</option>
				</select>
			</div>
			<label class="wbe-check">
				<input type="checkbox" name="wbe_clear_sale" value="1" />
				حذف تخفیف و بازه جشنواره
			</label>
			<div class="wbe-bulk-actions">
				<button type="submit" class="button button-primary" name="wbe_bulk_mode" value="selected" id="wbe-bulk-apply-selected">اعمال روی انتخاب‌شده‌ها</button>
				<button type="submit" class="button" name="wbe_bulk_mode" value="rows" id="wbe-bulk-save-dirty">ذخیره سلول‌های تغییرکرده</button>
			</div>
		</div>

		<div class="wbe-bulk-progress" id="wbe-bulk-progress" hidden>
			<div class="wbe-bulk-progress__track"><div class="wbe-bulk-progress__bar" id="wbe-bulk-bar"></div></div>
			<span class="wbe-bulk-progress__txt" id="wbe-bulk-prog-txt"></span>
		</div>

		<div class="wbe-bulk-scroll">
			<table class="widefat striped wbe-report wbe-bulk-table">
				<thead>
					<tr>
						<td class="check-column"><input type="checkbox" id="wbe-bulk-check-all" /></td>
						<th>محصول</th>
						<th>قیمت اصلی</th>
						<th>تخفیف ٪</th>
						<th>قیمت جشنواره</th>
						<th>از</th>
						<th>تا</th>
						<th>موجودی</th>
						<th>انقضا</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="9">محصول تنظیم‌شده‌ای مطابق فیلتر پیدا نشد.</td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $r ) : ?>
							<tr class="wbe-bulk-row<?php echo empty( $r['has_active'] ) ? ' wbe-row--empty' : ''; ?>" data-id="<?php echo (int) $r['id']; ?>" data-name="<?php echo esc_attr( $r['name'] . ' ' . $r['sku'] ); ?>">
								<th class="check-column">
									<input type="checkbox" class="wbe-bulk-id" name="ids[]" value="<?php echo (int) $r['id']; ?>" />
								</th>
								<td>
									<a href="<?php echo esc_url( get_edit_post_link( $r['id'] ) ); ?>"><?php echo esc_html( $r['name'] ); ?></a>
									<?php if ( $r['sku'] ) : ?><div class="wbe-muted"><?php echo esc_html( $r['sku'] ); ?></div><?php endif; ?>
									<?php if ( empty( $r['has_active'] ) ) : ?><div class="wbe-muted">بدون بچ فعال</div><?php endif; ?>
								</td>
								<td><input type="text" class="small-text" data-field="regular" data-orig="<?php echo esc_attr( $r['regular'] ); ?>" name="wbe_row[<?php echo (int) $r['id']; ?>][regular]" value="<?php echo esc_attr( $r['regular'] ); ?>" dir="ltr" /></td>
								<td><input type="text" class="small-text" data-field="discount" data-orig="<?php echo esc_attr( (string) $r['discount'] ); ?>" name="wbe_row[<?php echo (int) $r['id']; ?>][discount]" value="<?php echo esc_attr( (string) $r['discount'] ); ?>" dir="ltr" /></td>
								<td><input type="text" class="small-text" data-field="sale" data-orig="<?php echo esc_attr( $r['sale'] ); ?>" name="wbe_row[<?php echo (int) $r['id']; ?>][sale]" value="<?php echo esc_attr( $r['sale'] ); ?>" dir="ltr" /></td>
								<td><input type="text" class="small-text" data-field="from" data-orig="<?php echo esc_attr( $r['from_fa'] ); ?>" name="wbe_row[<?php echo (int) $r['id']; ?>][from]" value="<?php echo esc_attr( $r['from_fa'] ); ?>" placeholder="<?php echo esc_attr( $ph_date ); ?>" dir="ltr" /></td>
								<td><input type="text" class="small-text" data-field="to" data-orig="<?php echo esc_attr( $r['to_fa'] ); ?>" name="wbe_row[<?php echo (int) $r['id']; ?>][to]" value="<?php echo esc_attr( $r['to_fa'] ); ?>" placeholder="<?php echo esc_attr( $ph_date ); ?>" dir="ltr" /></td>
								<td><input type="text" class="small-text" data-field="stock" data-orig="<?php echo esc_attr( (string) $r['stock'] ); ?>" name="wbe_row[<?php echo (int) $r['id']; ?>][stock]" value="<?php echo esc_attr( (string) $r['stock'] ); ?>" dir="ltr" /></td>
								<td><input type="text" class="small-text" data-field="expiry" data-orig="<?php echo esc_attr( $r['expiry_fa'] ); ?>" name="wbe_row[<?php echo (int) $r['id']; ?>][expiry]" value="<?php echo esc_attr( $r['expiry_fa'] ); ?>" placeholder="<?php echo esc_attr( $ph_date ); ?>" dir="ltr" /></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<p class="wbe-muted">سلول را مستقیم عوض کنید. Shift+کلیک برای انتخاب بازه. ذخیره فقط ردیف‌های تغییرکرده را تکه‌تکه می‌فرستد.</p>
	</form>
</div>
