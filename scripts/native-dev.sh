#!/usr/bin/env bash
# Run the NativePHP/Electron shell + Vite for this repo. Host PHP is from Homebrew (see native-php.sh).
# Lando / container PHP versions for WordPress sites are configured in-app (Settings), not here.
#
# Run this from Terminal.app or Cursor's terminal on your Mac (not over SSH) so Electron can show a window.
# Laravel Vite is on port 5175 (see vite.config.js); Electron's dev bundler uses 5173 internally.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

PHP_BIN="$(bash "$(dirname "$0")/native-php.sh")"

export COMPOSER_DISABLE_DEV_TIMEOUT=1

exec npx concurrently -c "#93c5fd,#c4b5fd" \
  "$PHP_BIN artisan native:serve --no-dependencies --no-interaction -vvv" \
  "npm run dev" \
  --names=app,vite --kill-others-on-fail
