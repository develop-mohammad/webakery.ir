<?php
defined( 'ABSPATH' ) || exit;
$edition = ( defined( 'WBE_EDITION' ) && 'pro' === WBE_EDITION ) ? 'پرو' : 'رایگان';
?>
<div class="wrap wbe-wrap" dir="rtl">
	<h1>تنظیمات انقضای کالا <span class="wbe-ver"><?php echo esc_html( WBE_VERSION . ' — ' . $edition ); ?></span></h1>
	<p class="wbe-sub">سازنده: <a href="https://webakery.ir" target="_blank" rel="noopener">webakery.ir</a> — محمد حاجی مهدیخانی</p>

	<form method="post" action="options.php">
		<?php settings_fields( 'wbe_settings_group' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">تقویم پیش‌فرض همه محصولات</th>
				<td>
					<label><input type="radio" name="<?php echo esc_attr( WBE_Settings::OPTION ); ?>[calendar]" value="jalali" <?php checked( $s['calendar'], 'jalali' ); ?> /> شمسی</label>
					&nbsp;&nbsp;
					<label><input type="radio" name="<?php echo esc_attr( WBE_Settings::OPTION ); ?>[calendar]" value="gregorian" <?php checked( $s['calendar'], 'gregorian' ); ?> /> میلادی</label>
					<p class="description">هر محصول می‌تواند در ویرایش محصول این مقدار را جداگانه عوض کند.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">آستانه نزدیک به انقضا (روز)</th>
				<td>
					<input type="number" min="1" max="3650" name="<?php echo esc_attr( WBE_Settings::OPTION ); ?>[near_expiry_days]" value="<?php echo esc_attr( $s['near_expiry_days'] ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row">نمایش در فروشگاه</th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( WBE_Settings::OPTION ); ?>[show_near_price]" value="1" <?php checked( $s['show_near_price'], 1 ); ?> /> زیر قیمت محصول</label><br>
					<label><input type="checkbox" name="<?php echo esc_attr( WBE_Settings::OPTION ); ?>[show_in_description]" value="1" <?php checked( $s['show_in_description'], 1 ); ?> /> فیلد تاریخ انقضا در توضیحات محصول</label>
					<p class="description">محصولاتی که بچ برایشان ثبت نشده هیچ فیلدی نمی‌بینند.</p>
				</td>
			</tr>
		</table>
		<?php submit_button( 'ذخیره تنظیمات' ); ?>
	</form>
</div>
