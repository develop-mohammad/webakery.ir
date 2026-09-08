<?php
defined( 'ABSPATH' ) || exit;
$edition = ( defined( 'WBE_EDITION' ) && 'pro' === WBE_EDITION ) ? 'پرو' : 'رایگان';
$tg      = class_exists( 'WBE_Support' ) ? WBE_Support::telegram_handle() : 'HAJITODAY';
$tg_url  = class_exists( 'WBE_Support' ) ? WBE_Support::telegram_chat_url() : 'https://t.me/HAJITODAY';
?>
<div class="wrap wbe-wrap wbe-help-wrap" dir="rtl">
	<h1>راهنمای انقضای کالا <span class="wbe-ver"><?php echo esc_html( WBE_VERSION . ' — ' . $edition ); ?></span></h1>
	<p class="wbe-sub">سازنده: <a href="https://webakery.ir" target="_blank" rel="noopener">webakery.ir</a> — اگر باگی دیدید با دکمهٔ گزارش باگ برای <a href="<?php echo esc_url( $tg_url ); ?>" target="_blank" rel="noopener">@<?php echo esc_html( $tg ); ?></a> بفرستید.</p>

	<p>
		<button type="button" class="button button-primary wbe-bug-open">گزارش باگ با اسکرین و توضیح</button>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-expiry-bulk' ) ); ?>">ویرایش گروهی</a>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-expiry-settings' ) ); ?>">تنظیمات</a>
	</p>

	<div class="wbe-help-grid">
		<section class="wbe-help-card">
			<h2>۱. ایده اصلی</h2>
			<p>برای هر محصول (یا هر تنوع) چند <strong>بچ</strong> می‌گذارید: قیمت، تخفیف، موجودی و تاریخ انقضا.</p>
			<ul>
				<li><strong>موجودی فعال:</strong> نزدیک‌ترین انقضای دارای موجودی — همین روی فروشگاه دیده می‌شود.</li>
				<li><strong>موجودی رزرو:</strong> بچ‌های بعدی. وقتی فعال صفر یا منقضی شد، رزرو جایگزین می‌شود.</li>
			</ul>
		</section>

		<section class="wbe-help-card">
			<h2>۲. ویرایش تکی محصول</h2>
			<p>ووکامرس ← محصولات ← ویرایش. باکس «موجودی فعال» و «موجودی رزرو» کنار قیمت است.</p>
			<ul>
				<li>نام، قیمت، تخفیف، جشنواره، موجودی و انقضا را اینجا عوض کنید.</li>
				<li>SKU و وضعیت محصول را از همین صفحه ووکامرس یا از <strong>ویرایش گروهی</strong> عوض کنید.</li>
				<li>محصول متغیر: برای <strong>هر تنوع</strong> جداگانه بچ بگذارید (نه روی والد).</li>
			</ul>
		</section>

		<section class="wbe-help-card">
			<h2>۳. ویرایش گروهی (و رفع کندی)</h2>
			<p>بدون انتخاب <strong>برند</strong> هیچ محصولی لود نمی‌شود تا صفحه سنگین نشود.</p>
			<ol>
				<li>برند را انتخاب کنید و «اعمال فیلتر» را بزنید.</li>
				<li>در صورت نیاز دسته، وضعیت یا «دارای انقضا» را هم فیلتر کنید.</li>
				<li>برای دیدن رزرو، سوییچ «نمایش و ویرایش موجودی رزرو» را روشن کنید.</li>
				<li>سلول را عوض کنید و «ذخیره سلول‌های تغییرکرده» را بزنید، یا نوار بالا را پر کنید و روی انتخاب‌شده‌ها اعمال کنید.</li>
			</ol>
			<p class="description">ذخیره تکه‌تکه انجام می‌شود تا تایم‌اوت نگیرد. محصول متغیر در جدول به ردیف‌های تنوع شکسته می‌شود.</p>
		</section>

		<section class="wbe-help-card">
			<h2>۴. فروشگاه</h2>
			<ul>
				<li>تاریخ انقضا کنار محصول (از تنظیمات).</li>
				<li>تایمر «مانده تا پایان کمپین» اگر تخفیف و بازه جشنواره فعال باشد.</li>
				<li>محصول متغیر: بعد از انتخاب تنوع، انقضای همان تنوع نشان داده می‌شود.</li>
				<li>شورت‌کد: <code>[webakery_expiry]</code></li>
			</ul>
		</section>

		<section class="wbe-help-card">
			<h2>۵. گزارش باگ</h2>
			<p>اگر چیزی کند، تکراری یا غلط بود:</p>
			<ol>
				<li>دکمهٔ شناور «گزارش باگ» یا دکمهٔ بالای این صفحه را بزنید.</li>
				<li>توضیح بدهید چه کار کردید و چه دیدید.</li>
				<li>اسکرین همین صفحه را بگیرید یا فایل تصویر بچسبانید.</li>
				<li>ارسال، چت تلگرام <strong>@<?php echo esc_html( $tg ); ?></strong> را باز می‌کند؛ تصویر را همان‌جا پیوست کنید.</li>
			</ol>
		</section>
	</div>
</div>
