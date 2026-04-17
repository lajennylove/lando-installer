<?php

namespace App\Services;

use App\Models\RemoteSite;
use Illuminate\Support\Facades\Log;

class SshService
{
    public function __construct(
        private PlatformDetector $platform,
    ) {}

    /**
     * Build a mysqldump command over SSH using sshpass with environment variable (more secure
     * than embedding passwords in command strings, and avoids shell escaping issues).
     *
     * Streams mysqldump to a **local** `.sql` file, then runs `gzip -c` on disk (still no
     * remote files). Piping `ssh | gzip` can yield a truncated `.gz` while ssh exits 0 if the
     * stream breaks mid-archive; gzip then fails `gzip -t` / import.
     *
     * Hosts such as Cloudways/MySecureShell break `bash -c 'script'`. A single remote argv
     * `mysqldump ...` matches the preflight verifier.
     *
     * Uses `set -o pipefail` before ssh on macOS/Linux so a broken `ssh` fails the step.
     *
     * A background heartbeat line is printed every 25s while dumping: WithCommandExecution
     * treats 30s of log inactivity as step complete, but dump stdout goes to the .sql file so the
     * step log would otherwise stay quiet for minutes and complete too early (partial .sql/.gz).
     * Password is passed as `SSHPASS=… sshpass -e` on the ssh invocation (not `export`), because
     * NativePHP/Electron child shells have been observed to run `sshpass` without a visible SSHPASS.
     */
    public function buildMysqldumpCommand(RemoteSite $remote, string $localDumpPath): string
    {
        $sshPass = (string) $remote->ssh_password;
        $dbPass = (string) $remote->db_password;

        Log::debug('SshService::buildMysqldumpCommand', [
            'remote_id' => $remote->id,
            'ssh_server' => $remote->ssh_server_ip,
            'ssh_user' => $remote->ssh_user,
            'db_user' => $remote->db_user,
            'db_name' => $remote->db_name,
            'ssh_pass_length' => strlen($sshPass),
            'db_pass_length' => strlen($dbPass),
            'db_pass_first_3' => substr($dbPass, 0, 3),
        ]);

        if ($dbPass === '') {
            Log::error('SshService::buildMysqldumpCommand empty DB password');
        }

        $target = escapeshellarg("{$remote->ssh_user}@{$remote->ssh_server_ip}");
        // -T: no TTY (less noise on stdout from login-style shells).
        $sshOpts = '-T -o StrictHostKeyChecking=no -o ServerAliveInterval=60 -o ServerAliveCountMax=6';
        // Inline SSHPASS (same pattern as rsync below): some Electron/NativePHP shells drop `export`
        // so `sshpass -e` can run without SSHPASS and fail after a long heartbeat-only dump.
        $sshpassSsh = 'SSHPASS='.escapeshellarg($sshPass).' sshpass -e ssh '.$sshOpts;

        $remoteMysql = sprintf(
            'mysqldump -u %s -p%s %s',
            escapeshellarg($remote->db_user),
            escapeshellarg($dbPass),
            escapeshellarg($remote->db_name),
        );
        $remoteArg = escapeshellarg($remoteMysql);
        $localGz = escapeshellarg($localDumpPath);
        $localSqlPath = preg_replace('/\.sql\.gz$/u', '.sql', $localDumpPath);
        $localSql = escapeshellarg($localSqlPath);

        if ($this->platform->isWindows()) {
            // On Windows, sshpass and gzip are unavailable. Delegate to a PHP artisan command
            // that uses plink (PuTTY) for SSH and PHP's native GZipStream for compression.
            // The command writes heartbeat lines to stdout so the step log stays active during
            // long dumps (WithCommandExecution treats 30 s of log inactivity as step complete).
            $php = '"'.str_replace('"', '""', PHP_BINARY).'"';
            $artisan = '"'.str_replace('"', '""', base_path('artisan')).'"';
            $output = '"'.str_replace('"', '""', $localDumpPath).'"';

            return "{$php} {$artisan} lando:mysqldump-ssh --remote-id={$remote->id} --output={$output}";
        }

        $trapCleanup = escapeshellarg('kill $LANDODEV_HB 2>/dev/null; wait $LANDODEV_HB 2>/dev/null');

        // Use ';' (not '&&') so rm -f runs synchronously BEFORE the heartbeat background job starts.
        // '&&' has higher precedence than '&', so "rm && heartbeat &" would background the whole
        // AND-list — rm and sshpass would then race, rm could delete the file after '>' creates it
        // but before SSH finishes writing, causing "gzip: can't stat: dumpfile.sql".
        // Drop the extra '( )' around the while loop: with "( while ) &", $! is the outer subshell
        // and "kill $LANDODEV_HB" leaves the inner while-loop subshell orphaned (heartbeats keep
        // printing for minutes). Without '( )', $! IS the while-loop's bash process; killing it
        // stops the loop (the in-flight 'sleep 20' child finishes at most 20s later, then stops).
        //
        // Heartbeat interval: 20s (down from 25s).
        // Real-world test (betus.com.pa, 4.4 GB DB → 2.5 GB SQL → 454 MB .gz):
        //   SSH mysqldump  ≈ 95s
        //   gzip -c        ≈ 45s
        //   Total          ≈ 140s
        // WithCommandExecution marks a step done after 30s of log inactivity. The heartbeat is
        // the only thing keeping the log alive during both phases. 20s gives a 10s safety margin
        // vs the 5s margin of the previous 25s interval — enough headroom under CPU/IO load.
        return 'rm -f '.$localSql.' '.$localGz
            .'; while sleep 20; do echo "[Lando Studio] Database dump in progress $(date -u +%H:%M:%S)"; done & LANDODEV_HB=$!'
            .' && trap '.$trapCleanup.' EXIT'
            .' && set -o pipefail && '.$sshpassSsh.' '.$target.' '.$remoteArg.' > '.$localSql
            .' && gzip -c '.$localSql.' > '.$localGz.' && rm -f '.$localSql
            .' && kill $LANDODEV_HB 2>/dev/null && wait $LANDODEV_HB 2>/dev/null && trap - EXIT';
    }

