<?php

use App\Livewire\About;
use App\Livewire\CloneSite;
use App\Livewire\CreateSite;
use App\Livewire\DependencyCheck;
use App\Livewire\NewSite;
use App\Livewire\ImportSites;
use App\Livewire\Settings;
use App\Livewire\SiteDashboard;
use App\Models\Site;
use App\Services\DependencyChecker;
use Illuminate\Support\Facades\Route;

// First-run dependency check
Route::get('/setup', DependencyCheck::class)->name('setup');

// Home — gate on dependencies first, then smart-redirect to last site or /create
Route::get('/', function () {
    $checker = app(DependencyChecker::class);
    if (! $checker->allRequiredInstalled()) {
        return redirect()->route('setup');
    }

    $site = Site::latest()->first();
    if ($site) {
        return redirect()->route('sites.show', $site);
    }

    return redirect()->route('create');
})->name('home');

// Site creation
Route::get('/create', CreateSite::class)->name('create');
Route::get('/create/new', NewSite::class)->name('create.new');
Route::get('/create/clone', CloneSite::class)->name('create.clone');

// Individual site dashboard
Route::get('/sites/{site}', SiteDashboard::class)->name('sites.show');

// Settings & About
Route::get('/settings', Settings::class)->name('settings');
Route::get('/settings/import', ImportSites::class)->name('settings.import');
Route::get('/about', About::class)->name('about');
