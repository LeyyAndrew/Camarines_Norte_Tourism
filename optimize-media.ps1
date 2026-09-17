# =====================================================================
# optimize-media.ps1 — shrinks everything in uploads\
#
# RUN IT:
#   Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
#   .\optimize-media.ps1
#
# Originals are copied to uploads-original\ first. Nothing is lost.
# =====================================================================

$ErrorActionPreference = 'Stop'

$Uploads = "uploads"
$Backup  = "uploads-original"

if (-not (Test-Path $Uploads)) {
    Write-Host "No uploads\ folder found here." -ForegroundColor Red
    Write-Host "Run this from C:\xampp\htdocs\Tourism_System"
    exit 1
}

if (-not (Get-Command ffmpeg -ErrorAction SilentlyContinue)) {
    Write-Host "ffmpeg is not on your PATH." -ForegroundColor Red
    exit 1
}

$HasWebp = [bool](Get-Command cwebp -ErrorAction SilentlyContinue)
if (-not $HasWebp) {
    Write-Host "cwebp not found - skipping .webp step. That is fine.`n" -ForegroundColor Yellow
}

function Get-FolderSizeMB($path) {
    $bytes = (Get-ChildItem $path -Recurse -File | Measure-Object Length -Sum).Sum
    return [math]::Round($bytes / 1MB, 1)
}

# Back up once. Never overwrite an existing backup.
if (-not (Test-Path $Backup)) {
    Write-Host "==> Backing up originals to $Backup\" -ForegroundColor Cyan
    Copy-Item $Uploads $Backup -Recurse
} else {
    Write-Host "==> $Backup\ already exists, leaving it alone" -ForegroundColor Cyan
}

$before = Get-FolderSizeMB $Uploads
Write-Host "`n==> BEFORE: $before MB`n" -ForegroundColor Cyan

# ---------------------------------------------------------------------
# VIDEO — the fix that matters.
# +faststart moves the mp4 index to the front of the file. Without it
# a browser must download the WHOLE video before showing frame one,
# which is almost certainly why yours were stalling.
# ---------------------------------------------------------------------
Write-Host "==> Re-encoding video" -ForegroundColor Cyan

Get-ChildItem $Uploads -Recurse -Include *.mp4 -File | ForEach-Object {
    $v      = $_.FullName
    $tmp    = "$v.tmp.mp4"
    $sizeMB = [math]::Round($_.Length / 1MB, 1)

    & ffmpeg -y -loglevel error -i $v `
        -vf "scale=1280:-2,fps=24" `
        -c:v libx264 -profile:v main -crf 32 -preset slow `
        -movflags +faststart -an -t 12 `
        $tmp

    if ($LASTEXITCODE -eq 0 -and (Test-Path $tmp)) {
        Move-Item $tmp $v -Force
        $newMB = [math]::Round((Get-Item $v).Length / 1MB, 1)
        Write-Host "    $($_.Name): $sizeMB MB -> $newMB MB" -ForegroundColor Green
    } else {
        Write-Host "    $($_.Name): FAILED, left untouched" -ForegroundColor Red
        Remove-Item $tmp -ErrorAction SilentlyContinue
    }
}

# ---------------------------------------------------------------------
# IMAGES — cap at 1920px. A 4000px photo in an 800px card wastes
# about 96% of its bytes.
# ---------------------------------------------------------------------
Write-Host "`n==> Resizing oversized images (max 1920px wide)" -ForegroundColor Cyan

Get-ChildItem $Uploads -Recurse -Include *.jpg,*.jpeg,*.png -File | ForEach-Object {
    $img = $_.FullName
    $w = & ffprobe -v error -select_streams v:0 -show_entries stream=width `
                   -of csv=p=0 $img 2>$null

    if ($w -match '^\d+$' -and [int]$w -gt 1920) {
        $sizeMB = [math]::Round($_.Length / 1MB, 2)
        $tmp    = "$img.tmp$($_.Extension)"

        & ffmpeg -y -loglevel error -i $img -vf "scale=1920:-2" $tmp

        if ($LASTEXITCODE -eq 0 -and (Test-Path $tmp)) {
            Move-Item $tmp $img -Force
            $newMB = [math]::Round((Get-Item $img).Length / 1MB, 2)
            Write-Host "    $($_.Name): ${w}px -> 1920px  ($sizeMB MB -> $newMB MB)" -ForegroundColor Green
        } else {
            Remove-Item $tmp -ErrorAction SilentlyContinue
        }
    }
}

# ---------------------------------------------------------------------
# WEBP — written alongside the JPEGs, never replacing them.
# ---------------------------------------------------------------------
if ($HasWebp) {
    Write-Host "`n==> Writing .webp copies" -ForegroundColor Cyan
    $made = 0

    Get-ChildItem $Uploads -Recurse -Include *.jpg,*.jpeg -File | ForEach-Object {
        $out = [IO.Path]::ChangeExtension($_.FullName, '.webp')
        if (-not (Test-Path $out)) {
            & cwebp -quiet -q 78 $_.FullName -o $out
            if ($LASTEXITCODE -eq 0) { $made++ }
        }
    }

    Get-ChildItem $Uploads -Recurse -Include *.png -File | ForEach-Object {
        $out = [IO.Path]::ChangeExtension($_.FullName, '.webp')
        if (-not (Test-Path $out)) {
            & cwebp -quiet -lossless $_.FullName -o $out
            if ($LASTEXITCODE -eq 0) { $made++ }
        }
    }

    Write-Host "    $made .webp files created" -ForegroundColor Green
}

# ---------------------------------------------------------------------
# Report
# ---------------------------------------------------------------------
$after = Get-FolderSizeMB $Uploads
Write-Host "`n==> AFTER: $after MB" -ForegroundColor Cyan

if ($before -gt 0) {
    $saved = [math]::Round((1 - $after / $before) * 100, 1)
    Write-Host "==> Saved $saved percent" -ForegroundColor Green
}

Write-Host "`n==> Largest files still in uploads\:" -ForegroundColor Cyan
Get-ChildItem $Uploads -Recurse -File |
    Where-Object { $_.Length -gt 500KB } |
    Sort-Object Length -Descending |
    Select-Object -First 15 |
    ForEach-Object {
        "{0,8:N1} MB  {1}" -f ($_.Length / 1MB), $_.Name
    }

Write-Host "`nDone." -ForegroundColor Green
Write-Host "Originals are in $Backup\ - do not deploy that folder."