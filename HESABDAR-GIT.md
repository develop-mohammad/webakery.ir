# افزودن افزونه Hesabdar واقعی به گیت

پوشه افزونه را از هاست کپی کنید:

```
wp-content/plugins/hesabdar/
```

باید داخل ریپو این‌طور باشد:

```
hesabdar/hesabdar.php
```

## روش سریع (از کامپیوتر خودتان)

1. پوشه `hesabdar` را از هاست دانلود کنید (FTP یا File Manager) — **پوشه**، نه لزوماً ZIP.
2. داخل کلون ریپو بگذارید:

```bash
git clone https://github.com/develop-mohammad/webakery.ir.git
cd webakery.ir
git checkout cursor/hesabdar-real-source-b1e1
# پوشه hesabdar را اینجا کپی کنید (جایگزین پوشه خالی/قدیمی)
git add hesabdar
git commit -m "Add real Hesabdar plugin source"
git push -u origin cursor/hesabdar-real-source-b1e1
```

بعد از push، بگویید تا فاکتور را روی همان سورس واقعی اضافه کنم.
