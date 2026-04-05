# LandoDEV — Clone / remote DB dump work (status & handoff)

This document summarizes work on the **clone-from-remote** flow, terminal UX, and repeated **database dump / import / WP-CLI** failures. Use it when opening a **new thread** so the next session does not re-derive context from scratch.

---

## Product context

- **Laravel 12 + Livewire 3 + NativePHP/Electron** desktop app orchestrating **Lando** for local WordPress.
- Clone flow: start Lando → download WP core → **SSH mysqldump** to local → validate → **Lando DB import** → `wp-config` → cleanup dump → **`wp search-replace`** → rsync plugins → etc.
- **Constraint:** Do **not** write dump artifacts on the **remote** server (Cloudways / MySecureShell). Dumps must be produced **locally** only.

---

## Features and code changes delivered (this effort)

### Command logging / subprocess wrapping

- **`PlatformDetector::wrapCommandWithLogRedirect()`** — wraps a step in `( command ) > step.log 2>&1` so redirects like `| gzip > file` are not accidentally tied to the log file (historical bug: empty `.gz`).
- Used from **`WithCommandExecution`** and related flows so step logs stay correct.

### Remote connection preflight

- **`RemoteConnectionVerifier`** — SSH probe + short `mysqldump --no-data` before clone; UI to **retry / fix passwords** on failure; results persisted encrypted on **`RemoteSite`**.
- **`SshService`** aligned with **`SSHPASS=… sshpass -e`** style (see below).

### Database dump pipeline (`SshService::buildMysqldumpCommand`)

Evolution (all aimed at Cloudways + **no remote temp files**):

1. **Streaming `ssh | gzip > local`** — could truncate gzip or produce tiny archives on stream drop; **`pipefail`** added on macOS/Linux.
2. **Remote write + `scp`** — abandoned: policy **no remote files**; also **MySecureShell** mangled `bash -c '…'` (only first word became `-c` argument → `set` dumped environment).
3. **Local `.sql` then `gzip -c`** — mysqldump streams to **`dumpfile.sql`**, then compress to **`dumpfile.sql.gz`**, then delete `.sql` (still no server files).
4. **Heartbeat in step log** — `WithCommandExecution` marks a step complete after **30s with no log writes**. Dump stdout goes to the **SQL file**, so the log stayed quiet and the UI advanced **too early** → partial dump → “too small” / corrupt gzip. **Fix:** background loop printing `[LandoDEV] Database dump in progress …` every **25s** until `ssh` + `gzip` finish, with **`trap`** cleanup.
5. **`SSHPASS` inline for `sshpass -e`** — after heartbeats ran for many minutes, **`sshpass` failed** with:
   `sshpass: -e option given but SSHPASS environment variable not set`  
   **Cause:** `export SSHPASS=…` was not reliably visible to `sshpass` in the **NativePHP/Electron** child shell. **Fix:** use the same pattern as rsync:  
   `SSHPASS='…' sshpass -e ssh …` on the **ssh** invocation (no reliance on `export`).

### Lando DB import (`LandoService::dbImport`)

- **`lando db-import` on `.gz`** could feed raw gzip bytes to `mysql` → `\0` / `--binary-mode` errors.
- **Fix:** `gzip -dc` → temp **`dumpfile.sql`** → **`lando db-import dumpfile.sql`** → remove temp `.sql`.

### Validation (`SiteManager::validateDumpFileCommand`)

- Minimum size check + **`gzip -t`** so truncated archives fail before import.

### Cleanup

- **`removeDumpFileCommand`** removes both **`dumpfile.sql`** and **`dumpfile.sql.gz`** if present.

### Clone UI

- Progress layout: **⅓ steps / ⅔ terminal** on large screens (`lg:grid-cols-3`).
- **Autoscroll:** `WithCommandExecution` / **`SiteDashboard`** dispatch **`landodev-scroll-terminal`**; terminal divs listen with **`@landodev-scroll-terminal.window`** and **`requestAnimationFrame`** scroll; stable **`wire:key`** to avoid remount thrashing.

### Terminal / ASCII art (Lando banners)

- **`resources/css/app.css`**: `#terminal-output` monospace stack + tight **`line-height`**; **`.terminal-line`** uses **`white-space: pre`**, **`tab-size: 8`**, **`font-family: inherit`** so ASCII art aligns without a raw `<pre>` wrapper.

