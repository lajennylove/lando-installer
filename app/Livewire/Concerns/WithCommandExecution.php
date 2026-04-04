<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Enums\SiteStatus;
use App\Models\CommandLog;
use App\Models\Site;
use App\Services\PlatformDetector;
use Native\Laravel\Facades\ChildProcess;

trait WithCommandExecution
{
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

        $shell = app(PlatformDetector::class)->shellWrapper();
        $flag = app(PlatformDetector::class)->shellFlag();

        ChildProcess::start(
            cmd: [$shell, $flag, $command." > '{$logFile}' 2>&1"],
            alias: $alias,
        );
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

        $content = file_get_contents($logFile);
        $this->terminalOutput = $this->completedOutput.$content;
        $this->steps[$this->currentStep]['output'] = $this->tailLines($content, 10);

        // Overall timeout for a single step (10 minutes for lando start/rebuild)
        if ($elapsed > 600) {
            $this->failCurrentStep($site, 'Command timed out after 10 minutes.');

            return;
        }

        // Wait 30 seconds of inactivity before considering the step complete.
        // Lando commands have long pauses during builds (apt-get, node install,
        // healthchecks, "Continuing in 10 seconds..." warnings).
        $lastModified = filemtime($logFile);
        if ((time() - $lastModified) > 30) {
            $hasError = $this->detectErrors($content);

            if ($hasError) {
                $this->failCurrentStep($site, $this->tailLines($content, 5));

                return;
            }

            $this->steps[$this->currentStep]['status'] = 'completed';

            // Accumulate this step's output for the full log
            $stepLabel = $this->steps[$this->currentStep]['label'] ?? '';
            $this->completedOutput .= "\n--- [{$stepLabel}] ---\n".$content."\n";

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

    private function detectErrors(string $content): bool
    {
        $lastLines = $this->tailLines($content, 5);

        // Lando-specific fatal patterns (not warnings)
        $fatalPatterns = [
            'lando command not found',
            'Error response from daemon',
            'Cannot connect to the Docker daemon',
            'is not running',
            'EACCES: permission denied',
        ];

        foreach ($fatalPatterns as $pattern) {
            if (stripos($lastLines, $pattern) !== false) {
                return true;
            }
        }

        // Check for non-zero exit code marker if present
        if (preg_match('/exited with code (\d+)/', $lastLines, $matches)) {
            return (int) $matches[1] !== 0;
        }

        return false;
    }

    private function tailLines(string $content, int $lines): string
    {
        $allLines = explode("\n", trim($content));
        $slice = array_slice($allLines, -$lines);

        return implode("\n", $slice);
    }

    abstract protected function getSite(): ?Site;

    abstract protected function getStepDefinitions(): array;

    abstract protected function onSequenceComplete(Site $site): void;
}
