<?php
defined( 'ABSPATH' ) || exit;
/** @var bool $licensed */
?>
<div class="wbgs-g" data-wbgs-ui="google" dir="rtl">
	<div class="wbgs-g-hero" id="wbgs-hero">
		<div class="wbgs-g-logo">سجست‌یاب</div>
		<p class="wbgs-g-tag">همه پیشنهادهای واقعی گوگل برای یک عبارت</p>
		<div class="wbgs-g-box">
			<span class="wbgs-g-icon" aria-hidden="true">
				<svg width="20" height="20" viewBox="0 0 24 24"><path fill="#9aa0a6" d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 5 1.5-1.5-5-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
			</span>
			<input id="wbgs-seed" class="wbgs-g-input" type="text" dir="auto" placeholder="عبارت را بنویسید؛ مثلاً کفش" <?php disabled( ! $licensed ); ?> />
		</div>
		<input id="wbgs-seed-b" class="wbgs-g-input-b" type="text" dir="auto" placeholder="عبارت دوم برای مقایسه (اختیاری)" <?php disabled( ! $licensed ); ?> />
		<div class="wbgs-g-btns">
			<button type="button" class="wbgs-g-btn" id="wbgs-start" <?php disabled( ! $licensed ); ?>>نمایش همه عبارت‌ها</button>
			<button type="button" class="wbgs-g-btn" id="wbgs-stop" hidden>توقف</button>
		</div>
		<fieldset class="wbgs-modes wbgs-g-modes" <?php disabled( ! $licensed ); ?>>
			<legend>روش استخراج</legend>
			<label><input type="checkbox" name="wbgs-mode" value="space" checked /> فاصله</label>
			<label><input type="checkbox" name="wbgs-mode" value="alphabet" checked /> الفبا</label>
			<label><input type="checkbox" name="wbgs-mode" value="modifiers" checked /> پیشوند و پسوند</label>
			<label><input type="checkbox" name="wbgs-mode" value="longtail" checked /> لانگ‌تیل</label>
			<label><input type="checkbox" name="wbgs-mode" value="latin" /> a–z</label>
			<label><input type="checkbox" name="wbgs-mode" value="digits" /> ارقام</label>
		</fieldset>
		<div class="wbgs-progress" id="wbgs-progress" hidden>
			<div class="wbgs-progress-bar" id="wbgs-progress-bar"></div>
			<p class="wbgs-progress-text" id="wbgs-progress-text"></p>
		</div>
		<p class="wbgs-status" id="wbgs-status" role="status"></p>
	</div>

	<div class="wbgs-g-stage" id="wbgs-stage">
		<div class="wbgs-results-head">
			<h2>عبارت‌ها</h2>
			<span class="wbgs-count" id="wbgs-count">۰ عبارت</span>
		</div>
		<p class="wbgs-hint">ترند ایران از فید رسمی گوگل ترند (کشور IR) خوانده می‌شود؛ عدد آن حجم ماهانه نیست. سرچ ماهانه فقط با Keyword Planner.</p>
		<?php
		$empty_text = 'عبارت را بنویسید و دکمه را بزنید.';
		include WBGS_PATH . 'templates/results-chrome.php';
		?>
	</div>
</div>
