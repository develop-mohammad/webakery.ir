<?php
defined( 'ABSPATH' ) || exit;
$placeholder = ( 'jalali' === $effective ) ? '۱۴۰۵/۰۶/۰۵' : '2026/08/27';
?>
<div class="wbe-product-panel" id="wbe-product-panel" dir="rtl">
	<p class="form-field wbe-calendar-field">
		<label for="wbe_calendar">تقویم تاریخ انقضا</label>
		<select id="wbe_calendar" name="wbe_calendar">
			<option value="" <?php selected( $override, '' ); ?>>پیش‌فرض افزونه (<?php echo 'jalali' === $global ? 'شمسی' : 'میلادی'; ?>)</option>
			<option value="jalali" <?php selected( $override, 'jalali' ); ?>>شمسی</option>
			<option value="gregorian" <?php selected( $override, 'gregorian' ); ?>>میلادی</option>
		</select>
		<span class="description">فقط روی همین محصول اعمال می‌شود. اگر خالی باشد همان تنظیمات افزونه است.</span>
	</p>

	<?php wp_nonce_field( 'wbe_save_batches', 'wbe_batches_nonce' ); ?>

	<div class="wbe-batches">
		<div class="wbe-batches__head">
			<strong>قیمت رزرو، تخفیف، موجودی و تاریخ انقضا</strong>
			<button type="button" class="button wbe-add-batch">+ افزودن بچ</button>
		</div>
		<p class="description">مشتری فقط قیمت (با تخفیف) و انقضای بچ فعال را می‌بیند. درصد تخفیف روی همان قیمت بچ اعمال می‌شود.</p>
		<table class="widefat wbe-batches-table">
			<thead>
				<tr>
					<th>قیمت</th>
					<th>تخفیف ٪</th>
					<th>موجودی</th>
					<th>تاریخ انقضا</th>
					<th></th>
				</tr>
			</thead>
			<tbody id="wbe-batches-body">
				<?php
				if ( empty( $batches ) ) {
					$batches = array(
						array(
							'id'       => '',
							'price'    => '',
							'discount' => '',
							'stock'    => '',
							'expiry'   => '',
						),
					);
				}
				foreach ( $batches as $i => $b ) :
					$disp = ! empty( $b['expiry'] ) ? WBE_Jalali::format_ymd( $b['expiry'], $effective, false ) : '';
					$disc = isset( $b['discount'] ) && (int) $b['discount'] > 0 ? (int) $b['discount'] : '';
					?>
					<tr>
						<td>
							<input type="hidden" name="wbe_batches[<?php echo (int) $i; ?>][id]" value="<?php echo esc_attr( isset( $b['id'] ) ? $b['id'] : '' ); ?>" />
							<input type="text" class="short" name="wbe_batches[<?php echo (int) $i; ?>][price]" value="<?php echo esc_attr( isset( $b['price'] ) ? $b['price'] : '' ); ?>" placeholder="قیمت" dir="ltr" />
						</td>
						<td>
							<input type="number" class="short wbe-disc" min="0" max="100" step="1" name="wbe_batches[<?php echo (int) $i; ?>][discount]" value="<?php echo esc_attr( $disc ); ?>" placeholder="۰" dir="ltr" />
						</td>
						<td>
							<input type="number" class="short" min="0" step="1" name="wbe_batches[<?php echo (int) $i; ?>][stock]" value="<?php echo esc_attr( isset( $b['stock'] ) ? $b['stock'] : '' ); ?>" placeholder="موجودی" />
						</td>
						<td>
							<input type="text" class="short wbe-date" name="wbe_batches[<?php echo (int) $i; ?>][expiry]" value="<?php echo esc_attr( $disp ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" dir="ltr" />
						</td>
						<td>
							<button type="button" class="button-link wbe-remove-batch">حذف</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<table hidden>
			<tbody>
				<tr id="wbe-batch-tpl">
					<td>
						<input type="hidden" data-name="id" value="" />
						<input type="text" class="short" data-name="price" value="" placeholder="قیمت" dir="ltr" />
					</td>
					<td>
						<input type="number" class="short wbe-disc" min="0" max="100" step="1" data-name="discount" value="" placeholder="۰" dir="ltr" />
					</td>
					<td>
						<input type="number" class="short" min="0" step="1" data-name="stock" value="" placeholder="موجودی" />
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
