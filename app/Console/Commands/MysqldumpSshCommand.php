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
        // escapeshellarg() on Windows uses double-quotes; bash on the remote server accepts them.
        $remoteDump = sprintf(
            'mysqldump -u %s -p%s %s',
            escapeshellarg((string) $remote->db_user),
            escapeshellarg((string) $remote->db_password),
            escapeshellarg((string) $remote->db_name),
        );

        $this->line("[Lando Studio] Connecting to {$target} via plink...");
        $this->line("[Lando Studio] Running: mysqldump {$remote->db_name} → {$outputPath}");

        $cmd = [
            $plinkPath,
            '-batch',
            '-pw', (string) $remote->ssh_password,
            '-oStrictHostKeyChecking=no',
            '-oServerAliveInterval=60',
            '-oServerAliveCountMax=6',
            '-T',
            $target,
            $remoteDump,
        ];

        $descriptors = [
            0 => ['pipe', 'r'],  // stdin  (closed immediately)
            1 => ['pipe', 'r'],  // stdout → raw SQL data written to disk
            2 => ['pipe', 'r'],  // stderr → forwarded to our stdout (goes to log)
        ];

        $proc = proc_open($cmd, $descriptors, $pipes);
        if (! is_resource($proc)) {
            $this->error('[Lando Studio ERROR] Failed to start plink process.');

            return self::FAILURE;
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        fclose($pipes[0]);

        $outFile = fopen($sqlPath, 'wb');
        if (! $outFile) {
            proc_close($proc);
            $this->error("[Lando Studio ERROR] Cannot write to {$sqlPath}");

            return self::FAILURE;
        }

        $lastHeartbeat = time();
        $bytesReceived = 0;

        while (true) {
            $status = proc_get_status($proc);

            $data = fread($pipes[1], 65536);
            if ($data !== false && $data !== '') {
                fwrite($outFile, $data);
                $bytesReceived += strlen($data);
            }

            $err = fread($pipes[2], 4096);
            if ($err !== false && $err !== '') {
                // Forward plink stderr to our stdout so it appears in the step log
                $this->line('[plink] '.rtrim($err));
            }

            if (time() - $lastHeartbeat >= 20) {
                $mb = round($bytesReceived / 1_048_576, 1);
                $this->line('[Lando Studio] Database dump in progress '.date('H:i:s')." ({$mb} MB received)");
                $lastHeartbeat = time();
            }

            if (! $status['running']) {
                // Drain any remaining buffered output after process exit
                while (! feof($pipes[1])) {
                    $data = fread($pipes[1], 65536);
                    if ($data !== false && $data !== '') {
                        fwrite($outFile, $data);
                        $bytesReceived += strlen($data);
                    }
                }
                break;
            }

            usleep(100_000); // 100 ms poll interval
        }

        fclose($outFile);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

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
}
