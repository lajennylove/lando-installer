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
#[Title('Lando Studio')]
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
            'lando-plugins' => [
                'installed' => $checker->isLandoSetupComplete(),
                'version' => null,
                'label' => 'Lando Plugins',
                'description' => 'WordPress recipe and common plugins (run lando setup)',
                'required' => true,
                'install_action' => 'runLandoSetup',
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

        if ($platform->isWindows()) {
            $this->dependencies['putty'] = [
                'installed' => $checker->isPuttyInstalled(),
                'version' => $checker->getPuttyVersion(),
                'label' => 'PuTTY',
                'description' => 'Required for SSH password auth when cloning remote sites',
                'required' => false,
            ];
        }
    }

    public function install(string $dependency): void
    {
        // lando-plugins has its own install flow via runLandoSetup()
        if ($dependency === 'lando-plugins') {
            $this->runLandoSetup();

            return;
        }

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
        file_put_contents($this->installLogFile, "[Lando Studio] Installing {$dependency}...\n[Lando Studio] Command: {$command}\n");

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
            $this->installOutput = '[Lando Studio ERROR] Failed to start install process: '.$e->getMessage()."\n\nThe NativePHP bridge may not be ready. Please restart the app and try again.";
            file_put_contents($this->installLogFile, "\n[Lando Studio ERROR] ".$e->getMessage()."\n", FILE_APPEND);
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
            $raw = self::decodeLogBytes($raw);
            $this->installOutput = mb_substr($raw, -6000);
            $this->dispatch('landodev-scroll-terminal');
        }

        $this->checkDependencies();

        // lando setup: detect completion via log marker OR dependency check
        if ($this->installingDep === 'lando-setup') {
            $dep = $this->dependencies['lando-plugins'] ?? null;
            if (($dep && $dep['installed']) || str_contains($this->installOutput, '[Lando Studio] lando setup finished')) {
                $this->installing = false;
                $this->installingDep = '';
                $this->installLogFile = '';
                $this->dispatch('landodev-scroll-terminal');
                $this->notifySuccess('Lando plugins installed successfully!');
            }

            return;
        }

        $dep = $this->dependencies[$this->installingDep] ?? null;
        if ($dep && $dep['installed']) {
            $this->installing = false;
            $this->installingDep = '';
            $this->installLogFile = '';
            $this->dispatch('landodev-scroll-terminal');
            $this->notifySuccess("{$dep['label']} installed successfully!");
        }
    }

    public function runLandoSetup(): void
    {
        $checker = app(DependencyChecker::class);

        if (! $checker->isLandoInstalled()) {
            $this->notifyError('Lando must be installed before running setup.');

            return;
        }

        $platform = app(PlatformDetector::class);

        $this->installing = true;
        $this->installingDep = 'lando-setup';
        $this->installOutput = '';
        $this->installLogFile = storage_path('logs/install_lando-setup.log');

        @unlink($this->installLogFile);

        $lando = $checker->getLandoPath() ?? 'lando';
        $marker = '[Lando Studio] lando setup finished';

        if ($platform->isWindows()) {
            $log = addslashes($this->installLogFile);
            $cmd = [...$platform->powershellArgs(), $platform->powershellUtf8Prefix()."& \"{$lando}\" setup --yes *> '{$log}'; Add-Content -Path '{$log}' -Value \"{$marker}\""];
        } else {
            $logArg = escapeshellarg($this->installLogFile);
            $markerArg = escapeshellarg($marker);
            $command = escapeshellarg($lando)." setup --yes > {$logArg} 2>&1; echo {$markerArg} >> {$logArg}";
            $cmd = [$platform->shellWrapper(), $platform->shellFlag(), $command];
        }

        file_put_contents($this->installLogFile, "[Lando Studio] Running: {$lando} setup --yes\n");

        try {
            ChildProcess::start(cmd: $cmd, alias: 'lando-setup');
        } catch (\Throwable $e) {
            $this->installing = false;
            $this->installOutput = '[Lando Studio ERROR] Failed to start lando setup: '.$e->getMessage();
            file_put_contents($this->installLogFile, "\n[Lando Studio ERROR] ".$e->getMessage()."\n", FILE_APPEND);
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

    /**
     * PowerShell's *> redirect writes UTF-16 LE with BOM by default.
     * Detect the BOM (FF FE) and convert to UTF-8 so the terminal renders correctly.
     */
    private static function decodeLogBytes(string $raw): string
    {
        if (str_starts_with($raw, "\xFF\xFE")) {
            return mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE');
        }

        // UTF-8 BOM (rare but possible)
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            return substr($raw, 3);
        }

        return $raw;
    }

    public function continueToApp(): void {}

    public function render()
    {
        return view('livewire.dependency-check');
    }
}
