# GitHub Copilot — LandoDEV (lando-installer)

## Repository purpose

**LandoDEV** helps developers run **WordPress on Lando** from a **Laravel + Livewire** UI shipped in **NativePHP Electron**. This repo is the full Laravel application plus scripts to run it as a desktop app.

## Architecture (short)

- **HTTP/UI**: Laravel 12, Livewire full-page components, Flux components, Tailwind v4.
- **Long-running CLI**: Lando commands started via **NativePHP `ChildProcess`**, logs written to files, UI polls with Livewire (`wire:poll` / `pollActionStatus` or `WithCommandExecution::checkCommandStatus`).
- **Data**: Eloquent `Site` model, migrations under `database/migrations/`.
- **Config**: `config/lando_dev/` for default PHP/MariaDB/Redis lists; user overrides may live in `storage/` (see `.gitignore`).

## Developer workflows

| Goal | Command |
|------|---------|
| Install PHP/JS deps | `composer install && npm install` |
| Env + DB | `cp .env.example .env`, `php artisan key:generate`, `touch database/database.sqlite`, `php artisan migrate` |
| Web dev | `composer run dev` or `php artisan serve` + `npm run dev` |
| Desktop dev (macOS) | `composer run native:dev` |
| Frontend build | `npm run build` |
| Tests | `composer run test` or `php artisan test` |
| Format PHP | `./vendor/bin/pint` |

## Code patterns to preserve

- **Navigation**: `route(..., [], false)` for internal links compatible with Electron `127.0.0.1`.
- **Livewire**: Full-page components use `#[Layout('components.layouts.app')]`. Concerns: `WithCommandExecution`, `WithNotifications`.
- **Destroy / async**: Do not remove `Site` until Lando teardown + filesystem removal have finished per existing polling contract; then redirect off `/sites/{id}`.
- **Styling**: Flux-first; custom logo styles in `app.css` (`.app-logo-*`).

## Tech references

- Laravel: https://laravel.com/docs
- Livewire: https://livewire.laravel.com
- Flux: https://flux.laravel.com
- NativePHP: https://nativephp.com
- Lando: https://docs.lando.dev
- Tailwind: https://tailwindcss.com
- Vite: https://vitejs.dev

## Out of scope for blind refactors

Do not rename routes or `Site` binding without updating all Livewire snapshots and sidebar links. Do not change Vite port without checking Electron dev tooling.
