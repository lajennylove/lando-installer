<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Enums\SiteStatus;
use App\Models\CommandLog;
use App\Models\Site;
use App\Services\PlatformDetector;
use App\Support\CommandLogErrorDetector;
use App\Support\LogContentUtf8;
use Native\Laravel\Facades\ChildProcess;

trait WithCommandExecution
{
    /** Keep snapshot size bounded; full logs remain in storage/logs. */
    private const LIVEWIRE_OUTPUT_CAP_BYTES = 100_000;

    public int $currentStep = 0;

    public int $totalSteps = 0;

    public array $steps = [];

    public string $terminalOutput = '';

    public string $completedOutput = '';

    public bool $isExecuting = false;

    public bool $executionFailed = false;

    public ?float $executionStartedAt = null;

    public function executeStepSequence(array $stepDefinitions, Site $site): void
    {
        $this->isExecuting = true;
        $this->executionFailed = false;
        $this->totalSteps = count($stepDefinitions);
        $this->currentStep = 0;
        $this->terminalOutput = '';
        $this->completedOutput = '';
        $this->executionStartedAt = microtime(true);
        $this->steps = array_map(fn ($s) => [
            'label' => $s['label'],
            'hint' => $s['hint'] ?? null,
            'timeout' => $s['timeout'] ?? 600,
            'status' => 'pending',
            'output' => '',
        ], $stepDefinitions);

        $this->executeNextStep($stepDefinitions, $site);
    }

