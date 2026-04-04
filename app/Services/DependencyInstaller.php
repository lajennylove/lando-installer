<?php

namespace App\Services;

class DependencyInstaller
{
    public function __construct(
        private PlatformDetector $platform,
    ) {}

    public function getInstallCommand(string $dependency): string
    {
        return match ($dependency) {
            'lando' => $this->installLando(),
            'docker' => $this->installDocker(),
            'orbstack' => $this->installOrbStack(),
            default => throw new \InvalidArgumentException("Unknown dependency: {$dependency}"),
        };
    }

    public function installLando(): string
    {
        if ($this->platform->isWindows()) {
            return "iex (irm 'https://get.lando.dev/setup-lando.ps1' -UseB)";
        }

        return '/bin/bash -c "$(curl -fsSL https://get.lando.dev/setup-lando.sh)"';
    }

    public function installDocker(): string
    {
        return match ($this->platform->os()) {
            'macos' => 'brew install --cask docker',
            'windows' => 'winget install Docker.DockerDesktop',
            default => 'curl -fsSL https://get.docker.com -o get-docker.sh && sh get-docker.sh',
        };
    }

    public function installOrbStack(): string
    {
        return 'brew install --cask orbstack';
    }
}
