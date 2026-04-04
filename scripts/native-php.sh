#!/usr/bin/env bash
# Host PHP for the LandoDEV desktop app only (NativePHP + Electron).
# Use Homebrew PHP here so `artisan native:serve` matches nativephp/php-bin zips (8.3 / 8.4).
# This is unrelated to Settings → default PHP for WordPress sites; that value is applied inside Lando.
#
# NativePHP embeds PHP from nativephp/php-bin (only 8.3 and 8.4 zips are shipped).
# Running `artisan` with PHP 8.5+ makes php.js look for php-8.5.zip and fail (ENOENT).
# This picks a PHP 8.3/8.4 binary when the default `php` is not one of those.

set -euo pipefail

php_minor_series() {
  php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;'
}

pick_nativephp_php() {
  local series
  series="$(php_minor_series)"
  case "$series" in
    8.3|8.4)
      echo "php"
      return
      ;;
  esac

  if command -v php8.4 >/dev/null 2>&1; then
    echo "php8.4"
    return
  fi
  if [[ -x "/opt/homebrew/opt/php@8.4/bin/php" ]]; then
    echo "/opt/homebrew/opt/php@8.4/bin/php"
    return
  fi
  if [[ -x "/usr/local/opt/php@8.4/bin/php" ]]; then
    echo "/usr/local/opt/php@8.4/bin/php"
    return
  fi

  if command -v php8.3 >/dev/null 2>&1; then
    echo "php8.3"
    return
  fi
  if [[ -x "/opt/homebrew/opt/php@8.3/bin/php" ]]; then
    echo "/opt/homebrew/opt/php@8.3/bin/php"
    return
  fi

  echo "NativePHP's embedded PHP only ships 8.3 and 8.4 binaries. Your \`php\` is ${series}." >&2
  echo "Install PHP 8.4 and expose it as php8.4 or at:" >&2
  echo "  /opt/homebrew/opt/php@8.4/bin/php" >&2
  echo "Example: brew install php@8.4 && brew link php@8.4 --force --overwrite" >&2
  exit 1
}

pick_nativephp_php
