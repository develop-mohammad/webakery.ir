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

$modes       = WBE_Admin_Bulk::mode_labels();
$statuses    = WBE_Admin_Bulk::status_labels();
$brands      = class_exists( 'WBE_Product' ) ? WBE_Product::brand_terms() : array();
$show_res    = class_exists( 'WBE_Settings' ) ? WBE_Settings::show_reserved_stock() : true;
$needs_brand = empty( $needs_brand ) ? ! WBE_Admin_Bulk::has_brand_filter( $filters ) : (bool) $needs_brand;
$ph_date     = ( 'jalali' === $calendar ) ? '۱۴۰۵/۰۶/۰۵' : '2026/08/27';
$action      = admin_url( 'admin-post.php' );
$count       = is_array( $rows ) ? count( $rows ) : 0;
$colspan     = 11;
$csv_url     = wp_nonce_url(
	add_query_arg(
		array(
			'action'     => 'wbe_bulk_csv',
			's'          => $filters['q'],
			'wbe_cat'    => $filters['category'],
			'wbe_scope'  => $filters['scope'],
			'wbe_status' => $filters['status'],
			'wbe_brand'  => $filters['brand'],
		),
		admin_url( 'admin-post.php' )
	),
	'wbe_bulk_csv'
);
?>
<div class="wrap wbe-wrap wbe-bulk-wrap" dir="rtl" data-calendar="<?php echo esc_attr( $calendar ); ?>">
	<h1>ویرایش گروهی محصول</h1>
	<p class="wbe-sub">ابتدا <strong>برند</strong> را انتخاب کنید. ستون‌های <strong>موجودی فعال</strong> همیشه هستند؛ ستون‌های <strong>موجودی رزرو</strong> با سوییچ پایین روشن/خاموش می‌شوند و تماماً قابل ویرایش‌اند.</p>

	<div id="wbe-bulk-notice" hidden class="notice is-dismissible"></div>
	<?php if ( $updated || $skipped ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $updated ); ?> محصول به‌روز شد<?php echo $skipped ? ' — ' . (int) $skipped . ' محصول رد شد' : ''; ?>.</p></div>
	<?php endif; ?>
	<?php if ( ! empty( $empty ) ) : ?>
		<div class="notice notice-warning is-dismissible"><p>چیزی برای اعمال نبود. محصول را تیک بزنید یا سلولی را عوض کنید.</p></div>
	<?php endif; ?>
	<?php if ( $needs_brand ) : ?>
		<div class="notice notice-info"><p>هنوز محصولی لود نشده. از فیلتر زیر یک <strong>برند</strong> انتخاب کنید و «اعمال فیلتر» را بزنید.</p></div>
	<?php endif; ?>

	<form method="get" class="wbe-filters">
		<input type="hidden" name="page" value="webakery-expiry-bulk" />
		<?php if ( $brands ) : ?>
			<select name="wbe_brand" class="wbe-brand-required" required>
				<option value="">— انتخاب برند (الزامی) —</option>
				<?php foreach ( $brands as $b ) : ?>
					<option value="<?php echo (int) $b->term_id; ?>" <?php selected( (string) $filters['brand'], (string) $b->term_id ); ?>><?php echo esc_html( $b->name ); ?></option>
				<?php endforeach; ?>
			</select>
		<?php else : ?>
			<input type="search" name="wbe_brand" value="<?php echo esc_attr( $filters['brand'] ); ?>" placeholder="برند (الزامی)" required />
		<?php endif; ?>
		<input type="search" name="s" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="جستجوی سرور: نام یا SKU" />
		<select name="wbe_scope">
			<option value="all" <?php selected( $filters['scope'], 'all' ); ?>>همه محصولات</option>
			<option value="batches" <?php selected( $filters['scope'], 'batches' ); ?>>دارای انقضا</option>
			<option value="plain" <?php selected( $filters['scope'], 'plain' ); ?>>بدون انقضا</option>
		</select>
		<select name="wbe_status">
			<option value="">همه وضعیت‌ها</option>
			<?php foreach ( $statuses as $st => $st_label ) : ?>
				<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $filters['status'], $st ); ?>><?php echo esc_html( $st_label ); ?></option>
			<?php endforeach; ?>
		</select>
		<select name="wbe_cat">
			<option value="0">همه دسته‌بندی‌ها</option>
			<?php foreach ( $cats as $c ) : ?>
				<option value="<?php echo (int) $c->term_id; ?>" <?php selected( $filters['category'], (int) $c->term_id ); ?>><?php echo esc_html( $c->name ); ?></option>
			<?php endforeach; ?>
		</select>
		<label class="wbe-check wbe-reserved-toggle">
			<input type="hidden" name="wbe_show_reserved" value="0" />
			<input type="checkbox" name="wbe_show_reserved" value="1" <?php checked( $show_res, true ); ?> />
			نمایش و ویرایش موجودی رزرو
		</label>
		<button type="submit" class="button button-primary">اعمال فیلتر</button>
		<?php if ( ! $needs_brand ) : ?>
			<a class="button" href="<?php echo esc_url( $csv_url ); ?>">خروجی CSV</a>
			<input type="search" id="wbe-bulk-live" placeholder="فیلتر آنی همین صفحه" />
			<span class="wbe-muted" id="wbe-bulk-count"><?php echo (int) $count; ?> محصول</span>
		<?php endif; ?>
	</form>

	<form method="post" action="<?php echo esc_url( $action ); ?>" class="wbe-bulk-form" id="wbe-bulk-form">
		<input type="hidden" name="action" value="wbe_bulk_apply" />
		<input type="hidden" name="s" value="<?php echo esc_attr( $filters['q'] ); ?>" />
		<input type="hidden" name="wbe_cat" value="<?php echo (int) $filters['category']; ?>" />
		<input type="hidden" name="wbe_scope" value="<?php echo esc_attr( $filters['scope'] ); ?>" />
		<input type="hidden" name="wbe_status" value="<?php echo esc_attr( $filters['status'] ); ?>" />
		<input type="hidden" name="wbe_brand" value="<?php echo esc_attr( $filters['brand'] ); ?>" />
		<?php wp_nonce_field( 'wbe_bulk' ); ?>

		<div class="wbe-bulk-toolbar">
			<div class="wbe-bulk-field">
				<label for="wbe_regular_mode">قیمت اصلی (فعال)</label>
				<select id="wbe_regular_mode" name="wbe_regular_mode" class="wbe-bulk-mode">
					<?php foreach ( $modes as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="text" name="wbe_regular_value" class="wbe-bulk-value" placeholder="مبلغ یا درصد" dir="ltr" />
			</div>
			<div class="wbe-bulk-field">
				<label for="wbe_discount">تخفیف ٪ (فعال)</label>
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
				<label for="wbe_sale_from">شروع جشنواره</label>
				<input type="text" class="wbe-date" id="wbe_sale_from" name="wbe_sale_from" placeholder="<?php echo esc_attr( $ph_date ); ?>" dir="ltr" autocomplete="off" />
			</div>
			<div class="wbe-bulk-field">
				<label for="wbe_sale_to">پایان جشنواره</label>
				<input type="text" class="wbe-date" id="wbe_sale_to" name="wbe_sale_to" placeholder="<?php echo esc_attr( $ph_date ); ?>" dir="ltr" autocomplete="off" />
			</div>
			<div class="wbe-bulk-field">
				<label for="wbe_stock_mode">موجودی فعال</label>
				<select id="wbe_stock_mode" name="wbe_stock_mode" class="wbe-bulk-mode">
					<?php foreach ( $modes as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="text" name="wbe_stock_value" class="wbe-bulk-value" placeholder="تعداد" dir="ltr" />
			</div>
			<div class="wbe-bulk-field">
				<label for="wbe_expiry">انقضای فعال</label>
				<input type="text" class="wbe-date" id="wbe_expiry" name="wbe_expiry" placeholder="<?php echo esc_attr( $ph_date ); ?>" dir="ltr" autocomplete="off" />
			</div>
			<div class="wbe-bulk-field">
				<label for="wbe_set_status">وضعیت</label>
				<select id="wbe_set_status" name="wbe_set_status">
					<option value="">بدون تغییر</option>
					<?php foreach ( $statuses as $st => $st_label ) : ?>
						<option value="<?php echo esc_attr( $st ); ?>"><?php echo esc_html( $st_label ); ?></option>
					<?php endforeach; ?>
				</select>
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
			<table class="widefat striped wbe-report wbe-bulk-table<?php echo $show_res ? ' wbe-bulk-table--with-reserve' : ''; ?>">
				<thead>
					<tr>
						<td class="check-column"><input type="checkbox" id="wbe-bulk-check-all" /></td>
						<th class="wbe-th-active">نام محصول</th>
						<th class="wbe-th-active">SKU</th>
						<th class="wbe-th-active">وضعیت</th>
						<th class="wbe-th-active">قیمت اصلی</th>
						<th class="wbe-th-active">تخفیف ٪</th>
						<th class="wbe-th-active">قیمت جشنواره</th>
						<th class="wbe-th-active">شروع جشنواره</th>
						<th class="wbe-th-active">پایان جشنواره</th>
						<th class="wbe-th-active">موجودی</th>
						<th class="wbe-th-active">تاریخ انقضا</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $needs_brand ) : ?>
						<tr><td colspan="<?php echo (int) $colspan; ?>">برای لود محصولات ابتدا برند را انتخاب کنید.</td></tr>
					<?php elseif ( empty( $rows ) ) : ?>
						<tr><td colspan="<?php echo (int) $colspan; ?>">محصولی مطابق فیلتر پیدا نشد.</td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $r ) : ?>
							<?php
							$row_class = 'wbe-bulk-row';
							if ( empty( $r['has_batches'] ) ) {
								$row_class .= ' wbe-row--plain';
							} elseif ( empty( $r['has_active'] ) ) {
								$row_class .= ' wbe-row--empty';
							}
							$st       = isset( $statuses[ $r['status'] ] ) ? $r['status'] : 'publish';
							$reserves = ( $show_res && ! empty( $r['reserves'] ) && is_array( $r['reserves'] ) ) ? $r['reserves'] : array();
							$is_var   = ! empty( $r['is_variation'] );
							$edit_id  = ( $is_var && ! empty( $r['parent_id'] ) ) ? (int) $r['parent_id'] : (int) $r['id'];
							?>
							<tr class="<?php echo esc_attr( $row_class ); ?>" data-id="<?php echo (int) $r['id']; ?>" data-name="<?php echo esc_attr( $r['name'] . ' ' . $r['sku'] . ' ' . ( isset( $r['brand'] ) ? $r['brand'] : '' ) ); ?>">
								<th class="check-column">
									<input type="checkbox" class="wbe-bulk-id" name="ids[]" value="<?php echo (int) $r['id']; ?>" />
								</th>
								<td>
									<input type="text" class="wbe-cell-name" data-field="name" data-orig="<?php echo esc_attr( $r['name'] ); ?>" name="wbe_row[<?php echo (int) $r['id']; ?>][name]" value="<?php echo esc_attr( $r['name'] ); ?>" <?php echo $is_var ? 'readonly' : ''; ?> />
									<div class="wbe-muted">
										<?php if ( $is_var ) : ?>
											<span>تنوع</span> —
										<?php endif; ?>
										<a href="<?php echo esc_url( get_edit_post_link( $edit_id ) ); ?>">ویرایش محصول</a>
									</div>
									<?php if ( ! empty( $r['brand'] ) ) : ?>
										<div class="wbe-muted"><?php echo esc_html( $r['brand'] ); ?></div>
									<?php endif; ?>
									<?php if ( empty( $r['has_batches'] ) ) : ?>
										<div class="wbe-muted">بدون انقضا — تاریخ را پر کنید تا بچ ساخته شود</div>
									<?php elseif ( empty( $r['has_active'] ) ) : ?>
										<div class="wbe-muted">بدون بچ فعال</div>
									<?php endif; ?>
								</td>
								<td><input type="text" class="small-text wbe-cell-sku" data-field="sku" data-orig="<?php echo esc_attr( $r['sku'] ); ?>" name="wbe_row[<?php echo (int) $r['id']; ?>][sku]" value="<?php echo esc_attr( $r['sku'] ); ?>" dir="ltr" /></td>
								<td>
									<select class="wbe-cell-status" data-field="status" data-orig="<?php echo esc_attr( $st ); ?>" name="wbe_row[<?php echo (int) $r['id']; ?>][status]">
										<?php foreach ( $statuses as $opt => $opt_label ) : ?>
											<option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $st, $opt ); ?>><?php echo esc_html( $opt_label ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
								<td><input type="text" class="small-text" data-field="regular" data-orig="<?php echo esc_attr( $r['regular'] ); ?>" name="wbe_row[<?php echo (int) $r['id']; ?>][regular]" value="<?php echo esc_attr( $r['regular'] ); ?>" dir="ltr" /></td>
								<td><input type="text" class="small-text" data-field="discount" data-orig="<?php echo esc_attr( (string) $r['discount'] ); ?>" name="wbe_row[<?php echo (int) $r['id']; ?>][discount]" value="<?php echo esc_attr( (string) $r['discount'] ); ?>" dir="ltr" /></td>
								<td><input type="text" class="small-text" data-field="sale" data-orig="<?php echo esc_attr( $r['sale'] ); ?>" name="wbe_row[<?php echo (int) $r['id']; ?>][sale]" value="<?php echo esc_attr( $r['sale'] ); ?>" dir="ltr" /></td>
								<td><input type="text" class="small-text wbe-date" data-field="from" data-orig="<?php echo esc_attr( $r['from_fa'] ); ?>" name="wbe_row[<?php echo (int) $r['id']; ?>][from]" value="<?php echo esc_attr( $r['from_fa'] ); ?>" placeholder="<?php echo esc_attr( $ph_date ); ?>" dir="ltr" autocomplete="off" /></td>
								<td><input type="text" class="small-text wbe-date" data-field="to" data-orig="<?php echo esc_attr( $r['to_fa'] ); ?>" name="wbe_row[<?php echo (int) $r['id']; ?>][to]" value="<?php echo esc_attr( $r['to_fa'] ); ?>" placeholder="<?php echo esc_attr( $ph_date ); ?>" dir="ltr" autocomplete="off" /></td>
								<td><input type="text" class="small-text" data-field="stock" data-orig="<?php echo esc_attr( (string) $r['stock'] ); ?>" name="wbe_row[<?php echo (int) $r['id']; ?>][stock]" value="<?php echo esc_attr( (string) $r['stock'] ); ?>" dir="ltr" /></td>
								<td><input type="text" class="small-text wbe-date" data-field="expiry" data-orig="<?php echo esc_attr( $r['expiry_fa'] ); ?>" name="wbe_row[<?php echo (int) $r['id']; ?>][expiry]" value="<?php echo esc_attr( $r['expiry_fa'] ); ?>" placeholder="<?php echo esc_attr( $ph_date ); ?>" dir="ltr" autocomplete="off" /></td>
							</tr>
							<?php if ( $show_res ) : ?>
								<tr class="wbe-bulk-reserves-row" data-parent-id="<?php echo (int) $r['id']; ?>">
									<td colspan="<?php echo (int) $colspan; ?>">
										<div class="wbe-reserves-inline">
											<div class="wbe-reserves-inline__head">
												<strong>بچ‌های رزرو</strong>
												<span class="wbe-muted"><?php echo count( $reserves ); ?> مورد</span>
												<button type="button" class="button button-small wbe-bulk-add-reserve" data-id="<?php echo (int) $r['id']; ?>">+ افزودن رزرو</button>
											</div>
											<table class="wbe-reserves-mini">
												<thead>
													<tr>
														<th>#</th>
														<th>قیمت اصلی</th>
														<th>تخفیف ٪</th>
														<th>موجودی</th>
														<th>تاریخ انقضا</th>
														<th></th>
													</tr>
												</thead>
												<tbody class="wbe-reserves-body" data-id="<?php echo (int) $r['id']; ?>">
													<?php if ( empty( $reserves ) ) : ?>
														<tr class="wbe-reserve-empty-hint"><td colspan="6" class="wbe-muted">رزروی نیست — «افزودن رزرو» را بزنید یا از نوار بالا بچ رزرو اضافه کنید.</td></tr>
													<?php else : ?>
														<?php foreach ( $reserves as $ri => $rb ) : ?>
															<tr class="wbe-reserve-mini-row">
																<td><?php echo (int) $ri + 1; ?></td>
																<td>
																	<input type="hidden" name="wbe_row[<?php echo (int) $r['id']; ?>][reserves][<?php echo (int) $ri; ?>][id]" value="<?php echo esc_attr( isset( $rb['id'] ) ? $rb['id'] : '' ); ?>" />
																	<input type="text" class="small-text" data-field="reserves.<?php echo (int) $ri; ?>.price" data-orig="<?php echo esc_attr( isset( $rb['price'] ) ? $rb['price'] : '' ); ?>" name="wbe_row[<?php echo (int) $r['id']; ?>][reserves][<?php echo (int) $ri; ?>][price]" value="<?php echo esc_attr( isset( $rb['price'] ) ? $rb['price'] : '' ); ?>" dir="ltr" />
																</td>
																<td>
																	<input type="text" class="small-text" data-field="reserves.<?php echo (int) $ri; ?>.discount" data-orig="<?php echo esc_attr( (string) ( isset( $rb['discount'] ) ? $rb['discount'] : '' ) ); ?>" name="wbe_row[<?php echo (int) $r['id']; ?>][reserves][<?php echo (int) $ri; ?>][discount]" value="<?php echo esc_attr( (string) ( isset( $rb['discount'] ) ? $rb['discount'] : '' ) ); ?>" dir="ltr" />
																</td>
																<td>
																	<input type="text" class="small-text" data-field="reserves.<?php echo (int) $ri; ?>.stock" data-orig="<?php echo esc_attr( (string) ( isset( $rb['stock'] ) ? $rb['stock'] : '' ) ); ?>" name="wbe_row[<?php echo (int) $r['id']; ?>][reserves][<?php echo (int) $ri; ?>][stock]" value="<?php echo esc_attr( (string) ( isset( $rb['stock'] ) ? $rb['stock'] : '' ) ); ?>" dir="ltr" />
																</td>
																<td>
																	<input type="text" class="small-text wbe-date" data-field="reserves.<?php echo (int) $ri; ?>.expiry" data-orig="<?php echo esc_attr( isset( $rb['expiry_fa'] ) ? $rb['expiry_fa'] : '' ); ?>" name="wbe_row[<?php echo (int) $r['id']; ?>][reserves][<?php echo (int) $ri; ?>][expiry]" value="<?php echo esc_attr( isset( $rb['expiry_fa'] ) ? $rb['expiry_fa'] : '' ); ?>" placeholder="<?php echo esc_attr( $ph_date ); ?>" dir="ltr" autocomplete="off" />
																</td>
																<td><button type="button" class="button-link wbe-bulk-remove-reserve">حذف</button></td>
															</tr>
														<?php endforeach; ?>
													<?php endif; ?>
												</tbody>
											</table>
										</div>
									</td>
								</tr>
							<?php endif; ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<p class="wbe-muted">موجودی فعال در ردیف اصلی است. با روشن بودن «نمایش رزرو»، همهٔ بچ‌های رزرو زیر همان محصول دیده و ویرایش می‌شوند (مثلاً ۳ رزرو با قیمت/موجودی/انقضای جدا).</p>
	</form>
</div>
