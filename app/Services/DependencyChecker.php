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

        // On Windows, the PHP process PATH is frozen at launch, so newly-installed
        // binaries won't be found by `where` until the app restarts. Check common
        // install locations as a fallback.
        if ($this->platform->isWindows()) {
            $home = $this->platform->homeDir();
            $candidates = [
                $home.DIRECTORY_SEPARATOR.'AppData'.DIRECTORY_SEPARATOR.'Local'.DIRECTORY_SEPARATOR.'Lando'.DIRECTORY_SEPARATOR.'lando.exe',
                'C:\\ProgramData\\Lando\\lando.exe',
                'C:\\Program Files\\Lando\\lando.exe',
            ];
            foreach ($candidates as $path) {
                if (file_exists($path)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function isDockerInstalled(): bool
    {
        if ($this->commandExists('docker')) {
            return true;
        }

        if ($this->platform->isWindows()) {
            $candidates = [
                'C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe',
                'C:\\ProgramData\\DockerDesktop\\version-bin\\docker.exe',
            ];
            foreach ($candidates as $path) {
                if (file_exists($path)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function isOrbStackInstalled(): bool
    {
        // OrbStack's CLI binary is 'orb', not 'orbstack'.
        // Also check for the macOS app bundle as a fallback.
        return $this->commandExists('orb')
            || (PHP_OS_FAMILY === 'Darwin' && is_dir('/Applications/OrbStack.app'));
    }

    public function getLandoVersion(): ?string
    {
        return $this->getCommandOutput('lando version');
    }

    public function getDockerVersion(): ?string
    {
        return $this->getCommandOutput('docker --version');
    }

    public function getOrbStackVersion(): ?string
    {
        return $this->getCommandOutput('orb version');
    }

    public function getLandoPath(): ?string
    {
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
