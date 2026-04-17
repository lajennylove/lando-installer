<?php

namespace App\Services;

use App\Enums\SiteStatus;
use App\Livewire\Settings;
use App\Models\RemoteSite;
use App\Models\Site;
use Illuminate\Support\Str;

class SiteManager
{
    public function __construct(
        private PlatformDetector $platform,
        private LandoService $lando,
        private LandoYamlGenerator $yamlGenerator,
        private SshService $ssh,
    ) {}

    /**
     * @return array{php_version: string, db_version: string, redis_version: string}
     */
    private function resolvedLandoVersions(): array
    {
        return [
            'php_version' => Settings::getDefault('php_version') ?? config('lando_dev.defaults.php_version'),
            'db_version' => Settings::getDefault('db_version') ?? config('lando_dev.defaults.db_version'),
            'redis_version' => Settings::getDefault('redis_version') ?? config('lando_dev.defaults.redis_version'),
        ];
    }

    /**
     * Next unique host port for database portforward in .lando.yml (avoids Docker bind conflicts).
     */
    public function allocateDatabaseForwardPort(): int
    {
        $start = (int) config('lando_dev.defaults.database_forward_port_start', 32_787);
        $maxExisting = Site::query()->max('db_port');

        if ($maxExisting === null) {
            return $start;
        }

        return max($start, (int) $maxExisting + 1);
    }

    public function createNewSite(string $name, string $adminUser, string $adminPass, string $adminEmail, ?string $path = null, bool $installSage = true): Site
    {
        $slug = Str::slug($name);
        $sitePath = $path ?? ($this->platform->defaultCodePath().DIRECTORY_SEPARATOR.$slug);

        if (! is_dir($sitePath)) {
            mkdir($sitePath, 0755, true);
        }

        // Create the webroot directory before lando start so the container can mount it
        $webrootPath = $sitePath.DIRECTORY_SEPARATOR.'wp';
        if (! is_dir($webrootPath)) {
            mkdir($webrootPath, 0755, true);
        }

        $versions = $this->resolvedLandoVersions();
        $dbPort = $this->allocateDatabaseForwardPort();
        $this->yamlGenerator->write($slug, $sitePath, array_merge($versions, ['db_port' => $dbPort]));

        // Create .nvmrc for Node version consistency
        file_put_contents($sitePath.DIRECTORY_SEPARATOR.'.nvmrc', "22\n");

        $this->copyCaCertIfConfigured($sitePath);

        return Site::create([
            'name' => $slug,
            'path' => $sitePath,
            'url' => "https://{$slug}.lndo.site",
            'admin_url' => "https://{$slug}.lndo.site/wp-admin",
            'status' => SiteStatus::Creating,
            // Lando service versions (container), not the Homebrew PHP that runs the Electron app
            'php_version' => $versions['php_version'],
            'db_version' => $versions['db_version'],
            'redis_version' => $versions['redis_version'],
            'db_port' => $dbPort,
            'admin_username' => $adminUser,
            'admin_email' => $adminEmail,
            'theme_name' => $installSage ? $name : null,
        ]);
    }

    public function getNewSiteSteps(Site $site, ?string $adminPassword = null, bool $installSage = true): array
    {
        $path = $site->path;
        $name = $site->name;
        $adminPass = $adminPassword ?? 'admin';

        $steps = [
            [
                'label' => 'Starting Lando environment',
                'command' => $this->lando->start($path),
                'step' => 1,
            ],
            [
                'label' => 'Downloading WordPress core',
                'command' => $this->lando->wpCoreDownload($path),
                'step' => 2,
            ],
            [
                'label' => 'Creating WordPress config',
                'command' => $this->lando->wpConfigCreate($path),
                'step' => 3,
            ],
            [
                'label' => 'Installing WordPress',
                'command' => $this->lando->wpCoreInstall($path, $name, (string) $site->admin_username, $adminPass, (string) $site->admin_email),
                'step' => 4,
            ],
        ];

        if (! $installSage) {
            return $steps;
        }

        return array_merge($steps, [
            [
                'label' => 'Creating Sage theme',
                'command' => $this->lando->composerCreateProject($path, 'roots/sage', $name),
                'step' => 5,
            ],
            [
                'label' => 'Installing Composer dependencies',
                'command' => $this->lando->composerInstall($path, "wp/wp-content/themes/{$name}"),
                'step' => 6,
            ],
            [
                'label' => 'Installing Yarn dependencies',
                'command' => $this->lando->yarn($path, "wp/wp-content/themes/{$name}"),
                'step' => 7,
            ],
            [
                'label' => 'Building theme assets',
                'command' => $this->lando->yarn($path, "wp/wp-content/themes/{$name}", 'build'),
                'step' => 8,
            ],
            [
                'label' => 'Activating theme',
                'command' => $this->lando->wpThemeActivate($path, $name),
                'step' => 9,
            ],
        ]);
    }

