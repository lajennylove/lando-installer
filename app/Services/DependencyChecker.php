<?php

namespace App\Services;

class DependencyChecker
{
    public function __construct(
        private PlatformDetector $platform,
    ) {}

    public function checkAll(): array
    {
        $results = [
            'lando' => $this->isLandoInstalled(),
            'docker' => $this->isDockerInstalled(),
        ];

        if ($this->platform->isMacAppleSilicon()) {
            $results['orbstack'] = $this->isOrbStackInstalled();
        }

        return $results;
    }

    public function allRequiredInstalled(): bool
    {
        return $this->isLandoInstalled() && $this->isDockerInstalled();
    }

    public function isLandoInstalled(): bool
    {
        if ($this->commandExists('lando')) {
            return true;
        }

        // On Windows, the PHP process PATH is frozen at app launch, so newly-installed
        // binaries won't be found by `where` until the app restarts.
        // The official setup-lando.ps1 installs to %USERPROFILE%\.lando\bin\.
        // Check both lando.cmd (the shell wrapper used in practice) and lando.exe.
        if ($this->platform->isWindows()) {
            $bin = $this->platform->homeDir().'\\.lando\\bin\\';

            return file_exists($bin.'lando.cmd') || file_exists($bin.'lando.exe');
        }

        return false;
    }

    /** Full path to lando.cmd (preferred on Windows) or lando.exe fallback. */
    private function windowsLandoExe(): string
    {
        $bin = $this->platform->homeDir().'\\.lando\\bin\\';

        return file_exists($bin.'lando.cmd') ? $bin.'lando.cmd' : $bin.'lando.exe';
    }

    public function isDockerInstalled(): bool
    {
        if ($this->commandExists('docker')) {
            return true;
        }

        if ($this->platform->isWindows()) {
            // Official install location per Docker Desktop Windows docs.
            return file_exists($this->windowsDockerExe());
        }

        return false;
    }

    /** Default docker.exe path for Docker Desktop on Windows. */
    private function windowsDockerExe(): string
    {
        return 'C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe';
    }

    public function isOrbStackInstalled(): bool
    {
        // OrbStack's CLI binary is 'orb', not 'orbstack'.
        // Also check for the macOS app bundle as a fallback.
        return $this->commandExists('orb')
            || (PHP_OS_FAMILY === 'Darwin' && is_dir('/Applications/OrbStack.app'));
    }

    /**
     * Whether PuTTY's plink.exe is available on Windows.
     * plink is required for SSH password-based connections (clone/sync operations).
     */
    public function isPuttyInstalled(): bool
    {
        if ($this->commandExists('plink')) {
            return true;
        }

        return $this->getPlinkPath() !== null;
    }

    /**
     * Full path to plink.exe, or null if not found.
     * Checks the standard PuTTY install locations when not in PATH.
     */
    public function getPlinkPath(): ?string
    {
        $candidates = [
            'C:\\Program Files\\PuTTY\\plink.exe',
            'C:\\Program Files (x86)\\PuTTY\\plink.exe',
            getenv('LOCALAPPDATA').'\\Programs\\PuTTY\\plink.exe',
        ];

        foreach ($candidates as $path) {
            if ($path && file_exists($path)) {
                return $path;
            }
        }

        // Also check PATH via `where`
        $result = @shell_exec('where plink 2>nul');
        if ($result) {
            $first = trim(explode("\n", $result)[0]);
            if ($first && file_exists($first)) {
                return $first;
            }
        }

        return null;
    }

    public function getLandoVersion(): ?string
    {
        // On Windows use the full path in case the child process PATH is frozen.
        $cmd = $this->platform->isWindows()
            ? '"'.$this->windowsLandoExe().'" version'
            : 'lando version';

        return $this->getCommandOutput($cmd);
    }

    public function getDockerVersion(): ?string
    {
        // On Windows use the full path in case the child process PATH is frozen.
        $cmd = $this->platform->isWindows()
            ? '"'.$this->windowsDockerExe().'" --version'
            : 'docker --version';

        return $this->getCommandOutput($cmd);
    }

    public function getOrbStackVersion(): ?string
    {
        return $this->getCommandOutput('orb version');
    }

    public function getPuttyVersion(): ?string
    {
        $plink = $this->getPlinkPath() ?? 'plink';
        $result = $this->getCommandOutput('"'.$plink.'" -V');

        if (! $result) {
            return null;
        }

        // plink outputs: "plink: Release 0.82" — extract the version number
        if (preg_match('/Release\s+(\S+)/i', $result, $m)) {
            return $m[1];
        }

        return trim(explode("\n", $result)[0]);
    }

    public function getLandoPath(): ?string
    {
        // On Windows, always use the known install path so child processes launched by
        // NativePHP (which may have a frozen PATH) can find the binary without PATH lookup.
        // Prefer lando.cmd (the shell wrapper) over lando.exe.
        if ($this->platform->isWindows()) {
            $exe = $this->windowsLandoExe();

            return file_exists($exe) ? $exe : ($this->getCommandPath('lando') ?? $exe);
        }

        return $this->getCommandPath('lando');
    }

    private function commandExists(string $command): bool
    {
        $which = $this->platform->isWindows() ? 'where' : 'which';
        $null = $this->platform->isWindows() ? '2>nul' : '2>/dev/null';
        $result = @shell_exec("{$which} {$command} {$null}");

        return ! empty(trim($result ?? ''));
    }

    private function getCommandOutput(string $command): ?string
    {
        $null = $this->platform->isWindows() ? '2>nul' : '2>/dev/null';
        $result = @shell_exec("{$command} {$null}");

        return $result ? trim($result) : null;
    }

    private function getCommandPath(string $command): ?string
    {
        $which = $this->platform->isWindows() ? 'where' : 'which';
        $null = $this->platform->isWindows() ? '2>nul' : '2>/dev/null';
        $result = @shell_exec("{$which} {$command} {$null}");

        return $result ? trim(explode("\n", $result)[0]) : null;
    }
}
