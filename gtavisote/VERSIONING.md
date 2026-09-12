# نسخه‌بندی وی‌سوت

عدد نسخه در دو جا باید یکی باشد:

- `VERSION`
- `package.json` → `version`

فرمت: `MAJOR.MINOR.PATCH` (مثل `1.0.0`)

| تغییر | چه عددی بالا برود |
|---|---|
| باگ و متن و ظاهر کوچک | PATCH — `1.0.0` → `1.0.1` |
| بخش یا نوع محصول جدید | MINOR — `1.0.1` → `1.1.0` |
| تغییر بزرگ ناسازگار (مثلاً عوض شدن ساختار data) | MAJOR — `1.1.0` → `2.0.0` |

## روال گیت

1. روی برنچ کار کن (`cursor/...`).
2. تغییر را بساز و با `npm run check` تست کن.
3. خط جدید در `CHANGELOG.md` بنویس.
4. `VERSION` و `package.json` را یکی کن.
5. commit با پیام انگلیسی واضح، بعد push.

نمونه پیام:

`feat(gtavisote): add zarinpal checkout`

`fix(gtavisote): keep rumor posts out of news archive`
