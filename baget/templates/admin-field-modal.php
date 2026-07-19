<?php
defined( 'ABSPATH' ) || exit;
?>
<div id="wccp-field-modal" class="wccp-modal" hidden>
	<div class="wccp-modal-card wccp-modal-wide" role="dialog" aria-modal="true" aria-labelledby="wccp-modal-title">
		<div class="wccp-modal-head">
			<div>
				<h3 id="wccp-modal-title">ساخت سوال / فیلد</h3>
				<p class="wccp-muted" id="wccp-modal-help">مثل فرم‌ساز وردپرس: عنوان را بنویسید، نوع را انتخاب کنید، گزینه‌ها را اضافه کنید.</p>
			</div>
			<button type="button" class="wccp-icon-btn" id="wccp-modal-close" title="بستن">×</button>
		</div>

		<form id="wccp-field-form">
			<input type="hidden" id="wccp-field-key" value="" />
			<input type="hidden" id="wccp-field-is-default" value="0" />

			<div class="wccp-form-grid">
				<div class="wccp-form-main">
					<label class="wccp-field-block">
						<span class="wccp-label">۱) عنوان سوال / فیلد <em>*</em></span>
						<input type="text" id="wccp-field-label" class="widefat" placeholder="مثلاً: نحوه آشنایی با ما؟ / اطلاعات بیشتر سفارش" />
					</label>

					<div class="wccp-field-block" id="wccp-field-type-wrap">
						<span class="wccp-label">۲) نوع فیلد را انتخاب کنید</span>
						<input type="hidden" id="wccp-field-type" value="text" />
						<div class="wccp-type-grid" id="wccp-type-grid">
							<button type="button" class="wccp-type-card" data-type="text">
								<span class="wccp-type-ic">Aa</span>
								<strong>متنی</strong>
								<small>پاسخ کوتاه</small>
							</button>
							<button type="button" class="wccp-type-card" data-type="textarea">
								<span class="wccp-type-ic">¶</span>
								<strong>چندخطی</strong>
								<small>پاسخ بلند</small>
							</button>
							<button type="button" class="wccp-type-card" data-type="tel">
								<span class="wccp-type-ic">☎</span>
								<strong>تلفن</strong>
								<small>شماره تماس</small>
							</button>
							<button type="button" class="wccp-type-card" data-type="email">
								<span class="wccp-type-ic">@</span>
								<strong>ایمیل</strong>
								<small>آدرس ایمیل</small>
							</button>
							<button type="button" class="wccp-type-card" data-type="select">
								<span class="wccp-type-ic">▾</span>
								<strong>کشویی</strong>
								<small>یک گزینه از لیست</small>
							</button>
							<button type="button" class="wccp-type-card is-choice" data-type="radio">
								<span class="wccp-type-ic">◉</span>
								<strong>رادیو</strong>
								<small>سوال یک‌گزینه‌ای</small>
							</button>
							<button type="button" class="wccp-type-card is-choice" data-type="checkboxes">
								<span class="wccp-type-ic">☑</span>
								<strong>چندگزینه‌ای</strong>
								<small>چند گزینه همزمان</small>
							</button>
							<button type="button" class="wccp-type-card is-info" data-type="info">
								<span class="wccp-type-ic">ℹ</span>
								<strong>متن ساده</strong>
								<small>فقط اطلاع‌رسانی — بدون پر کردن</small>
							</button>
						</div>
					</div>

					<div class="wccp-field-block" id="wccp-field-options-wrap" hidden>
						<span class="wccp-label">۳) گزینه‌های پاسخ</span>
						<p class="wccp-help">هر ردیف یک گزینه است. دکمه «+ گزینه» بزنید. حداقل یک گزینه لازم است.</p>
						<div id="wccp-options-list" class="wccp-options-list"></div>
						<button type="button" class="button" id="wccp-add-option">+ افزودن گزینه</button>
						<textarea id="wccp-field-options" class="wccp-options-hidden" hidden rows="3"></textarea>
					</div>

					<div class="wccp-field-block" id="wccp-field-content-wrap" hidden>
						<label class="wccp-label" for="wccp-field-content">۳) متن اطلاع‌رسانی (اطلاعات بیشتر سفارش)</label>
						<p class="wccp-help">این متن فقط نمایش داده می‌شود؛ مشتری چیزی پر نمی‌کند.</p>
						<textarea id="wccp-field-content" class="widefat" rows="5" placeholder="مثلاً: پس از پرداخت، لینک دانلود تا ۱۵ دقیقه برای شما ایمیل می‌شود."></textarea>
					</div>

					<label class="wccp-check-row wccp-field-block" id="wccp-field-required-wrap">
						<input type="checkbox" id="wccp-field-required" value="1" />
						<span>این سوال / فیلد اجباری باشد</span>
					</label>
				</div>

				<aside class="wccp-form-side">
					<div class="wccp-preview-card">
						<div class="wccp-preview-title">پیش‌نمایش</div>
						<div id="wccp-live-preview" class="wccp-live-preview">
							<div class="wccp-preview-empty">عنوان و نوع را انتخاب کنید…</div>
						</div>
					</div>
					<div class="wccp-tips">
						<strong>راهنما</strong>
						<ul>
							<li><b>رادیو:</b> کاربر فقط یک گزینه انتخاب می‌کند.</li>
							<li><b>چندگزینه‌ای:</b> کاربر می‌تواند چند گزینه بزند.</li>
							<li><b>متن ساده:</b> فقط توضیح/اطلاع‌رسانی است و پر نمی‌شود.</li>
							<li>بعد از ذخیره، فیلد در ستون «فعال» می‌آید — صفحه checkout را رفرش کنید.</li>
						</ul>
					</div>
				</aside>
			</div>

			<div class="wccp-modal-actions">
				<button type="button" class="button" id="wccp-modal-cancel">انصراف</button>
				<button type="submit" class="button button-primary" id="wccp-modal-submit">ذخیره سوال / فیلد</button>
			</div>
		</form>
	</div>
</div>
