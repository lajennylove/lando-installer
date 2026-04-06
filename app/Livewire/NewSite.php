<?php

namespace App\Livewire;

use App\Enums\SiteStatus;
use App\Livewire\Concerns\WithCommandExecution;
use App\Livewire\Concerns\WithNotifications;
use App\Models\Site;
use App\Services\PlatformDetector;
use App\Services\SiteManager;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('LandoDEV')]
class NewSite extends Component
{
    use WithCommandExecution;
    use WithNotifications;

    public string $siteName = '';

    public string $adminUsername = 'admin';

    public string $adminPassword = '';

    public string $adminEmail = '';

    public string $path = '';

    public bool $installSage = true;

    public bool $showProgress = false;

    public ?int $siteId = null;

    private ?array $cachedSteps = null;

    public function mount(): void
    {
        $this->path = app(PlatformDetector::class)->defaultCodePath();
    }

    public function updatedSiteName(): void
    {
        $slug = Str::slug($this->siteName);
        $this->path = app(PlatformDetector::class)->defaultCodePath().DIRECTORY_SEPARATOR.$slug;
    }

    public function createSite(): void
    {
        $this->validate([
            'siteName' => ['required', 'min:2', 'regex:/^[a-zA-Z0-9\s\-]+$/'],
            'adminUsername' => 'required|min:3',
            'adminPassword' => 'required|min:6',
            'adminEmail' => 'required|email',
        ]);

        $slug = Str::slug($this->siteName);

        if (Site::where('name', $slug)->exists()) {
            $this->addError('siteName', 'A site with this name already exists.');

            return;
        }

        $manager = app(SiteManager::class);
        $site = $manager->createNewSite($slug, $this->adminUsername, $this->adminPassword, $this->adminEmail, $this->path, $this->installSage);

        $this->siteId = $site->id;
        $this->showProgress = true;

        $this->dispatch('site-created')->to(SiteList::class);

        $steps = $manager->getNewSiteSteps($site, $this->adminPassword, $this->installSage);
        $this->cachedSteps = $steps;
        $this->executeStepSequence($steps, $site);
    }

    protected function getSite(): ?Site
    {
        return $this->siteId ? Site::find($this->siteId) : null;
    }

    public function getFailedLogFileProperty(): ?string
    {
        return $this->executionFailed ? ($this->getSite()?->log_file) : null;
    }

    public function getLastErrorProperty(): ?string
    {
        return $this->executionFailed ? ($this->getSite()?->last_error) : null;
    }

    protected function getStepDefinitions(): array
    {
        if ($this->cachedSteps === null) {
            $site = $this->getSite();
            $this->cachedSteps = $site ? app(SiteManager::class)->getNewSiteSteps($site, $this->adminPassword, $this->installSage) : [];
        }

        return $this->cachedSteps;
    }

    protected function onSequenceComplete(Site $site): void
    {
        $site->update(['status' => SiteStatus::Running]);
        $this->notifySuccess("Site '{$site->name}' created successfully!");
        $this->dispatch('site-created')->to(SiteList::class);
    }

    public function render()
    {
        return view('livewire.new-site');
    }
}
