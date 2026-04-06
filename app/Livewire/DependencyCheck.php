<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithNotifications;
use App\Services\DependencyChecker;
use App\Services\DependencyInstaller;
use App\Services\PlatformDetector;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Native\Laravel\Facades\ChildProcess;

#[Layout('components.layouts.app')]
#[Title('LandoDEV')]
class DependencyCheck extends Component
{
    use WithNotifications;

    public array $dependencies = [];

    public bool $installing = false;

    public string $installingDep = '';

    public string $installOutput = '';

    public string $installLogFile = '';

    public function mount(): void
    {
        $this->checkDependencies();
    }

    public function checkDependencies(): void
    {
        $checker = app(DependencyChecker::class);
        $platform = app(PlatformDetector::class);

        $this->dependencies = [
            'lando' => [
                'installed' => $checker->isLandoInstalled(),
                'version' => $checker->getLandoVersion(),
                'label' => 'Lando',
                'description' => 'Container-based local development environment',
                'required' => true,
            ],
            'docker' => [
                'installed' => $checker->isDockerInstalled(),
                'version' => $checker->getDockerVersion(),
                'label' => 'Docker Desktop',
                'description' => 'Container runtime engine',
                'required' => true,
            ],
        ];

        if ($platform->isMacAppleSilicon()) {
            $this->dependencies['orbstack'] = [
                'installed' => $checker->isOrbStackInstalled(),
                'version' => $checker->getOrbStackVersion(),
                'label' => 'OrbStack',
                'description' => 'Fast Docker alternative for Apple Silicon (optional)',
                'required' => false,
            ];
        }
    }

    public function install(string $dependency): void
    {
        $installer = app(DependencyInstaller::class);
        $platform = app(PlatformDetector::class);

        $this->installing = true;
        $this->installingDep = $dependency;
        $this->installOutput = '';
        // Stable name (no time()) so pollInstallStatus can read the same file.
        $this->installLogFile = storage_path("logs/install_{$dependency}.log");

        @unlink($this->installLogFile);

        $command = $installer->getInstallCommand($dependency);

        // Pre-write so the log file exists before the process starts.
        file_put_contents($this->installLogFile, "[LandoDEV] Installing {$dependency}...\n[LandoDEV] Command: {$command}\n");

        if ($platform->isWindows()) {
            // Spread all PS args; the log redirect is PowerShell-native syntax.
            $shellArgs = $platform->powershellArgs();
            $cmd = [...$shellArgs, "{$command} *> '".addslashes($this->installLogFile)."'"];
        } else {
            $cmd = [$platform->shellWrapper(), $platform->shellFlag(), "{$command} > ".escapeshellarg($this->installLogFile).' 2>&1'];
        }

        try {
            ChildProcess::start(
                cmd: $cmd,
                alias: "install-{$dependency}",
            );
        } catch (\Throwable $e) {
            $this->installing = false;
            $this->installOutput = '[LandoDEV ERROR] Failed to start install process: '.$e->getMessage()."\n\nThe NativePHP bridge may not be ready. Please restart the app and try again.";
            file_put_contents($this->installLogFile, "\n[LandoDEV ERROR] ".$e->getMessage()."\n", FILE_APPEND);
        }
    }

    public function pollInstallStatus(): void
    {
        if (! $this->installing) {
            return;
        }

        // Read latest log output and dispatch scroll event for the terminal.
        if ($this->installLogFile && file_exists($this->installLogFile)) {
            $raw = file_get_contents($this->installLogFile) ?: '';
            $this->installOutput = mb_substr($raw, -6000);
            $this->dispatch('landodev-scroll-terminal');
        }

        $this->checkDependencies();

        $dep = $this->dependencies[$this->installingDep] ?? null;
        if ($dep && $dep['installed']) {
            $this->installing = false;
            $this->installingDep = '';
            $this->installLogFile = '';
            $this->dispatch('landodev-scroll-terminal');
            $this->notifySuccess("{$dep['label']} installed successfully!");
        }
    }

    public function allRequiredMet(): bool
    {
        foreach ($this->dependencies as $dep) {
            if ($dep['required'] && ! $dep['installed']) {
                return false;
            }
        }

        return true;
    }

    public function continueToApp(): void {}

    public function render()
    {
        return view('livewire.dependency-check');
    }
}
