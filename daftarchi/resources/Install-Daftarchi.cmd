@echo off
setlocal EnableExtensions
cd /d "%~dp0"

if not exist "%~dp0Daftarchi.exe" (
  echo First extract the ZIP, then run this file.
  pause
  exit /b 1
)

set "DEST=%LOCALAPPDATA%\Programs\Daftarchi"
mkdir "%DEST%" 2>nul
robocopy "%~dp0." "%DEST%" /E /NFL /NDL /NJH /NJS /NC /NS /NP /XF Install-Daftarchi.cmd
if %ERRORLEVEL% GEQ 8 (
  echo Copy failed. Close Daftarchi if it is open, then try again.
  pause
  exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -Command "$dest=$env:LOCALAPPDATA + '\Programs\Daftarchi'; $lnk=[Environment]::GetFolderPath('Desktop') + '\Daftarchi.lnk'; $s=New-Object -ComObject WScript.Shell; $l=$s.CreateShortcut($lnk); $l.TargetPath=Join-Path $dest 'Daftarchi.exe'; $l.WorkingDirectory=$dest; $l.Save()"

start "" "%DEST%\Daftarchi.exe"
exit /b 0
