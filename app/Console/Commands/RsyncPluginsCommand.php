<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RemoteSite;
use App\Services\DependencyChecker;
use Illuminate\Console\Command;

/**
 * Windows-only: download wp-content/plugins from a remote server using pscp
 * (PuTTY's SCP client, bundled with plink). Replaces the Unix rsync pipeline.
 *
 * Writes heartbeat lines to stdout every 20 s so WithCommandExecution's 30 s
 * log-inactivity timer doesn't fire early on large plugin directories.
 */
class RsyncPluginsCommand extends Command
{
    protected $signature = 'lando:rsync-plugins
        {--remote-id= : ID of the RemoteSite model}
        {--output=    : Local path for the plugins directory}';

    protected $description = 'Download wp-content/plugins from remote via pscp (Windows, replaces rsync)';

    public function handle(DependencyChecker $checker): int
    {
        $remoteId = $this->option('remote-id');
        $localPluginsPath = $this->option('output');

        if (! $remoteId || ! $localPluginsPath) {
            $this->error('[Lando Studio ERROR] --remote-id and --output are required.');

            return self::FAILURE;
        }

        $remote = RemoteSite::find($remoteId);
        if (! $remote) {
            $this->error("[Lando Studio ERROR] RemoteSite #{$remoteId} not found.");

            return self::FAILURE;
        }

        $plinkPath = $checker->getPlinkPath();
        if (! $plinkPath) {
            $this->error('[Lando Studio ERROR] PuTTY (plink.exe) is not installed. Install it from Settings > System Dependencies.');

            return self::FAILURE;
        }

        // pscp.exe ships alongside plink.exe in the same PuTTY install directory
        $pscpPath = str_replace('plink.exe', 'pscp.exe', $plinkPath);

        if (! file_exists($pscpPath)) {
            $this->error("[Lando Studio ERROR] pscp.exe not found at {$pscpPath}. Reinstall PuTTY.");

            return self::FAILURE;
        }

        $remotePlugins = $this->remotePluginsDirectory($remote);
        $host = "{$remote->ssh_user}@{$remote->ssh_server_ip}";

        $this->line("[Lando Studio] Syncing plugins from {$host}:{$remotePlugins}");
        $this->line("[Lando Studio] → {$localPluginsPath}");

        if (! is_dir($localPluginsPath)) {
            mkdir($localPluginsPath, 0755, true);
        }

        $cmd = [
            $pscpPath,
            '-batch',
            '-scp',  // force SCP protocol — SFTP mode fails on absolute paths
            '-pw', (string) $remote->ssh_password,
            '-r',  // recursive
            "{$host}:{$remotePlugins}",
            rtrim($localPluginsPath, '/\\'),
        ];

        // On Windows, stream_set_blocking(false) silently fails on proc_open pipes,
        // causing fread() to block indefinitely. Use temp files for stdout/stderr
        // and poll proc_get_status for completion.
        $stdoutPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lando-pscp-'.uniqid().'.out';
        $stderrPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lando-pscp-'.uniqid().'.err';

        $descriptors = [
            0 => ['pipe', 'r'],                // stdin  (child reads — closed immediately)
            1 => ['file', $stdoutPath, 'w'],   // stdout → pscp progress
            2 => ['file', $stderrPath, 'w'],   // stderr → error messages
        ];

        $proc = proc_open($cmd, $descriptors, $pipes);
        if (! is_resource($proc)) {
            $this->error('[Lando Studio ERROR] Failed to start pscp process.');

            return self::FAILURE;
        }

        fclose($pipes[0]);

        $lastHeartbeat = time();

        while (true) {
            $status = proc_get_status($proc);

            if (! $status['running']) {
                break;
            }

            if (time() - $lastHeartbeat >= 20) {
                $this->line('[Lando Studio] Plugin sync in progress '.date('H:i:s'));
                $lastHeartbeat = time();
            }

            usleep(500_000);
        }

        $exitCode = proc_close($proc);

        // Report stdout (pscp progress)
        if (file_exists($stdoutPath)) {
            $out = trim((string) file_get_contents($stdoutPath));
            if ($out !== '') {
                $this->line($out);
            }
            @unlink($stdoutPath);
        }

        // Report stderr
        if (file_exists($stderrPath)) {
            $err = trim((string) file_get_contents($stderrPath));
            if ($err !== '') {
                $this->line('[pscp] '.$err);
            }
            @unlink($stderrPath);
        }

        if ($exitCode !== 0) {
            $this->error("[Lando Studio ERROR] pscp exited with code {$exitCode}. Check SSH credentials.");

            return self::FAILURE;
        }

        $this->line('[Lando Studio] Plugins synced successfully.');

        return self::SUCCESS;
    }

    private function remotePluginsDirectory(RemoteSite $remote): string
    {
        $root = $remote->remote_path;
        if (is_string($root) && $root !== '') {
            return rtrim($root, '/').'/wp-content/plugins/';
        }

        return "applications/{$remote->db_name}/public_html/wp-content/plugins/";
    }
}
