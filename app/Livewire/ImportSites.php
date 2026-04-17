<?php

namespace App\Livewire;

use App\Enums\SiteStatus;
use App\Livewire\Concerns\WithNotifications;
use App\Models\Site;
use App\Services\LocalSiteScanner;
use App\Services\SiteManager;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Import Local Sites — Lando Studio')]
class ImportSites extends Component
{
    use WithNotifications;

    /** @var array<int, array{name: string, path: string, php_version: string, db_type: string, db_version: string, redis_version: string|null, db_port: int|null}> */
    public array $candidates = [];

    /** Names currently being imported (for loading state). */
    public array $importing = [];

    public function mount(LocalSiteScanner $scanner): void
    {
        $this->candidates = $scanner->findUnimported();
    }

    public function import(string $siteName, LocalSiteScanner $scanner, SiteManager $siteManager): void
    {
        // Find the candidate by name
        $candidate = collect($this->candidates)->firstWhere('name', $siteName);

        if (! $candidate) {
            $this->notifyError("Site \"{$siteName}\" not found in the import list.");

            return;
        }

        // Guard against double-import
        if (Site::where('name', $siteName)->exists()) {
            $this->notifyError("\"{$siteName}\" is already in Lando Studio.");
            $this->candidates = $scanner->findUnimported();

            return;
        }

        $this->importing[] = $siteName;

        $dbPort = $candidate['db_port'] ?? $siteManager->allocateDatabaseForwardPort();

        Site::create([
            'name' => $candidate['name'],
            'path' => $candidate['path'],
            'url' => "https://{$candidate['name']}.lndo.site",
            'admin_url' => "https://{$candidate['name']}.lndo.site/wp-admin",
            'status' => SiteStatus::Unknown,
            'php_version' => $candidate['php_version'],
            'db_version' => $candidate['db_version'],
            'redis_version' => $candidate['redis_version'],
            'db_port' => $dbPort,
        ]);

        $this->importing = array_values(array_diff($this->importing, [$siteName]));

        $this->notifySuccess("\"{$siteName}\" has been imported into Lando Studio.");
        $this->dispatch('site-created')->to(SiteList::class);

        // Refresh list — remove the just-imported site
        $this->candidates = $scanner->findUnimported();
    }

    public function importAll(LocalSiteScanner $scanner, SiteManager $siteManager): void
    {
        if (empty($this->candidates)) {
            $this->notifyInfo('No sites to import.');

            return;
        }

        $count = 0;

        foreach ($this->candidates as $candidate) {
            if (Site::where('name', $candidate['name'])->exists()) {
                continue;
            }

            $dbPort = $candidate['db_port'] ?? $siteManager->allocateDatabaseForwardPort();

            Site::create([
                'name' => $candidate['name'],
                'path' => $candidate['path'],
                'url' => "https://{$candidate['name']}.lndo.site",
                'admin_url' => "https://{$candidate['name']}.lndo.site/wp-admin",
                'status' => SiteStatus::Unknown,
                'php_version' => $candidate['php_version'],
                'db_version' => $candidate['db_version'],
                'redis_version' => $candidate['redis_version'],
                'db_port' => $dbPort,
            ]);

            $count++;
        }

        $this->candidates = $scanner->findUnimported();

        $label = $count === 1 ? '1 site' : "{$count} sites";
        $this->notifySuccess("{$label} imported into Lando Studio.");
        $this->dispatch('site-created')->to(SiteList::class);
    }

    public function render(): View
    {
        return view('livewire.import-sites');
    }
}