    private function executeNextStep(array $stepDefinitions, Site $site): void
    {
        if ($this->currentStep >= $this->totalSteps) {
            $this->isExecuting = false;
            $this->executionStartedAt = null;
            $this->onSequenceComplete($site);

            return;
        }

        $step = $stepDefinitions[$this->currentStep];
        $this->steps[$this->currentStep]['status'] = 'running';
        $this->executionStartedAt = microtime(true);

        $logFile = storage_path(
            'logs/lando_'.$site->id.'_step_'.$this->currentStep.'_'.time().'.log'
        );

        $logDir = dirname($logFile);
        if (! is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $command = $step['command'];
        $alias = 'lando-site-'.$site->id.'-step-'.$this->currentStep.'-'.uniqid();

        CommandLog::create([
            'site_id' => $site->id,
            'command' => $command,
            'alias' => $alias,
            'status' => 'running',
            'step_number' => $this->currentStep,
            'step_label' => $step['label'],
            'log_file_path' => $logFile,
            'started_at' => now(),
        ]);

        $site->update(['log_file' => $logFile]);

        // Pre-write the command so the log file exists immediately and the terminal
        // shows what is being attempted even if the child process fails to start.
        file_put_contents($logFile, "[LandoDEV] Running: {$command}\n");

        $platform = app(PlatformDetector::class);
        $wrapped = $platform->wrapCommandWithLogRedirect($command, $logFile);

        if ($platform->isWindows()) {
            // Use PowerShell — lando on Windows runs via PS, not cmd.exe.
            $cmd = [...$platform->powershellArgs(), $wrapped];
        } else {
            $cmd = [$platform->shellWrapper(), $platform->shellFlag(), $wrapped];
        }

        try {
            ChildProcess::start(
                cmd: $cmd,
                alias: $alias,
            );
        } catch (\Throwable $e) {
            // NativePHP bridge (localhost:4002) unreachable — log and surface the error.
            $msg = 'ChildProcess::start() failed: '.$e->getMessage();
            file_put_contents($logFile, "\n[LandoDEV ERROR] {$msg}\n", FILE_APPEND);
            $this->failCurrentStep($site, $msg);
        }
    }

    public function checkCommandStatus(): void
    {
        if (! $this->isExecuting) {
            return;
        }

        $site = $this->getSite();
        if (! $site || ! $site->log_file) {
            return;
        }

        $logFile = $site->log_file;
        $elapsed = microtime(true) - ($this->executionStartedAt ?? microtime(true));

        // If log file doesn't exist yet, check for timeout
        if (! file_exists($logFile)) {
            if ($elapsed > 30) {
                $this->failCurrentStep($site, 'Command failed to start — log file was never created. Check that Lando is installed and in your PATH.');
            }

            return;
        }

        $content = LogContentUtf8::forLivewire((string) file_get_contents($logFile));
        $this->terminalOutput = $this->capLivewireOutput($this->completedOutput.$content);
        $this->dispatch('landodev-scroll-terminal');
        $this->steps[$this->currentStep]['output'] = $this->tailLines($content, 10);

        // Overall timeout for a single step; default 10 min, but dump steps may override higher.
        $stepTimeout = $this->steps[$this->currentStep]['timeout'] ?? 600;
        if ($elapsed > $stepTimeout) {
            $timeoutMinutes = (int) round($stepTimeout / 60);
            $this->failCurrentStep($site, "Command timed out after {$timeoutMinutes} minutes.");

            return;
        }

        // Wait 30 seconds of inactivity before considering the step complete.
        // Lando commands have long pauses during builds (apt-get, node install,
        // healthchecks, "Continuing in 10 seconds..." warnings).
        $lastModified = filemtime($logFile);
        if ((time() - $lastModified) > 30) {
            $hasError = CommandLogErrorDetector::indicatesFailure($content);

            if ($hasError) {
                $this->failCurrentStep($site, $this->tailLines($content, 50));

                return;
            }

            $this->steps[$this->currentStep]['status'] = 'completed';

            // Accumulate this step's output for the full log
            $stepLabel = $this->steps[$this->currentStep]['label'] ?? '';
            $this->completedOutput .= "\n--- [{$stepLabel}] ---\n".$content."\n";
            $this->completedOutput = $this->capLivewireOutput($this->completedOutput);

            CommandLog::where('site_id', $site->id)
                ->where('step_number', $this->currentStep)
                ->where('status', 'running')
                ->update([
                    'status' => 'completed',
                    'output' => $this->tailLines($content, 100),
                    'completed_at' => now(),
                ]);

            $this->currentStep++;
            $this->executeNextStep($this->getStepDefinitions(), $site);
        }
    }

    private function failCurrentStep(Site $site, string $errorMessage): void
    {
        $this->steps[$this->currentStep]['status'] = 'failed';
        $this->isExecuting = false;
        $this->executionFailed = true;
        $this->executionStartedAt = null;
        $site->update(['status' => SiteStatus::Error, 'last_error' => $errorMessage]);

        CommandLog::where('site_id', $site->id)
            ->where('step_number', $this->currentStep)
            ->where('status', 'running')
            ->update([
                'status' => 'failed',
                'error_output' => $errorMessage,
                'completed_at' => now(),
            ]);
    }

    public function retryFromFailedStep(): void
    {
        $site = $this->getSite();
        if (! $site) {
            return;
        }

        $this->executionFailed = false;
        $this->isExecuting = true;

        $stepDefinitions = $this->getStepDefinitions();
        $this->executeNextStep($stepDefinitions, $site);
    }

    private function tailLines(string $content, int $lines): string
    {
        $allLines = explode("\n", trim($content));
        $slice = array_slice($allLines, -$lines);

        return implode("\n", $slice);
    }

    private function capLivewireOutput(string $buffer): string
    {
        if (strlen($buffer) <= self::LIVEWIRE_OUTPUT_CAP_BYTES) {
            return $buffer;
        }

        return "[Earlier output truncated — see storage/logs for full step logs.]\n\n"
            .substr($buffer, -self::LIVEWIRE_OUTPUT_CAP_BYTES);
    }

    abstract protected function getSite(): ?Site;

    abstract protected function getStepDefinitions(): array;

    abstract protected function onSequenceComplete(Site $site): void;
}
