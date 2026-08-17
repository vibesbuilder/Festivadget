# Festivadget-Release-Paket schnüren (Task #92.3, Joomla-Prinzip).
#
# Ergebnis: release/festivadget-v<version>.zip – ein Paket, das ein Kunde nur
# noch per FTP in seinen Webroot entpackt/hochlädt und dann /install/ im
# Browser öffnet (Web-Installer schreibt push/config.php). Keine Build-Maschine
# beim Kunden nötig; dieses Skript läuft beim Maintainer (oder später in CI).
#
# Aufruf:  powershell -File Festivadget\tools\build-release.ps1  [-SkipBuild]
#
# Inhalt des Pakets:
#   /              App-Build (dist/: index.html, assets, icons, data, docs, sw)
#   /push          PHP-Backend inkl. cms/ und vendor/ (ohne config.php!)
#   /install       Web-Installer (löscht sich nach der Einrichtung selbst)
#   /LICENSE       AGPLv3

param([switch]$SkipBuild)

$ErrorActionPreference = "Stop"
$app = Split-Path -Parent $PSScriptRoot   # .../Festivadget (App-Ordner)

# --- 1. Neutraler App-Build (ohne Instanz-Werte aus der lokalen .env) -----------
if (-not $SkipBuild) {
    Write-Host "== Build (neutral, ohne VAPID-Build-Key)"
    # Prozess-Env übersteuert die .env-Datei: Kundenpaket ohne eingebauten Key
    # (der Installer/Server liefert ihn zur Laufzeit über push/vapid.php).
    $env:VITE_VAPID_PUBLIC_KEY = ""
    pnpm -C $app build
    if ($LASTEXITCODE -ne 0) { throw "pnpm build fehlgeschlagen." }
    Remove-Item Env:\VITE_VAPID_PUBLIC_KEY -ErrorAction SilentlyContinue
}
if (-not (Test-Path "$app\dist\index.html")) { throw "dist/ fehlt - zuerst bauen." }

# --- 2. Staging zusammenstellen --------------------------------------------------
$staging = "$app\release\staging"
if (Test-Path $staging) { Remove-Item $staging -Recurse -Force }
New-Item -ItemType Directory -Force $staging | Out-Null

Write-Host "== Staging: App-Build"
robocopy "$app\dist" $staging /S /NFL /NDL /NJH /NJS | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy dist fehlgeschlagen." }

