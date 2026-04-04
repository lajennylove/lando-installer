<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithNotifications;
use App\Models\RemoteSite;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Settings extends Component
{
    use WithNotifications;

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

    public string $localDomain = '';

    public string $sshServerIp = '';

    public string $sshUser = '';

    public string $sshPassword = '';

    public string $dbName = '';

    public string $dbUser = '';

    public string $dbPassword = '';

    public string $themeName = '';

    public string $repoUrl = '';

    public bool $showDeleteModal = false;

    public ?int $deletingRemoteSiteId = null;

    public function mount(): void
    {
        $this->phpVersions = config('lando_dev.defaults.php_versions');
        $this->dbVersions = config('lando_dev.defaults.db_versions');
        $this->redisVersions = config('lando_dev.defaults.redis_versions');

        $defaults = $this->loadDefaults();
        $this->defaultPhpVersion = $defaults['php_version'] ?? config('lando_dev.defaults.php_version');
        $this->defaultDbVersion = $defaults['db_version'] ?? config('lando_dev.defaults.db_version');
        $this->defaultRedisVersion = $defaults['redis_version'] ?? config('lando_dev.defaults.redis_version');
        $this->defaultCodePath = $defaults['code_path'] ?? config('lando_dev.defaults.code_path') ?? '~/code/sites/';
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
        $this->localDomain = $remote->local_domain ?? '';
        $this->sshServerIp = $remote->ssh_server_ip;
        $this->sshUser = $remote->ssh_user;
        $this->sshPassword = $remote->ssh_password ?? '';
        $this->dbName = $remote->db_name;
        $this->dbUser = $remote->db_user;
        $this->dbPassword = $remote->db_password ?? '';
        $this->themeName = $remote->theme_name ?? '';
        $this->repoUrl = $remote->repo_url ?? '';
        $this->showRemoteSiteForm = true;
    }

    public function saveRemoteSite(): void
    {
        $this->validate([
            'remoteDomain' => 'required|url',
            'sshServerIp' => 'required',
            'sshUser' => 'required',
            'dbName' => 'required',
            'dbUser' => 'required',
        ]);

        $data = [
            'remote_domain' => $this->remoteDomain,
            'local_domain' => $this->localDomain ?: null,
            'ssh_server_ip' => $this->sshServerIp,
            'ssh_user' => $this->sshUser,
            'ssh_password' => $this->sshPassword ?: null,
            'db_name' => $this->dbName,
            'db_user' => $this->dbUser,
            'db_password' => $this->dbPassword ?: null,
            'theme_name' => $this->themeName ?: null,
            'repo_url' => $this->repoUrl ?: null,
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

    private function resetRemoteForm(): void
    {
        $this->editingRemoteSiteId = null;
        $this->remoteDomain = '';
        $this->localDomain = '';
        $this->sshServerIp = '';
        $this->sshUser = '';
        $this->sshPassword = '';
        $this->dbName = '';
        $this->dbUser = '';
        $this->dbPassword = '';
        $this->themeName = '';
        $this->repoUrl = '';
    }

    public function render()
    {
        return view('livewire.settings');
    }
}
