@echo off
REM Festivadget - bauen und hochladen.
REM
REM   deploy-data.bat            -> nur Inhaltsdaten (dist\data\*.json + version.json)
REM   deploy-data.bat full       -> komplette App (ganzer dist\-Ordner, fuer App-Updates)
REM   deploy-data.bat push       -> Push-Backend (push\*.php + push\cms\*.php) - OHNE
REM                                 config.php/config.example.php/vapid-keys.php und ohne
REM                                 vendor\ (config bleibt Server-Sache, vendor: docs\PUSH.md)
REM
REM Ablauf data/full: pnpm run import -> build:data -> build, danach Upload.
REM Ablauf push: nur Upload (PHP braucht keinen Build).
REM Voraussetzung: Node/pnpm im PATH, curl (in Windows 10/11 enthalten), deploy.env.bat angelegt.
REM Hinweis: Diese Datei liegt im App-Ordner Festivadget\ und springt per cd /d "%~dp0"
REM hierher; die pnpm-Scripts laufen damit im Paket "festivadget".

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

REM --- Daten-Upload (Standard) -------------------------------------------
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

REM --- Voll-Upload (gesamtes dist\) --------------------------------------
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

REM --- Push-Backend-Upload (nur PHP-Endpoints, keine Secrets) --------------
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
  REM config.php (Server-Secrets) und config.example.php gehoeren nicht auf den
  REM Server; vapid-keys.php soll dort laut docs\PUSH.md nicht liegen bleiben.
  if /i "%%~nxF"=="config.php" set "SKIP=1"
  if /i "%%~nxF"=="config.example.php" set "SKIP=1"
  if /i "%%~nxF"=="vapid-keys.php" set "SKIP=1"
  if not defined SKIP (
    echo   ^> push/%%~nxF
    curl -sS %CURL_OPTS% --ftp-create-dirs -T "%%F" "ftp://%FTP_HOST%%FTP_REMOTE_ROOT%/push/%%~nxF" --user "%FTP_USER%:%FTP_PASS%" || goto :fail
    set /a N+=1
  )
)
REM .htaccess schuetzt config/Settings/Cache - muss mit (Glob erfasst sie nicht).
if exist "push\.htaccess" (
  echo   ^> push/.htaccess
  curl -sS %CURL_OPTS% --ftp-create-dirs -T "push\.htaccess" "ftp://%FTP_HOST%%FTP_REMOTE_ROOT%/push/.htaccess" --user "%FTP_USER%:%FTP_PASS%" || goto :fail
  set /a N+=1
)
REM Admin-UI (CMS) gehoert zum Backend dazu.
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
