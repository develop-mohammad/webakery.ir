<?php
defined( 'ABSPATH' ) || exit;
$placeholder = ( 'jalali' === $effective ) ? '۱۴۰۵/۰۶/۰۵' : '2026/08/27';
$today       = class_exists( 'WBE_Jalali' ) ? WBE_Jalali::today_ymd() : gmdate( 'Y-m-d' );
$active_idx  = ( ! empty( $batches ) && class_exists( 'WBE_Engine' ) ) ? WBE_Engine::active_index( $batches, $today ) : null;
$active      = ( null !== $active_idx && isset( $batches[ $active_idx ] ) ) ? $batches[ $active_idx ] : null;
$reserves    = array();
if ( ! empty( $batches ) ) {
	foreach ( $batches as $i => $b ) {
		if ( null !== $active_idx && (int) $i === (int) $active_idx ) {
			continue;
		}
		$reserves[] = $b;
	}
}

$a_price = $active && isset( $active['price'] ) ? $active['price'] : ( isset( $wc_price ) ? $wc_price : '' );
$a_disc  = $active ? (int) WBE_Engine::discount_of( $active ) : ( isset( $wc_disc ) && '' !== $wc_disc ? (int) $wc_disc : 0 );
$a_sale  = '';
if ( $active ) {
	$a_sale = (string) WBE_Engine::effective_sale( $active );
} elseif ( ! empty( $wc_sale ) ) {
	$a_sale = (string) $wc_sale;
} elseif ( $a_disc > 0 && $a_price && class_exists( 'WBE_Engine' ) ) {
	$a_sale = (string) WBE_Engine::sale_price( $a_price, $a_disc );
}
$a_stock  = $active ? (int) $active['stock'] : ( isset( $wc_stock ) && '' !== $wc_stock && null !== $wc_stock ? (int) $wc_stock : '' );
$a_expiry = ( $active && ! empty( $active['expiry'] ) ) ? WBE_Jalali::format_ymd( $active['expiry'], $effective, false ) : '';
$a_id     = $active && isset( $active['id'] ) ? $active['id'] : '';
$a_disc_v = $a_disc > 0 ? (string) $a_disc : '';
?>
<div class="wbe-product-panel" id="wbe-product-panel" dir="rtl" data-calendar="<?php echo esc_attr( $effective ); ?>" data-wc-price="<?php echo esc_attr( isset( $wc_price ) ? $wc_price : '' ); ?>">
	<?php wp_nonce_field( 'wbe_save_batches', 'wbe_batches_nonce' ); ?>

	<p class="form-field wbe-calendar-field">
		<label for="wbe_calendar">تقویم تاریخ انقضا</label>
		<select id="wbe_calendar" name="wbe_calendar">
			<option value="" <?php selected( $override, '' ); ?>>پیش‌فرض افزونه (<?php echo 'jalali' === $global ? 'شمسی' : 'میلادی'; ?>)</option>
			<option value="jalali" <?php selected( $override, 'jalali' ); ?>>شمسی</option>
			<option value="gregorian" <?php selected( $override, 'gregorian' ); ?>>میلادی</option>
		</select>
	</p>
	<p class="form-field wbe-countdown-field">
		<label class="wbe-inline-check">
			<input type="checkbox" id="wbe_hide_countdown" name="wbe_hide_countdown" value="1" <?php checked( ! empty( $hide_cd ) ); ?> />
			تایمر «مانده تا پایان کمپین» را برای این محصول نشان نده
		</label>
	</p>

	<div class="wbe-active-box">
		<div class="wbe-batches__head">
			<strong>موجودی فعال (قابل ویرایش)</strong>
		</div>
		<p class="description">قیمت، موجودی و انقضای فعال روی فروشگاه دیده می‌شود. SKU و وضعیت را از ویرایش گروهی عوض کنید.</p>
		<div class="wbe-active-grid">
			<p class="form-field">
				<label for="wbe_name">۱. نام محصول</label>
				<input type="text" id="wbe_name" name="wbe_name" value="<?php echo esc_attr( $product_name ); ?>" />
			</p>
			<p class="form-field">
				<label for="wbe_active_price">۲. قیمت اصلی</label>
				<input type="hidden" name="wbe_active[id]" value="<?php echo esc_attr( $a_id ); ?>" />
				<input type="text" class="wbe-batch-price" id="wbe_active_price" name="wbe_active[price]" value="<?php echo esc_attr( (string) $a_price ); ?>" dir="ltr" />
			</p>
			<p class="form-field">
				<label for="wbe_active_discount">۳. درصد تخفیف</label>
				<input type="number" class="wbe-disc" id="wbe_active_discount" name="wbe_active[discount]" min="0" max="100" step="1" value="<?php echo esc_attr( $a_disc_v ); ?>" dir="ltr" />
			</p>
			<p class="form-field">
				<label for="wbe_active_sale">۴. قیمت جشنواره</label>
				<input type="text" class="wbe-batch-sale" id="wbe_active_sale" name="wbe_active[sale]" value="<?php echo esc_attr( (string) $a_sale ); ?>" dir="ltr" />
			</p>
			<p class="form-field">
				<label for="wbe_sale_from">۵. زمان شروع جشنواره</label>
				<input type="text" class="wbe-date" id="wbe_sale_from" name="wbe_sale_from" value="<?php echo esc_attr( $sale_from_fa ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" dir="ltr" autocomplete="off" />
			</p>
			<p class="form-field">
				<label for="wbe_sale_to">۶. زمان پایان جشنواره</label>
				<input type="text" class="wbe-date" id="wbe_sale_to" name="wbe_sale_to" value="<?php echo esc_attr( $sale_to_fa ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" dir="ltr" autocomplete="off" />
			</p>
			<p class="form-field">
				<label for="wbe_active_stock">۷. موجودی</label>
				<input type="number" class="wbe-batch-stock" id="wbe_active_stock" name="wbe_active[stock]" min="0" step="1" value="<?php echo esc_attr( (string) $a_stock ); ?>" />
			</p>
			<p class="form-field">
				<label for="wbe_active_expiry">۸. تاریخ انقضا</label>
				<input type="text" class="wbe-date" id="wbe_active_expiry" name="wbe_active[expiry]" value="<?php echo esc_attr( $a_expiry ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" dir="ltr" autocomplete="off" />
			</p>
		</div>
	</div>

	<div class="wbe-batches wbe-reserve-box">
		<div class="wbe-batches__head">
			<strong>موجودی رزرو (قابل ویرایش)</strong>
			<button type="button" class="button wbe-add-batch">+ افزودن بچ رزرو</button>
		</div>
		<p class="description">برای هر بچ رزرو: قیمت اصلی، درصد تخفیف، موجودی، تاریخ انقضا.</p>
		<table class="widefat wbe-batches-table">
			<thead>
				<tr>
					<th>قیمت اصلی</th>
					<th>تخفیف ٪</th>
					<th>موجودی</th>
					<th>تاریخ انقضا</th>
					<th></th>
				</tr>
			</thead>
			<tbody id="wbe-batches-body" class="wbe-batches-body">
				<?php if ( empty( $reserves ) ) : ?>
					<tr class="wbe-batch-row is-reserve wbe-reserve-empty">
						<td colspan="5" class="wbe-muted">هنوز بچ رزرو ندارید — «افزودن بچ رزرو» را بزنید.</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $reserves as $i => $b ) :
						$disp = ! empty( $b['expiry'] ) ? WBE_Jalali::format_ymd( $b['expiry'], $effective, false ) : '';
						$disc = isset( $b['discount'] ) && (int) $b['discount'] > 0 ? (int) $b['discount'] : '';
						?>
						<tr class="wbe-batch-row is-reserve">
							<td>
								<input type="hidden" name="wbe_reserve[<?php echo (int) $i; ?>][id]" value="<?php echo esc_attr( isset( $b['id'] ) ? $b['id'] : '' ); ?>" />
								<input type="text" class="short wbe-batch-price" name="wbe_reserve[<?php echo (int) $i; ?>][price]" value="<?php echo esc_attr( isset( $b['price'] ) ? $b['price'] : '' ); ?>" placeholder="قیمت اصلی" dir="ltr" />
							</td>
							<td>
								<input type="number" class="short wbe-disc" min="0" max="100" step="1" name="wbe_reserve[<?php echo (int) $i; ?>][discount]" value="<?php echo esc_attr( (string) $disc ); ?>" placeholder="۰" dir="ltr" />
							</td>
							<td>
								<input type="number" class="short wbe-batch-stock" min="0" step="1" name="wbe_reserve[<?php echo (int) $i; ?>][stock]" value="<?php echo esc_attr( isset( $b['stock'] ) ? $b['stock'] : '' ); ?>" placeholder="موجودی رزرو" />
							</td>
							<td>
								<input type="text" class="short wbe-date" name="wbe_reserve[<?php echo (int) $i; ?>][expiry]" value="<?php echo esc_attr( $disp ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" dir="ltr" autocomplete="off" />
							</td>
							<td>
								<button type="button" class="button-link wbe-remove-batch">حذف</button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<table hidden>
			<tbody>
				<tr id="wbe-batch-tpl" class="wbe-batch-tpl wbe-batch-row is-reserve">
					<td>
						<input type="hidden" data-name="id" value="" />
						<input type="text" class="short wbe-batch-price" data-name="price" value="" placeholder="قیمت اصلی" dir="ltr" />
					</td>
					<td>
						<input type="number" class="short wbe-disc" min="0" max="100" step="1" data-name="discount" value="" placeholder="۰" dir="ltr" />
					</td>
					<td>
						<input type="number" class="short wbe-batch-stock" min="0" step="1" data-name="stock" value="" placeholder="موجودی رزرو" />
					</td>
					<td>
						<input type="text" class="short wbe-date" data-name="expiry" value="" placeholder="<?php echo esc_attr( $placeholder ); ?>" dir="ltr" autocomplete="off" />
					</td>
					<td>
						<button type="button" class="button-link wbe-remove-batch">حذف</button>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</div>