    public function buildRsyncPluginsCommand(RemoteSite $remote, string $localPluginsPath): string
    {
        if ($this->platform->isWindows()) {
            // rsync is unavailable on Windows. Delegate to a PHP artisan command that uses
            // pscp (PuTTY SCP, bundled with plink) for recursive plugin download.
            $php = '"'.str_replace('"', '""', PHP_BINARY).'"';
            $artisan = '"'.str_replace('"', '""', base_path('artisan')).'"';
            $output = '"'.str_replace('"', '""', $localPluginsPath).'"';

            return "{$php} {$artisan} lando:rsync-plugins --remote-id={$remote->id} --output={$output}";
        }

        $remotePlugins = $this->remotePluginsDirectory($remote);
        $host = "{$remote->ssh_user}@{$remote->ssh_server_ip}";
        $remoteArg = escapeshellarg($remotePlugins);
        $localArg = escapeshellarg($localPluginsPath);

        // -q: quiet (no per-file listing); avoids huge logs and oversized Livewire payloads
        // Use SSHPASS env var approach for consistency
        return 'SSHPASS='.escapeshellarg($remote->ssh_password)." sshpass -e rsync -azq -e 'ssh -o StrictHostKeyChecking=no' {$host}:{$remoteArg} {$localArg}";
    }

    /**
     * Path to wp-content/plugins on the remote host (rsync source).
     * Prefers explicit {@see RemoteSite::$remote_path}; otherwise legacy pattern relative to SSH home.
     */
    public function remotePluginsDirectory(RemoteSite $remote): string
    {
        $root = $remote->remote_path;
        if (is_string($root) && $root !== '') {
            return rtrim($root, '/').'/wp-content/plugins/';
        }

        return "applications/{$remote->db_name}/public_html/wp-content/plugins/";
    }

    public function buildHtaccessRewriteContent(string $remoteDomain): string
    {
        $remoteDomain = rtrim($remoteDomain, '/');

        return <<<HTACCESS
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^index\.php\$ - [L]

# Proxy missing uploads to the remote server instead of downloading the full uploads folder
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_URI} ^/wp-content/uploads/
RewriteRule ^wp-content/uploads/(.*)\$ {$remoteDomain}/wp-content/uploads/\$1 [R=301,L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
HTACCESS;
    }
}
