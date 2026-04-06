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
            // PowerShell syntax — DependencyCheck::install() runs this via powershell.exe
            return "iex (irm 'https://get.lando.dev/setup-lando.ps1' -UseB)";
        }

        // The setup-lando.sh script handles macOS and all major Linux distros.
        return '/bin/bash -c "$(curl -fsSL https://get.lando.dev/setup-lando.sh)"';
    }

    public function installDocker(): string
    {
        return match ($this->platform->os()) {
            // Bootstrap Homebrew first if absent, then install Docker Desktop cask.
            'macos' => $this->withBrewBootstrap('brew install --cask docker'),
            // winget ships with Windows 10 (1709+) and Windows 11.
            'windows' => 'winget install Docker.DockerDesktop',
            // get.docker.com auto-detects the distro (apt, yum, dnf, zypper, etc.).
            default => 'curl -fsSL https://get.docker.com | sh',
        };
    }

    public function installOrbStack(): string
    {
        // OrbStack is macOS-only; bootstrap Homebrew if needed.
        return $this->withBrewBootstrap('brew install --cask orbstack');
    }

    /**
     * Prepend a Homebrew bootstrap to a brew command so it works on a fresh
     * macOS machine that does not yet have Homebrew installed.
     *
     * NONINTERACTIVE=1 suppresses all prompts in the Homebrew install script.
     */
    private function withBrewBootstrap(string $brewCommand): string
    {
        $installBrew = 'NONINTERACTIVE=1 /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"';

        return "command -v brew >/dev/null 2>&1 || {$installBrew} && {$brewCommand}";
    }
}
