<?php
defined( 'ABSPATH' ) || exit;
$features = NM_Pro::feature_list();
$presets = NM_Templates::presets();
?>
<div class="nm-panel-card nm-pro-hero">
	<h2>نوبت من پرو — ۵۹۹,۰۰۰ تومان</h2>
	<p>بدون محدودیت استفاده · اتصال لایسنس به <a href="https://webakery.ir" target="_blank" rel="noopener">webakery.ir</a></p>
	<?php if ( NM_Pro::is_active() ) : ?>
		<span class="nm-pill ok">✓ امکانات پرو روی این سایت فعال است</span>
	<?php else : ?>
		<a class="button button-primary button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=nobat-man&tab=license' ) ); ?>">فعال‌سازی لایسنس</a>
	<?php endif; ?>
	<ul class="nm-checklist">
		<?php foreach ( $features as $f ) : ?><li><?php echo esc_html( $f ); ?></li><?php endforeach; ?>
	</ul>
</div>

<div class="nm-grid-2">
	<div class="nm-panel-card">
		<h3>قالب‌های آماده</h3>
		<form method="post">
			<?php wp_nonce_field( 'nm_template' ); ?>
			<input type="hidden" name="nm_apply_template" value="1" />
			<select name="template" class="widefat">
				<?php foreach ( $presets as $k => $p ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $p['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<p><button class="button button-primary">اعمال قالب</button></p>
		</form>
	</div>

	<div class="nm-panel-card">
		<h3>بیزینس جدید</h3>
		<form method="post">
			<?php wp_nonce_field( 'nm_business' ); ?>
			<input type="hidden" name="nm_save_business" value="1" />
			<label>نام<input type="text" name="name" class="widefat" required /></label>
			<label>نوع
				<select name="type" class="widefat">
					<option value="consulting">مشاوره</option>
					<option value="villa">ویلا/خانه</option>
					<option value="clinic">کلینیک</option>
					<option value="other">سایر</option>
				</select>
			</label>
			<p><button class="button button-primary">ایجاد</button></p>
		</form>
		<?php $biz = NM_Business::all(); if ( $biz ) : ?>
			<ul><?php foreach ( $biz as $b ) : ?><li><?php echo esc_html( $b->name . ' (' . $b->type . ')' ); ?></li><?php endforeach; ?></ul>
		<?php endif; ?>
	</div>
</div>

<div class="nm-panel-card">
	<h3>قیمت‌گذاری متغیر</h3>
	<form method="post" class="nm-fields-admin">
		<?php wp_nonce_field( 'nm_pricing' ); ?>
		<input type="hidden" name="nm_save_pricing" value="1" />
		<label>روز هفته (۰=شنبه … خالی=همه)<input type="number" name="weekday" min="0" max="6" class="widefat" /></label>
		<label>تاریخ شمسی خاص<input type="text" name="jalali_date" class="widefat nm-jalali-input" placeholder="1404/07/01" /></label>
		<label>از ساعت<input type="time" name="start_time" /></label>
		<label>تا ساعت<input type="time" name="end_time" /></label>
		<label>قیمت (تومان)<input type="number" name="price" class="widefat" required /></label>
		<label>برچسب<input type="text" name="label" class="widefat" /></label>
		<p><button class="button button-primary">افزودن قانون قیمت</button></p>
	</form>
</div>

<div class="nm-panel-card">
	<h3>پرداخت قسطی / اشتراک</h3>
	<form method="post">
		<?php wp_nonce_field( 'nm_settings' ); ?>
		<input type="hidden" name="nm_save_settings" value="1" />
		<label><input type="checkbox" name="settings[enable_installments]" value="1" <?php checked( (int) NM_Settings::get( 'enable_installments' ) ); ?> /> فعال‌سازی قسط</label>
		<label>تعداد اقساط<input type="number" name="settings[installment_count]" value="<?php echo esc_attr( (int) NM_Settings::get( 'installment_count', 2 ) ); ?>" class="widefat" min="2" max="12" /></label>
		<p><button class="button">ذخیره</button></p>
	</form>
	<p class="nm-muted">پلن‌های اشتراک از طریق کلاس NM_Subscriptions قابل مدیریت هستند (ماهانه ۴ و ۸ جلسه).</p>
</div>
