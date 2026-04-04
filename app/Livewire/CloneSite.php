<?php

namespace App\Livewire;

use App\Enums\SiteStatus;
use App\Livewire\Concerns\WithCommandExecution;
use App\Livewire\Concerns\WithNotifications;
use App\Models\RemoteSite;
use App\Models\Site;
use App\Services\PlatformDetector;
use App\Services\SiteManager;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('LandoDEV')]
class CloneSite extends Component
{
    use WithCommandExecution;
    use WithNotifications;

    // Step 1: Select remote site
    public ?int $remoteSiteId = null;

    public string $siteName = '';

    public string $path = '';

    // Progress
    public bool $showProgress = false;

    public ?int $siteId = null;

    private ?array $cachedSteps = null;

    // Remote sites for dropdown
    public function getRemoteSitesProperty()
    {
        return RemoteSite::orderBy('remote_domain')->get();
    }

    public function mount(): void
    {
        $this->path = app(PlatformDetector::class)->defaultCodePath();
    }

    public function updatedRemoteSiteId(): void
    {
        if ($this->remoteSiteId) {
            $remote = RemoteSite::find($this->remoteSiteId);
            if ($remote && $remote->theme_name) {
                $this->siteName = $remote->theme_name;
                $this->updatedSiteName();
            }
        }
    }

    public function updatedSiteName(): void
    {
        $slug = Str::slug($this->siteName);
        $this->path = app(PlatformDetector::class)->defaultCodePath().DIRECTORY_SEPARATOR.$slug;
    }

    public function startClone(): void
    {
        $this->validate([
            'remoteSiteId' => 'required|exists:remote_sites,id',
            'siteName' => ['required', 'min:2', 'regex:/^[a-zA-Z0-9\s\-]+$/'],
        ]);

        $slug = Str::slug($this->siteName);

        if (Site::where('name', $slug)->exists()) {
            $this->addError('siteName', 'A site with this name already exists.');

            return;
        }

        $remoteSite = RemoteSite::findOrFail($this->remoteSiteId);
        $manager = app(SiteManager::class);
        $site = $manager->cloneSite($remoteSite, $slug, $this->path);

        $this->siteId = $site->id;
        $this->showProgress = true;

        $this->dispatch('site-created')->to(SiteList::class);

        $steps = $manager->getCloneSiteSteps($site, $remoteSite);
        $this->executeStepSequence($steps, $site);
    }

    protected function getSite(): ?Site
    {
        return $this->siteId ? Site::find($this->siteId) : null;
    }

    protected function getStepDefinitions(): array
    {
        if ($this->cachedSteps === null) {
            $site = $this->getSite();
            if ($site && $site->remote_site_id) {
                $remote = RemoteSite::find($site->remote_site_id);
                $this->cachedSteps = $remote
                    ? app(SiteManager::class)->getCloneSiteSteps($site, $remote)
                    : [];
            } else {
                $this->cachedSteps = [];
            }
        }

        return $this->cachedSteps;
    }

    protected function onSequenceComplete(Site $site): void
    {
        $site->update(['status' => SiteStatus::Running]);
        $this->notifySuccess("Site '{$site->name}' cloned successfully!");
        $this->dispatch('site-created')->to(SiteList::class);
    }

    public function render()
    {
        return view('livewire.clone-site');
    }
}
