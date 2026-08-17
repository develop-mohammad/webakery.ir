# افزودن محصول «کد تخفیف دسته‌بندی» به سرور لایسنس

شناسه محصول (product slug): `webakery-category-coupons`

## ۱) ثبت محصول در `config.php` سرور لایسنس

در آرایه محصولات، این ورودی را اضافه کنید:

```php
'webakery-category-coupons' => [
	'name'         => 'کد تخفیف دسته‌بندی',
	'price'        => 299000,          // تومان
	'version'      => '1.0.0',
	'file'         => 'webakery-category-coupons.zip',
	'homepage'     => 'https://webakery.ir/product/webakery-category-coupons/',
	'requires'     => '5.8',
	'requires_php' => '7.4',
	'tested'       => '6.7',
	'changelog'    => '<h4>1.0.0</h4><ul><li>انتشار اولیه</li></ul>',
],
```

## ۲) بارگذاری فایل به‌روزرسانی

فایل `webakery-category-coupons.zip` (از ریشه ریپو) را در پوشه `updates/`
سرور لایسنس بگذارید. آدرس دانلود همان چیزی است که کلاینت با
`action=update` می‌گیرد.

## ۳) بررسی

```
POST https://webakery.ir/license-server/api/?action=update
{ "product": "webakery-category-coupons", "version": "0.9.0", "domain": "example.com" }
```

پاسخ باید `success: true` و `version: 1.0.0` و `package` (لینک ZIP) داشته باشد.

## نکات

- کلاینت لایسنس داخل افزونه: `includes/class-wb-license.php`
- صفحه بازگشت پس از پرداخت: `admin.php?page=webakery-category-coupons&tab=license`
- دوره آزمایشی: ۷ روز. پس از پایان دوره، ساخت کد تخفیف قفل می‌شود
  (کدهای ساخته‌شده قبلی در ووکامرس دست‌نخورده می‌مانند).
