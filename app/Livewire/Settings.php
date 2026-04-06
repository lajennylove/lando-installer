<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithNotifications;
use App\Models\RemoteSite;
use App\Models\UserPreference;
use App\Services\ApplicationDatabaseReset;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('LandoDEV')]
class Settings extends Component
{
    use WithNotifications;

    /** App appearance: 'system' | 'light' | 'dark'. Stored in user_preferences. */
    public string $appearance = 'system';

    /** Defaults for new Lando-managed sites (container PHP/DB/Redis), not the host NativePHP runtime. */
    public string $defaultPhpVersion = '';

    public string $defaultDbVersion = '';

    public string $defaultRedisVersion = '';

    public array $phpVersions = [];

    public array $dbVersions = [];

    public array $redisVersions = [];

    public string $defaultCodePath = '';

    // Remote site form
    public bool $showRemoteSiteForm = false;

    public ?int $editingRemoteSiteId = null;

    public string $remoteDomain = '';

    /** Subdomain part only; stored as https://{slug}.lndo.site in local_domain. */
    public string $localSiteName = '';

    public string $sshServerIp = '';

    public string $sshUser = '';

    public string $sshPassword = '';

    /** Absolute path to WordPress root on the remote server (directory that contains wp-config.php). */
    public string $remotePath = '';

    public string $dbName = '';

    public string $dbUser = '';

    public string $dbPassword = '';

    public string $themeName = '';

    public string $repoUrl = '';

    public bool $installComposerDependencies = false;

    public bool $installNodeDependencies = false;

    public bool $showDeleteModal = false;

    public ?int $deletingRemoteSiteId = null;

    public bool $showResetAppDataModal = false;

    public function mount(): void
    {
        $this->appearance = UserPreference::get('appearance', 'system');

        $this->phpVersions = config('lando_dev.defaults.php_versions');
        $this->dbVersions = config('lando_dev.defaults.db_versions');
        $this->redisVersions = config('lando_dev.defaults.redis_versions');

        $defaults = $this->loadDefaults();
        $this->defaultPhpVersion = $defaults['php_version'] ?? config('lando_dev.defaults.php_version');
        $this->defaultDbVersion = $defaults['db_version'] ?? config('lando_dev.defaults.db_version');
        $this->defaultRedisVersion = $defaults['redis_version'] ?? config('lando_dev.defaults.redis_version');
        $this->defaultCodePath = $defaults['code_path'] ?? config('lando_dev.defaults.code_path') ?? '~/code/sites/';
    }

    public function setAppearance(string $value): void
    {
        $allowed = ['system', 'light', 'dark'];
        $value = in_array($value, $allowed, true) ? $value : 'system';

        $this->appearance = $value;
        UserPreference::set('appearance', $value);

        // Directly toggle the class — no localStorage, no window.Flux dependency.
        if ($value === 'dark') {
            $this->js("document.documentElement.classList.add('dark')");
        } elseif ($value === 'light') {
            $this->js("document.documentElement.classList.remove('dark')");
        } else {
            $this->js("window.matchMedia('(prefers-color-scheme: dark)').matches ? document.documentElement.classList.add('dark') : document.documentElement.classList.remove('dark')");
        }
    }

    public function saveDefaultPhpVersion(string $version): void
    {
        $this->defaultPhpVersion = $version;
        $this->persistDefaults();
        $this->notifySuccess("Default PHP version set to {$version}.");
    }

    public function saveDefaultDbVersion(string $version): void
    {
        $this->defaultDbVersion = $version;
        $this->persistDefaults();
        $this->notifySuccess("Default MariaDB version set to {$version}.");
    }

    public function saveDefaultRedisVersion(string $version): void
    {
        $this->defaultRedisVersion = $version;
        $this->persistDefaults();
        $this->notifySuccess("Default Redis version set to {$version}.");
    }

    public function saveDefaultCodePath(): void
    {
        $this->persistDefaults();
        $this->notifySuccess('Default code path updated.');
    }

    private function persistDefaults(): void
    {
        $path = storage_path('landodev_defaults.json');
        file_put_contents($path, json_encode([
            'php_version' => $this->defaultPhpVersion,
            'db_version' => $this->defaultDbVersion,
            'redis_version' => $this->defaultRedisVersion,
            'code_path' => $this->defaultCodePath,
        ], JSON_PRETTY_PRINT));
    }

    private function loadDefaults(): array
    {
        $path = storage_path('landodev_defaults.json');
        if (file_exists($path)) {
            return json_decode(file_get_contents($path), true) ?? [];
        }

        return [];
    }

    /**
     * Persisted defaults for Lando site provisioning (.lando.yml / services). Not Brew/NativePHP host PHP.
     */
    public static function getDefault(string $key): ?string
    {
        $path = storage_path('landodev_defaults.json');
        if (file_exists($path)) {
            $data = json_decode(file_get_contents($path), true) ?? [];

            return $data[$key] ?? null;
        }

        return null;
    }

    public function getRemoteSitesProperty()
    {
        return RemoteSite::orderBy('remote_domain')->get();
    }

    public function addRemoteSite(): void
    {
        $this->resetRemoteForm();
        $this->showRemoteSiteForm = true;
    }

