<?php

namespace App\Livewire;

use App\Enums\SiteStatus;
use App\Livewire\Concerns\WithCommandExecution;
use App\Livewire\Concerns\WithNotifications;
use App\Models\RemoteSite;
use App\Models\Site;
use App\Services\LandoService;
use App\Services\LandoYamlGenerator;
use App\Services\PlatformDetector;
use App\Services\RemoteConnectionVerifier;
use App\Services\SiteManager;
use App\Support\LogContentUtf8;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Native\Laravel\Facades\ChildProcess;

#[Layout('components.layouts.app')]
#[Title('Lando Studio')]
class SiteDashboard extends Component
{
    use WithCommandExecution;
    use WithNotifications;

    public Site $site;

    public bool $actionRunning = false;

    public string $actionLabel = '';

    public string $actionOutput = '';

    public bool $showDestroyModal = false;

    public ?float $actionStartedAt = null;

    // Password change
    public bool $showPasswordModal = false;

    public string $newPassword = '';

    // Version options (same lists as Settings defaults)
    public array $phpVersions = [];

    public array $dbVersions = [];

    public array $redisVersions = [];

    // Active theme
    public string $activeTheme = '';

    public array $availableThemes = [];

    // Remote site linking
    public ?int $selectedRemoteSiteId = null;

    // New Remote Site form
    public bool $showNewRemoteModal = false;

    public string $newRemoteDomain = '';

    public string $newRemoteSshIp = '';

    public string $newRemoteSshUser = '';

    public string $newRemoteSshPassword = '';

    public string $newRemoteDbName = '';

    public string $newRemoteDbUser = '';

    public string $newRemoteDbPassword = '';

    public string $newRemoteThemeName = '';

    public string $newRemotePath = '';

    // Sync Remote
    public bool $showSyncModal = false;

    public string $syncLocalTheme = '';

    public bool $syncConnectionCheckInProgress = false;

    /** Steps cached for WithCommandExecution retry. */
    private ?array $cachedSyncSteps = null;

    public function mount(Site $site): void
    {
        $this->phpVersions = config('lando_dev.defaults.php_versions');
        $this->dbVersions = config('lando_dev.defaults.db_versions');
        $this->redisVersions = config('lando_dev.defaults.redis_versions');

        $this->site = $site;
        $this->activeTheme = $site->theme_name ?? '';
        $this->selectedRemoteSiteId = $site->remote_site_id;
        $this->syncLocalTheme = $site->theme_name ?? '';
        $this->checkRealStatus();
        if (! $this->isProjectMissingOnDisk()) {
            $this->loadAvailableThemes();
        }
    }

    public function isProjectMissingOnDisk(): bool
    {
        $path = $this->site->path;

        return $path === '' || ! is_dir($path);
    }

    /**
     * Drop the DB row when the project directory is gone (e.g. folder deleted outside the app).
     */
    public function removeOrphanFromApp(): void
    {
        if (! $this->isProjectMissingOnDisk()) {
            $this->notifyError('The project folder still exists. Use Destroy to remove a real site.');

            return;
        }

        $this->deleteSiteRecordAndRedirect();
    }

    private function deleteSiteRecordAndRedirect(): void
    {
        $siteName = $this->site->name;
        $this->site->delete();
        $this->dispatch('site-deleted')->to(SiteList::class);
        $this->notifySuccess("Removed '{$siteName}' from the app.");
        $this->redirect(route('create', [], false), navigate: false);
    }

    public function loadAvailableThemes(): void
    {
        $lando = app(LandoService::class);
        $cmd = $lando->wpThemeList($this->site->path);
        $output = @shell_exec($cmd.' 2>/dev/null');

        if ($output) {
            $themes = json_decode($output, true);
            if (is_array($themes)) {
                $this->availableThemes = array_map(fn ($t) => [
                    'name' => $t['name'] ?? '',
                    'title' => $t['title'] ?? $t['name'] ?? '',
                    'status' => $t['status'] ?? '',
                ], $themes);

                // Update active theme from WP; persist to DB on first detection so the
                // theme screenshot is available without requiring a manual theme switch.
                foreach ($this->availableThemes as $theme) {
                    if ($theme['status'] === 'active') {
                        $this->activeTheme = $theme['name'];
                        if (! $this->site->theme_name) {
                            $this->site->update(['theme_name' => $theme['name']]);
                            $this->site->refresh();
                        }
                        break;
                    }
                }
            }
        }
    }

