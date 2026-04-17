# Windows equivalent of native-dev.sh
# Run from project root: composer run native:dev:win
# Or directly: powershell -ExecutionPolicy Bypass -File scripts/native-dev.ps1

$ErrorActionPreference = "Stop"

# Ensure node and php are in PATH
$env:PATH = "C:\node;C:\php;" + $env:PATH

# Corporate TLS certificate (required for this machine's MITM proxy)
$certBundle = "C:\php\extras\ssl\cacert.pem"
if (Test-Path $certBundle) {
    $env:NODE_EXTRA_CA_CERTS = $certBundle
    $env:SSL_CERT_FILE        = $certBundle
    $env:CURL_CA_BUNDLE       = $certBundle
}

# NativePHP only ships PHP 8.3/8.4 binaries. If current PHP is newer,
# create a stub zip from 8.4 so Electron's php.js can unpack it.
$phpMinor = (& C:\php\php.exe -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
$binDir   = Join-Path $PSScriptRoot "..\vendor\nativephp\php-bin\bin\win\x64"
$stubZip  = Join-Path $binDir "php-${phpMinor}.zip"
$base84   = Join-Path $binDir "php-8.4.zip"

if (-not (Test-Path $stubZip) -and (Test-Path $base84)) {
    Write-Host "[native-dev] php-${phpMinor}.zip not found - copying php-8.4.zip as stub" -ForegroundColor Yellow
    Copy-Item $base84 $stubZip
}

# Install Electron npm deps if missing
$electronJs = Join-Path $PSScriptRoot "..\vendor\nativephp\electron\resources\js"
if (-not (Test-Path (Join-Path $electronJs "node_modules\electron"))) {
    Write-Host "[native-dev] Installing Electron npm dependencies..." -ForegroundColor Cyan
    Push-Location $electronJs
    & C:\node\npm.cmd install
    Pop-Location
}

# Start NativePHP + Vite
$root = Join-Path $PSScriptRoot ".."
Set-Location $root

Write-Host "[native-dev] Starting NativePHP Electron app + Vite..." -ForegroundColor Green

& C:\node\npx.cmd concurrently -c "#93c5fd,#c4b5fd" `
    "C:\php\php.exe artisan native:serve --no-dependencies --no-interaction -vvv" `
    "C:\node\npm.cmd run dev" `
    --names=app,vite --kill-others-on-fail
