<?php
defined( 'ABSPATH' ) || exit;
/** @var array $campaign */

$is_new     = empty( $campaign['id'] );
$tree       = WBCC_Admin::category_tree();
$selected   = WBCC_Campaigns::ids( $campaign['categories'] );
$excluded   = WBCC_Campaigns::ids( $campaign['exclude_categories'] );
$back_url   = admin_url( 'admin.php?page=' . WBCC_MENU . '&tab=campaigns' );

/** چک‌باکس با فیلد مخفی ۰ تا برداشتن تیک هم ذخیره شود */
$checkbox = function ( $name, $label, $checked, $hint = '' ) {
	printf(
		'<label class="wbcc-check"><input type="hidden" name="%1$s" value="0">'
		. '<input type="checkbox" name="%1$s" value="1" %2$s><span>%3$s</span>%4$s</label>',
		esc_attr( $name ),
		checked( ! empty( $checked ), true, false ),
		esc_html( $label ),
		$hint ? '<em class="wbcc-hint">' . esc_html( $hint ) . '</em>' : ''
	);
};
?>

<h2 class="wbcc-form-title"><?php echo $is_new ? 'کمپین تخفیف جدید' : 'ویرایش کمپین: ' . esc_html( $campaign['name'] ); ?></h2>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wbcc-form">
	<?php wp_nonce_field( 'wbcc_save_campaign' ); ?>
	<input type="hidden" name="action" value="wbcc_save_campaign">
	<input type="hidden" name="id" value="<?php echo (int) $campaign['id']; ?>">

	<div class="wbcc-grid">

		<!-- ستون ۱: پایه + دسته‌بندی -->
		<div class="wbcc-card-box">
			<h3>۱) نام و دسته‌بندی محصولات</h3>

			<p>
				<label class="wbcc-label">نام کمپین</label>
				<input type="text" name="name" class="regular-text" required
				       placeholder="مثلاً: تخفیف ویژه لوازم خانگی"
				       value="<?php echo esc_attr( $campaign['name'] ); ?>">
			</p>

			<?php $checkbox( 'enabled', 'کمپین فعال باشد', $campaign['enabled'] ); ?>

			<label class="wbcc-label">دسته‌بندی‌هایی که تخفیف روی آن‌ها اعمال می‌شود</label>
			<?php if ( ! $tree ) : ?>
				<p class="wbcc-muted">هیچ دسته‌بندی محصولی پیدا نشد. اول در ووکامرس دسته‌بندی بسازید.</p>
			<?php else : ?>
				<div class="wbcc-cat-tools">
					<input type="search" class="wbcc-cat-search" placeholder="جست‌وجوی دسته‌بندی…">
					<button type="button" class="button-link wbcc-cat-all">انتخاب همه</button>
					<button type="button" class="button-link wbcc-cat-none">هیچ‌کدام</button>
				</div>
				<div class="wbcc-cat-list" data-role="include">
					<?php foreach ( $tree as $term ) : ?>
						<label class="wbcc-cat" style="padding-right:<?php echo (int) $term['depth'] * 18; ?>px">
							<input type="checkbox" name="categories[]" value="<?php echo (int) $term['id']; ?>"
								<?php checked( in_array( $term['id'], $selected, true ) ); ?>>
							<span><?php echo esc_html( $term['name'] ); ?></span>
							<em>(<?php echo esc_html( WBCC_Date::fa_digits( $term['count'] ) ); ?> محصول)</em>
						</label>
					<?php endforeach; ?>
				</div>
				<?php $checkbox( 'include_children', 'زیردسته‌ها هم شامل شوند', $campaign['include_children'] ); ?>

				<details class="wbcc-details">
					<summary>دسته‌بندی‌های مستثنا (اختیاری)</summary>
					<div class="wbcc-cat-list wbcc-cat-list-sm">
						<?php foreach ( $tree as $term ) : ?>
							<label class="wbcc-cat" style="padding-right:<?php echo (int) $term['depth'] * 18; ?>px">
								<input type="checkbox" name="exclude_categories[]" value="<?php echo (int) $term['id']; ?>"
									<?php checked( in_array( $term['id'], $excluded, true ) ); ?>>
								<span><?php echo esc_html( $term['name'] ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</details>
			<?php endif; ?>
		</div>

		<!-- ستون ۲: مقدار تخفیف -->
		<div class="wbcc-card-box">
			<h3>۲) مقدار تخفیف</h3>

			<p>
				<label class="wbcc-label">نوع تخفیف</label>
				<select name="type" class="wbcc-type">
					<option value="percent" <?php selected( $campaign['type'], 'percent' ); ?>>درصدی روی محصولات دسته‌بندی</option>
					<option value="fixed_product" <?php selected( $campaign['type'], 'fixed_product' ); ?>>مبلغ ثابت روی هر محصول</option>
					<option value="fixed_cart" <?php selected( $campaign['type'], 'fixed_cart' ); ?>>مبلغ ثابت روی کل سبد</option>
				</select>
			</p>

			<div class="wbcc-range">
				<p>
					<label class="wbcc-label">از</label>
					<input type="number" name="min" step="0.01" min="0" value="<?php echo esc_attr( WBCC_Campaigns::trim_zeros( $campaign['min'] ) ); ?>">
				</p>
				<p>
					<label class="wbcc-label">تا</label>
					<input type="number" name="max" step="0.01" min="0" value="<?php echo esc_attr( WBCC_Campaigns::trim_zeros( $campaign['max'] ) ); ?>">
				</p>
				<p>
					<label class="wbcc-label">پله</label>
					<input type="number" name="step" step="0.01" min="0" value="<?php echo esc_attr( WBCC_Campaigns::trim_zeros( $campaign['step'] ) ); ?>">
				</p>
			</div>
			<p class="wbcc-hint-block">
				هر کد یک مقدار تصادفی در همین بازه می‌گیرد. مثال: از <code>۴۰</code> تا <code>۵۰</code> با پله <code>۵</code>
				یعنی کدها ۴۰٪، ۴۵٪ یا ۵۰٪ می‌شوند. پله <code>۰</code> = هر عددی در بازه.
				برای تخفیف ثابت، «از» و «تا» را برابر بگذارید.
			</p>

			<div class="wbcc-range">
				<p>
					<label class="wbcc-label">پیشوند کد</label>
					<input type="text" name="prefix" maxlength="12" dir="ltr" placeholder="OFF"
					       value="<?php echo esc_attr( $campaign['prefix'] ); ?>">
				</p>
				<p>
					<label class="wbcc-label">طول بخش تصادفی</label>
					<input type="number" name="code_length" min="4" max="20" value="<?php echo (int) $campaign['code_length']; ?>">
				</p>
			</div>
			<p class="wbcc-hint-block">نمونه کد: <code dir="ltr"><?php echo esc_html( ( $campaign['prefix'] ?: 'OFF' ) . '-A7K3M9' ); ?></code></p>
		</div>

		<!-- ستون ۳: قوانین مصرف -->
		<div class="wbcc-card-box">
			<h3>۳) قوانین مصرف</h3>

			<div class="wbcc-range">
				<p>
					<label class="wbcc-label">اعتبار (روز)</label>
					<input type="number" name="expires_days" min="0" max="3650" value="<?php echo (int) $campaign['expires_days']; ?>">
				</p>
				<p>
					<label class="wbcc-label">سقف مصرف هر کد</label>
					<input type="number" name="usage_limit" min="0" value="<?php echo (int) $campaign['usage_limit']; ?>">
				</p>
				<p>
					<label class="wbcc-label">سقف مصرف هر کاربر</label>
					<input type="number" name="usage_limit_per_user" min="0" value="<?php echo (int) $campaign['usage_limit_per_user']; ?>">
				</p>
			</div>
			<p class="wbcc-hint-block">عدد ۰ یعنی بدون محدودیت. اعتبار ۰ یعنی کد هرگز منقضی نمی‌شود.</p>

			<div class="wbcc-range">
				<p>
					<label class="wbcc-label">حداقل مبلغ سبد</label>
					<input type="text" name="min_spend" dir="ltr" value="<?php echo esc_attr( $campaign['min_spend'] ); ?>">
				</p>
				<p>
					<label class="wbcc-label">حداکثر مبلغ سبد</label>
					<input type="text" name="max_spend" dir="ltr" value="<?php echo esc_attr( $campaign['max_spend'] ); ?>">
				</p>
			</div>

			<?php
			$checkbox( 'individual_use', 'با کدهای دیگر جمع نشود', $campaign['individual_use'] );
			$checkbox( 'exclude_sale_items', 'روی محصولات حراج اعمال نشود', $campaign['exclude_sale_items'] );
			$checkbox( 'free_shipping', 'ارسال رایگان هم بدهد', $campaign['free_shipping'] );
			?>
		</div>

		<!-- ستون ۴: خودکارسازی -->
		<div class="wbcc-card-box">
			<h3>۴) ساخت خودکار و دریافت توسط مشتری</h3>

			<p>
				<label class="wbcc-label">تعداد کد در هر «ساخت دسته‌ای» دستی</label>
				<input type="number" name="batch_count" min="1" max="500" value="<?php echo (int) $campaign['batch_count']; ?>">
			</p>

			<?php $checkbox( 'auto_enabled', 'ساخت خودکار زمان‌بندی‌شده', $campaign['auto_enabled'] ); ?>
			<div class="wbcc-range">
				<p>
					<label class="wbcc-label">دوره</label>
					<select name="auto_interval">
						<?php foreach ( WBCC_Cron::intervals() as $key => $data ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $campaign['auto_interval'], $key ); ?>>
								<?php echo esc_html( $data['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<label class="wbcc-label">تعداد کد در هر دوره</label>
					<input type="number" name="auto_count" min="1" max="500" value="<?php echo (int) $campaign['auto_count']; ?>">
				</p>
			</div>

			<hr>

			<?php $checkbox( 'public_enabled', 'مشتری بتواند خودش کد بگیرد (شورت‌کد / ویجت المنتور)', $campaign['public_enabled'] ); ?>
			<p>
				<label class="wbcc-label">فاصله دریافت مجدد هر مشتری (ساعت)</label>
				<input type="number" name="public_cooldown" min="0" max="8760" value="<?php echo (int) $campaign['public_cooldown']; ?>">
			</p>
			<?php if ( ! $is_new ) : ?>
				<p class="wbcc-hint-block">
					شورت‌کد: <code dir="ltr">[webakery_coupon campaign="<?php echo (int) $campaign['id']; ?>"]</code>
				</p>
			<?php endif; ?>
		</div>

	</div>

	<p class="wbcc-submit">
		<button type="submit" class="button button-primary button-hero">ذخیره کمپین</button>
		<button type="submit" name="generate_now" value="1" class="button button-hero">ذخیره + ساخت فوری کدها</button>
		<a class="button" href="<?php echo esc_url( $back_url ); ?>">بازگشت</a>
	</p>
</form>