### Remote file cleanup (manual / one-off)

- User requested removal of stray **`landodev_*`** artifacts under **`/tmp`** and searches under **`public_html`** for matching **`.sql` / `.sql.gz`** — documented in chat; app flow no longer creates files on the server for dumps.

---

## Problems encountered (chronological themes)

| Symptom | Likely cause | Direction taken |
|--------|----------------|-----------------|
| Empty / tiny `.gz` | Log redirect stole gzip stdout | Subshell wrap for logging |
| Truncated gzip, import `\0` error | `lando db-import` on raw `.gz` | Decompress to `.sql` then import |
| Truncated gzip from `ssh \| gzip` | Stream ended mid-archive | Local `.sql` then gzip; `pipefail` |
| `bash -c` → env dump (`BASH=…`) | MySecureShell / quoting — `-c` got only `set` | Single remote argv: `mysqldump …` only |
| “Dump too small” shortly after SSH warning | **30s log idle** treated as step done while dump still writing to **file** | Heartbeat lines every 25s |
| Long heartbeat then step **failed** + `SSHPASS` not set | `export` not inherited by `sshpass` in Electron child shell | Inline `SSHPASS=… sshpass -e ssh …` |
| “Replacing domain references” / WP not installed | DB never imported (upstream dump/import failure) | Fix dump/import chain first |

---

## Sample terminal output (user report — mixed success + failure)

Progress was visible (heartbeat worked; dump was **not** marked complete at 30s of silence):

```text
--- [Downloading WordPress core] ---
Downloading WordPress 6.9.4 (en_US)...
md5 hash verified: 9355f37ee9ec885c93e3ca87ba8538d4
Success: WordPress downloaded.

sshpass: -e option given but SSHPASS environment variable not set
[LandoDEV] Database dump in progress 15:05:14
[LandoDEV] Database dump in progress 15:05:39
… (repeats every ~25s) …
[LandoDEV] Database dump in progress 15:14:49
```

Despite the heartbeats, **“Dumping remote database”** was ultimately marked **failed** — consistent with **`sshpass`** not receiving **`SSHPASS`** after `export` (now addressed with inline `SSHPASS=` on the `sshpass` line).

---

## Key files (quick map)

| Area | Path |
|------|------|
| Step runner + 30s idle + scroll dispatch | `app/Livewire/Concerns/WithCommandExecution.php` |
| Log wrap | `app/Services/PlatformDetector.php` |
| mysqldump command | `app/Services/SshService.php` |
| Clone steps, validate, cleanup | `app/Services/SiteManager.php` |
| DB import | `app/Services/LandoService.php` |
| Failure heuristics in logs | `app/Support/CommandLogErrorDetector.php` |
| Preflight SSH/DB | `app/Services/RemoteConnectionVerifier.php` |
| Clone UI | `resources/views/livewire/clone-site.blade.php` |
| Terminal CSS | `resources/css/app.css` |
| SshService tests | `tests/Unit/SshServiceTest.php` |

---

## Suggested next checks (fresh session)

1. Re-run clone after **`SSHPASS=… sshpass -e`** change; confirm **no** `sshpass: … SSHPASS … not set` in the dump step log.
2. If dump still fails: inspect **full step log** under `storage/logs/lando_{site}_step_*` for **ssh / mysqldump / gzip** stderr.
3. Confirm **`sshpass`** exists on the **host** PATH inside the Electron app environment (same as terminal).
4. If **30s idle** bites other silent steps, consider **ChildProcess exit-code polling** instead of log-mtime heuristics (larger change).

---

## Open risks / debt

- **Windows** dump path still uses **`| gzip`** without heartbeat / pipefail parity with macOS/Linux.
- **Very long `gzip`** after ssh: heartbeat runs until **`ssh` returns**; if **`gzip`** alone exceeded 30s with **zero** log lines, idle completion could still fire — monitor if seen.
- **`CommandLogErrorDetector`** includes strings like “Dump file is too small”; ensure step logs remain **per-step files** so completed steps do not false-positive each other.

---

*Last updated from engineering handoff notes — clone / dump / terminal UX thread.*
