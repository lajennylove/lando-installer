<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RemoteSite;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Verifies SSH and remote MySQL (via mysqldump) before starting a clone.
 * Uses the same sshpass + ssh pattern as {@see SshService}.
 */
final class RemoteConnectionVerifier
{
    /**
     * @param  string|null  $sshPasswordOverride  Plain text; if null/empty, uses {@see RemoteSite::$ssh_password}
     * @param  string|null  $dbPasswordOverride  Plain text; if null/empty, uses {@see RemoteSite::$db_password}
     */
    public function verify(
        RemoteSite $remote,
        ?string $sshPasswordOverride = null,
        ?string $dbPasswordOverride = null,
    ): RemoteVerificationResult {
        $sshPass = (is_string($sshPasswordOverride) && $sshPasswordOverride !== '')
            ? $sshPasswordOverride
            : (string) $remote->ssh_password;

        $dbPass = (is_string($dbPasswordOverride) && $dbPasswordOverride !== '')
            ? $dbPasswordOverride
            : (string) $remote->db_password;

        Log::debug('RemoteConnectionVerifier::verify', [
            'remote_id' => $remote->id,
            'ssh_server' => $remote->ssh_server_ip,
            'ssh_user' => $remote->ssh_user,
            'db_user' => $remote->db_user,
            'db_name' => $remote->db_name,
            'using_ssh_override' => filled($sshPasswordOverride),
            'using_db_override' => filled($dbPasswordOverride),
            'ssh_pass_length' => strlen($sshPass),
            'db_pass_length' => strlen($dbPass),
        ]);

        if ($sshPass === '') {
            Log::warning('RemoteConnectionVerifier: empty SSH password');

            return RemoteVerificationResult::sshFailed('No SSH password is stored for this remote site. Enter it below or update the site in Settings.');
        }

        $target = $remote->ssh_user.'@'.$remote->ssh_server_ip;

        $sshProbe = Process::env(['SSHPASS' => $sshPass])
            ->timeout(30)
            ->run([
                'sshpass', '-e', 'ssh', '-T',
                '-o', 'StrictHostKeyChecking=no',
                '-o', 'ConnectTimeout=20',
                '-o', 'BatchMode=no',
                $target,
                'echo', 'LANDODEV_SSH_OK',
            ]);

        $sshOut = $sshProbe->output().$sshProbe->errorOutput();
        $sshExitCode = $sshProbe->exitCode();
        Log::debug('RemoteConnectionVerifier: SSH probe result', [
            'exit_code' => $sshExitCode,
            'output_contains_marker' => str_contains($sshOut, 'LANDODEV_SSH_OK'),
            'output_length' => strlen($sshOut),
        ]);

        if (! str_contains($sshOut, 'LANDODEV_SSH_OK')) {
            $detail = trim($sshOut) !== '' ? trim($sshOut) : 'SSH exited with code '.$sshExitCode;
            Log::warning('RemoteConnectionVerifier: SSH failed', ['detail' => $detail]);

            return RemoteVerificationResult::sshFailed(
                'Could not connect over SSH. Check host, username, and password. '.$detail
            );
        }

        Log::debug('RemoteConnectionVerifier: SSH OK');

        if ($dbPass === '') {
            return RemoteVerificationResult::databaseFailed(
                'No database password is stored for this remote site. Enter the MySQL password from production wp-config.php or Settings.'
            );
        }

        $schemaProbe = Process::env(['SSHPASS' => $sshPass])
            ->timeout(90)
            ->run([
                'sshpass', '-e', 'ssh', '-T',
                '-o', 'StrictHostKeyChecking=no',
                '-o', 'ConnectTimeout=20',
                $target,
                'mysqldump',
                '--no-data',
                '--single-transaction',
                '-u', $remote->db_user,
                '-p'.$dbPass,
                $remote->db_name,
            ]);

        $dbOut = $schemaProbe->output().$schemaProbe->errorOutput();
        $dbExitCode = $schemaProbe->exitCode();
        $hasDumpMarker = str_contains($dbOut, 'MariaDB dump') || str_contains($dbOut, 'MySQL dump');
        $hasAccessDenied = stripos($dbOut, 'Access denied') !== false || stripos($dbOut, '1045') !== false;

        Log::debug('RemoteConnectionVerifier: DB probe result', [
            'exit_code' => $dbExitCode,
            'output_length' => strlen($dbOut),
            'has_dump_marker' => $hasDumpMarker,
            'has_access_denied' => $hasAccessDenied,
        ]);

        if ($hasAccessDenied) {
            Log::warning('RemoteConnectionVerifier: DB access denied');

            return RemoteVerificationResult::databaseFailed(
                'MySQL rejected the database user or password. Use the DB credentials from production wp-config.php (they may differ from the SSH user).'
            );
        }

        if (! $hasDumpMarker) {
            $snippet = trim(mb_substr($dbOut, 0, 400));
            Log::warning('RemoteConnectionVerifier: DB dump marker not found', ['snippet' => $snippet]);

            return RemoteVerificationResult::databaseFailed(
                $snippet !== ''
                    ? 'Could not confirm a database dump from the server: '.$snippet
                    : 'Could not confirm a database dump from the server (empty output).'
            );
        }

        Log::info('RemoteConnectionVerifier: verification passed');

        return RemoteVerificationResult::success();
    }
}
