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

    public function createNewSite(string $name, string $adminUser, string $adminPass, string $adminEmail, ?string $path = null): Site
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
        $this->yamlGenerator->write($slug, $sitePath, $versions);

        // Create .nvmrc for Node version consistency
        file_put_contents($sitePath.DIRECTORY_SEPARATOR.'.nvmrc', "22\n");

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
            'admin_username' => $adminUser,
            'admin_email' => $adminEmail,
            'theme_name' => $slug,
        ]);
    }

    public function getNewSiteSteps(Site $site, ?string $adminPassword = null): array
    {
        $path = $site->path;
        $name = $site->name;
        $adminPass = $adminPassword ?? 'admin';

        return [
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
        ];
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
        $this->yamlGenerator->write($slug, $sitePath, $versions);

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
            'theme_name' => $remoteSite->theme_name,
            'remote_site_id' => $remoteSite->id,
        ]);
    }

    public function getCloneSiteSteps(Site $site, RemoteSite $remoteSite): array
    {
        $path = $site->path;
        $name = $site->name;
        $dumpFile = "{$path}/dumpfile.sql.gz";

        return [
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
                'command' => $this->ssh->buildMysqldumpCommand($remoteSite, $dumpFile),
                'step' => 3,
            ],
            [
                'label' => 'Importing database',
                'command' => $this->lando->dbImport($path, 'dumpfile.sql.gz'),
                'step' => 4,
            ],
            [
                'label' => 'Cleaning up dump file',
                'command' => "rm -f {$dumpFile}",
                'step' => 5,
            ],
            [
                'label' => 'Creating WordPress config',
                'command' => $this->lando->wpConfigCreate($path),
                'step' => 6,
            ],
            [
                'label' => 'Replacing domain references',
                'command' => $this->lando->wpSearchReplace($path, rtrim($remoteSite->remote_domain, '/'), "https://{$name}.lndo.site"),
                'step' => 7,
            ],
            [
                'label' => 'Syncing plugins from remote',
                'command' => $this->ssh->buildRsyncPluginsCommand($remoteSite, "{$path}/wp/wp-content/plugins/"),
                'step' => 8,
            ],
            [
                'label' => 'Configuring image proxy',
                'command' => 'echo '.escapeshellarg($this->ssh->buildHtaccessRewriteContent($remoteSite->remote_domain))." > {$path}/wp/.htaccess",
                'step' => 9,
            ],
            [
                'label' => 'Installing theme dependencies',
                'command' => $remoteSite->repo_url
                    ? "cd {$path}/wp/wp-content/themes && git clone {$remoteSite->repo_url} {$remoteSite->theme_name}"
                    : 'echo "No repo URL configured, skipping theme clone"',
                'step' => 10,
            ],
            [
                'label' => 'Building theme',
                'command' => $remoteSite->theme_name
                    ? $this->lando->yarn($path, "wp/wp-content/themes/{$remoteSite->theme_name}", 'build')
                    : 'echo "No theme configured, skipping build"',
                'step' => 11,
            ],
        ];
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
}
