@echo off
chcp 65001 >nul
cd /d "%~dp0"
echo Opening SEO Studio on this laptop...
start "" "%~dp0index.html"
echo.
echo If the browser did not open, double-click index.html
pause
