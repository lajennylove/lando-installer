# Windows equivalent of native-dev.sh
# Run from project root: composer run native:dev:win
# Or directly: powershell -ExecutionPolicy Bypass -File scripts/native-dev.ps1

$ErrorActionPreference = "Stop"

# Locate Node 20+ -- check well-known Windows locations before falling back to PATH
$nodeCandidates = @(
    "$env:LOCALAPPDATA\node20",
    "$env:LOCALAPPDATA\node22",
    "C:\tools\node20",
    "C:\tools\node22",
    "C:\Program Files\nodejs"
)
$nodeDir = $null
foreach ($candidate in $nodeCandidates) {
    $exe = Join-Path $candidate "node.exe"
    if (Test-Path $exe) {
        $ver = & $exe --version 2>$null
        if ($ver -match '^v(2[0-9]|[3-9][0-9])\.') {
            $nodeDir = $candidate
            break
        }
    }
}
if (-not $nodeDir) {
    $nodeCmdObj = Get-Command node -ErrorAction SilentlyContinue
    if (-not $nodeCmdObj) { throw "[native-dev] Node.js not found. Install Node.js 20+ and add it to your PATH." }
    $nodeDir = Split-Path $nodeCmdObj.Source
    $ver = & (Join-Path $nodeDir "node.exe") --version 2>$null
    Write-Host "[native-dev] WARNING: Using Node $ver -- Vite requires Node.js 20.19+ or 22.12+" -ForegroundColor Yellow
}

$npxCmd = Join-Path $nodeDir "npx.cmd"
$npmCmd = Join-Path $nodeDir "npm.cmd"

Write-Host "[native-dev] Using node: $nodeDir ($((& (Join-Path $nodeDir 'node.exe') --version)))" -ForegroundColor DarkGray

# Locate PHP 8.4+ -- check well-known Windows locations before falling back to PATH
$php84Candidates = @(
    "C:\tools\php84\php.exe",
    "C:\php84\php.exe",
    "C:\php\php.exe"
)
$phpCmd = $null
foreach ($candidate in $php84Candidates) {
    if (Test-Path $candidate) {
        $ver = & $candidate -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" 2>$null
        if ($ver -match '^8\.[4-9]' -or $ver -match '^[9-9]\.') {
            $phpCmd = $candidate
            break
        }
    }
}
if (-not $phpCmd) {
    $phpCmdObj = Get-Command php -ErrorAction SilentlyContinue
    if (-not $phpCmdObj) { throw "[native-dev] 'php' not found. Install PHP 8.4+ and add it to your PATH." }
    $phpCmd = $phpCmdObj.Source
    $ver = & $phpCmd -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" 2>$null
    Write-Host "[native-dev] WARNING: Using PHP $ver -- this project requires PHP >= 8.4.0" -ForegroundColor Yellow
}

Write-Host "[native-dev] Using php : $phpCmd" -ForegroundColor DarkGray

# Corporate TLS certificate (optional -- only applied if found alongside the PHP binary)
$certBundle = Join-Path (Split-Path $phpCmd) "extras\ssl\cacert.pem"
if (Test-Path $certBundle) {
    $env:NODE_EXTRA_CA_CERTS = $certBundle
    $env:SSL_CERT_FILE        = $certBundle
    $env:CURL_CA_BUNDLE       = $certBundle
}

# NativePHP only ships PHP 8.3/8.4 binaries. If current PHP is newer,
# create a stub zip from 8.4 so Electron's php.js can unpack it.
$phpMinor = (& $phpCmd -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
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
    & $npmCmd install
    Pop-Location
}

# Prepend the chosen Node directory so all child processes (npm, vite) use the right version
$env:PATH = "$nodeDir;" + $env:PATH

# Start NativePHP + Vite
$root = Join-Path $PSScriptRoot ".."
Set-Location $root

Write-Host "[native-dev] Starting NativePHP Electron app + Vite..." -ForegroundColor Green

& $npxCmd concurrently -c "#93c5fd,#c4b5fd" `
    "$phpCmd artisan native:serve --no-dependencies --no-interaction -vvv" `
    "npm run dev" `
    --names=app,vite --kill-others-on-fail