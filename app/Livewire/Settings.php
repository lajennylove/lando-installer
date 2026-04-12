<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithNotifications;
use App\Models\RemoteSite;
use App\Models\UserPreference;
use App\Services\ApplicationDatabaseReset;
use App\Services\DependencyChecker;
use App\Services\PlatformDetector;
use App\Support\LogContentUtf8;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Native\Laravel\Facades\ChildProcess;

#[Layout('components.layouts.app')]
#[Title('Lando Studio')]
class Settings extends Component
{
    use WithFileUploads;
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

    // Lando update
    public bool $landoUpdating = false;

    public string $landoUpdateOutput = '';

    public string $landoUpdateLogFile = '';

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

    // Batch import/export
    public bool $showBatchModal = false;

    /** @var mixed Livewire temp upload */
    public $batchCsvFile = null;

    public array $batchRows = [];

    public bool $batchSelectAll = true;

    public bool $batchParseError = false;

    public string $batchParseErrorMessage = '';

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

    public function runLandoUpdate(): void
    {
        $checker = app(DependencyChecker::class);

        if (! $checker->isLandoInstalled()) {
            $this->notifyError('Lando must be installed before running an update.');

            return;
        }

        $platform = app(PlatformDetector::class);
        $lando = $checker->getLandoPath() ?? 'lando';
        $marker = '[Lando Studio] lando update finished';

        $this->landoUpdating = true;
        $this->landoUpdateOutput = '';
        $this->landoUpdateLogFile = storage_path('logs/lando_update.log');

        @unlink($this->landoUpdateLogFile);
        file_put_contents($this->landoUpdateLogFile, "[Lando Studio] Running: {$lando} update -y\n");

        if ($platform->isWindows()) {
            $log = addslashes($this->landoUpdateLogFile);
            $cmd = [...$platform->powershellArgs(), "& \"{$lando}\" update -y *> '{$log}'; Add-Content -Path '{$log}' -Value \"{$marker}\""];
        } else {
            $logArg = escapeshellarg($this->landoUpdateLogFile);
            $markerArg = escapeshellarg($marker);
            $command = escapeshellarg($lando)." update -y > {$logArg} 2>&1; echo {$markerArg} >> {$logArg}";
            $cmd = [$platform->shellWrapper(), $platform->shellFlag(), $command];
        }

        try {
            ChildProcess::start(cmd: $cmd, alias: 'lando-update');
        } catch (\Throwable $e) {
            $this->landoUpdating = false;
            $this->landoUpdateOutput = '[Lando Studio ERROR] Failed to start lando update: '.$e->getMessage();
        }
    }

    public function pollLandoUpdate(): void
    {
        if (! $this->landoUpdating) {
            return;
        }

        if ($this->landoUpdateLogFile && file_exists($this->landoUpdateLogFile)) {
            $raw = file_get_contents($this->landoUpdateLogFile) ?: '';
            $this->landoUpdateOutput = mb_substr(LogContentUtf8::forLivewire($raw), -6000);
            $this->dispatch('landodev-scroll-terminal');
        }

        if (str_contains($this->landoUpdateOutput, '[Lando Studio] lando update finished')) {
            $this->landoUpdating = false;
            $this->landoUpdateLogFile = '';
            $this->dispatch('landodev-scroll-terminal');
            $this->notifySuccess('Lando updated successfully!');
        }
    }

    public function openBatchModal(): void
    {
        $this->batchRows = [];
        $this->batchCsvFile = null;
        $this->batchParseError = false;
        $this->batchParseErrorMessage = '';
        $this->batchSelectAll = true;
        $this->showBatchModal = true;
    }

