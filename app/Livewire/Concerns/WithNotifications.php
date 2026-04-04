<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

trait WithNotifications
{
    protected function notifySuccess(string $message, int $duration = 5000): void
    {
        $this->dispatch('notify',
            message: $message,
            type: 'success',
            duration: $duration
        );
    }

    protected function notifyError(string $message, int $duration = 5000): void
    {
        $this->dispatch('notify',
            message: $message,
            type: 'error',
            duration: $duration
        );
    }

    protected function notifyWarning(string $message, int $duration = 5000): void
    {
        $this->dispatch('notify',
            message: $message,
            type: 'warning',
            duration: $duration
        );
    }

    protected function notifyInfo(string $message, int $duration = 5000): void
    {
        $this->dispatch('notify',
            message: $message,
            type: 'info',
            duration: $duration
        );
    }

    protected function notify(string $message, string $type = 'info', int $duration = 5000): void
    {
        $this->dispatch('notify',
            message: $message,
            type: $type,
            duration: $duration
        );
    }
}
