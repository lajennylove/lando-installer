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

        $command = $installer->getInstallCommand($dependency);
        $logFile = storage_path("logs/install_{$dependency}_".time().'.log');

        $shell = $platform->shellWrapper();
        $flag = $platform->shellFlag();

        ChildProcess::start(
            cmd: [$shell, $flag, $command." > {$logFile} 2>&1"],
            alias: "install-{$dependency}",
        );
    }

    public function pollInstallStatus(): void
    {
        if (! $this->installing) {
            return;
        }

        $this->checkDependencies();

        $dep = $this->dependencies[$this->installingDep] ?? null;
        if ($dep && $dep['installed']) {
            $this->installing = false;
            $this->installingDep = '';
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
