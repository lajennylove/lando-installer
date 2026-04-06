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
            return rtrim(env('USERPROFILE', 'C:\\Users\\'.get_current_user()), DIRECTORY_SEPARATOR);
        }

        return rtrim(env('HOME', '/home/'.get_current_user()), DIRECTORY_SEPARATOR);
    }

    public function defaultCodePath(): string
    {
        // Check persisted settings first (from Settings UI / landodev_defaults.json)
        $persisted = Settings::getDefault('code_path');
        if (is_string($persisted) && trim($persisted) !== '') {
            $expanded = str_replace('~', $this->homeDir(), $persisted);

            return rtrim($expanded, DIRECTORY_SEPARATOR);
        }

        $configured = config('lando_dev.defaults.code_path');
        if ($configured) {
            return rtrim($configured, DIRECTORY_SEPARATOR);
        }

        return $this->homeDir().DIRECTORY_SEPARATOR.'code'.DIRECTORY_SEPARATOR.'sites';
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
     * Wrap a shell command so step logging does not steal stdout from inner redirects/pipes.
     *
     * On Unix:  (command) > 'logfile' 2>&1
     * On Windows (PowerShell): & { command } *> 'logfile'
     *   *> captures all PS streams (stdout, stderr, verbose, warning…)
     *   Single-quoted path is a PS literal string — safe for any Windows path.
     */
    public function wrapCommandWithLogRedirect(string $command, string $logFile): string
    {
        if ($this->isWindows()) {
            $log = str_replace("'", "''", $logFile); // escape PS single-quoted string

            return "& { {$command} } *> '{$log}'";
        }

        $log = escapeshellarg($logFile);

        return '('.$command.') > '.$log.' 2>&1';
    }
}