    public function exportRemoteSites(): mixed
    {
        $remotes = RemoteSite::all();
        $headers = [
            'remote_domain', 'local_domain', 'ssh_server_ip', 'ssh_user', 'ssh_password',
            'remote_path', 'db_name', 'db_user', 'db_password', 'theme_name', 'repo_url',
            'install_composer_dependencies', 'install_node_dependencies',
        ];

        $isDemo = $remotes->isEmpty();
        $filename = $isDemo ? 'remote-sites-template.csv' : 'remote-sites-export.csv';

        return response()->streamDownload(function () use ($remotes, $headers, $isDemo) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers, '|');

            if ($isDemo) {
                fputcsv($out, [
                    'https://example.com', 'https://my-site.lndo.site',
                    '1.2.3.4', 'forge', 'secret',
                    '/home/forge/example.com/public', 'wp_production',
                    'wp_user', 'db_password', 'my-theme',
                    'https://github.com/org/theme', '0', '0',
                ], '|');
            } else {
                foreach ($remotes as $r) {
                    fputcsv($out, [
                        $r->remote_domain, $r->local_domain ?? '',
                        $r->ssh_server_ip, $r->ssh_user, $r->ssh_password ?? '',
                        $r->remote_path ?? '', $r->db_name, $r->db_user,
                        $r->db_password ?? '', $r->theme_name ?? '',
                        $r->repo_url ?? '',
                        $r->install_composer_dependencies ? '1' : '0',
                        $r->install_node_dependencies ? '1' : '0',
                    ], '|');
                }
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/plain']);
    }

    public function updatedBatchCsvFile(): void
    {
        $this->parseBatchCsv();
    }

    private function parseBatchCsv(): void
    {
        $this->batchRows = [];
        $this->batchParseError = false;

        $path = $this->batchCsvFile->getRealPath();
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $expectedHeaders = [
            'remote_domain', 'local_domain', 'ssh_server_ip', 'ssh_user', 'ssh_password',
            'remote_path', 'db_name', 'db_user', 'db_password', 'theme_name', 'repo_url',
            'install_composer_dependencies', 'install_node_dependencies',
        ];

        $headers = str_getcsv(array_shift($lines), '|');
        if ($headers !== $expectedHeaders) {
            $this->batchParseError = true;
            $this->batchParseErrorMessage = 'CSV headers do not match the expected format. Please use the template.';

            return;
        }

        $existing = RemoteSite::all()->keyBy('remote_domain');

        foreach ($lines as $line) {
            $values = str_getcsv($line, '|');
            if (count($values) !== count($expectedHeaders)) {
                continue;
            }

            $row = array_combine($expectedHeaders, $values);
            $existingRecord = $existing->get($row['remote_domain']);
            $changedFields = [];

            if ($existingRecord) {
                $checkFields = [
                    'local_domain', 'ssh_server_ip', 'ssh_user', 'remote_path',
                    'db_name', 'db_user', 'theme_name', 'repo_url',
                    'install_composer_dependencies', 'install_node_dependencies',
                ];

                foreach ($checkFields as $field) {
                    $csvVal = $field === 'install_composer_dependencies' || $field === 'install_node_dependencies'
                        ? (bool) (int) $row[$field]
                        : ($row[$field] ?: null);
                    $dbVal = $existingRecord->$field;
                    if ((string) $csvVal !== (string) $dbVal) {
                        $changedFields[] = $field;
                    }
                }

                foreach (['ssh_password', 'db_password'] as $passField) {
                    if (! empty($row[$passField])) {
                        $changedFields[] = $passField;
                    }
                }
            }

            $this->batchRows[] = [
                'data' => $row,
                'exists' => $existingRecord !== null,
                'existingId' => $existingRecord?->id,
                'changedFields' => $changedFields,
                'selected' => true,
            ];
        }
    }

    public function toggleBatchSelectAll(): void
    {
        foreach ($this->batchRows as $i => $row) {
            $this->batchRows[$i]['selected'] = $this->batchSelectAll;
        }
    }

    public function executeBatchImport(): void
    {
        $created = 0;
        $updated = 0;

        foreach ($this->batchRows as $row) {
            if (! $row['selected']) {
                continue;
            }

            $data = [
                'remote_domain' => $row['data']['remote_domain'],
                'local_domain' => $row['data']['local_domain'] ?: null,
                'ssh_server_ip' => $row['data']['ssh_server_ip'],
                'ssh_user' => $row['data']['ssh_user'],
                'remote_path' => $row['data']['remote_path'] ?: null,
                'db_name' => $row['data']['db_name'],
                'db_user' => $row['data']['db_user'],
                'theme_name' => $row['data']['theme_name'] ?: null,
                'repo_url' => $row['data']['repo_url'] ?: null,
                'install_composer_dependencies' => (bool) (int) $row['data']['install_composer_dependencies'],
                'install_node_dependencies' => (bool) (int) $row['data']['install_node_dependencies'],
            ];

            if (! empty($row['data']['ssh_password'])) {
                $data['ssh_password'] = $row['data']['ssh_password'];
            }
            if (! empty($row['data']['db_password'])) {
                $data['db_password'] = $row['data']['db_password'];
            }

            if ($row['exists'] && $row['existingId']) {
                RemoteSite::findOrFail($row['existingId'])->update($data);
                $updated++;
            } else {
                RemoteSite::create($data);
                $created++;
            }
        }

        $this->batchRows = [];
        $this->batchCsvFile = null;
        $this->showBatchModal = false;

        $msg = collect([
            $created ? "{$created} created" : null,
            $updated ? "{$updated} updated" : null,
        ])->filter()->join(', ');

        $this->notifySuccess("Import complete: {$msg}.");
    }

    public function render()
    {
        return view('livewire.settings');
    }
}
