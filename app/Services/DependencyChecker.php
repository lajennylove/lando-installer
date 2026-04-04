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
        return $this->commandExists('lando');
    }

    public function isDockerInstalled(): bool
    {
        return $this->commandExists('docker');
    }

    public function isOrbStackInstalled(): bool
    {
        return $this->commandExists('orbstack');
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
        return $this->getCommandOutput('orbstack version');
    }

    public function getLandoPath(): ?string
    {
        return $this->getCommandPath('lando');
    }

    private function commandExists(string $command): bool
    {
        $which = $this->platform->isWindows() ? 'where' : 'which';
        $result = @shell_exec("{$which} {$command} 2>/dev/null");

        return ! empty(trim($result ?? ''));
    }

    private function getCommandOutput(string $command): ?string
    {
        $result = @shell_exec("{$command} 2>/dev/null");

        return $result ? trim($result) : null;
    }

    private function getCommandPath(string $command): ?string
    {
        $which = $this->platform->isWindows() ? 'where' : 'which';
        $result = @shell_exec("{$which} {$command} 2>/dev/null");

        return $result ? trim(explode("\n", $result)[0]) : null;
    }
}
