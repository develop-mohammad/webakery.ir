#!/usr/bin/env bash
# ساخت ZIP نسخه دمو برای افزونه‌های پولی webakery.ir
# خروجی: ریشه ریپو → *-demo.zip
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

build_one() {
	local src_dir="$1"
	local zip_name="$2"
	local const_name="$3"
	local main_file="$4"
	local display="$5"

	local folder
	folder="$(basename "$src_dir")"
	local work="$TMP/$folder"
	rm -rf "$work"
	mkdir -p "$TMP"
	cp -a "$ROOT/$src_dir" "$work"

	# فعال‌سازی حالت دمو
	if grep -q "define( '${const_name}', false )" "$work/$main_file"; then
		sed -i "s/define( '${const_name}', false )/define( '${const_name}', true )/" "$work/$main_file"
	else
		echo "ERROR: ثابت ${const_name} در ${src_dir}/${main_file} پیدا نشد" >&2
		exit 1
	fi

	# هدر افزونه: پسوند «دمو»
	python3 - "$work/$main_file" <<'PY'
import re, sys
path = sys.argv[1]
text = open(path, encoding='utf-8').read()
text2, n1 = re.subn(
    r'(^\s*\*\s*Plugin Name:.*)$',
    r'\1 — دمو',
    text,
    count=1,
    flags=re.M,
)
if n1 == 0:
    raise SystemExit('Plugin Name not found: ' + path)
text3, n2 = re.subn(
    r'(^\s*\*\s*Description:.*)$',
    r'\1 [نسخه دمو — بدون لایسنس، فقط برای تست قبل از خرید]',
    text2,
    count=1,
    flags=re.M,
)
open(path, 'w', encoding='utf-8').write(text3)
print('  header: name+=%s desc+=%s' % (n1, n2))
PY

	# راهنمای کوتاه داخل ZIP (heredoc نقل‌قول‌دار تا با set -u تداخل نکند)
	{
		echo "نسخه دمو — ${display}"
		echo "================================"
		echo "• بدون نیاز به لایسنس"
		echo "• همه امکانات برای تست فعال است"
		echo "• بنر «نسخه دمو» در پیشخوان نمایش داده می‌شود"
		echo "• مناسب بررسی قبل از خرید / مارکت‌پلیس"
		echo ""
		echo "پس از رضایت:"
		echo "1) نسخه کامل را از https://webakery.ir بخرید"
		echo "2) نسخه دمو را حذف کنید"
		echo "3) ZIP اصلی را نصب و لایسنس را فعال کنید"
		echo ""
		echo "سازنده: webakery.ir"
	} > "$work/DEMO-FA.txt"

	(
		cd "$TMP"
		rm -f "${OUT}/${zip_name}"
		zip -qr "${OUT}/${zip_name}" "$folder"
	)
	echo "OK ${zip_name} (${const_name}=true)"
}

echo "ساخت ZIPهای دمو در: ${OUT}"
build_one hesabdar hesabdar-demo.zip HESABDAR_DEMO hesabdar.php Hesabdar
build_one nobat-man nobat-man-demo.zip NM_DEMO nobat-man.php NobatMan
build_one access-levels access-levels-demo.zip AL_DEMO access-levels.php Barbari
build_one baget baget-demo.zip WCCP_DEMO baget.php Baget
build_one webakery-chat-box webakery-chat-box-demo.zip WBCB_DEMO webakery-chat-box.php WebakeryChat

echo ""
echo "تمام. فایل‌ها:"
ls -lh "${OUT}"/*-demo.zip

# کپی داخل مسیر عمومی هاست (license-server/demos)
DEMOS_DIR="${ROOT}/license-server/demos"
mkdir -p "${DEMOS_DIR}"
cp -f "${OUT}/hesabdar-demo.zip" "${DEMOS_DIR}/hesabdar-demo.zip"
cp -f "${OUT}/nobat-man-demo.zip" "${DEMOS_DIR}/nobat-man-demo.zip"
cp -f "${OUT}/access-levels-demo.zip" "${DEMOS_DIR}/access-levels-demo.zip"
cp -f "${OUT}/baget-demo.zip" "${DEMOS_DIR}/baget-demo.zip"
cp -f "${OUT}/webakery-chat-box-demo.zip" "${DEMOS_DIR}/webakery-chat-box-demo.zip"
echo "کپی شد به: ${DEMOS_DIR}"
ls -lh "${DEMOS_DIR}"/*-demo.zip
