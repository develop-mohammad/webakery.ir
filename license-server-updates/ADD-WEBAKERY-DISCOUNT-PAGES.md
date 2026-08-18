# افزودن محصول «صفحه‌های تخفیف هوشمند» به سرور لایسنس

شناسه محصول (product slug): `webakery-discount-pages`

## ۱) ثبت محصول در `config.php` سرور لایسنس

در آرایه محصولات، این ورودی را اضافه کنید:

```php
'webakery-discount-pages' => [
	'name'         => 'صفحه‌های تخفیف هوشمند',
	'price'        => 329000,          // تومان
	'version'      => '1.2.0',
	'file'         => 'webakery-discount-pages.zip',
	'homepage'     => 'https://webakery.ir/product/webakery-discount-pages/',
	'requires'     => '5.8',
	'requires_php' => '7.4',
	'tested'       => '6.7',
	'changelog'    => '<h4>1.2.0</h4><ul>'
		. '<li>اعمال گروهی تخفیف روی همه محصولات یک دسته‌بندی + بازگرداندن گروهی</li>'
		. '</ul><h4>1.1.1</h4><ul>'
		. '<li>جدول همه محصولات در حراج در پیشخوان</li>'
		. '</ul><h4>1.1.0</h4><ul>'
		. '<li>محدود کردن صفحه تخفیف به دسته‌بندی محصول</li>'
		. '<li>جابه‌جایی خودکار با تغییر دسته‌بندی محصول</li>'
		. '<li>ابزار عیب‌یابی محصول در پیشخوان</li>'
		. '</ul><h4>1.0.0</h4><ul><li>انتشار اولیه</li></ul>',
],
```

## ۲) بارگذاری فایل به‌روزرسانی

فایل `webakery-discount-pages.zip` (از ریشه ریپو) را در پوشه `updates/`
سرور لایسنس بگذارید. آدرس دانلود همان چیزی است که کلاینت با
`action=update` می‌گیرد.

## ۳) بررسی

```
POST https://webakery.ir/license-server/api/?action=update
{ "product": "webakery-discount-pages", "version": "0.9.0", "domain": "example.com" }
```

پاسخ باید `success: true` و `version: 1.0.0` و `package` (لینک ZIP) داشته باشد.

## نکات

- کلاینت لایسنس داخل افزونه: `includes/class-wb-license.php`
- صفحه بازگشت پس از پرداخت: `admin.php?page=webakery-discount-pages&tab=license`
- دوره آزمایشی: ۷ روز. پس از پایان دوره، اختصاص/جابه‌جایی خودکار محصولات
  به صفحه‌های تخفیف قفل می‌شود (صفحه‌ها و ترم‌های ساخته‌شده قبلی دست‌نخورده
  می‌مانند، ولی محصولات جدید یا تغییریافته دیگر خودکار جابه‌جا نمی‌شوند).
