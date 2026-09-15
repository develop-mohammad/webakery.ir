@echo off
cd /d "%~dp0"
if not exist "%~dp0Daftarchi.exe" (
  echo Daftarchi.exe is missing. Extract the whole ZIP first.
  pause
  exit /b 1
)
start "" "%~dp0Daftarchi.exe"
