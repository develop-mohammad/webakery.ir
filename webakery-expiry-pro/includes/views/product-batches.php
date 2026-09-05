<?php
defined( 'ABSPATH' ) || exit;
$placeholder = ( 'jalali' === $effective ) ? '۱۴۰۵/۰۶/۰۵' : '2026/08/27';
$today       = class_exists( 'WBE_Jalali' ) ? WBE_Jalali::today_ymd() : gmdate( 'Y-m-d' );
$active_idx  = ( ! empty( $batches ) && class_exists( 'WBE_Engine' ) ) ? WBE_Engine::active_index( $batches, $today ) : null;
$reserved_n  = ( ! empty( $batches ) && class_exists( 'WBE_Engine' ) ) ? WBE_Engine::reserved_stock( $batches, $today ) : 0;
?>
<div class="wbe-product-panel" id="wbe-product-panel" dir="rtl" data-wc-price="<?php echo esc_attr( isset( $wc_price ) ? $wc_price : '' ); ?>">
	<p class="form-field wbe-calendar-field">
		<label for="wbe_calendar">تقویم تاریخ انقضا</label>
		<select id="wbe_calendar" name="wbe_calendar">
			<option value="" <?php selected( $override, '' ); ?>>پیش‌فرض افزونه (<?php echo 'jalali' === $global ? 'شمسی' : 'میلادی'; ?>)</option>
			<option value="jalali" <?php selected( $override, 'jalali' ); ?>>شمسی</option>
			<option value="gregorian" <?php selected( $override, 'gregorian' ); ?>>میلادی</option>
		</select>
		<span class="description">فقط روی همین محصول اعمال می‌شود. اگر خالی باشد همان تنظیمات افزونه است.</span>
	</p>
	<p class="form-field wbe-countdown-field">
		<label for="wbe_hide_countdown">زمان تا پایان کمپین</label>
		<label class="wbe-inline-check">
			<input type="checkbox" id="wbe_hide_countdown" name="wbe_hide_countdown" value="1" <?php checked( ! empty( $hide_cd ) ); ?> />
			تایمر «مانده تا پایان کمپین» را برای این محصول نشان نده
		</label>
		<span class="description">یک تیک بزنید و محصول را ذخیره کنید. برای خاموش کردن روی همه محصولات: انقضای کالا ← تنظیمات.</span>
	</p>

	<?php wp_nonce_field( 'wbe_save_batches', 'wbe_batches_nonce' ); ?>

	<div class="wbe-batches">
		<div class="wbe-batches__head">
			<strong>بچ‌های قیمت، جشنواره، تخفیف، موجودی و انقضا</strong>
			<button type="button" class="button wbe-add-batch">+ افزودن بچ رزرو</button>
		</div>
		<p class="description">
			بچ <strong>فعال</strong> همان چیزی است که مشتری می‌بیند. بچ‌های بعدی <strong>رزرو</strong> هستند — موجودی، قیمت اصلی، قیمت جشنواره و درصد تخفیفشان را اینجا عوض کنید.
			جمع موجودی رزرو الان: <strong dir="ltr" id="wbe-reserved-total"><?php echo esc_html( (string) (int) $reserved_n ); ?></strong>.
			ویرایش چندتایی: <a href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-expiry-bulk' ) ); ?>">ویرایش گروهی</a>.
		</p>
		<table class="widefat wbe-batches-table">
			<thead>
				<tr>
					<th>وضعیت</th>
					<th>قیمت اصلی</th>
					<th>قیمت جشنواره</th>
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
							'price'    => isset( $wc_price ) ? $wc_price : '',
							'sale'     => '',
							'discount' => isset( $wc_disc ) ? $wc_disc : '',
							'stock'    => '',
							'expiry'   => '',
						),
					);
					$active_idx = 0;
				}
				foreach ( $batches as $i => $b ) :
					$disp     = ! empty( $b['expiry'] ) ? WBE_Jalali::format_ymd( $b['expiry'], $effective, false ) : '';
					$disc     = isset( $b['discount'] ) && (int) $b['discount'] > 0 ? (int) $b['discount'] : '';
					$price    = isset( $b['price'] ) ? $b['price'] : '';
					$sale_val = '';
					if ( isset( $b['sale'] ) && '' !== $b['sale'] && null !== $b['sale'] ) {
						$sale_val = $b['sale'];
					} elseif ( $disc && $price && class_exists( 'WBE_Engine' ) ) {
						$sale_val = WBE_Engine::sale_price( $price, (int) $disc );
					}
					$is_active = ( null !== $active_idx && (int) $i === (int) $active_idx );
					$row_cls   = $is_active ? 'wbe-batch-row is-active' : 'wbe-batch-row is-reserve';
					?>
					<tr class="<?php echo esc_attr( $row_cls ); ?>">
						<td>
							<span class="wbe-batch-status"><?php echo $is_active ? 'فعال' : 'رزرو'; ?></span>
							<input type="hidden" name="wbe_batches[<?php echo (int) $i; ?>][id]" value="<?php echo esc_attr( isset( $b['id'] ) ? $b['id'] : '' ); ?>" />
						</td>
						<td>
							<input type="text" class="short wbe-batch-price" name="wbe_batches[<?php echo (int) $i; ?>][price]" value="<?php echo esc_attr( $price ); ?>" placeholder="قیمت اصلی" dir="ltr" />
						</td>
						<td>
							<input type="text" class="short wbe-batch-sale" name="wbe_batches[<?php echo (int) $i; ?>][sale]" value="<?php echo esc_attr( (string) $sale_val ); ?>" placeholder="جشنواره" dir="ltr" />
						</td>
						<td>
							<input type="number" class="short wbe-disc" min="0" max="100" step="1" name="wbe_batches[<?php echo (int) $i; ?>][discount]" value="<?php echo esc_attr( (string) $disc ); ?>" placeholder="۰" dir="ltr" />
						</td>
						<td>
							<input type="number" class="short wbe-batch-stock" min="0" step="1" name="wbe_batches[<?php echo (int) $i; ?>][stock]" value="<?php echo esc_attr( isset( $b['stock'] ) ? $b['stock'] : '' ); ?>" placeholder="<?php echo $is_active ? 'موجودی فعال' : 'موجودی رزرو'; ?>" />
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
				<tr id="wbe-batch-tpl" class="wbe-batch-row is-reserve">
					<td>
						<span class="wbe-batch-status">رزرو</span>
						<input type="hidden" data-name="id" value="" />
					</td>
					<td>
						<input type="text" class="short wbe-batch-price" data-name="price" value="" placeholder="قیمت اصلی" dir="ltr" />
					</td>
					<td>
						<input type="text" class="short wbe-batch-sale" data-name="sale" value="" placeholder="جشنواره" dir="ltr" />
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
