# افزودن محصول nobat-man به license-server

در `license-server/config.php` این مقادیر را اضافه کنید:

## LS_PRICES (مبالغ به ریال)

```php
'nobat-man' => 5990000, // ۵۹۹,۰۰۰ تومان
```

## LS_PLUGIN_LABELS

```php
'nobat-man' => 'نوبت من — رزرو نوبت مشاوره',
```

## LS_PLUGIN_META

```php
'nobat-man' => [
    'icon' => '📅',
    'desc' => 'رزرو نوبت مشاوره با تقویم شمسی، پرداخت و نسخه پرو',
],
```

## LS_UPDATES

```php
'nobat-man' => [
    'version'      => '1.0.0',
    'package'      => 'https://webakery.ir/license-server/updates/nobat-man.zip',
    'requires'     => '5.8',
    'tested'       => '6.7',
    'requires_php' => '7.4',
    'changelog'    => 'نسخه ۱.۰.۰: انتشار اولیه نوبت من.',
],
```

سپس فایل ZIP افزونه را در مسیر زیر کپی کنید:

`license-server/updates/nobat-man.zip`

شناسه محصول در افزونه: `nobat-man`
