<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class MenuServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer(['components.layouts.app'], function ($view) {
            $view->with('secondMenu', config('lando_dev.menu.second'));
        });
    }
}
