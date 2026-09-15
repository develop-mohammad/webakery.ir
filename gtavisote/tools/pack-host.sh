#!/usr/bin/env bash
# زیپ مخصوص آپلود در لیارا (فقط پوشه وی‌سوت، بدون دیتای لوکال)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-$ROOT/../gtavisote-host.zip}"
cd "$ROOT"
rm -f "$OUT"
zip -r "$OUT" . \
  -x 'data/*.json' \
  -x 'data/*.lock' \
  -x 'data/*.tmp' \
  -x 'public/uploads/*' \
  -x 'node_modules/*' \
  -x '.git/*' \
  -x '*.log'
echo "ZIP: $OUT"
unzip -l "$OUT" | tail -n 8
