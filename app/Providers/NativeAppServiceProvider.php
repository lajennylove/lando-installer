<?php

namespace App\Providers;

use Native\Laravel\Contracts\ProvidesPhpIni;
use Native\Laravel\Facades\Dock;
use Native\Laravel\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    public function boot(): void
    {
        Window::open()
            ->width(1600)
            ->height(800)
            ->minWidth(1400)
            ->minHeight(650);

        if (PHP_OS_FAMILY === 'Darwin') {
            Dock::show();
        }
    }

    public function phpIni(): array
    {
        return [];
    }
}