    public function cloneSite(RemoteSite $remoteSite, string $name, ?string $path = null): Site
    {
        $slug = Str::slug($name);
        $sitePath = $path ?? ($this->platform->defaultCodePath().DIRECTORY_SEPARATOR.$slug);

        if (! is_dir($sitePath)) {
            mkdir($sitePath, 0755, true);
        }

        $webrootPath = $sitePath.DIRECTORY_SEPARATOR.'wp';
        if (! is_dir($webrootPath)) {
            mkdir($webrootPath, 0755, true);
        }

        $versions = $this->resolvedLandoVersions();
        $dbPort = $this->allocateDatabaseForwardPort();
        $this->yamlGenerator->write($slug, $sitePath, array_merge($versions, ['db_port' => $dbPort]));

        $this->copyCaCertIfConfigured($sitePath);

        return Site::create([
            'name' => $slug,
            'path' => $sitePath,
            'url' => "https://{$slug}.lndo.site",
            'admin_url' => "https://{$slug}.lndo.site/wp-admin",
            'status' => SiteStatus::Creating,
            // Lando service versions (container), not the Homebrew PHP that runs the Electron app
            'php_version' => $versions['php_version'],
            'db_version' => $versions['db_version'],
            'redis_version' => $versions['redis_version'],
            'db_port' => $dbPort,
            'theme_name' => $remoteSite->theme_name,
            'remote_site_id' => $remoteSite->id,
        ]);
    }

    public function getCloneSiteSteps(Site $site, RemoteSite $remoteSite): array
    {
        $path = $site->path;
        $name = $site->name;
        $dumpFile = "{$path}/dumpfile.sql.gz";

        $steps = [
            [
                'label' => 'Starting Lando environment',
                'command' => $this->lando->start($path),
                'step' => 1,
            ],
            [
                'label' => 'Downloading WordPress core',
                'command' => $this->lando->wpCoreDownload($path),
                'step' => 2,
            ],
            [
                'label' => 'Dumping remote database',
                'hint' => 'Streams SQL over SSH then compresses locally — nothing is written on the remote server. Expect 2–10 minutes depending on database size.',
                'timeout' => 1200,
                'command' => $this->ssh->buildMysqldumpCommand($remoteSite, $dumpFile),
                'step' => 3,
            ],
            [
                'label' => 'Validating dump file',
                'command' => $this->validateDumpFileCommand($dumpFile),
                'step' => 4,
            ],
            [
                'label' => 'Importing database',
                'command' => $this->lando->dbImport($path, 'dumpfile.sql.gz'),
                'step' => 5,
            ],
            [
                'label' => 'Creating WordPress config',
                'command' => $this->cloneWpConfigCommand($path, $site),
                'step' => 6,
            ],
            [
                'label' => 'Cleaning up dump file',
                'command' => $this->removeDumpFileCommand($dumpFile),
                'step' => 7,
            ],
            [
                'label' => 'Replacing domain references',
                'hint' => 'Search-Replace prod-url for localdev-url.',
                'timeout' => 1200,
                'command' => $this->lando->wpSearchReplaceImported($path, "https://{$name}.lndo.site", $remoteSite->theme_name ?: null),
                'step' => 8,
            ],
            [
                'label' => 'Activating local theme',
                // The production DB stores the theme folder name used on the server (e.g. "locker-room").
                // We clone into a folder named after remote_sites.theme_name (e.g. "lockerroom").
                // These can differ, causing WordPress to silently output nothing. Force both options
                // to match the local folder name so the theme is found and rendered correctly.
                'command' => $remoteSite->theme_name
                    ? 'cd '.escapeshellarg($path)." && {$this->lando->getLandoPath()} wp option update template ".escapeshellarg($remoteSite->theme_name)." --path=wp && {$this->lando->getLandoPath()} wp option update stylesheet ".escapeshellarg($remoteSite->theme_name).' --path=wp'
                    : 'echo "No theme configured, skipping theme activation"',
                'step' => 9,
            ],
            [
                'label' => 'Syncing plugins from remote',
                'command' => $this->ssh->buildRsyncPluginsCommand($remoteSite, "{$path}/wp/wp-content/plugins/"),
                'step' => 10,
            ],
            [
                'label' => 'Configuring image proxy',
                'command' => 'echo '.escapeshellarg($this->ssh->buildHtaccessRewriteContent($remoteSite->remote_domain))." > {$path}/wp/.htaccess",
                'step' => 11,
            ],
        ];

        $themeSubdir = $remoteSite->theme_name
            ? "wp/wp-content/themes/{$remoteSite->theme_name}"
            : null;
        $cloneTheme = $remoteSite->repo_url && $remoteSite->theme_name;

        $stepNum = 12;
        $steps[] = [
            'label' => 'Cloning theme repository',
            // --progress forces git to write progress lines to stderr even when stderr is not a TTY
            // (which it isn't — it's redirected to the step log file). Without it, git is silent
            // after "Cloning into '...'..." and the 30s log-idle timer fires prematurely, advancing
            // to the Composer step before the clone finishes — so composer.json isn't there yet.
            // The trailing echo writes one final line after git exits, confirming the clone is done.
            'command' => $remoteSite->repo_url
                ? "cd {$path}/wp/wp-content/themes && git clone --progress {$remoteSite->repo_url} {$remoteSite->theme_name} && echo \"Theme cloned successfully.\""
                : 'echo "No repo URL configured, skipping theme clone"',
            'step' => $stepNum++,
        ];

        if ($cloneTheme) {
            $themePath = "{$path}/wp/wp-content/themes/{$remoteSite->theme_name}";
            $steps[] = [
                'label' => 'Switching to dev branch',
                'command' => "cd {$themePath} && git checkout dev && echo \"Switched to branch: $(git branch --show-current)\"",
                'step' => $stepNum++,
            ];
        }

        if ($cloneTheme && $remoteSite->install_composer_dependencies && $themeSubdir) {
            $steps[] = [
                'label' => 'Installing Composer dependencies',
                'command' => $this->lando->composerInstall($path, $themeSubdir),
                'step' => $stepNum++,
            ];
        }

        if ($cloneTheme && $remoteSite->install_node_dependencies && $themeSubdir) {
            $steps[] = [
                'label' => 'Installing Node dependencies',
                'command' => $this->lando->yarn($path, $themeSubdir),
                'step' => $stepNum++,
            ];
        }

        $steps[] = [
            'label' => 'Building theme',
            'command' => $remoteSite->theme_name && $themeSubdir
                ? $this->lando->yarn($path, $themeSubdir, 'build')
                : 'echo "No theme configured, skipping build"',
            'step' => $stepNum++,
        ];

        return $steps;
    }

