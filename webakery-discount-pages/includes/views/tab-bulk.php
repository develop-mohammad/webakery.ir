<?php
defined( 'ABSPATH' ) || exit;
?>

<div class="wdp-grid wdp-grid-2">

	<div class="wdp-card-box">
		<h3>🏷️ اعمال تخفیف روی همه محصولات یک دسته‌بندی</h3>
		<p class="wdp-hint-block">
			به‌جای اینکه تک‌تک محصولات را باز کنید و «قیمت حراج» بگذارید، یک دسته‌بندی را انتخاب کنید؛
			افزونه خودش برای همه محصولات آن دسته «قیمت حراج» ووکامرس را تنظیم می‌کند و بلافاصله
			آن‌ها را به صفحه تخفیف مناسب هم می‌فرستد.
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'wdp_bulk_apply' ); ?>
			<input type="hidden" name="action" value="wdp_bulk_apply">

			<p>
				<label class="wdp-label">دسته‌بندی محصول</label>
				<?php WDP_Taxonomy::render_category_checklist( array(), 'bulk_categories[]' ); ?>
			</p>

			<label class="wdp-check">
				<input type="hidden" name="include_children" value="0">
				<input type="checkbox" name="include_children" value="1" checked>
				<span>شامل زیردسته‌های همان دسته‌بندی‌ها هم بشود</span>
			</label>

			<p style="margin-top:14px">
				<label class="wdp-label">نوع تخفیف</label>
				<label style="margin-left:16px"><input type="radio" name="bulk_type" value="percent" checked> درصدی (٪)</label>
				<label><input type="radio" name="bulk_type" value="fixed"> مبلغ ثابت</label>
			</p>

			<p>
				<label class="wdp-label">مقدار تخفیف</label>
				<input type="text" name="bulk_value" dir="ltr" placeholder="مثلاً ۲۰" style="max-width:160px">
				<span class="wdp-hint">برای درصدی، عدد بین ۱ تا ۹۹ وارد کنید؛ برای مبلغ ثابت، مبلغ کسرشده را به تومان.</span>
			</p>

			<p class="wdp-range">
				<span style="flex:1;min-width:150px">
					<label class="wdp-label">تاریخ شروع (اختیاری)</label>
					<input type="date" name="date_from">
				</span>
				<span style="flex:1;min-width:150px">
					<label class="wdp-label">تاریخ پایان (اختیاری)</label>
					<input type="date" name="date_to">
				</span>
			</p>
			<p class="wdp-hint-block">
				اگر تاریخ بگذارید، تخفیف زمان‌بندی‌شده ووکامرس فعال می‌شود و خودش سر موعد شروع/تمام می‌شود
				(افزونه هم هر ساعت صفحه‌های تخفیف را با آن هماهنگ نگه می‌دارد). خالی بگذارید تا تخفیف
				همین الان و بدون تاریخ پایان فعال شود.
			</p>

			<label class="wdp-check">
				<input type="hidden" name="overwrite" value="0">
				<input type="checkbox" name="overwrite" value="1">
				<span>روی محصولاتی هم که از قبل تخفیف فعال دارند، این تخفیف را جایگزین کن</span>
			</label>

			<p><button type="submit" class="button button-primary" onclick="return confirm('این کار روی همه محصولات دسته‌بندی(های) انتخاب‌شده قیمت حراج تنظیم می‌کند. ادامه می‌دهید؟')">اعمال تخفیف روی این دسته‌بندی</button></p>
		</form>
	</div>

	<div class="wdp-card-box">
		<h3>↩️ حذف تخفیف از یک دسته‌بندی</h3>
		<p class="wdp-hint-block">
			برای برگرداندن محصولات یک دسته‌بندی به قیمت عادی (حذف قیمت حراج) از این فرم استفاده کنید.
			این کار فقط «قیمت حراج» را پاک می‌کند؛ قیمت اصلی محصول تغییر نمی‌کند.
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'wdp_bulk_revert' ); ?>
			<input type="hidden" name="action" value="wdp_bulk_revert">

			<p>
				<label class="wdp-label">دسته‌بندی محصول</label>
				<?php WDP_Taxonomy::render_category_checklist( array(), 'bulk_categories[]' ); ?>
			</p>

			<label class="wdp-check">
				<input type="hidden" name="include_children" value="0">
				<input type="checkbox" name="include_children" value="1" checked>
				<span>شامل زیردسته‌های همان دسته‌بندی‌ها هم بشود</span>
			</label>

			<p style="margin-top:14px">
				<button type="submit" class="button wdp-danger-btn" onclick="return confirm('تخفیف همه محصولات این دسته‌بندی(ها) حذف می‌شود. ادامه می‌دهید؟')">حذف تخفیف از این دسته‌بندی</button>
			</p>
		</form>

		<h3 style="margin-top:26px">نکات</h3>
		<ul class="wdp-guide">
			<li>تخفیف درصدی روی <strong>قیمت اصلی همان محصول</strong> حساب می‌شود؛ یعنی محصولات با قیمت‌های متفاوت هرکدام مبلغ متفاوتی کم می‌شوند.</li>
			<li>برای محصولات متغیر (رنگ/سایز)، تخفیف روی تک‌تک متغیرها بر اساس قیمت خودشان اعمال می‌شود.</li>
			<li>بعد از اعمال، این محصولات طبق معمول توسط موتور تشخیص به صفحه تخفیف مناسب (اگر ساخته باشید) اضافه می‌شوند.</li>
			<li>پیش‌فرض این است که روی محصولاتی که از قبل تخفیف دارند دست نمی‌زند، مگر گزینه «بازنویسی» را تیک بزنید.</li>
		</ul>
	</div>

</div>
