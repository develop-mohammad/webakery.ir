<?php
defined( 'ABSPATH' ) || exit;
/** @var string $empty_text */
$empty_text = isset( $empty_text ) ? $empty_text : 'هنوز چیزی استخراج نشده.';
?>
		<div class="wbgs-desk" id="wbgs-desk">
			<label class="wbgs-desk-label" for="wbgs-history">گزارش‌های ذخیره‌شده</label>
			<select id="wbgs-history" class="wbgs-select">
				<option value="">تاریخچه خالی است</option>
			</select>
			<button type="button" class="button" id="wbgs-load" disabled>باز کردن</button>
			<button type="button" class="button" id="wbgs-save" disabled>ذخیره گزارش</button>
			<button type="button" class="button" id="wbgs-compare-saved" disabled>مقایسه با این گزارش</button>
		</div>
		<p class="wbgs-usage" id="wbgs-usage" hidden></p>
		<div class="wbgs-actions">
			<button type="button" class="button" id="wbgs-view-list" disabled>عبارت‌ها</button>
			<button type="button" class="button" id="wbgs-view-tree" disabled>درخت کیورد</button>
			<button type="button" class="button" id="wbgs-view-cluster" disabled>پیلار کلاستر</button>
			<button type="button" class="button" id="wbgs-view-brief" disabled>بریف محتوا</button>
			<button type="button" class="button" id="wbgs-view-compare" disabled>مقایسه</button>
			<button type="button" class="button" id="wbgs-copy" disabled>کپی</button>
			<button type="button" class="button" id="wbgs-csv" disabled>CSV اکسل</button>
			<button type="button" class="button" id="wbgs-txt" disabled>TXT</button>
			<button type="button" class="button" id="wbgs-brief-txt" disabled>دانلود بریف</button>
		</div>
		<div class="wbgs-intent-filters" id="wbgs-intent-filters" hidden></div>
		<ol class="wbgs-list" id="wbgs-list"></ol>
		<div class="wbgs-ktree" id="wbgs-tree" hidden></div>
		<div class="wbgs-pillar" id="wbgs-cluster" hidden></div>
		<div class="wbgs-briefs" id="wbgs-brief" hidden></div>
		<div class="wbgs-compare" id="wbgs-compare" hidden></div>
		<p class="wbgs-empty" id="wbgs-empty"><?php echo esc_html( $empty_text ); ?></p>
