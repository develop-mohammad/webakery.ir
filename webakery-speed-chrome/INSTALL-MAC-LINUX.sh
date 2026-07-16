#!/bin/sh
echo "============================================"
echo "  Webakery Speed - Chrome Extension"
echo "============================================"
echo ""
echo "1) Extract the ZIP first"
echo "2) manifest.json must be in THIS folder"
echo "3) chrome://extensions -> Developer mode ON"
echo "4) Load unpacked -> select THIS folder"
echo ""
if command -v google-chrome >/dev/null 2>&1; then
  google-chrome "chrome://extensions" 2>/dev/null &
elif command -v chromium >/dev/null 2>&1; then
  chromium "chrome://extensions" 2>/dev/null &
elif command -v open >/dev/null 2>&1; then
  open "googlechrome://extensions" 2>/dev/null || open -a "Google Chrome" "chrome://extensions" 2>/dev/null &
fi
