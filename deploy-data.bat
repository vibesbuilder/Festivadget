@echo off
REM Festivadget - build and upload.
REM
REM   deploy-data.bat            -> content data only (dist\data\*.json + version.json)
REM   deploy-data.bat full       -> full app (entire dist\ folder, for app updates)
REM   deploy-data.bat push       -> push backend (push\*.php + push\cms\*.php) - WITHOUT
REM                                 config.php/config.example.php/vapid-keys.php and without
REM                                 vendor\ (config stays server-side, vendor: docs\PUSH.md)
REM
REM Flow data/full: pnpm run import -> build:data -> build, then upload.
REM Flow push: upload only (PHP needs no build).
REM Prerequisites: Node/pnpm in PATH, curl (included in Windows 10/11), deploy.env.bat created.
REM Note: this file lives in the app folder Festivadget\ and jumps here via cd /d "%~dp0";
REM the pnpm scripts thus run in the "festivadget" package.

setlocal enabledelayedexpansion
cd /d "%~dp0"

if not exist "deploy.env.bat" (
  echo [Fehler] deploy.env.bat fehlt - aus deploy.env.example.bat erstellen und Zugangsdaten eintragen.
  exit /b 1
)
call "deploy.env.bat"

set "MODE=data"
if /i "%~1"=="full" set "MODE=full"
if /i "%~1"=="push" goto :uploadpush

echo(
echo === 1/4  Import aus Quellen (pnpm run import) ===
call pnpm run import || goto :fail

echo(
echo === 2/4  Validierung + version.json (pnpm run build:data) ===
call pnpm run build:data || goto :fail

echo(
echo === 3/4  Produktions-Build (pnpm run build) ===
call pnpm run build || goto :fail

if /i "%MODE%"=="full" goto :uploadfull

REM --- Data upload (default) ---------------------------------------------
echo(
echo === 4/4  Upload Inhaltsdaten nach %FTP_HOST%%FTP_REMOTE_ROOT%/data ===
if not exist "dist\data\*.json" (
  echo [Fehler] Keine Dateien unter dist\data\ gefunden.
  goto :fail
)
set "N=0"
for %%F in (dist\data\*.json) do (
  echo   ^> data/%%~nxF
  curl -sS %CURL_OPTS% --ftp-create-dirs -T "%%F" "ftp://%FTP_HOST%%FTP_REMOTE_ROOT%/data/%%~nxF" --user "%FTP_USER%:%FTP_PASS%" || goto :fail
  set /a N+=1
)
echo(
echo Fertig (Daten). !N! Datei(en) hochgeladen. Clients ziehen in ^<= 2 min nach.
endlocal
exit /b 0

REM --- Full upload (entire dist\) ----------------------------------------
:uploadfull
echo(
echo === 4/4  Upload KOMPLETTE App (dist\) nach %FTP_HOST%%FTP_REMOTE_ROOT%/ ===
if not exist "dist\index.html" (
  echo [Fehler] dist\index.html fehlt - Build unvollstaendig.
  goto :fail
)
pushd "dist"
set "ROOTP=%CD%"
set "N=0"
for /r %%F in (*) do (
  set "REL=%%F"
  set "REL=!REL:%ROOTP%\=!"
  set "REL=!REL:\=/!"
  echo   ^> !REL!
  curl -sS %CURL_OPTS% --ftp-create-dirs -T "%%F" "ftp://%FTP_HOST%%FTP_REMOTE_ROOT%/!REL!" --user "%FTP_USER%:%FTP_PASS%" || (popd & goto :fail)
  set /a N+=1
)
popd
echo(
echo Fertig (komplette App). !N! Datei(en) hochgeladen.
echo Hinweis: Das Push-Backend laedt "deploy-data.bat push" hoch (config.php/vendor\ ausgenommen).
endlocal
exit /b 0

REM --- Push backend upload (PHP endpoints only, no secrets) ----------------
:uploadpush
echo(
echo === Upload Push-Backend nach %FTP_HOST%%FTP_REMOTE_ROOT%/push ===
if not exist "push\*.php" (
  echo [Fehler] Keine PHP-Dateien unter push\ gefunden.
  goto :fail
)
set "N=0"
for %%F in (push\*.php) do (
  set "SKIP="
  REM config.php (server secrets) and config.example.php do not belong on the
  REM server; per docs\PUSH.md, vapid-keys.php should not remain there either.
  if /i "%%~nxF"=="config.php" set "SKIP=1"
  if /i "%%~nxF"=="config.example.php" set "SKIP=1"
  if /i "%%~nxF"=="vapid-keys.php" set "SKIP=1"
  if not defined SKIP (
    echo   ^> push/%%~nxF
    curl -sS %CURL_OPTS% --ftp-create-dirs -T "%%F" "ftp://%FTP_HOST%%FTP_REMOTE_ROOT%/push/%%~nxF" --user "%FTP_USER%:%FTP_PASS%" || goto :fail
    set /a N+=1
  )
)
REM .htaccess protects config/settings/cache - must come along (the glob misses it).
if exist "push\.htaccess" (
  echo   ^> push/.htaccess
  curl -sS %CURL_OPTS% --ftp-create-dirs -T "push\.htaccess" "ftp://%FTP_HOST%%FTP_REMOTE_ROOT%/push/.htaccess" --user "%FTP_USER%:%FTP_PASS%" || goto :fail
  set /a N+=1
)
REM The admin UI (CMS) is part of the backend.
for %%F in (push\cms\*.php) do (
  echo   ^> push/cms/%%~nxF
  curl -sS %CURL_OPTS% --ftp-create-dirs -T "%%F" "ftp://%FTP_HOST%%FTP_REMOTE_ROOT%/push/cms/%%~nxF" --user "%FTP_USER%:%FTP_PASS%" || goto :fail
  set /a N+=1
)
echo(
echo Fertig (Push-Backend). !N! Datei(en) hochgeladen.
echo Nicht enthalten: config.php (am Server pflegen), vendor\ (Composer, docs\PUSH.md).
endlocal
exit /b 0

:fail
echo(
echo [ABGEBROCHEN] Schritt fehlgeschlagen - bitte Ausgabe oben pruefen.
endlocal
exit /b 1
