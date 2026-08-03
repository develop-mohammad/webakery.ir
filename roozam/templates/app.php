<?php
defined( 'ABSPATH' ) || exit;
/** @var string $title */
?>
<div class="rzm" data-rzm-root dir="rtl" lang="fa">
	<div class="rzm-shell">
		<header class="rzm-hero">
			<div class="rzm-brand">
				<span class="rzm-mark" aria-hidden="true"></span>
				<div>
					<p class="rzm-brand-name"><?php echo esc_html( $title ); ?></p>
					<p class="rzm-brand-tag">برنامه‌ریز روزانه</p>
				</div>
			</div>
			<div class="rzm-dateblock">
				<button type="button" class="rzm-navbtn" data-rzm-prev aria-label="روز قبل">‹</button>
				<div class="rzm-date-main">
					<strong data-rzm-weekday>—</strong>
					<span data-rzm-jalali>—</span>
				</div>
				<button type="button" class="rzm-navbtn" data-rzm-next aria-label="روز بعد">›</button>
			</div>
			<button type="button" class="rzm-cta" data-rzm-plan>
				<span class="rzm-cta-glow" aria-hidden="true"></span>
				برنامه‌ریزی امروز
			</button>
		</header>

		<section class="rzm-progress" aria-label="پیشرفت امروز">
			<div class="rzm-ring" data-rzm-ring>
				<svg viewBox="0 0 72 72" width="72" height="72" aria-hidden="true">
					<circle class="rzm-ring-bg" cx="36" cy="36" r="30"></circle>
					<circle class="rzm-ring-fg" cx="36" cy="36" r="30" data-rzm-ring-fg></circle>
				</svg>
				<span data-rzm-progress-text>۰٪</span>
			</div>
			<div class="rzm-progress-copy">
				<h2>پیشرفت امروز</h2>
				<p data-rzm-progress-sub>هنوز کاری ثبت نشده</p>
			</div>
			<div class="rzm-toolbar">
				<button type="button" class="rzm-ghost" data-rzm-open-task>+ کار جدید</button>
				<button type="button" class="rzm-ghost" data-rzm-open-routines>عادت‌ها</button>
				<button type="button" class="rzm-ghost" data-rzm-open-prefs>ساعات روز</button>
			</div>
		</section>

		<div class="rzm-alert" data-rzm-alert hidden></div>

		<section class="rzm-timeline" aria-label="برنامه روز">
			<div class="rzm-timeline-head">
				<h2>برنامه روز</h2>
				<span data-rzm-task-count></span>
			</div>
			<ul class="rzm-list" data-rzm-list></ul>
			<p class="rzm-empty" data-rzm-empty hidden>هنوز کاری برای امروز ندارید. یک کار اضافه کنید یا «برنامه‌ریزی امروز» را بزنید.</p>
		</section>

		<section class="rzm-note">
			<label for="rzm-note">یادداشت روز</label>
			<textarea id="rzm-note" data-rzm-note rows="3" placeholder="نکته، تمرکز، یا قول امروز…"></textarea>
		</section>
	</div>

	<dialog class="rzm-dialog" data-rzm-dialog-task>
		<form method="dialog" class="rzm-dialog-body" data-rzm-task-form>
			<h3>کار جدید</h3>
			<label>عنوان
				<input type="text" name="title" required maxlength="120" placeholder="مثلاً آماده‌سازی پیشنهاد مشتری" />
			</label>
			<div class="rzm-grid2">
				<label>مدت (دقیقه)
					<input type="number" name="duration" min="5" max="480" value="30" required />
				</label>
				<label>اولویت
					<select name="priority">
						<option value="high">مهم</option>
						<option value="medium" selected>عادی</option>
						<option value="low">کم‌اهمیت</option>
					</select>
				</label>
			</div>
			<div class="rzm-grid2">
				<label>ساعت شروع (اختیاری)
					<input type="time" name="start" />
				</label>
				<label>دسته
					<input type="text" name="category" maxlength="40" placeholder="کار / شخصی / سلامت" />
				</label>
			</div>
			<div class="rzm-dialog-actions">
				<button type="button" class="rzm-ghost" data-rzm-close>انصراف</button>
				<button type="submit" class="rzm-cta">افزودن</button>
			</div>
		</form>
	</dialog>

	<dialog class="rzm-dialog" data-rzm-dialog-routines>
		<div class="rzm-dialog-body">
			<h3>عادت‌های روزانه</h3>
			<p class="rzm-muted">عادت‌های فعال هر روز در برنامه‌ریزی هوشمند لحاظ می‌شوند.</p>
			<ul class="rzm-routine-list" data-rzm-routines></ul>
			<form class="rzm-routine-form" data-rzm-routine-form>
				<input type="text" name="title" placeholder="عادت جدید…" required maxlength="80" />
				<input type="number" name="duration" min="5" max="240" value="20" title="دقیقه" />
				<button type="submit" class="rzm-ghost">+</button>
			</form>
			<div class="rzm-dialog-actions">
				<button type="button" class="rzm-cta" data-rzm-close>بستن</button>
			</div>
		</div>
	</dialog>

	<dialog class="rzm-dialog" data-rzm-dialog-prefs>
		<form method="dialog" class="rzm-dialog-body" data-rzm-prefs-form>
			<h3>ساعات روز شما</h3>
			<div class="rzm-grid2">
				<label>بیدار شدن
					<input type="time" name="wake_time" required />
				</label>
				<label>خواب
					<input type="time" name="sleep_time" required />
				</label>
			</div>
			<label>استراحت بین کارها (دقیقه)
				<input type="number" name="break_minutes" min="0" max="60" />
			</label>
			<div class="rzm-dialog-actions">
				<button type="button" class="rzm-ghost" data-rzm-close>انصراف</button>
				<button type="submit" class="rzm-cta">ذخیره</button>
			</div>
		</form>
	</dialog>
</div>