    public function editRemoteSite(int $id): void
    {
        $remote = RemoteSite::findOrFail($id);

        $this->editingRemoteSiteId = $id;
        $this->remoteDomain = $remote->remote_domain;
        $this->localSiteName = $this->localSiteNameFromStored($remote->local_domain);
        $this->sshServerIp = $remote->ssh_server_ip;
        $this->sshUser = $remote->ssh_user;
        $this->sshPassword = $remote->ssh_password ?? '';
        $this->remotePath = $remote->remote_path ?? '';
        $this->dbName = $remote->db_name;
        $this->dbUser = $remote->db_user;
        $this->dbPassword = $remote->db_password ?? '';
        $this->themeName = $remote->theme_name ?? '';
        $this->repoUrl = $remote->repo_url ?? '';
        $this->installComposerDependencies = (bool) $remote->install_composer_dependencies;
        $this->installNodeDependencies = (bool) $remote->install_node_dependencies;
        $this->showRemoteSiteForm = true;
    }

    public function saveRemoteSite(): void
    {
        $this->validate([
            'remoteDomain' => 'required|url',
            'localSiteName' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9\s\-]+$/'],
            'sshServerIp' => 'required',
            'sshUser' => 'required',
            'remotePath' => 'required|string|max:512',
            'dbName' => 'required',
            'dbUser' => 'required',
        ]);

        $repoUrl = $this->repoUrl ?: null;

        $localSlug = Str::slug(trim($this->localSiteName));
        $localDomain = $localSlug !== '' ? "https://{$localSlug}.lndo.site" : null;

        $data = [
            'remote_domain' => $this->remoteDomain,
            'local_domain' => $localDomain,
            'ssh_server_ip' => $this->sshServerIp,
            'ssh_user' => $this->sshUser,
            'ssh_password' => $this->sshPassword ?: null,
            'remote_path' => rtrim($this->remotePath),
            'db_name' => $this->dbName,
            'db_user' => $this->dbUser,
            'db_password' => $this->dbPassword ?: null,
            'theme_name' => $this->themeName ?: null,
            'repo_url' => $repoUrl,
            'install_composer_dependencies' => $repoUrl && $this->installComposerDependencies,
            'install_node_dependencies' => $repoUrl && $this->installNodeDependencies,
        ];

        if ($this->editingRemoteSiteId) {
            RemoteSite::findOrFail($this->editingRemoteSiteId)->update($data);
            $this->notifySuccess('Remote site updated.');
        } else {
            RemoteSite::create($data);
            $this->notifySuccess('Remote site added.');
        }

        $this->showRemoteSiteForm = false;
        $this->resetRemoteForm();
    }

    public function confirmDeleteRemoteSite(int $id): void
    {
        $this->deletingRemoteSiteId = $id;
        $this->showDeleteModal = true;
    }

    public function deleteRemoteSite(): void
    {
        if ($this->deletingRemoteSiteId) {
            RemoteSite::findOrFail($this->deletingRemoteSiteId)->delete();
            $this->notifySuccess('Remote site deleted.');
        }

        $this->showDeleteModal = false;
        $this->deletingRemoteSiteId = null;
    }

    public function cancelRemoteForm(): void
    {
        $this->showRemoteSiteForm = false;
        $this->resetRemoteForm();
    }

    /**
     * Populate the short-name field when editing a row that stores a full Lando URL.
     */
    private function localSiteNameFromStored(?string $localDomain): string
    {
        if ($localDomain === null || trim($localDomain) === '') {
            return '';
        }

        $trimmed = rtrim(trim($localDomain), '/');
        if (preg_match('#^https?://([^/]+)$#i', $trimmed, $m) === 1) {
            $host = $m[1];
            if (preg_match('#^(.+)\.lndo\.site$#i', $host, $hm) === 1) {
                return $hm[1];
            }
        }

        return $trimmed;
    }

    private function resetRemoteForm(): void
    {
        $this->editingRemoteSiteId = null;
        $this->remoteDomain = '';
        $this->localSiteName = '';
        $this->sshServerIp = '';
        $this->sshUser = '';
        $this->sshPassword = '';
        $this->remotePath = '';
        $this->dbName = '';
        $this->dbUser = '';
        $this->dbPassword = '';
        $this->themeName = '';
        $this->repoUrl = '';
        $this->installComposerDependencies = false;
        $this->installNodeDependencies = false;
    }

    public function resetApplicationData(): void
    {
        ApplicationDatabaseReset::wipe();
        $this->showResetAppDataModal = false;
        $this->notifySuccess('Application data cleared on all app database files (including NativePHP).');
        // Sidebar SiteList is outside this component’s DOM subtree; events must target it explicitly.
        $this->dispatch('application-data-reset')->to(SiteList::class);
        $this->dispatch('site-deleted')->to(SiteList::class);
    }

    /**
     * @return list<array{connection: string, path: string, file_exists: bool, site_count: int|null, error?: string}>
     */
    public function databaseLocationRows(): array
    {
        return ApplicationDatabaseReset::sqliteLocationsForDisplay();
    }

    public function render()
    {
        return view('livewire.settings');
    }
}