    public function checkRealStatus(): void
    {
        $lando = app(LandoService::class);

        if (! is_dir($this->site->path)) {
            $this->site->update(['status' => SiteStatus::Unknown]);
            $this->site->refresh();

            return;
        }

        $running = $lando->isRunning($this->site->path, $this->site->name);
        $newStatus = $running ? SiteStatus::Running : SiteStatus::Stopped;

        if ($this->site->status !== $newStatus && $this->site->status !== SiteStatus::Creating) {
            $this->site->update(['status' => $newStatus]);
            $this->site->refresh();
            $this->dispatch('site-status-changed')->to(SiteList::class);
        }
    }

    public function startSite(): void
    {
        if ($this->isProjectMissingOnDisk()) {
            $this->notifyError('Project folder is missing on disk.');

            return;
        }
        $this->runAction('Starting', app(LandoService::class)->start($this->site->path));
    }

    public function stopSite(): void
    {
        if ($this->isProjectMissingOnDisk()) {
            $this->notifyError('Project folder is missing on disk.');

            return;
        }
        $this->runAction('Stopping', app(LandoService::class)->stop($this->site->path));
    }

    public function restartSite(): void
    {
        if ($this->isProjectMissingOnDisk()) {
            $this->notifyError('Project folder is missing on disk.');

            return;
        }
        $this->runAction('Restarting', app(LandoService::class)->restart($this->site->path));
    }

    public function rebuildSite(): void
    {
        if ($this->isProjectMissingOnDisk()) {
            $this->notifyError('Project folder is missing on disk.');

            return;
        }
        $this->runAction('Rebuilding', app(LandoService::class)->rebuild($this->site->path));
    }

    public function confirmDestroy(): void
    {
        $this->showDestroyModal = true;
    }

    public function destroySite(): void
    {
        $this->showDestroyModal = false;

        if ($this->isProjectMissingOnDisk()) {
            $this->deleteSiteRecordAndRedirect();

            return;
        }

        $manager = app(SiteManager::class);
        $destroyCmd = $manager->destroySite($this->site);
        $deleteCmd = $manager->getDeletePath($this->site);
        $combined = "{$destroyCmd} && {$deleteCmd}";

        $this->runDestroyAction($combined);
    }

