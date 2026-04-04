#!/usr/bin/env bash
# NativePHP dev server only (LandoDEV desktop UI). Host PHP from Homebrew — not Lando’s PHP for sites.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

PHP_BIN="$(bash "$(dirname "$0")/native-php.sh")"

export COMPOSER_DISABLE_DEV_TIMEOUT=1

exec "$PHP_BIN" artisan native:serve --no-dependencies -vvv