Write-Host "== Staging: push/ (ohne Secrets/Laufzeit-Dateien)"
robocopy "$app\push" "$staging\push" /S /NFL /NDL /NJH /NJS `
    /XF config.php cms-settings.json weather-settings.json | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy push fehlgeschlagen." }

Write-Host "== Staging: Beispieldaten statt Echtdaten (sample-data/)"
# Das oeffentliche Paket enthaelt NIE die echten Festivaldaten des Maintainers:
# data/ wird komplett durch sample-data/data ersetzt, sample-data/assets
# ueberlagert Bild-Assets (Gelaendeplan, Sponsor-Logo). public/data und die
# Live-Instanz bleiben unberuehrt - der Tausch passiert nur im Staging.
if (-not (Test-Path "$app\sample-data\data\festival.json")) {
    throw "sample-data/data fehlt - Paket wuerde Echtdaten enthalten."
}
Remove-Item "$staging\data" -Recurse -Force -ErrorAction SilentlyContinue
robocopy "$app\sample-data\data" "$staging\data" /S /NFL /NDL /NJH /NJS | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy sample-data fehlgeschlagen." }
# RID-spezifische Bild-Assets raus (Artist-Fotos, Sponsor-Logos, Logo-SVG);
# neutraler Hintergrund = mitgeliefertes background.example.webp.
Remove-Item "$staging\img\artists", "$staging\img\sponsors", "$staging\img\logo.svg" -Recurse -Force -ErrorAction SilentlyContinue
Copy-Item "$staging\img\background.example.webp" "$staging\img\background.webp" -Force
robocopy "$app\sample-data\assets" $staging /S /NFL /NDL /NJH /NJS | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy sample-assets fehlgeschlagen." }
# Build-Strings neutralisieren: Titel/Manifest tragen sonst den Instanz-Namen
# aus dem Build (index.html/<title>, apple-Label, statisches PWA-Manifest).
foreach ($f in @("$staging\index.html", "$staging\manifest.webmanifest")) {
    $c = Get-Content $f -Raw
    $c = $c.Replace("ROCK IM DORF Festival", "Sommerklang Festival").Replace("ROCK IM DORF", "SOMMERKLANG")
    Set-Content -Path $f -Value $c -Encoding utf8 -NoNewline
}

Write-Host "== Staging: install/ + LICENSE"
robocopy "$app\install" "$staging\install" /S /NFL /NDL /NJH /NJS | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy install fehlgeschlagen." }
Copy-Item "$app\LICENSE" "$staging\LICENSE"

# --- 3. Sicherheits-Checks --------------------------------------------------------
if (Test-Path "$staging\push\config.php") { throw "SICHERHEIT: config.php im Paket!" }
# Keine Echtdaten im Paket: der Beispiel-Datensatz heisst "Sommerklang".
$festivalName = (Get-Content "$staging\data\festival.json" -Raw | ConvertFrom-Json).name
if ($festivalName -notmatch 'Sommerklang') { throw "SICHERHEIT: data/ enthaelt nicht die Beispieldaten ($festivalName)." }
if (Test-Path "$staging\data\uploads") { throw "SICHERHEIT: data/uploads im Paket." }
if (-not (Test-Path "$staging\push\vendor\autoload.php")) {
    throw "push/vendor fehlt - einmalig 'composer install' in push/ ausfuehren."
}
if (-not (Test-Path "$staging\push\cms\index.php")) { throw "push/cms fehlt im Paket." }
if (-not (Test-Path "$staging\docs")) { Write-Warning "docs/ fehlt im Build (Manuals)." }

# --- 4. ZIP -----------------------------------------------------------------------
$version = (Get-Content "$app\package.json" -Raw | ConvertFrom-Json).version
# VERSION-Datei: zeigt dem CMS-Update-Tab den installierten Stand.
Set-Content -Path "$staging\VERSION" -Value $version -Encoding ascii -NoNewline

$zip = "$app\release\festivadget-v$version.zip"
if (Test-Path $zip) { Remove-Item $zip -Force }
Write-Host "== Packe $zip"
# Nicht Compress-Archive: das schreibt unter Windows PowerShell 5.1
# Backslash-Eintragsnamen ins ZIP - Linux-Unzip legt dann Dateien mit
# Backslash im Namen an. Daher ZipArchive mit Forward-Slash-Pfaden.
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive = [System.IO.Compression.ZipFile]::Open($zip, 'Create')
try {
    $base = (Resolve-Path $staging).Path
    Get-ChildItem $staging -Recurse -File | ForEach-Object {
        $entry = $_.FullName.Substring($base.Length + 1) -replace '\\', '/'
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $archive, $_.FullName, $entry,
            [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
} finally {
    $archive.Dispose()
}

# Update-Paket: wie das Release, aber OHNE data/ (Kundeninhalte!) und ohne
# install/. Zum Einspielen per FTP-Ueberschreiben (Minimal) oder 1-Klick im
# CMS-Tab "Update" (Komfort) - data/, config.php und Uploads bleiben unberuehrt.
$updateZip = "$app\release\festivadget-update-v$version.zip"
if (Test-Path $updateZip) { Remove-Item $updateZip -Force }
Write-Host "== Packe $updateZip"
$archive = [System.IO.Compression.ZipFile]::Open($updateZip, 'Create')
try {
    $base = (Resolve-Path $staging).Path
    Get-ChildItem $staging -Recurse -File | ForEach-Object {
        $entry = $_.FullName.Substring($base.Length + 1) -replace '\\', '/'
        if ($entry -like 'data/*' -or $entry -like 'install/*') { return }
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $archive, $_.FullName, $entry,
            [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
} finally {
    $archive.Dispose()
}

$mb = [math]::Round((Get-Item $zip).Length / 1MB, 1)
$umb = [math]::Round((Get-Item $updateZip).Length / 1MB, 1)
Write-Host ""
Write-Host "Fertig: $zip ($mb MB) + Update-Paket ($umb MB)"
Write-Host "data/ im Paket = Beispieldaten (sample-data/, Sommerklang Festival)."
