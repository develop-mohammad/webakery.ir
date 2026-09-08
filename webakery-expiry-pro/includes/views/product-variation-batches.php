<?php
defined( 'ABSPATH' ) || exit;
$placeholder = ( 'jalali' === $effective ) ? '۱۴۰۵/۰۶/۰۵' : '2026/08/27';
$today       = class_exists( 'WBE_Jalali' ) ? WBE_Jalali::today_ymd() : gmdate( 'Y-m-d' );
$loop        = isset( $loop ) ? (int) $loop : 0;
$prefix      = 'wbe_var[' . $loop . ']';
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
$attr_lbl = isset( $attr_label ) ? (string) $attr_label : '';
?>
<div class="wbe-product-panel wbe-variation-panel" dir="rtl" data-loop="<?php echo (int) $loop; ?>" data-wc-price="<?php echo esc_attr( isset( $wc_price ) ? $wc_price : '' ); ?>">
	<?php if ( 0 === $loop ) : ?>
		<?php wp_nonce_field( 'wbe_save_batches', 'wbe_batches_nonce' ); ?>
	<?php endif; ?>

	<p class="form-field wbe-calendar-field">
		<label for="wbe_var_<?php echo (int) $loop; ?>_calendar">تقویم تاریخ انقضا</label>
		<select id="wbe_var_<?php echo (int) $loop; ?>_calendar" name="<?php echo esc_attr( $prefix ); ?>[calendar]">
			<option value="" <?php selected( $override, '' ); ?>>پیش‌فرض / والد (<?php echo 'jalali' === $global ? 'شمسی' : 'میلادی'; ?>)</option>
			<option value="jalali" <?php selected( $override, 'jalali' ); ?>>شمسی</option>
			<option value="gregorian" <?php selected( $override, 'gregorian' ); ?>>میلادی</option>
		</select>
	</p>
	<p class="form-field wbe-countdown-field">
		<label class="wbe-inline-check">
			<input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[hide_countdown]" value="1" <?php checked( ! empty( $hide_cd ) ); ?> />
			تایمر کمپین را برای این تنوع نشان نده
		</label>
	</p>
	<?php if ( $attr_lbl ) : ?>
		<p class="description wbe-var-attrs"><strong>تنوع:</strong> <?php echo esc_html( $attr_lbl ); ?></p>
	<?php endif; ?>

	<div class="wbe-active-box">
		<div class="wbe-batches__head">
			<strong>موجودی فعال این تنوع</strong>
		</div>
		<p class="description">قیمت، موجودی و انقضای همین تنوع روی فروشگاه دیده می‌شود.</p>
		<div class="wbe-active-grid wbe-active-grid--variation">
			<p class="form-field">
				<label>SKU</label>
				<input type="text" class="wbe-var-sku" name="<?php echo esc_attr( $prefix ); ?>[sku]" value="<?php echo esc_attr( isset( $product_sku ) ? $product_sku : '' ); ?>" dir="ltr" />
			</p>
			<p class="form-field">
				<label>قیمت اصلی</label>
				<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[active][id]" value="<?php echo esc_attr( $a_id ); ?>" />
				<input type="text" class="wbe-batch-price" name="<?php echo esc_attr( $prefix ); ?>[active][price]" value="<?php echo esc_attr( (string) $a_price ); ?>" dir="ltr" />
			</p>
			<p class="form-field">
				<label>درصد تخفیف</label>
				<input type="number" class="wbe-disc" name="<?php echo esc_attr( $prefix ); ?>[active][discount]" min="0" max="100" step="1" value="<?php echo esc_attr( $a_disc_v ); ?>" dir="ltr" />
			</p>
			<p class="form-field">
				<label>قیمت جشنواره</label>
				<input type="text" class="wbe-batch-sale" name="<?php echo esc_attr( $prefix ); ?>[active][sale]" value="<?php echo esc_attr( (string) $a_sale ); ?>" dir="ltr" />
			</p>
			<p class="form-field">
				<label>شروع جشنواره</label>
				<input type="text" class="wbe-date" name="<?php echo esc_attr( $prefix ); ?>[sale_from]" value="<?php echo esc_attr( $sale_from_fa ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" dir="ltr" />
			</p>
			<p class="form-field">
				<label>پایان جشنواره</label>
				<input type="text" class="wbe-date" name="<?php echo esc_attr( $prefix ); ?>[sale_to]" value="<?php echo esc_attr( $sale_to_fa ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" dir="ltr" />
			</p>
			<p class="form-field">
				<label>موجودی</label>
				<input type="number" class="wbe-batch-stock" name="<?php echo esc_attr( $prefix ); ?>[active][stock]" min="0" step="1" value="<?php echo esc_attr( (string) $a_stock ); ?>" />
			</p>
			<p class="form-field">
				<label>تاریخ انقضا</label>
				<input type="text" class="wbe-date" name="<?php echo esc_attr( $prefix ); ?>[active][expiry]" value="<?php echo esc_attr( $a_expiry ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" dir="ltr" />
			</p>
		</div>
	</div>

	<div class="wbe-batches wbe-reserve-box">
		<div class="wbe-batches__head">
			<strong>موجودی رزرو این تنوع</strong>
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
			<tbody class="wbe-batches-body">
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
								<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[reserve][<?php echo (int) $i; ?>][id]" value="<?php echo esc_attr( isset( $b['id'] ) ? $b['id'] : '' ); ?>" />
								<input type="text" class="short wbe-batch-price" name="<?php echo esc_attr( $prefix ); ?>[reserve][<?php echo (int) $i; ?>][price]" value="<?php echo esc_attr( isset( $b['price'] ) ? $b['price'] : '' ); ?>" placeholder="قیمت اصلی" dir="ltr" />
							</td>
							<td>
								<input type="number" class="short wbe-disc" min="0" max="100" step="1" name="<?php echo esc_attr( $prefix ); ?>[reserve][<?php echo (int) $i; ?>][discount]" value="<?php echo esc_attr( (string) $disc ); ?>" placeholder="۰" dir="ltr" />
							</td>
							<td>
								<input type="number" class="short wbe-batch-stock" min="0" step="1" name="<?php echo esc_attr( $prefix ); ?>[reserve][<?php echo (int) $i; ?>][stock]" value="<?php echo esc_attr( isset( $b['stock'] ) ? $b['stock'] : '' ); ?>" placeholder="موجودی رزرو" />
							</td>
							<td>
								<input type="text" class="short wbe-date" name="<?php echo esc_attr( $prefix ); ?>[reserve][<?php echo (int) $i; ?>][expiry]" value="<?php echo esc_attr( $disp ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" dir="ltr" />
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
				<tr class="wbe-batch-tpl wbe-batch-row is-reserve">
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
						<input type="text" class="short wbe-date" data-name="expiry" value="" placeholder="<?php echo esc_attr( $placeholder ); ?>" dir="ltr" />
					</td>
					<td>
						<button type="button" class="button-link wbe-remove-batch">حذف</button>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</div>
