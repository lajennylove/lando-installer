<?php

namespace App\Services;

use App\Livewire\Settings;

class PlatformDetector
{
    private ?string $os = null;

    private ?string $arch = null;

    public function os(): string
    {
        if ($this->os === null) {
            $this->os = match (PHP_OS_FAMILY) {
                'Darwin' => 'macos',
                'Windows' => 'windows',
                default => 'linux',
            };
        }

        return $this->os;
    }

    public function arch(): string
    {
        if ($this->arch === null) {
            $uname = php_uname('m');
            $this->arch = str_contains($uname, 'arm') || str_contains($uname, 'aarch64')
                ? 'arm64'
                : 'x86_64';
        }

        return $this->arch;
    }

    public function isMacAppleSilicon(): bool
    {
        return $this->os() === 'macos' && $this->arch() === 'arm64';
    }

    public function isWindows(): bool
    {
        return $this->os() === 'windows';
    }

    public function homeDir(): string
    {
        if ($this->isWindows()) {
            return rtrim(env('USERPROFILE', 'C:\\Users\\'.get_current_user()), DIRECTORY_SEPARATOR);
        }

        return rtrim(env('HOME', '/home/'.get_current_user()), DIRECTORY_SEPARATOR);
    }

    public function defaultCodePath(): string
    {
        // Check persisted settings first (from Settings UI / landodev_defaults.json)
        $persisted = Settings::getDefault('code_path');
        if (is_string($persisted) && trim($persisted) !== '') {
            $expanded = str_replace('~', $this->homeDir(), $persisted);

            return rtrim($expanded, DIRECTORY_SEPARATOR);
        }

        $configured = config('lando_dev.defaults.code_path');
        if ($configured) {
            return rtrim($configured, DIRECTORY_SEPARATOR);
        }

        return $this->homeDir().DIRECTORY_SEPARATOR.'code'.DIRECTORY_SEPARATOR.'sites';
    }

    public function shellWrapper(): string
    {
        return $this->isWindows() ? 'cmd' : 'bash';
    }

    public function shellFlag(): string
    {
        return $this->isWindows() ? '/c' : '-c';
    }
}
