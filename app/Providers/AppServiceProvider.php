<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Clone / Lando progress can exceed Livewire’s default 1MB JSON snapshot limit.
        config(['livewire.payload.max_size' => 4 * 1024 * 1024]);
    }
}
