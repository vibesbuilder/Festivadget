# Build the Festivadget release package (task #92.3, Joomla principle).
#
# Result: release/festivadget-v<version>.zip - a package a customer only
# unpacks/uploads to their webroot via FTP, then opens /install/ in the
# browser (the web installer writes push/config.php). No build machine needed
# at the customer's; this script runs at the maintainer's (or later in CI).
#
# Usage:  powershell -File Festivadget\tools\build-release.ps1  [-SkipBuild]
#
# Package content:
#   /              app build (dist/: index.html, assets, icons, data, docs, sw)
#   /push          PHP backend incl. cms/ and vendor/ (without config.php!)
#   /install       web installer (deletes itself after setup)
#   /LICENSE       AGPLv3

param([switch]$SkipBuild)

$ErrorActionPreference = "Stop"
$app = Split-Path -Parent $PSScriptRoot   # .../Festivadget (App-Ordner)

# --- 1. Neutral app build (without instance values from the local .env) ---------
if (-not $SkipBuild) {
    Write-Host "== Build (neutral: vite --mode release clears instance env via .env.release)"
    # CAUTION: $env:X = "" DELETES the variable under PowerShell -> the local
    # .env would win (that is how the RID VAPID key ended up in v1.2.x packages).
    # Hence .env.release (empty overrides) + vite --mode release.
    pnpm -C $app exec tsc -b
    if ($LASTEXITCODE -ne 0) { throw "tsc failed." }
    pnpm -C $app exec vite build --mode release
    if ($LASTEXITCODE -ne 0) { throw "vite build failed." }
}
if (-not (Test-Path "$app\dist\index.html")) { throw "dist/ missing - build first." }

# --- 2. Assemble staging ----------------------------------------------------------
$staging = "$app\release\staging"
if (Test-Path $staging) { Remove-Item $staging -Recurse -Force }
New-Item -ItemType Directory -Force $staging | Out-Null

Write-Host "== Staging: app build"
robocopy "$app\dist" $staging /S /NFL /NDL /NJH /NJS | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy dist failed." }

Write-Host "== Staging: push/ (without secrets/runtime files)"
robocopy "$app\push" "$staging\push" /S /NFL /NDL /NJH /NJS `
    /XF config.php cms-settings.json weather-settings.json | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy push failed." }

Write-Host "== Staging: sample data instead of real data (sample-data/)"
# The public package NEVER contains the maintainer's real festival data:
# data/ is fully replaced by sample-data/data, sample-data/assets overlays
# image assets (venue map, sponsor logo). public/data and the live instance
# stay untouched - the swap happens only in staging.
if (-not (Test-Path "$app\sample-data\data\festival.json")) {
    throw "sample-data/data missing - the package would contain real data."
}
Remove-Item "$staging\data" -Recurse -Force -ErrorAction SilentlyContinue
robocopy "$app\sample-data\data" "$staging\data" /S /NFL /NDL /NJH /NJS | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy sample-data failed." }
# Remove RID-specific image assets (artist photos, sponsor logos, logo SVG);
# neutral background = the bundled background.example.webp.
Remove-Item "$staging\img\artists", "$staging\img\sponsors", "$staging\img\logo.svg" -Recurse -Force -ErrorAction SilentlyContinue
Copy-Item "$staging\img\background.example.webp" "$staging\img\background.webp" -Force
robocopy "$app\sample-data\assets" $staging /S /NFL /NDL /NJH /NJS | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy sample-assets failed." }
# Neutralize build strings: title/manifest would otherwise carry the instance
# name from the build (index.html/<title>, apple label, static PWA manifest).
foreach ($f in @("$staging\index.html", "$staging\manifest.webmanifest")) {
    # Read/write UTF-8 without BOM correctly (Get-Content would guess ANSI).
    $c = [System.IO.File]::ReadAllText($f)
    $c = $c.Replace("ROCK IM DORF Festival", "Gadget Festival").Replace("ROCK IM DORF", "GADGET")
    $c = $c.Replace('lang="de"', 'lang="en"').Replace('"lang":"de"', '"lang":"en"')
    # Replace the description (umlauts!) via ASCII regex - PS 5.1 otherwise reads ps1 as ANSI.
    $c = $c -replace 'Festivadget[^"<]*Begleiter[^"<]*', 'Festivadget - Festival companion for multi-day events - works offline.'
    $c = $c -replace 'Festival-Begleiter[^"<]*', 'Festival companion for multi-day events - works offline.'
    [System.IO.File]::WriteAllText($f, $c, (New-Object System.Text.UTF8Encoding($false)))
}

Write-Host "== Staging: install/ + LICENSE"
robocopy "$app\install" "$staging\install" /S /NFL /NDL /NJH /NJS | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy install failed." }
Copy-Item "$app\LICENSE" "$staging\LICENSE"

# --- 3. Safety checks --------------------------------------------------------------
if (Test-Path "$staging\push\config.php") { throw "SECURITY: config.php in the package!" }
# No real data in the package: the sample dataset is called "Gadget Festival".
$festivalName = (Get-Content "$staging\data\festival.json" -Raw | ConvertFrom-Json).name
if ($festivalName -notmatch 'Gadget') { throw "SECURITY: data/ does not contain the sample data ($festivalName)." }
if (Test-Path "$staging\data\uploads") { throw "SECURITY: data/uploads in the package." }
if (-not (Test-Path "$staging\push\vendor\autoload.php")) {
    throw "push/vendor missing - run 'composer install' in push/ once."
}
if (-not (Test-Path "$staging\push\cms\index.php")) { throw "push/cms missing in the package." }
if (-not (Test-Path "$staging\docs")) { Write-Warning "docs/ fehlt im Build (Manuals)." }
# Every image referenced in artists.json must also be in the package - else
# the demo shows broken placeholders (images come from sample-data/assets).
$missingArt = @()
foreach ($a in (Get-Content "$staging\data\artists.json" -Raw | ConvertFrom-Json)) {
    if ($a.image) {
        $rel = ($a.image -replace '^/', '') -replace '/', '\'
        if (-not (Test-Path (Join-Path $staging $rel))) { $missingArt += "$($a.id): $($a.image)" }
    }
}
if ($missingArt.Count -gt 0) {
    throw "Artist images missing in the package (place them in sample-data/assets): " + ($missingArt -join ", ")
}

# --- 4. ZIP -----------------------------------------------------------------------
$version = (Get-Content "$app\package.json" -Raw | ConvertFrom-Json).version
# VERSION file: shows the CMS update tab the installed state.
Set-Content -Path "$staging\VERSION" -Value $version -Encoding ascii -NoNewline

$zip = "$app\release\festivadget-v$version.zip"
if (Test-Path $zip) { Remove-Item $zip -Force }
Write-Host "== Packing $zip"
# Not Compress-Archive: under Windows PowerShell 5.1 it writes backslash
# entry names into the ZIP - Linux unzip then creates files with backslashes
# in their names. Hence ZipArchive with forward-slash paths.
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

# Update package: like the release, but WITHOUT data/ (customer content!) and
# without install/. Applied via FTP overwrite (minimal) or 1-click in the CMS
# tab "Update" (convenience) - data/, config.php and uploads stay untouched.
$updateZip = "$app\release\festivadget-update-v$version.zip"
if (Test-Path $updateZip) { Remove-Item $updateZip -Force }
Write-Host "== Packing $updateZip"
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
Write-Host "Done: $zip ($mb MB) + update package ($umb MB)"
Write-Host "data/ in the package = sample data (sample-data/, Gadget Festival)."
