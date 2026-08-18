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
    Write-Host "== Build (neutral: vite --mode release leert Instanz-Env via .env.release)"
    # ACHTUNG: $env:X = "" LOESCHT die Variable unter PowerShell -> die lokale
    # .env wuerde gewinnen (so landete der RID-VAPID-Key in v1.2.x-Paketen).
    # Daher .env.release (leere Overrides) + vite --mode release.
    pnpm -C $app exec tsc -b
    if ($LASTEXITCODE -ne 0) { throw "tsc fehlgeschlagen." }
    pnpm -C $app exec vite build --mode release
    if ($LASTEXITCODE -ne 0) { throw "vite build fehlgeschlagen." }
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
    # UTF-8 ohne BOM korrekt lesen/schreiben (Get-Content wuerde ANSI raten).
    $c = [System.IO.File]::ReadAllText($f)
    $c = $c.Replace("ROCK IM DORF Festival", "Gadget Festival").Replace("ROCK IM DORF", "GADGET")
    $c = $c.Replace('lang="de"', 'lang="en"').Replace('"lang":"de"', '"lang":"en"')
    # Beschreibung (Umlaute!) per ASCII-Regex ersetzen - PS 5.1 liest ps1 sonst als ANSI.
    $c = $c -replace 'Festivadget[^"<]*Begleiter[^"<]*', 'Festivadget - Festival companion for multi-day events - works offline.'
    $c = $c -replace 'Festival-Begleiter[^"<]*', 'Festival companion for multi-day events - works offline.'
    [System.IO.File]::WriteAllText($f, $c, (New-Object System.Text.UTF8Encoding($false)))
}

Write-Host "== Staging: install/ + LICENSE"
robocopy "$app\install" "$staging\install" /S /NFL /NDL /NJH /NJS | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy install fehlgeschlagen." }
Copy-Item "$app\LICENSE" "$staging\LICENSE"

# --- 3. Sicherheits-Checks --------------------------------------------------------
if (Test-Path "$staging\push\config.php") { throw "SICHERHEIT: config.php im Paket!" }
# Keine Echtdaten im Paket: der Beispiel-Datensatz heisst "Gadget Festival".
$festivalName = (Get-Content "$staging\data\festival.json" -Raw | ConvertFrom-Json).name
if ($festivalName -notmatch 'Gadget') { throw "SICHERHEIT: data/ enthaelt nicht die Beispieldaten ($festivalName)." }
if (Test-Path "$staging\data\uploads") { throw "SICHERHEIT: data/uploads im Paket." }
if (-not (Test-Path "$staging\push\vendor\autoload.php")) {
    throw "push/vendor fehlt - einmalig 'composer install' in push/ ausfuehren."
}
if (-not (Test-Path "$staging\push\cms\index.php")) { throw "push/cms fehlt im Paket." }
if (-not (Test-Path "$staging\docs")) { Write-Warning "docs/ fehlt im Build (Manuals)." }
# Jedes in artists.json referenzierte Bild muss auch im Paket liegen - sonst
# zeigt die Demo kaputte Platzhalter (Bilder kommen aus sample-data/assets).
$missingArt = @()
foreach ($a in (Get-Content "$staging\data\artists.json" -Raw | ConvertFrom-Json)) {
    if ($a.image) {
        $rel = ($a.image -replace '^/', '') -replace '/', '\'
        if (-not (Test-Path (Join-Path $staging $rel))) { $missingArt += "$($a.id): $($a.image)" }
    }
}
if ($missingArt.Count -gt 0) {
    throw "Artist-Bilder fehlen im Paket (in sample-data/assets ablegen): " + ($missingArt -join ", ")
}

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
Write-Host "data/ im Paket = Beispieldaten (sample-data/, Gadget Festival)."