    /**
     * Same pattern as runAction: log output + pollActionStatus until 30s inactivity, then DB delete + redirect.
     */
    private function runDestroyAction(string $shellCommand): void
    {
        $this->actionRunning = true;
        $this->actionLabel = 'Destroying';
        $this->actionOutput = '';
        $this->actionStartedAt = microtime(true);

        $logFile = storage_path('logs/lando_destroy_'.$this->site->id.'_'.time().'.log');
        $logDir = dirname($logFile);
        if (! is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $this->site->update(['log_file' => $logFile]);

        $platform = app(PlatformDetector::class);

        $wrapped = $platform->wrapCommandWithLogRedirect($shellCommand, $logFile);

        ChildProcess::start(
            cmd: [$platform->shellWrapper(), $platform->shellFlag(), $wrapped],
            alias: 'destroy-site-'.$this->site->id.'-'.uniqid(),
        );
    }

    private function completeDestroySite(): void
    {
        $siteName = $this->site->name;
        $this->site->commandLogs()->delete();
        $this->site->delete();

        $this->actionRunning = false;
        $this->actionLabel = '';
        $this->actionStartedAt = null;

        $this->dispatch('site-deleted')->to(SiteList::class);
        $this->notifySuccess("Site '{$siteName}' destroyed.");

        // Relative URL: absolute route() uses APP_URL; Electron/NativePHP often runs on 127.0.0.1:8100.
        // Full page: avoids SPA follow-up requests to /sites/{id} after the row is gone (404).
        $this->redirect(route('create', [], false), navigate: false);
    }

    public function openInFinder(): void
    {
        if ($this->isProjectMissingOnDisk()) {
            $this->notifyError('Project folder is missing on disk.');

            return;
        }

        $path = $this->site->path.DIRECTORY_SEPARATOR.'wp';
        $platform = app(PlatformDetector::class);

        $cmd = match ($platform->os()) {
            'macos' => "open '{$path}'",
            'windows' => "explorer \"{$path}\"",
            default => "xdg-open '{$path}'",
        };

        ChildProcess::start(
            cmd: [$platform->shellWrapper(), $platform->shellFlag(), $cmd],
            alias: 'open-folder-'.$this->site->id,
        );
    }

    public function showChangePassword(): void
    {
        if ($this->isProjectMissingOnDisk()) {
            $this->notifyError('Project folder is missing on disk.');

            return;
        }

        $this->newPassword = '';
        $this->showPasswordModal = true;
    }

    public function changePassword(): void
    {
        if ($this->isProjectMissingOnDisk()) {
            $this->notifyError('Project folder is missing on disk.');

            return;
        }

        $this->validate([
            'newPassword' => 'required|min:6',
        ]);

        $lando = app(LandoService::class);
        $command = $lando->wpUserUpdatePassword($this->site->path, $this->site->admin_username, $this->newPassword);

        $this->showPasswordModal = false;
        $this->runAction('Changing password', $command);
    }

    public function changePhpVersion(string $version): void
    {
        if ($this->isProjectMissingOnDisk()) {
            $this->notifyError('Project folder is missing on disk.');

            return;
        }

        if ($version === $this->site->php_version) {
            return;
        }

        $this->site->update(['php_version' => $version]);
        $this->site->refresh();
        $this->regenerateLandoYaml();
        $this->rebuildSite();
    }

    public function changeDbVersion(string $version): void
    {
        if ($this->isProjectMissingOnDisk()) {
            $this->notifyError('Project folder is missing on disk.');

            return;
        }

        if ($version === $this->site->db_version) {
            return;
        }

        $this->site->update(['db_version' => $version]);
        $this->site->refresh();
        $this->regenerateLandoYaml();
        $this->rebuildSite();
    }

    public function changeRedisVersion(string $version): void
    {
        if ($this->isProjectMissingOnDisk()) {
            $this->notifyError('Project folder is missing on disk.');

            return;
        }

        if ($version === $this->site->redis_version) {
            return;
        }

        $this->site->update(['redis_version' => $version]);
        $this->site->refresh();
        $this->regenerateLandoYaml();
        $this->rebuildSite();
    }

    private function regenerateLandoYaml(): void
    {
        $generator = app(LandoYamlGenerator::class);
        $dbPort = $this->site->db_port;
        if ($dbPort === null) {
            $dbPort = app(SiteManager::class)->allocateDatabaseForwardPort();
            $this->site->update(['db_port' => $dbPort]);
            $this->site->refresh();
        }
        $generator->write($this->site->name, $this->site->path, [
            'php_version' => $this->site->php_version,
            'db_version' => $this->site->db_version,
            'redis_version' => $this->site->redis_version,
            'db_port' => $dbPort,
        ]);
    }

    public function switchTheme(string $themeName): void
    {
        if ($this->isProjectMissingOnDisk()) {
            $this->notifyError('Project folder is missing on disk.');

            return;
        }

        if ($themeName === $this->activeTheme || $themeName === '') {
            return;
        }

        $this->activeTheme = $themeName;
        $this->site->update(['theme_name' => $themeName]);
        $this->site->refresh();

        $lando = app(LandoService::class);
        $this->runAction('Activating theme', $lando->wpThemeActivate($this->site->path, $themeName));
    }

    public function pollActionStatus(): void
    {
        // Delegate to WithCommandExecution for sync operations
        if ($this->isExecuting) {
            $this->checkCommandStatus();

            return;
        }

        if (! $this->actionRunning) {
            return;
        }

        $elapsed = microtime(true) - ($this->actionStartedAt ?? microtime(true));

        $logFile = $this->site->log_file;
        if (! $logFile || ! file_exists($logFile)) {
            if ($elapsed > 30) {
                $this->actionRunning = false;
                $this->actionLabel = '';
                $this->actionStartedAt = null;
                $this->actionOutput = 'Command failed to start — log file was never created. Check that Lando is installed and in your PATH.';
                $this->dispatch('landodev-scroll-terminal');
                $this->notifyError('Action failed to start.');
            }

            return;
        }

        $content = LogContentUtf8::forLivewire((string) file_get_contents($logFile));
        $this->actionOutput = $content;
        $this->dispatch('landodev-scroll-terminal');

        if ($elapsed > 600) {
            $this->actionRunning = false;
            $this->actionLabel = '';
            $this->actionStartedAt = null;
            $this->notifyError('Action timed out after 10 minutes.');

            return;
        }

        $lastModified = filemtime($logFile);
        if ((time() - $lastModified) > 30) {
            if ($this->actionLabel === 'Destroying') {
                if ($this->destroyLogHasFatalError($content)) {
                    $this->actionRunning = false;
                    $this->actionLabel = '';
                    $this->actionStartedAt = null;
                    $this->notifyError('Destroy failed. Check the output below.');

                    return;
                }

                $this->completeDestroySite();

                return;
            }

            $completedAction = $this->actionLabel;
            $this->actionRunning = false;
            $this->actionLabel = '';
            $this->actionStartedAt = null;

            $newStatus = match ($completedAction) {
                'Stopping' => SiteStatus::Stopped,
                'Starting', 'Restarting', 'Rebuilding' => SiteStatus::Running,
                default => $this->site->status,
            };
            $this->site->update(['status' => $newStatus]);
            $this->site->refresh();

            $this->dispatch('site-status-changed')->to(SiteList::class);
            $this->notifySuccess("{$completedAction} completed.");
            $this->refreshSiteInfo();
        }
    }

    /**
     * Avoid broad patterns like "is not running" — normal destroy output can contain similar text.
     */
    private function destroyLogHasFatalError(string $content): bool
    {
        $lastLines = $this->tailLogLines($content, 20);

        $fatalPatterns = [
            'lando command not found',
            'Error response from daemon',
            'Cannot connect to the Docker daemon',
            'EACCES: permission denied',
        ];

        foreach ($fatalPatterns as $pattern) {
            if (stripos($lastLines, $pattern) !== false) {
                return true;
            }
        }

        if (preg_match('/exited with code (\d+)/', $lastLines, $matches)) {
            return (int) $matches[1] !== 0;
        }

        return false;
    }

    private function tailLogLines(string $content, int $lines): string
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return '';
        }

