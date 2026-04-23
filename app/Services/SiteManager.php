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
     * Before starting a site, ensure its .lando.yml database portforward is not already bound by
     * another process. If it is, reassigns the next free port both in the YAML file and in the DB.
     */
    public function ensurePortAvailable(Site $site): void
    {
        $yamlPath = rtrim($site->path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.lando.yml';

        if (! file_exists($yamlPath)) {
            return;
        }

        // Use the YAML parser to get the current explicit port (null = dynamic, skip)
        $parser = app(LandoYamlParser::class);
        $parsed = $parser->parse($yamlPath);
        $currentPort = $parsed['db_port'] ?? null;

        if ($currentPort === null) {
            return;
        }

        if (! $this->isPortBound($currentPort)) {
            return;
        }

        // Find the next free port
        $newPort = $currentPort + 1;
        while ($this->isPortBound($newPort)) {
            $newPort++;
        }

        // Rewrite only the portforward line inside the database: block
        $content = file_get_contents($yamlPath);
        $content = $this->replaceDbPortInYaml($content, $currentPort, $newPort);
        file_put_contents($yamlPath, $content);

        // Persist the new port in the database record
        $site->db_port = $newPort;
        $site->save();
    }

    /**
     * Check whether a TCP port is currently bound on localhost.
     */
    private function isPortBound(int $port): bool
    {
        $sock = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.3);
        if ($sock !== false) {
            fclose($sock);

            return true;
        }

        return false;
    }

    /**
     * Replace the portforward integer value in the database: service block only,
     * leaving the cache: service's "portforward: true" untouched.
     */
    private function replaceDbPortInYaml(string $content, int $oldPort, int $newPort): string
    {
        // Find the database: service block (2-space indented service key)
        $dbStart = strpos($content, "\n  database:\n");
        if ($dbStart === false) {
            return $content;
        }

        // Find where the next sibling service starts (same 2-space indent level)
        $afterDb = $dbStart + strlen("\n  database:\n");
        if (preg_match('/\n  [a-zA-Z]/', $content, $m, PREG_OFFSET_CAPTURE, $afterDb)) {
            $nextService = $m[0][1];
        } else {
            $nextService = strlen($content);
        }

        // Replace portforward: <integer> only within the database block
        $dbBlock = substr($content, $dbStart, $nextService - $dbStart);
        $dbBlock = preg_replace(
            '/(\n\s+portforward:\s*)'.preg_quote((string) $oldPort, '/').'(?=\s|$)/',
            '${1}'.$newPort,
            $dbBlock
        );

        return substr($content, 0, $dbStart).$dbBlock.substr($content, $nextService);
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

        $steps = [];

        $platform = app(PlatformDetector::class);
        if ($platform->isWindows()) {
            $steps[] = [
                'label' => 'Cleaning up stale Docker resources',
                'command' => "docker ps -aq --filter \"name={$name}\" | ForEach-Object { docker rm -f \$_ } 2>\$null; "
                    ."docker network ls --filter \"name={$name}_\" -q | ForEach-Object { docker network rm \$_ } 2>\$null; "
                    ."Write-Host 'Docker cleanup complete.'",
                'step' => 1,
            ];
        }

        $steps = array_merge($steps, [
            [
                'label' => 'Starting Lando environment',
                'command' => $this->lando->start($path),
                'step' => count($steps) + 1,
            ],
            [
                'label' => 'Downloading WordPress core',
                'command' => $this->lando->wpCoreDownload($path),
                'step' => count($steps) + 2,
            ],
            [
                'label' => 'Creating WordPress config',
                'command' => $this->lando->wpConfigCreate($path),
                'step' => count($steps) + 3,
            ],
            [
                'label' => 'Installing WordPress',
                'command' => $this->lando->wpCoreInstall($path, $name, (string) $site->admin_username, $adminPass, (string) $site->admin_email),
                'step' => count($steps) + 4,
            ],
        ]);

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
                'timeout' => 1800,
                'inactivity_timeout' => 120,
            ],
            [
                'label' => 'Installing Yarn dependencies',
                'command' => $this->lando->yarn($path, "wp/wp-content/themes/{$name}"),
                'step' => 7,
                'timeout' => 600,
                'inactivity_timeout' => 120,
            ],
            [
                'label' => 'Building theme assets',
                'command' => $this->lando->yarn($path, "wp/wp-content/themes/{$name}", 'build'),
                'step' => 8,
                'timeout' => 600,
                'inactivity_timeout' => 120,
            ],
            [
                'label' => 'Clearing view cache',
                'command' => $this->lando->clearAcornCache($path),
                'step' => 9,
            ],
            [
                'label' => 'Activating theme',
                'command' => $this->lando->wpThemeActivate($path, $name),
                'step' => 10,
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
        $dumpFile = rtrim($path, '/\\').DIRECTORY_SEPARATOR.'dumpfile.sql.gz';

        $steps = [];

        // On Windows, a previous failed destroy may leave stale Docker containers /
        // networks with the same site name, causing port-conflict errors on lando start.
        // Remove them proactively before starting.
        $platform = app(PlatformDetector::class);
        if ($platform->isWindows()) {
            $steps[] = [
                'label' => 'Cleaning up stale Docker resources',
                'command' => "docker ps -aq --filter \"name={$name}\" | ForEach-Object { docker rm -f \$_ } 2>\$null; "
                    ."docker network ls --filter \"name={$name}_\" -q | ForEach-Object { docker network rm \$_ } 2>\$null; "
                    ."Write-Host 'Docker cleanup complete.'",
                'step' => 1,
            ];
        }

        $steps[] = [
            'label' => 'Starting Lando environment',
            'command' => $this->lando->start($path),
            'step' => count($steps) + 1,
        ];

        // On Windows, Docker Desktop may silently skip build_as_root curl steps,
        // leaving WP-CLI missing. Explicitly install it into the running container.
        $ensureWpCli = $this->lando->ensureWpCli($path);
        if ($ensureWpCli !== null) {
            $steps[] = [
                'label' => 'Ensuring WP-CLI is available',
                'command' => $ensureWpCli,
                'step' => count($steps) + 1,
            ];
        }

        $steps = array_merge($steps, [
            [
                'label' => 'Downloading WordPress core',
                'command' => $this->lando->wpCoreDownload($path),
                'step' => count($steps) + 1,
            ],
            [
                'label' => 'Dumping remote database',
                'hint' => 'Streams SQL over SSH then compresses locally — nothing is written on the remote server. Expect 2–10 minutes depending on database size.',
                'timeout' => 1200,
                'command' => $this->ssh->buildMysqldumpCommand($remoteSite, $dumpFile),
                'step' => count($steps) + 2,
            ],
            [
                'label' => 'Validating dump file',
                'command' => $this->validateDumpFileCommand($dumpFile),
                'step' => count($steps) + 3,
            ],
            [
                'label' => 'Importing database',
                'command' => $this->lando->dbImport($path, 'dumpfile.sql.gz'),
                'timeout' => 1200,
                'step' => count($steps) + 4,
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
                'command' => $remoteSite->theme_name
                    ? $this->activateThemeCommand($path, $remoteSite->theme_name)
                    : ($this->platform->isWindows() ? 'Write-Host "No theme configured, skipping theme activation"' : 'echo "No theme configured, skipping theme activation"'),
                'step' => 9,
            ],
            [
                'label' => 'Syncing plugins from remote',
                'command' => $this->ssh->buildRsyncPluginsCommand($remoteSite, $path.DIRECTORY_SEPARATOR.'wp'.DIRECTORY_SEPARATOR.'wp-content'.DIRECTORY_SEPARATOR.'plugins'),
                'timeout' => 1800,
                'step' => 10,
            ],
            [
                'label' => 'Configuring image proxy',
                'command' => $this->htaccessCommand($path, $remoteSite->remote_domain),
                'step' => 11,
            ],
        ]);

        $themeSubdir = $remoteSite->theme_name
            ? "wp/wp-content/themes/{$remoteSite->theme_name}"
            : null;
        $cloneTheme = $remoteSite->repo_url && $remoteSite->theme_name;

        $stepNum = 12;
        $steps[] = [
            'label' => 'Cloning theme repository',
            'command' => $remoteSite->repo_url
                ? $this->gitCloneCommand($path, $remoteSite->repo_url, $remoteSite->theme_name)
                : ($this->platform->isWindows() ? 'Write-Host "No repo URL configured, skipping theme clone"' : 'echo "No repo URL configured, skipping theme clone"'),
            'step' => $stepNum++,
        ];

        if ($cloneTheme) {
            $themePath = $path.DIRECTORY_SEPARATOR.'wp'.DIRECTORY_SEPARATOR.'wp-content'.DIRECTORY_SEPARATOR.'themes'.DIRECTORY_SEPARATOR.$remoteSite->theme_name;
            $steps[] = [
                'label' => 'Switching to dev branch',
                'command' => $this->gitCheckoutCommand($themePath, 'dev'),
                'step' => $stepNum++,
            ];
        }

        if ($cloneTheme && $remoteSite->install_composer_dependencies && $themeSubdir) {
            $steps[] = [
                'label' => 'Installing Composer dependencies',
                'command' => $this->lando->composerInstall($path, $themeSubdir),
                'step' => $stepNum++,
                'timeout' => 1800,
                'inactivity_timeout' => 120,
            ];
        }

        if ($cloneTheme && $remoteSite->install_node_dependencies && $themeSubdir) {
            $steps[] = [
                'label' => 'Installing Node dependencies',
                'command' => $this->lando->yarn($path, $themeSubdir),
                'step' => $stepNum++,
                'timeout' => 600,
                'inactivity_timeout' => 120,
            ];
        }

        $steps[] = [
            'label' => 'Building theme',
            'command' => $remoteSite->theme_name && $themeSubdir
                ? $this->lando->yarn($path, $themeSubdir, 'build')
                : 'echo "No theme configured, skipping build"',
            'step' => $stepNum++,
            'timeout' => 600,
            'inactivity_timeout' => 120,
        ];

        // Lando health-checks compile Blade views before the Vite build creates
        // manifest.json, caching a "manifest not found" fatal error.  Clear the
        // Acorn view cache so the first real page load recompiles cleanly.
        $steps[] = [
            'label' => 'Clearing view cache',
            'command' => $this->lando->clearAcornCache($path),
            'step' => $stepNum++,
        ];

        return $steps;
    }

    /**
     * Host PHP runs artisan to write wp-config.php (WpConfigGenerator) and detect $table_prefix.
     */
    private function cloneWpConfigCommand(string $path, Site $site): string
    {
        if ($this->platform->isWindows()) {
            $php = '"'.str_replace('"', '""', PHP_BINARY).'"';
            $artisan = '"'.str_replace('"', '""', base_path('artisan')).'"';
            $psPath = str_replace("'", "''", $path);
            $siteId = (string) $site->id;

            return "& {$php} {$artisan} lando-dev:clone-wp-config '{$psPath}' '{$siteId}'";
        }

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
        $minBytes = 500;

        if ($this->platform->isWindows()) {
            // The command runs inside PowerShell already (wrapCommandWithLogRedirect).
            // Use single-quoted PS path to avoid any double-quote nesting issues.
            $psPath = str_replace("'", "''", str_replace('/', '\\', $dumpFile));

            return "if ((Get-Item '{$psPath}').Length -lt {$minBytes}) { Write-Error 'Dump file is too small or empty - mysqldump may have failed'; exit 1 } else { Write-Host 'Dump file validated' }";
        }

        $escaped = escapeshellarg($dumpFile);

        return "if [ ! -s {$escaped} ] || [ \$(stat -f%z {$escaped} 2>/dev/null || stat -c%s {$escaped} 2>/dev/null) -lt {$minBytes} ]; then echo 'ERROR: Dump file is too small or empty - mysqldump may have failed. Check SSH credentials and remote database settings.' >&2; exit 1; fi; if ! gzip -t {$escaped}; then echo 'ERROR: dumpfile.sql.gz is not a complete gzip archive (transfer or dump may have been interrupted).' >&2; exit 1; fi; echo 'Dump file validated:' && ls -lh {$escaped}";
    }

    private function removeDumpFileCommand(string $dumpFile): string
    {
        $sqlFile = preg_replace('/\.sql\.gz$/u', '.sql', $dumpFile);

        if ($this->platform->isWindows()) {
            $winGz = str_replace('/', '\\', $dumpFile);
            $winSql = str_replace('/', '\\', $sqlFile);

            return "Remove-Item -Force -ErrorAction SilentlyContinue '{$winGz}', '{$winSql}'; Write-Host 'Dump file removed.'";
        }

        return 'rm -f '.escapeshellarg($dumpFile).' '.escapeshellarg($sqlFile)." && echo 'Removed local dump files (dumpfile.sql / .gz).'";
    }

    private function activateThemeCommand(string $path, string $themeName): string
    {
        if ($this->platform->isWindows()) {
            $lando = $this->lando->getLandoPath();
            $psLando = '& "'.str_replace('"', '""', $lando).'"';
            $psTheme = str_replace("'", "''", $themeName);

            return "Set-Location \"{$path}\"; {$psLando} wp option update template '{$psTheme}' --path=wp; {$psLando} wp option update stylesheet '{$psTheme}' --path=wp";
        }

        return 'cd '.escapeshellarg($path)." && {$this->lando->getLandoPath()} wp option update template ".escapeshellarg($themeName)." --path=wp && {$this->lando->getLandoPath()} wp option update stylesheet ".escapeshellarg($themeName).' --path=wp';
    }

    private function htaccessCommand(string $path, ?string $remoteDomain): string
    {
        $content = $this->ssh->buildHtaccessRewriteContent($remoteDomain);

        if ($this->platform->isWindows()) {
            $htaccessPath = $path.DIRECTORY_SEPARATOR.'wp'.DIRECTORY_SEPARATOR.'.htaccess';
            $psPath = str_replace("'", "''", $htaccessPath);
            $psContent = str_replace("'", "''", $content);

            // PS5's -Encoding UTF8 writes a BOM (\xef\xbb\xbf) which Apache
            // treats as an invalid directive, causing a 500 error.
            // Use .NET's BOM-free UTF8 encoder instead.
            return '$utf8 = New-Object System.Text.UTF8Encoding($false); '
                ."[System.IO.File]::WriteAllText('{$psPath}', '{$psContent}', \$utf8); "
                ."Write-Host '.htaccess configured'";
        }

        return 'echo '.escapeshellarg($content)." > {$path}/wp/.htaccess";
    }

    private function gitCloneCommand(string $path, string $repoUrl, string $themeName): string
    {
        if ($this->platform->isWindows()) {
            $themesDir = $path.DIRECTORY_SEPARATOR.'wp'.DIRECTORY_SEPARATOR.'wp-content'.DIRECTORY_SEPARATOR.'themes';

            return "Set-Location \"{$themesDir}\"; git clone --progress {$repoUrl} {$themeName}; Write-Host 'Theme cloned successfully.'";
        }

        return "cd {$path}/wp/wp-content/themes && git clone --progress {$repoUrl} {$themeName} && echo \"Theme cloned successfully.\"";
    }

    private function gitCheckoutCommand(string $path, string $branch): string
    {
        if ($this->platform->isWindows()) {
            return "Set-Location \"{$path}\"; git checkout {$branch}; Write-Host \"Switched to branch: {$branch}\"";
        }

        return "cd {$path} && git checkout {$branch} && echo \"Switched to branch: \$(git branch --show-current)\"";
    }

    public function destroySite(Site $site): string
    {
        $platform = app(PlatformDetector::class);

        if ($platform->isWindows()) {
            $landoDestroy = $this->lando->destroy($site->path);
            $name = $site->name;

            // After lando destroy, force-remove any lingering Docker containers and
            // networks that share the site name. On Windows, lando's stderr warnings
            // (NativeCommandError) can interfere with cleanup, leaving stale port
            // bindings that block the next lando start for a same-named site.
            $dockerCleanup = "docker ps -aq --filter \"name={$name}\" | ForEach-Object { docker rm -f \$_ } 2>\$null; "
                ."docker network ls --filter \"name={$name}_\" -q | ForEach-Object { docker network rm \$_ } 2>\$null";

            return "{$landoDestroy}; {$dockerCleanup}";
        }

        return $this->lando->destroy($site->path);
    }

    public function getDeletePath(Site $site): string
    {
        if (app(PlatformDetector::class)->isWindows()) {
            $path = app(PlatformDetector::class)->normalizePathSeparators($site->path);

            // cmd's rmdir handles Windows file locks more reliably than Remove-Item.
            // A brief pause lets Docker Desktop release handles after lando destroy.
            // PS5 doesn't support &&; use ; to unconditionally run Write-Host.
            return 'Start-Sleep -Seconds 2; cmd /c rmdir /s /q "'.$path.'"; Write-Host \'Site folder removed.\'';
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

    /**
     * Steps to sync a fresh database dump from production into an existing local site.
     *
     * Reuses the dump → validate → import → wp-config → cleanup → search-replace chain
     * from getCloneSiteSteps, but skips folder creation, lando start, wp-core-download,
     * git clone, composer/node/build steps — those already exist locally.
     *
     * @param  string|null  $localThemeName  Override for the local theme folder name.
     *                                       Falls back to $site->theme_name.
     */
    public function getSyncSteps(Site $site, RemoteSite $remoteSite, ?string $localThemeName = null): array
    {
        $path = $site->path;
        $localTheme = $localThemeName ?: $site->theme_name ?: null;
        $dumpFile = rtrim($path, '/\\').DIRECTORY_SEPARATOR.'dumpfile.sql.gz';
        $localUrl = "https://{$site->name}.lndo.site";

        $steps = [
            [
                'label' => 'Dumping remote database',
                'hint' => 'Streams SQL over SSH then compresses locally — nothing is written on the remote server. Expect 2–10 minutes depending on database size.',
                'timeout' => 1200,
                'command' => $this->ssh->buildMysqldumpCommand($remoteSite, $dumpFile),
                'step' => 1,
            ],
            [
                'label' => 'Validating dump file',
                'command' => $this->validateDumpFileCommand($dumpFile),
                'step' => 2,
            ],
            [
                'label' => 'Importing database',
                'command' => $this->lando->dbImport($path, 'dumpfile.sql.gz'),
                'timeout' => 1200,
                'step' => 3,
            ],
            [
                'label' => 'Creating WordPress config',
                'command' => $this->cloneWpConfigCommand($path, $site),
                'step' => 4,
            ],
            [
                'label' => 'Cleaning up dump file',
                'command' => $this->removeDumpFileCommand($dumpFile),
                'step' => 5,
            ],
            [
                'label' => 'Replacing domain references',
                'hint' => 'Search-replace prod URL → local URL.',
                'timeout' => 1200,
                'command' => $this->lando->wpSearchReplaceImported($path, $localUrl, $localTheme),
                'step' => 6,
            ],
            [
                'label' => 'Rebuilding Lando environment',
                'hint' => 'Runs `lando rebuild -y` to apply all database and config changes.',
                'timeout' => 600,
                'command' => $this->lando->rebuild($path),
                'step' => 7,
            ],
        ];

        return $steps;
    }
}
