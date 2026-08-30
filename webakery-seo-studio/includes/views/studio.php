<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap wbss-wrap">
	<div class="wbss-app" dir="rtl" lang="fa">
		<aside class="wbss-nav" aria-label="بخش‌های سئو استودیو">
			<div class="wbss-brand">
				<strong>سئو استودیو</strong>
				<span>گزارش مصور لوکال</span>
			</div>
			<nav>
				<button type="button" class="wbss-nav-btn is-active" data-view="dashboard">نمای کلی</button>
				<button type="button" class="wbss-nav-btn" data-view="keywords">کیورد و رتبه</button>
				<button type="button" class="wbss-nav-btn" data-view="content">تولید محتوا</button>
				<button type="button" class="wbss-nav-btn" data-view="technical">سئو تکنیکال</button>
				<button type="button" class="wbss-nav-btn" data-view="backlinks">بک‌لینک</button>
				<button type="button" class="wbss-nav-btn" data-view="press">رپورتاژ</button>
				<button type="button" class="wbss-nav-btn" data-view="activity">گزارش اقدامات</button>
				<button type="button" class="wbss-nav-btn" data-view="settings">پروژه‌ها</button>
			</nav>
			<p class="wbss-nav-meta">webakery.ir · v<?php echo esc_html( WBSS_VERSION ); ?></p>
		</aside>

		<main class="wbss-main">
			<header class="wbss-bar">
				<div>
					<h1 id="wbss-title">نمای کلی</h1>
					<p class="wbss-sub" id="wbss-sub">هر اقدام سئو اینجا ثبت و نمودار می‌شود.</p>
				</div>
				<div class="wbss-bar-tools">
					<label class="wbss-field">
						<span>پروژه</span>
						<select id="wbss-project"></select>
					</label>
					<label class="wbss-field" id="wbss-days-wrap">
						<span>بازه</span>
						<select id="wbss-days">
							<option value="7">۷ روز</option>
							<option value="30" selected>۳۰ روز</option>
							<option value="90">۹۰ روز</option>
							<option value="180">۱۸۰ روز</option>
						</select>
					</label>
					<button type="button" class="button button-primary" id="wbss-add" hidden>ثبت مورد جدید</button>
					<button type="button" class="button" id="wbss-export">خروجی JSON</button>
				</div>
			</header>
			<div id="wbss-view" class="wbss-view"></div>
		</main>
	</div>

	<div class="wbss-modal" id="wbss-modal" hidden>
		<div class="wbss-modal-card" role="dialog" aria-modal="true">
			<header>
				<h2 id="wbss-modal-title">فرم</h2>
				<button type="button" class="wbss-icon-btn" id="wbss-modal-close" aria-label="بستن">×</button>
			</header>
			<form id="wbss-form"></form>
			<footer>
				<button type="button" class="button" id="wbss-modal-cancel">انصراف</button>
				<button type="submit" form="wbss-form" class="button button-primary" id="wbss-modal-save">ذخیره</button>
			</footer>
		</div>
	</div>
</div>
