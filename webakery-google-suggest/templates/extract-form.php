<?php
defined( 'ABSPATH' ) || exit;
/** @var bool $licensed */
?>
<div class="wbgs-grid">
	<section class="wbgs-card">
		<h2>عبارت پایه</h2>
		<p class="wbgs-hint">مثل «کفش». ابزار همان عبارت را با فاصله و حروف الفبا از گوگل می‌پرسد؛ چیزی از خودش نمی‌سازد.</p>

		<label class="wbgs-label" for="wbgs-seed">عبارت</label>
		<input id="wbgs-seed" class="wbgs-input" type="text" dir="auto" placeholder="کفش" <?php disabled( ! $licensed ); ?> />
		<label class="wbgs-label" for="wbgs-seed-b">عبارت دوم برای مقایسه (اختیاری)</label>
		<input id="wbgs-seed-b" class="wbgs-input" type="text" dir="auto" placeholder="مثلاً کتانی" <?php disabled( ! $licensed ); ?> />

		<fieldset class="wbgs-modes" <?php disabled( ! $licensed ); ?>>
			<legend>روش‌ها</legend>
			<label><input type="checkbox" name="wbgs-mode" value="space" checked /> فاصله قبل و بعد</label>
			<label><input type="checkbox" name="wbgs-mode" value="alphabet" checked /> حرف‌گردانی فارسی (ا تا ی)</label>
			<label><input type="checkbox" name="wbgs-mode" value="latin" /> حروف انگلیسی a–z</label>
			<label><input type="checkbox" name="wbgs-mode" value="digits" /> ارقام ۰–۹</label>
			<label><input type="checkbox" name="wbgs-mode" value="modifiers" /> پیشوند و پسوند رایج (خرید، قیمت، چیست، ترین، شهر…)</label>
			<label><input type="checkbox" name="wbgs-mode" value="longtail" checked /> لانگ‌تیل (ادامهٔ همان عبارت‌ها در گوگل)</label>
		</fieldset>

		<div class="wbgs-actions">
			<button type="button" class="button button-primary" id="wbgs-start" <?php disabled( ! $licensed ); ?>>استخراج از گوگل</button>
			<button type="button" class="button" id="wbgs-stop" hidden>توقف</button>
		</div>

		<div class="wbgs-progress" id="wbgs-progress" hidden>
			<div class="wbgs-progress-bar" id="wbgs-progress-bar"></div>
			<p class="wbgs-progress-text" id="wbgs-progress-text"></p>
		</div>
		<p class="wbgs-status" id="wbgs-status" role="status"></p>
	</section>

	<section class="wbgs-card">
		<div class="wbgs-results-head">
			<h2>نتایج</h2>
			<span class="wbgs-count" id="wbgs-count">۰ عبارت</span>
		</div>
		<p class="wbgs-hint">دسته‌بندی پنج‌محوره: طول و حجم جستجو، قصد کاربر از جستجو، موقعیت جغرافیایی و زمان، مفهوم و ارتباط، نام برند. امتیاز رقابت نسبی از خودِ عبارت است (KD اهرفس نیست). عدد ماهانه فقط از Keyword Planner می‌آید؛ وگرنه «—» می‌ماند.</p>
		<?php
		$empty_text = 'هنوز چیزی استخراج نشده.';
		include WBGS_PATH . 'templates/results-chrome.php';
		?>
	</section>
</div>