        $allLines = explode("\n", $trimmed);
        $slice = array_slice($allLines, -$lines);

        return implode("\n", $slice);
    }

    public function refreshSiteInfo(): void
    {
        $lando = app(LandoService::class);
        $infoCmd = $lando->info($this->site->path);
        $output = @shell_exec($infoCmd.' 2>/dev/null');

        if ($output) {
            $info = json_decode($output, true);
            if (is_array($info)) {
                $updates = [];
                foreach ($info as $service) {
                    if (($service['service'] ?? '') === 'database' && isset($service['external_connection']['port'])) {
                        $updates['db_port'] = (int) $service['external_connection']['port'];
                    }
                }
                if ($updates) {
                    $this->site->update($updates);
                    $this->site->refresh();
                }
            }
        }
    }

    private function runAction(string $label, string $command): void
    {
        $this->actionRunning = true;
        $this->actionLabel = $label;
        $this->actionOutput = '';
        $this->actionStartedAt = microtime(true);

        $logFile = storage_path('logs/lando_action_'.$this->site->id.'_'.time().'.log');
        $logDir = dirname($logFile);
        if (! is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $this->site->update(['log_file' => $logFile]);

        $platform = app(PlatformDetector::class);
        $wrapped = $platform->wrapCommandWithLogRedirect($command, $logFile);

        ChildProcess::start(
            cmd: [$platform->shellWrapper(), $platform->shellFlag(), $wrapped],
            alias: 'action-site-'.$this->site->id.'-'.uniqid(),
        );
    }

    // ── Remote Site Linking ─────────────────────────────────────────────────

    public function getRemoteSitesProperty()
    {
        return RemoteSite::orderBy('remote_domain')->get();
    }

    public function linkRemoteSite(): void
    {
        if ($this->selectedRemoteSiteId === null) {
            $this->unlinkRemoteSite();

            return;
        }

        if (! RemoteSite::find($this->selectedRemoteSiteId)) {
            $this->notifyError('Selected remote site not found.');

            return;
        }

        $this->site->update(['remote_site_id' => $this->selectedRemoteSiteId]);
        $this->site->refresh();
        $this->notifySuccess('Remote site linked.');
    }

    public function unlinkRemoteSite(): void
    {
        $this->selectedRemoteSiteId = null;
        $this->site->update(['remote_site_id' => null]);
        $this->site->refresh();
        $this->notifySuccess('Remote site unlinked.');
    }

    public function openNewRemoteModal(): void
    {
        $this->newRemoteDomain = '';
        $this->newRemoteSshIp = '';
        $this->newRemoteSshUser = '';
        $this->newRemoteSshPassword = '';
        $this->newRemoteDbName = '';
        $this->newRemoteDbUser = '';
        $this->newRemoteDbPassword = '';
        $this->newRemoteThemeName = $this->site->theme_name ?? '';
        $this->newRemotePath = '';
        $this->showNewRemoteModal = true;
    }

    public function saveNewRemoteSite(): void
    {
        $this->validate([
            'newRemoteDomain' => 'required|string|max:255',
            'newRemoteSshIp' => 'required|string|max:255',
            'newRemoteSshUser' => 'required|string|max:255',
            'newRemoteDbName' => 'required|string|max:255',
            'newRemoteDbUser' => 'required|string|max:255',
        ], [], [
            'newRemoteDomain' => 'domain',
            'newRemoteSshIp' => 'SSH server IP',
            'newRemoteSshUser' => 'SSH user',
            'newRemoteDbName' => 'database name',
            'newRemoteDbUser' => 'database user',
        ]);

        $remote = RemoteSite::create([
            'remote_domain' => $this->newRemoteDomain,
            'local_domain' => $this->site->domain ?? ($this->site->name.'.lndo.site'),
            'ssh_server_ip' => $this->newRemoteSshIp,
            'ssh_user' => $this->newRemoteSshUser,
            'ssh_password' => $this->newRemoteSshPassword ?: null,
            'db_name' => $this->newRemoteDbName,
            'db_user' => $this->newRemoteDbUser,
            'db_password' => $this->newRemoteDbPassword ?: null,
            'theme_name' => $this->newRemoteThemeName ?: null,
            'remote_path' => $this->newRemotePath ?: null,
        ]);

        $this->site->update(['remote_site_id' => $remote->id]);
        $this->site->refresh();
        $this->selectedRemoteSiteId = $remote->id;
        $this->showNewRemoteModal = false;
        $this->notifySuccess("Remote site '{$remote->remote_domain}' created and linked.");
    }

    // ── Sync Remote ─────────────────────────────────────────────────────────

    public function confirmSync(): void
    {
        if (! $this->site->remote_site_id) {
            $this->notifyError('Link a remote site first before syncing.');

            return;
        }

        if ($this->isProjectMissingOnDisk()) {
            $this->notifyError('Project folder is missing on disk.');

            return;
        }

        $this->syncLocalTheme = $this->site->theme_name ?? '';
        $this->showSyncModal = true;
    }

    public function startSync(): void
    {
        $this->showSyncModal = false;

        $remoteSite = RemoteSite::find($this->site->remote_site_id);
        if (! $remoteSite) {
            $this->notifyError('Linked remote site no longer exists. Please re-link.');

            return;
        }

        $this->syncConnectionCheckInProgress = true;

        try {
            $verify = app(RemoteConnectionVerifier::class)->verify($remoteSite);
        } catch (\Throwable $e) {
            $this->syncConnectionCheckInProgress = false;
            $this->notifyError('Connection check failed: '.$e->getMessage());

            return;
        }

        $this->syncConnectionCheckInProgress = false;

        if (! $verify->ok) {
            $this->notifyError("Connection check failed ({$verify->phase}): {$verify->message}. Update credentials in Settings.");

            return;
        }

        $this->cachedSyncSteps = null;
        $manager = app(SiteManager::class);
        $steps = $manager->getSyncSteps($this->site, $remoteSite, $this->syncLocalTheme ?: null);

        $this->executeStepSequence($steps, $this->site);
    }

    // WithCommandExecution contract ──────────────────────────────────────────

    protected function getSite(): ?Site
    {
        return $this->site;
    }

    protected function getStepDefinitions(): array
    {
        if ($this->cachedSyncSteps !== null) {
            return $this->cachedSyncSteps;
        }

        $remoteSite = RemoteSite::find($this->site->remote_site_id);
        if (! $remoteSite) {
            return [];
        }

        $this->cachedSyncSteps = app(SiteManager::class)->getSyncSteps(
            $this->site,
            $remoteSite,
            $this->syncLocalTheme ?: null
        );

        return $this->cachedSyncSteps;
    }

    protected function onSequenceComplete(Site $site): void
    {
        $this->notifySuccess("Database synced from production for '{$site->name}'.");
        $this->checkRealStatus();
    }

    public function getThemeScreenshotProperty(): ?string
    {
        // Use the DB value when available; fall back to the live in-memory activeTheme
        // for new sites that haven't had theme_name persisted yet.
        $themeName = $this->site->theme_name ?: $this->activeTheme;

        if (! $themeName || ! $this->site->path) {
            return null;
        }

        $themePath = $this->site->path.'/wp/wp-content/themes/'.$themeName;

        foreach (['screenshot.png', 'screenshot.jpg', 'screenshot.webp'] as $file) {
            $fullPath = $themePath.'/'.$file;
            if (file_exists($fullPath)) {
                $data = base64_encode(file_get_contents($fullPath));
                $ext = pathinfo($file, PATHINFO_EXTENSION);
                $mime = match ($ext) {
                    'png' => 'image/png',
                    'jpg' => 'image/jpeg',
                    'webp' => 'image/webp',
                    default => 'image/png',
                };

                return "data:{$mime};base64,{$data}";
            }
        }

        return null;
    }

    public function render()
    {
        return view('livewire.site-dashboard');
    }
}
