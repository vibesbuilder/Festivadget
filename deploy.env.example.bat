@echo off
REM Vorlage fuer deploy.env.bat - echte Zugangsdaten NICHT committen
REM (deploy.env.bat steht in .gitignore). Kopiere diese Datei nach deploy.env.bat
REM und trage die FTP-Zugangsdaten deines Webspace ein.

REM FTP-Host (ohne ftp://), z. B. ftp.rockimdorf.at
set "FTP_HOST=ftp.example.at"

REM Docroot der App auf dem Server = Ordner, in dem index.html liegt
REM (das, was die Subdomain z. B. app.rockimdorf.at ausliefert).
REM   - Liegt die App direkt im Docroot:        leer lassen  (set "FTP_REMOTE_ROOT=")
REM   - Liegt sie in einem Unterordner /app:    set "FTP_REMOTE_ROOT=/app"   (ohne Slash am Ende)
REM Die Daten landen automatisch unter <ROOT>/data.
set "FTP_REMOTE_ROOT="

REM Zugangsdaten
set "FTP_USER=dein-ftp-benutzer"
set "FTP_PASS=dein-ftp-passwort"

REM curl-Optionen. Standard erzwingt verschluesseltes FTPS (empfohlen).
REM Falls dein Host nur unverschluesseltes FTP kann, "--ssl-reqd" entfernen.
set "CURL_OPTS=--ssl-reqd"