    /**
     * Host PHP runs artisan to write wp-config.php (WpConfigGenerator) and detect $table_prefix.
     */
    private function cloneWpConfigCommand(string $path, Site $site): string
    {
        return sprintf(
            '%s %s lando-dev:clone-wp-config %s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(base_path('artisan')),
            escapeshellarg($path),
            escapeshellarg((string) $site->id)
        );
    }

    private function validateDumpFileCommand(string $dumpFile): string
    {
        $escaped = escapeshellarg($dumpFile);
        $minBytes = 500;

        if ($this->platform->isWindows()) {
            return "powershell -Command \"if ((Get-Item {$escaped}).Length -lt {$minBytes}) { Write-Error 'Dump file is too small or empty - mysqldump may have failed'; exit 1 } else { Write-Host 'Dump file validated' }\"";
        }

        return "if [ ! -s {$escaped} ] || [ \$(stat -f%z {$escaped} 2>/dev/null || stat -c%s {$escaped} 2>/dev/null) -lt {$minBytes} ]; then echo 'ERROR: Dump file is too small or empty - mysqldump may have failed. Check SSH credentials and remote database settings.' >&2; exit 1; fi; if ! gzip -t {$escaped}; then echo 'ERROR: dumpfile.sql.gz is not a complete gzip archive (transfer or dump may have been interrupted).' >&2; exit 1; fi; echo 'Dump file validated:' && ls -lh {$escaped}";
    }

    private function removeDumpFileCommand(string $dumpFile): string
    {
        $sqlFile = preg_replace('/\.sql\.gz$/u', '.sql', $dumpFile);

        if ($this->platform->isWindows()) {
            $winGz = str_replace('/', '\\', $dumpFile);
            $winSql = str_replace('/', '\\', $sqlFile);

            return 'del /f /q '.escapeshellarg($winGz).' '.escapeshellarg($winSql).' 2>nul & echo Dump file removed.';
        }

        return 'rm -f '.escapeshellarg($dumpFile).' '.escapeshellarg($sqlFile)." && echo 'Removed local dump files (dumpfile.sql / .gz).'";
    }

    public function destroySite(Site $site): string
    {
        return $this->lando->destroy($site->path);
    }

    public function getDeletePath(Site $site): string
    {
        if (app(PlatformDetector::class)->isWindows()) {
            return "rmdir /s /q \"{$site->path}\"";
        }

        return "rm -rf '{$site->path}'";
    }

    /**
     * Copy the user-configured CA certificate into the site directory so Lando
     * can mount it into the container and install it in the system CA store.
     */
    private function copyCaCertIfConfigured(string $sitePath): void
    {
        $certPath = Settings::getDefault('ca_cert_path');

        if (! $certPath || ! is_file($certPath)) {
            return;
        }

        $dest = $sitePath.DIRECTORY_SEPARATOR.'lando-ca.crt';
        copy($certPath, $dest);
    }
}
