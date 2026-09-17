# Create or reset admin@admin.com / 12345678 on the live Render Postgres.
# Usage (from server/):
#   .\scripts\ensure-live-admin.ps1
#   .\scripts\ensure-live-admin.ps1 -DatabaseUrl "postgresql://..."

param(
    [string]$DatabaseUrl = ""
)

$ErrorActionPreference = "Stop"
$root = Split-Path $PSScriptRoot -Parent
Set-Location $root

if (-not $DatabaseUrl) {
    $DatabaseUrl = $env:LIVE_DATABASE_URL
}

if (-not $DatabaseUrl) {
    $liveEnv = Join-Path $root ".env.live"
    if (Test-Path $liveEnv) {
        Get-Content $liveEnv | ForEach-Object {
            if ($_ -match '^\s*(?:LIVE_)?DATABASE_URL=(.*)$') {
                $DatabaseUrl = $Matches[1].Trim().Trim('"').Trim("'")
            }
        }
    }
}

if (-not $DatabaseUrl) {
    Write-Error "Set DATABASE_URL in server/.env.live (see .env.live.example) or pass -DatabaseUrl."
}

# Render Internal hosts have no dots and are unreachable from a laptop.
$hostPart = ([Uri]$DatabaseUrl.Replace("postgresql://", "http://")).Host
if ($hostPart -and $hostPart -notmatch '\.') {
    Write-Warning "Host '$hostPart' looks like a Render Internal URL. From local you need the External Database URL (hostname ends in .render.com)."
}

if ($DatabaseUrl -notmatch 'sslmode=') {
    if ($DatabaseUrl.Contains('?')) { $DatabaseUrl = "$DatabaseUrl&sslmode=require" }
    else { $DatabaseUrl = "$DatabaseUrl`?sslmode=require" }
}

$env:DB_CONNECTION = "pgsql"
$env:DB_SSLMODE = "require"
$env:DB_URL = $DatabaseUrl
$env:DATABASE_URL = $DatabaseUrl

Write-Host "Connecting to live Postgres and ensuring admin@admin.com ..."
php artisan fieldops:ensure-admin
