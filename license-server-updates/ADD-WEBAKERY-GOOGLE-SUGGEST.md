# افزودن محصول «سجست‌یاب گوگل» به سرور لایسنس

شناسه محصول (product slug): `webakery-google-suggest`

## ۱) ثبت محصول در `config.php` سرور لایسنس

در آرایه محصولات، این ورودی را اضافه کنید:

```php
'webakery-google-suggest' => [
	'name'         => 'سجست‌یاب گوگل',
	'price'        => 199000,          // تومان
	'version'      => '1.1.0',
	'file'         => 'webakery-google-suggest.zip',
	'homepage'     => 'https://webakery.ir/product/webakery-google-suggest/',
	'requires'     => '5.8',
	'requires_php' => '7.4',
	'tested'       => '6.7',
	'changelog'    => '<h4>1.1.0</h4><ul><li>شورت‌کد شبیه گوگل، اینتنت، درخت کیورد و پیلار کلاستر</li><li>سرچ ماهانه فقط از Keyword Planner</li></ul>',
],
```

## ۲) بارگذاری فایل به‌روزرسانی

فایل `webakery-google-suggest.zip` (از ریشه ریپو) را در پوشه `updates/`
سرور لایسنس بگذارید. آدرس دانلود همان چیزی است که کلاینت با
`action=update` می‌گیرد.

## ۳) بررسی

```
POST https://webakery.ir/license-server/api/?action=update
{ "product": "webakery-google-suggest", "version": "0.9.0", "domain": "example.com" }
```

پاسخ باید `success: true` و `version: 1.0.0` و `package` (لینک ZIP) داشته باشد.

## نکات

- کلاینت لایسنس داخل افزونه: `includes/class-wb-license.php`
- صفحه بازگشت پس از پرداخت: `admin.php?page=webakery-google-suggest&tab=license`
- دوره آزمایشی: ۷ روز. پس از پایان دوره، استخراج قفل می‌شود.
