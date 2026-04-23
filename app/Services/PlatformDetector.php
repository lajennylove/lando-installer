<?php

namespace App\Services;

use App\Livewire\Settings;

class PlatformDetector
{
    private ?string $os = null;

    private ?string $arch = null;

    public function os(): string
    {
        if ($this->os === null) {
            $this->os = match (PHP_OS_FAMILY) {
                'Darwin' => 'macos',
                'Windows' => 'windows',
                default => 'linux',
            };
        }

        return $this->os;
    }

    public function arch(): string
    {
        if ($this->arch === null) {
            $uname = php_uname('m');
            $this->arch = str_contains($uname, 'arm') || str_contains($uname, 'aarch64')
                ? 'arm64'
                : 'x86_64';
        }

        return $this->arch;
    }

    public function isMacAppleSilicon(): bool
    {
        return $this->os() === 'macos' && $this->arch() === 'arm64';
    }

    public function isWindows(): bool
    {
        return $this->os() === 'windows';
    }

    public function homeDir(): string
    {
        if ($this->isWindows()) {
            // getenv() reads the actual Windows environment directly — safer than
            // env() which goes through Laravel Dotenv and may not have USERPROFILE.
            $home = getenv('USERPROFILE')
                ?: (getenv('HOMEDRIVE').getenv('HOMEPATH'))
                ?: 'C:\\Users\\'.get_current_user();

            return rtrim($home, DIRECTORY_SEPARATOR);
        }

        return rtrim(getenv('HOME') ?: '/home/'.get_current_user(), DIRECTORY_SEPARATOR);
    }

    public function defaultCodePath(): string
    {
        // Check persisted settings first (from Settings UI / landodev_defaults.json)
        $persisted = Settings::getDefault('code_path');
        if (is_string($persisted) && trim($persisted) !== '') {
            $expanded = str_replace('~', $this->homeDir(), $persisted);

            return $this->normalizePathSeparators($expanded);
        }

        $configured = config('lando_dev.defaults.code_path');
        if ($configured) {
            return $this->normalizePathSeparators($configured);
        }

        return $this->homeDir().DIRECTORY_SEPARATOR.'code'.DIRECTORY_SEPARATOR.'sites';
    }

    /**
     * Normalize directory separators and strip trailing slashes.
     * On Windows, converts all forward slashes to backslashes so paths
     * stored with mixed separators (e.g. from user input or settings JSON)
     * are safe to use with Set-Location and file system APIs.
     */
    public function normalizePathSeparators(string $path): string
    {
        if ($this->isWindows()) {
            return rtrim(str_replace('/', '\\', $path), '\\');
        }

        return rtrim($path, '/');
    }

    public function shellWrapper(): string
    {
        return $this->isWindows() ? 'powershell' : 'bash';
    }

    public function shellFlag(): string
    {
        // Windows: this is only used as a fallback; most callers should use
        // powershellArgs() which returns the full ['-ExecutionPolicy','Bypass','-NoProfile','-Command'] list.
        return $this->isWindows() ? '-Command' : '-c';
    }

    /**
     * Full argument list for running a PowerShell command on Windows.
     * Each item must be a separate element — passing combined flags as one
     * string causes Windows spawn() to treat them as a single malformed arg.
     *
     * Usage: ChildProcess::start(cmd: [...$platform->powershellArgs(), $command], ...)
     *
     * @return array<string>
     */
    public function powershellArgs(): array
    {
        return ['powershell', '-ExecutionPolicy', 'Bypass', '-NoProfile', '-Command'];
    }

    /**
     * PowerShell snippet that must be prepended to any command that redirects
     * output to a log file on Windows.
     *
     * Without this, PowerShell's *> / >> redirection uses the legacy Windows
     * code page (cp1252/cp437), turning UTF-8 characters like ✔ (U+2714,
     * bytes E2 9C 94) into garbled sequences such as "ΓêÜ".
     *
     * [Console]::OutputEncoding — how PS reads stdout from external processes.
     * $OutputEncoding           — how PS writes to files via *> and >> streams.
     */
    public function powershellUtf8Prefix(): string
    {
        return '[Console]::OutputEncoding = [System.Text.Encoding]::UTF8; '
            .'$OutputEncoding = [System.Text.Encoding]::UTF8; ';
    }

    /**
     * Wrap a shell command so step logging does not steal stdout from inner redirects/pipes.
     *
     * On Unix:  (command) > 'logfile' 2>&1; echo '[LANDO_STEP_DONE]' >> 'logfile'
     * On Windows (PowerShell): & { command } *> 'logfile'; Add-Content -Path 'logfile' -Value '[LANDO_STEP_DONE]'
     *   *> captures all PS streams (stdout, stderr, verbose, warning…)
     *   Single-quoted path is a PS literal string — safe for any Windows path.
     *   The completion marker lets pollers detect process exit without relying
     *   solely on log-file inactivity (which races with the overall timeout).
     */
    public const STEP_DONE_MARKER = '[LANDO_STEP_DONE]';

    public function wrapCommandWithLogRedirect(string $command, string $logFile): string
    {
        $marker = self::STEP_DONE_MARKER;

        if ($this->isWindows()) {
            $log = str_replace("'", "''", $logFile); // escape PS single-quoted string

            return $this->powershellUtf8Prefix()."& { {$command} } *> '{$log}'; Add-Content -Path '{$log}' -Value '{$marker}'";
        }

        $log = escapeshellarg($logFile);

        return '('.$command.') > '.$log.' 2>&1; echo '.escapeshellarg($marker).' >> '.$log;
    }
}
