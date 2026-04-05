<?php

declare(strict_types=1);

namespace App\Services;

final class RemoteVerificationResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $phase = null,
        public readonly ?string $message = null,
    ) {}

    public static function success(): self
    {
        return new self(true);
    }

    public static function sshFailed(string $message): self
    {
        return new self(false, 'ssh', $message);
    }

    public static function databaseFailed(string $message): self
    {
        return new self(false, 'database', $message);
    }
}
