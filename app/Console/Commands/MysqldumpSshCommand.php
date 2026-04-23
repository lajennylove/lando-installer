<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RemoteSite;
use App\Services\DependencyChecker;
use Illuminate\Console\Command;

/**
 * Windows-only: stream mysqldump over SSH using plink, then compress with PHP's
 * native GZipStream. Replaces the Unix `sshpass -e ssh ... | gzip` pipeline.
 *
 * Writes heartbeat lines to stdout every 20 s so WithCommandExecution's 30 s
 * log-inactivity timer doesn't fire early during a long dump.
 *
 * stdout is captured by the parent shell's *> redirect into the step log file.
 * The .sql.gz file is written directly to disk (not via stdout).
 */
class MysqldumpSshCommand extends Command
{
    protected $signature = 'lando:mysqldump-ssh
        {--remote-id= : ID of the RemoteSite model}
        {--output=    : Local path for the output .sql.gz file}';

    protected $description = 'Stream mysqldump over SSH via plink and compress locally (Windows)';

    public function handle(DependencyChecker $checker): int
    {
        $remoteId = $this->option('remote-id');
        $outputPath = $this->option('output');

        if (! $remoteId || ! $outputPath) {
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

        $sqlPath = (string) preg_replace('/\.sql\.gz$/u', '.sql', $outputPath);
        $target = "{$remote->ssh_user}@{$remote->ssh_server_ip}";

        // Build the remote mysqldump invocation.
        // The command runs on a remote LINUX bash shell via plink, so we must use
        // bash-style single-quote escaping. Windows' escapeshellarg() wraps in
        // double-quotes which causes bash to interpret $, !, ` inside passwords,
        // and can even strip characters like '!' entirely.
        $remoteDump = sprintf(
            'mysqldump -u %s -p%s %s',
            $this->bashEscape((string) $remote->db_user),
            $this->bashEscape((string) $remote->db_password),
            $this->bashEscape((string) $remote->db_name),
        );

        $this->line("[Lando Studio] Connecting to {$target} via plink...");
        $this->line("[Lando Studio] Running: mysqldump {$remote->db_name} → {$outputPath}");

        $cmd = [
            $plinkPath,
            '-batch',
            '-pw', (string) $remote->ssh_password,
            '-T',
            $target,
            $remoteDump,
        ];

        // On Windows, stream_set_blocking(false) silently fails on proc_open pipes,
        // causing fread() to block indefinitely. Instead, write plink's stdout directly
        // to the SQL file via a file descriptor, and capture stderr in a temp file.
        // We poll file size growth for heartbeats and proc_get_status for completion.
        $stderrPath = $sqlPath.'.stderr';

        $descriptors = [
            0 => ['pipe', 'r'],               // stdin  (child reads — closed immediately)
            1 => ['file', $sqlPath, 'w'],      // stdout → SQL data written directly to disk
            2 => ['file', $stderrPath, 'w'],   // stderr → captured for error reporting
        ];

        $proc = proc_open($cmd, $descriptors, $pipes);
        if (! is_resource($proc)) {
            $this->error('[Lando Studio ERROR] Failed to start plink process.');

            return self::FAILURE;
        }

        fclose($pipes[0]); // close stdin

        $lastHeartbeat = time();
        $lastSize = 0;

        while (true) {
            $status = proc_get_status($proc);

            if (! $status['running']) {
                break;
            }

            if (time() - $lastHeartbeat >= 20) {
                clearstatcache(true, $sqlPath);
                $currentSize = file_exists($sqlPath) ? (int) filesize($sqlPath) : 0;
                $mb = round($currentSize / 1_048_576, 1);
                $this->line('[Lando Studio] Database dump in progress '.date('H:i:s')." ({$mb} MB received)");
                $lastHeartbeat = time();
                $lastSize = $currentSize;
            }

            usleep(500_000); // 500 ms poll interval
        }

        $exitCode = proc_close($proc);

        // Report any stderr from plink/mysqldump
        if (file_exists($stderrPath)) {
            $stderr = trim((string) file_get_contents($stderrPath));
            if ($stderr !== '') {
                $this->line('[plink] '.$stderr);
            }
            @unlink($stderrPath);
        }

        clearstatcache(true, $sqlPath);
        $bytesReceived = file_exists($sqlPath) ? (int) filesize($sqlPath) : 0;

        if ($exitCode !== 0) {
            @unlink($sqlPath);
            $this->error("[Lando Studio ERROR] plink/mysqldump exited with code {$exitCode}. Check SSH credentials and server logs.");

            return self::FAILURE;
        }

        $mb = round($bytesReceived / 1_048_576, 1);
        $this->line("[Lando Studio] Dump received: {$mb} MB. Compressing to .sql.gz...");

        // Compress .sql → .sql.gz using PHP's native gzip support (no external tool needed)
        $inFile = fopen($sqlPath, 'rb');
        $gzFile = gzopen($outputPath, 'wb9');

        if (! $inFile || ! $gzFile) {
            $this->error('[Lando Studio ERROR] Cannot open files for gzip compression.');

            return self::FAILURE;
        }

        while (! feof($inFile)) {
            gzwrite($gzFile, (string) fread($inFile, 65536));
        }

        gzclose($gzFile);
        fclose($inFile);
        @unlink($sqlPath);

        $sizeMb = round(filesize($outputPath) / 1_048_576, 1);
        $this->line("[Lando Studio] Dump compressed: {$sizeMb} MB → {$outputPath}");

        return self::SUCCESS;
    }

    /**
     * Escape a string for use inside a bash command line using single quotes.
     * This is needed because the remote command runs on Linux via plink, and
     * PHP's escapeshellarg() on Windows uses double-quotes which allow bash
     * to interpret $, !, ` and other metacharacters.
     */
    private function bashEscape(string $value): string
    {
        return "'".str_replace("'", "'\\''", $value)."'";
    }
}
