# LandoDEV — Claude / AI assistant context

This file documents the **lando-installer** (LandoDEV) codebase so coding agents can maintain it without rediscovering architecture each session.

## What this repository is

A **desktop-first** developer tool: **Electron** shell (via **NativePHP**) running a **Laravel** web app that controls **Lando** to run **WordPress** locally. Users create sites, clone/remotes, change PHP/DB/Redis versions (YAML regen + rebuild), start/stop/destroy apps, and view streamed CLI output in the UI.

## Stack (authoritative)

- **Backend**: PHP 8.2+, Laravel 12
- **Frontend**: Livewire 3, Flux UI (`livewire/flux`), Tailwind CSS v4, Vite 7
- **Desktop**: `nativephp/electron` — `composer run native:dev` runs `native:serve` + `npm run dev` (see `scripts/native-dev.sh`)
- **External**: Lando CLI, Docker, WordPress inside Lando containers

## Routing (see `routes/web.php`)

| Route | Component | Notes |
|-------|-----------|--------|
| `/setup` | `DependencyCheck` | First-run checks |
| `/` | closure | Redirect latest site or `/create` |
| `/create` | `CreateSite` | Hub |
| `/create/new` | `NewSite` | New site wizard (`WithCommandExecution`) |
| `/create/clone` | `CloneSite` | Clone flow |
| `/sites/{site}` | `SiteDashboard` | Per-site controls; `Site` model binding |
| `/settings` | `Settings` | Defaults JSON in storage (gitignored file name in `.gitignore`) |
| `/about` | `About` | Version info |

## Important behaviors

1. **URLs in Electron**: App often runs at `http://127.0.0.1:8100`. Links and redirects should use **relative** URLs (`route('name', [], false)`) so Livewire navigate does not jump to wrong host from `APP_URL`.

2. **Site destroy**: Must wait for Lando + delete path in background log; on completion, remove DB row and redirect with **non-SPA** full navigation where needed to avoid 404 races on deleted `{site}` routes.

3. **ANSI in logs**: Blade uses `App\Support\AnsiToHtml::lineToHtml()` for terminal blocks.

4. **Logo UI**: `public/assets/rocket.png`; Flux `brand` view overridden under `resources/views/flux/brand.blade.php` (logo `size-[60px]` on `<img>`, no extra rounded wrapper).

5. **Vite port 5175** — conflicts avoided with Electron’s 5173.

## Files agents edit often

- `app/Livewire/SiteDashboard.php` — actions, destroy flow, `pollActionStatus`
- `app/Services/SiteManager.php` — lando commands, paths
- `resources/views/livewire/*.blade.php` — UI
- `config/lando_dev/defaults.php` — version dropdown source

## Guardrails

Match existing code style; run **Pint** on PHP; run **tests** after non-trivial changes. Do not commit secrets or local-only storage state.
