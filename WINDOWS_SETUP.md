# Lando Studio — Windows Developer Setup Guide

> **Audience**: Windows developers joining the project. Mac developers should follow the standard `composer run native:dev` flow documented in the README.

---

## Prerequisites

Before starting, ensure you have the following installed on your machine:

- **Git** (https://git-scm.com/download/win)
- **Docker Desktop for Windows** (https://www.docker.com/products/docker-desktop/)
- **Lando** (https://docs.lando.dev/install/windows)

---

## 1. Install PHP 8.4 (portable)

NativePHP embeds its own PHP runtime and only ships binaries for **PHP 8.3 and 8.4**.  
You must use PHP 8.4 on Windows to avoid compatibility issues.

1. Download the PHP 8.4 **Non-Thread Safe (NTS)** zip from https://windows.php.net/download/
2. Extract it to `C:\php`
3. Copy the bundled ini template:
   ```powershell
   Copy-Item C:\php\php.ini-development C:\php\php.ini
   ```
4. Open `C:\php\php.ini` and enable these extensions (remove the leading `;`):
   ```ini
   extension=curl
   extension=fileinfo
   extension=mbstring
   extension=openssl
   extension=pdo_sqlite
   extension=sqlite3
   extension=zip
   ```
5. Add the CA bundle paths (required for Composer HTTPS, see Section 3):
   ```ini
   openssl.cafile = "C:\php\extras\ssl\cacert.pem"
   curl.cainfo    = "C:\php\extras\ssl\cacert.pem"
   ```
6. Add `C:\php` to your Windows **User PATH** environment variable.

> **Note**: Install the [Visual C++ 2015–2022 Redistributable](https://aka.ms/vs/17/release/vc_redist.x64.exe) if PHP fails to launch.

---

## 2. Install Composer (portable)

```powershell
# Download Composer PHAR
Invoke-WebRequest -Uri "https://getcomposer.org/composer.phar" -OutFile "C:\php\composer.phar"

# Create a .bat shim so `composer` works from any terminal
Set-Content C:\php\composer.bat '@echo off
php "C:\php\composer.phar" %*'
```

Verify:
```powershell
composer --version
# Composer version 2.x.x
```

---

## 3. Corporate TLS Certificate (CLTB Canada network only)

If you are on the CLTB Canada corporate network, all HTTPS traffic passes through a TLS proxy. PHP and Node must trust the organisation's root CA.

1. Obtain the corporate CA certificate — ask your team lead or download the `.crt` file from IT.
2. Download the Mozilla CA bundle:
   ```powershell
   New-Item -ItemType Directory -Force -Path C:\php\extras\ssl
   Invoke-WebRequest -Uri "https://curl.se/ca/cacert.pem" -OutFile "C:\php\extras\ssl\cacert.pem"
   ```
3. Append the corporate CA to the bundle:
   ```powershell
   $corp = Get-Content "C:\path\to\CLTBCANADA-ROOT-CA.crt" -Raw
   Add-Content "C:\php\extras\ssl\cacert.pem" "`n$corp"
   ```
4. Persist the environment variables (run once in PowerShell):
   ```powershell
   [System.Environment]::SetEnvironmentVariable("SSL_CERT_FILE",       "C:\php\extras\ssl\cacert.pem", "User")
   [System.Environment]::SetEnvironmentVariable("CURL_CA_BUNDLE",      "C:\php\extras\ssl\cacert.pem", "User")
   [System.Environment]::SetEnvironmentVariable("NODE_EXTRA_CA_CERTS", "C:\php\extras\ssl\cacert.pem", "User")
   ```
5. Open a **new** PowerShell window for the variables to take effect.

---

## 4. Install Node 20 (portable)

NVM is not recommended on Windows for this project (NativePHP compatibility).

1. Download the Node 20 **zip** (not installer) from https://nodejs.org/en/download — choose *Windows Binary (.zip)* → x64.
2. Extract it to `C:\node`.
3. Add `C:\node` to your Windows **User PATH**.
4. Configure npm to use the corporate CA bundle:
   ```powershell
   C:\node\npm.cmd config set cafile "C:\php\extras\ssl\cacert.pem"
   ```

Verify:
```powershell
node --version   # v20.x.x
npm --version    # 10.x.x
```

---

## 5. Clone and bootstrap the project

```powershell
git clone <repo-url> C:\Users\<you>\code\lando-installer
cd C:\Users\<you>\code\lando-installer

# Install PHP dependencies
composer install

# Environment file
Copy-Item .env.example .env
php artisan key:generate

# Database
New-Item -ItemType File -Path database\database.sqlite -Force
php artisan migrate

# Frontend assets
npm install
npm run build
```

---

## 6. One-time NativePHP / Electron setup

These steps are required **once** after cloning (and must be repeated after `composer install` / `composer update`).

### 6a. Install Electron's internal npm dependencies

```powershell
$env:NODE_EXTRA_CA_CERTS = "C:\php\extras\ssl\cacert.pem"
cd vendor\nativephp\electron\resources\js
npm install
cd ..\..\..\..\..\   # back to project root
```

### 6b. Create the PHP 8.4 stub for NativePHP

NativePHP only ships PHP 8.3 and 8.4 binaries. If your system PHP version differs, copy the 8.4 zip so Electron can find it:

```powershell
$ver = php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;"
$dir = "vendor\nativephp\php-bin\bin\win\x64"
if (-not (Test-Path "$dir\php-${ver}.zip")) {
    Copy-Item "$dir\php-8.4.zip" "$dir\php-${ver}.zip"
    Write-Host "Stub created: php-${ver}.zip"
}
```

> ⚠️ Re-run this after every `composer install` or `composer update` because `vendor/` is regenerated.

---

## 7. Daily development workflow

### Start the desktop app (Electron)

```powershell
composer run native:dev:win
```

This is the Windows equivalent of `composer run native:dev` used by Mac developers.  
It starts NativePHP (`artisan native:serve`) and Vite in parallel, then opens the Electron window.

### Start the web-only dev server (no Electron)

Use this if you only need the browser UI at http://127.0.0.1:8000:

```powershell
npx concurrently -c "#93c5fd,#c4b5fd,#fb7185" `
  "php artisan serve" `
  "php artisan queue:work --tries=1 --timeout=0" `
  "npm run dev"
```

> `php artisan pail` is not available on Windows (requires `pcntl` extension). Use `php artisan queue:work` instead.

### Run tests

```powershell
composer run test
```

### Format PHP code

```powershell
.\vendor\bin\pint
```

---

## 8. What was added to this repo for Windows support

| File | Purpose |
|------|---------|
| `scripts/native-dev.ps1` | PowerShell equivalent of `scripts/native-dev.sh` |
| `composer.json` → `native:dev:win` | Composer script entry that calls the PS1 file |

No existing scripts or Mac workflows were modified.

---

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| `SSL certificate` errors in Composer/npm | Section 3 — corporate CA not appended or env vars not set |
| `php-8.x.zip not found` / Electron crash | Section 6b — re-run the stub copy after `composer install` |
| `The [pcntl] extension is required` | Use `queue:work` instead of `pail` (Section 7) |
| `bash: No such file or directory` | Use `composer run native:dev:win` not `composer run native:dev` |
| `ChildProcess::start() failed: cURL error 7` | Electron bridge not running — start app via `composer run native:dev:win` |
| Electron window does not appear | Ensure Docker Desktop is running; check `storage/logs/laravel.log` |
