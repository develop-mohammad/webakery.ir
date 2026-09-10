# افزودن محصول «دیدبانی» به سرور لایسنس

شناسه محصول (product slug): `didbani`

## ۱) ثبت محصول در `config.php` سرور لایسنس

در آرایه محصولات، این ورودی را اضافه کنید:

```php
'didbani' => [
	'name'         => 'دیدبانی',
	'price'        => 399000,          // تومان
	'version'      => '1.1.0',
	'file'         => 'didbani.zip',
	'homepage'     => 'https://webakery.ir/product/didbani/',
	'requires'     => '5.8',
	'requires_php' => '7.4',
	'tested'       => '6.7',
	'changelog'    => '<h4>1.1.0</h4><ul><li>رتبه موبایل در شهرهای ایران + رشد/افت</li></ul><h4>1.0.0</h4><ul><li>انتشار اولیه: کرول رقبا و رتبه گوگل/بینگ از API</li></ul>',
],
```

و در `LS_PRICES` (ریال):

```php
'didbani' => 3990000, // ۳۹۹٬۰۰۰ تومان
```

`LS_PLUGIN_LABELS`:

```php
'didbani' => 'دیدبانی — رصد رقبا و رتبهٔ کلیدواژه',
```

`LS_PLUGIN_META`:

```php
'didbani' => [
	'icon' => '👁',
	'desc' => 'کرول صفحات عمومی رقبا و رصد رتبه در گوگل و بینگ',
],
```

`LS_UPDATES`:

```php
'didbani' => [
	'version'      => '1.1.0',
	'package'      => 'https://webakery.ir/license-server/updates/didbani.zip',
	'requires'     => '5.8',
	'tested'       => '6.7',
	'requires_php' => '7.4',
	'changelog'    => 'نسخه ۱.۱.۰: رتبه موبایل در شهرهای ایران و نمایش رشد/افت.',
],
```

## ۲) بارگذاری فایل به‌روزرسانی

فایل `didbani.zip` (از ریشه ریپو) را در پوشه `updates/`
سرور لایسنس بگذارید. آدرس دانلود همان چیزی است که کلاینت با
`action=update` می‌گیرد.

## ۳) بررسی

```
POST https://webakery.ir/license-server/api/?action=update
{ "product": "didbani", "version": "0.9.0", "domain": "example.com" }
```

پاسخ باید `success: true` و `version: 1.0.0` و `package` (لینک ZIP) داشته باشد.

## نکات

- کلاینت لایسنس داخل افزونه: `includes/class-wb-license.php`
- صفحه بازگشت پس از پرداخت: `admin.php?page=didbani&tab=license`
- دوره آزمایشی: ۷ روز. پس از پایان دوره، کرول و رتبه‌یابی قفل می‌شود.
- قیمت پیش‌نویس است تا در فروش قطعی شود.
