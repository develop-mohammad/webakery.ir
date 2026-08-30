#!/bin/sh
cd "$(dirname "$0")"
FILE="$(pwd)/index.html"
if command -v xdg-open >/dev/null 2>&1; then
	xdg-open "$FILE"
elif command -v open >/dev/null 2>&1; then
	open "$FILE"
elif command -v python3 >/dev/null 2>&1; then
	echo "باز کردن http://127.0.0.1:8091"
	python3 -m http.server 8091
else
	echo "فایل را در مرورگر باز کنید:"
	echo "$FILE"
fi
