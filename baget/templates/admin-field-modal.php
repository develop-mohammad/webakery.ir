<?php
defined( 'ABSPATH' ) || exit;
?>
<div id="wccp-field-modal" class="wccp-modal" hidden>
	<div class="wccp-modal-card" role="dialog" aria-modal="true">
		<h3 id="wccp-modal-title">فیلد سفارشی</h3>
		<form id="wccp-field-form">
			<input type="hidden" id="wccp-field-key" value="" />
			<label>عنوان فیلد
				<input type="text" id="wccp-field-label" class="widefat" required />
			</label>
			<label>نوع فیلد
				<select id="wccp-field-type" class="widefat">
					<option value="text">متنی</option>
					<option value="textarea">چندخطی</option>
					<option value="tel">تلفن</option>
					<option value="email">ایمیل</option>
					<option value="select">انتخابی (dropdown)</option>
					<option value="radio">رادیو (یک گزینه)</option>
					<option value="checkboxes">چندگزینه‌ای (checkbox)</option>
				</select>
			</label>
			<label id="wccp-field-options-wrap">گزینه‌ها <small class="wccp-muted">(هر خط یک گزینه)</small>
				<textarea id="wccp-field-options" class="widefat" rows="5" placeholder="گزینه ۱&#10;گزینه ۲&#10;گزینه ۳"></textarea>
			</label>
			<label class="wccp-check-row">
				<input type="checkbox" id="wccp-field-required" value="1" /> اجباری
			</label>
			<div class="wccp-modal-actions">
				<button type="button" class="button" id="wccp-modal-cancel">انصراف</button>
				<button type="submit" class="button button-primary">ذخیره فیلد</button>
			</div>
		</form>
	</div>
</div>
