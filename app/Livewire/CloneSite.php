<?php

namespace App\Livewire;

use App\Enums\SiteStatus;
use App\Livewire\Concerns\WithCommandExecution;
use App\Livewire\Concerns\WithNotifications;
use App\Models\RemoteSite;
use App\Models\Site;
use App\Services\PlatformDetector;
use App\Services\RemoteConnectionVerifier;
use App\Services\SiteManager;
use Illuminate\Support\Facades\Log;
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

    // Pre-flight connection check (pause/resume pattern)
    public bool $connectionCheckInProgress = false;

    public bool $connectionCheckFailed = false;

    public ?string $connectionFailurePhase = null;

    public string $connectionFailureMessage = '';

    public int $connectionAttemptCount = 0;

    /** Plain text; saved encrypted on successful verify when non-empty. */
    public string $connectionFixSshPassword = '';

    public string $connectionFixDbPassword = '';

    // Remote sites for dropdown
    public function getRemoteSitesProperty()
    {
        return RemoteSite::orderBy('remote_domain')->get();
    }

    public function mount(): void
    {
        $this->path = app(PlatformDetector::class)->defaultCodePath();
        $this->resetConnectionState();
    }

    public function updatedRemoteSiteId(): void
    {
        if (! $this->remoteSiteId) {
            return;
        }

        $remote = RemoteSite::find($this->remoteSiteId);
        if ($remote && $remote->theme_name && trim($this->siteName) === '') {
            $this->siteName = $remote->theme_name;
            $this->updatedSiteName();
        }
    }

    public function updatedSiteName(): void
    {
        $slug = Str::slug($this->siteName);
        $this->path = app(PlatformDetector::class)->defaultCodePath().DIRECTORY_SEPARATOR.$slug;
    }

    private function resetConnectionState(): void
    {
        $this->connectionCheckInProgress = false;
        $this->connectionCheckFailed = false;
        $this->connectionFailurePhase = null;
        $this->connectionFailureMessage = '';
        $this->connectionAttemptCount = 0;
        $this->connectionFixSshPassword = '';
        $this->connectionFixDbPassword = '';
    }

    public function startClone(): void
    {
        Log::info('CloneSite::startClone called', [
            'remote_site_id' => $this->remoteSiteId,
            'site_name' => $this->siteName,
        ]);

        $this->validate([
            'remoteSiteId' => 'required|exists:remote_sites,id',
            'siteName' => ['required', 'min:2', 'regex:/^[a-zA-Z0-9\s\-]+$/'],
        ]);

        $slug = Str::slug($this->siteName);

        if (Site::where('name', $slug)->exists()) {
            $this->addError('siteName', 'A site with this name already exists.');

            return;
        }

        // Check if credentials are populated (diagnostic for encryption issues)
        $remoteSite = RemoteSite::find($this->remoteSiteId);
        if (! $remoteSite) {
            $this->notifyError('Remote site not found.');

            return;
        }

        $sshPass = (string) $remoteSite->ssh_password;
        $dbPass = (string) $remoteSite->db_password;

        Log::info('CloneSite::startClone credentials check', [
            'remote_id' => $remoteSite->id,
            'ssh_pass_length' => strlen($sshPass),
            'db_pass_length' => strlen($dbPass),
        ]);

        if ($sshPass === '' || $dbPass === '') {
            $this->connectionCheckFailed = true;
            $this->connectionFailurePhase = $sshPass === '' ? 'ssh' : 'database';
            $this->connectionFailureMessage = 'Stored credentials appear empty. This can happen if the APP_KEY changed or encryption failed. Please re-enter the passwords below.';
            $this->notifyWarning('Stored credentials appear empty. Please re-enter them below.');

            return;
        }

        $this->resetConnectionState();
        $this->runConnectionCheck(canStartCloneOnSuccess: true);
    }

    /**
     * Retry the connection check with user-provided credentials.
     */
    public function retryConnection(): void
    {
        if (! $this->remoteSiteId) {
            return;
        }

        $this->connectionCheckFailed = false;
        $this->connectionFailurePhase = null;
        $this->connectionFailureMessage = '';

        $this->runConnectionCheck(canStartCloneOnSuccess: true);
    }

    /**
     * Cancel the clone attempt after repeated connection failures.
     * Resets the form since no site has been created yet (pre-flight guards creation).
     */
    public function cancelCloneAttempt(): void
    {
        $this->resetConnectionState();
        $this->notifyInfo('Clone cancelled. No site was created.');
    }

    private function runConnectionCheck(bool $canStartCloneOnSuccess): void
    {
        Log::info('CloneSite::runConnectionCheck starting', [
            'attempt' => $this->connectionAttemptCount + 1,
            'remote_site_id' => $this->remoteSiteId,
            'has_ssh_override' => filled($this->connectionFixSshPassword),
            'has_db_override' => filled($this->connectionFixDbPassword),
        ]);

        $this->connectionCheckInProgress = true;
        $this->connectionAttemptCount++;

        $remoteSite = RemoteSite::find($this->remoteSiteId);
        if (! $remoteSite) {
            Log::error('CloneSite::runConnectionCheck remote site not found', ['id' => $this->remoteSiteId]);
            $this->connectionCheckInProgress = false;
            $this->notifyError('Remote site not found.');

            return;
        }

        try {
            $verify = app(RemoteConnectionVerifier::class)->verify(
                $remoteSite,
                filled($this->connectionFixSshPassword) ? $this->connectionFixSshPassword : null,
                filled($this->connectionFixDbPassword) ? $this->connectionFixDbPassword : null,
            );
        } catch (\Throwable $e) {
            $this->connectionCheckInProgress = false;
            $this->connectionCheckFailed = true;
            $this->connectionFailurePhase = 'ssh';
            $this->connectionFailureMessage = $e->getMessage();
            $this->notifyError('Could not run connection check: '.$e->getMessage());

            return;
        }

        if (! $verify->ok) {
            Log::warning('CloneSite::runConnectionCheck failed', [
                'phase' => $verify->phase,
                'message' => $verify->message,
            ]);

            $this->connectionCheckInProgress = false;
            $this->connectionCheckFailed = true;
            $this->connectionFailurePhase = $verify->phase;
            $this->connectionFailureMessage = (string) $verify->message;

            if ($verify->phase === 'ssh') {
                $this->notifyWarning('SSH connection failed. Update the SSH password and try again, or click Cancel to abort.');
            } else {
                $this->notifyWarning('Database connection failed. Use the MySQL password from production wp-config.php and try again, or click Cancel to abort.');
            }

            return;
        }

        Log::info('CloneSite::runConnectionCheck passed');

        // Success: save any override credentials encrypted
        $credentialUpdates = [];
        if (filled($this->connectionFixSshPassword)) {
            $credentialUpdates['ssh_password'] = $this->connectionFixSshPassword;
        }
        if (filled($this->connectionFixDbPassword)) {
            $credentialUpdates['db_password'] = $this->connectionFixDbPassword;
        }
        if ($credentialUpdates !== []) {
            $remoteSite->update($credentialUpdates);
        }

        $this->connectionCheckInProgress = false;
        $this->connectionCheckFailed = false;
        $this->connectionFixSshPassword = '';
        $this->connectionFixDbPassword = '';

        if ($canStartCloneOnSuccess) {
            Log::info('CloneSite::runConnectionCheck proceeding with clone');
            $this->proceedWithClone();
        } else {
            Log::info('CloneSite::runConnectionCheck success but not proceeding (canStartCloneOnSuccess=false)');
        }
    }

    private function proceedWithClone(): void
    {
        $slug = Str::slug($this->siteName);
        $remoteSite = RemoteSite::findOrFail($this->remoteSiteId);

        $this->cachedSteps = null;

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

    /**
     * Clear the step cache before retrying so updated credentials (e.g. fixed db_user
     * in Settings) are re-read from the database instead of using the stale cached commands.
     */
    public function retryFromFailedStep(): void
    {
        $this->cachedSteps = null;
        parent::retryFromFailedStep();
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
