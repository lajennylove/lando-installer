<?php

use App\Models\Site;
use App\Services\ApplicationDatabaseReset;
use App\Services\WpConfigGenerator;
use App\Support\DatabaseTablePrefixDetector;
use App\Support\MysqlDumpTablePrefixDetector;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Closure parameter names must match Artisan argument names exactly (e.g. {siteId} → $siteId).
Artisan::command('lando-dev:clone-wp-config {path : Absolute site root (contains wp/; DB already imported)} {siteId : Site id (UUID) for forwarded DB port}', function (string $path, string $siteId) {
    $log = Log::channel('clone_wp_config');

    try {
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        $log->info('clone-wp-config started', [
            'path' => $path,
            'site_id' => $siteId,
            'argv' => $_SERVER['argv'] ?? [],
        ]);

        if (! is_dir($path.DIRECTORY_SEPARATOR.'wp')) {
            $log->warning('missing wp directory', ['path' => $path]);
            $this->error('Site path must contain a wp/ directory (Lando webroot is wp, not wordpress).');

            return 1;
        }

        $site = Site::find($siteId);
        if (! $site) {
            $log->warning('site row not found', ['site_id' => $siteId]);
            $this->error("Site not found: {$siteId}");

            return 1;
        }

        $log->debug('site resolved', [
            'site_id' => $site->id,
            'db_port' => $site->db_port,
            'name' => $site->name,
        ]);

        // Host PDO to 127.0.0.1:db_port — avoids `lando mysql` leaking WP-CLI noise into this step's log.
        $log->debug('attempting PDO prefix detection', [
            'host' => '127.0.0.1',
            'port' => $site->db_port,
            'db_name' => config('lando_dev.defaults.db_name'),
        ]);

        $prefix = DatabaseTablePrefixDetector::fromSiteForwardedPort($site);
        $prefixSource = 'pdo_forwarded_port';

        if ($prefix === null || $prefix === '') {
            $dump = $path.DIRECTORY_SEPARATOR.'dumpfile.sql.gz';
            $dumpExists = is_readable($dump);
            $log->debug('PDO detection failed, checking dump file', [
                'dump_path' => $dump,
                'dump_exists' => $dumpExists,
                'dump_size' => $dumpExists ? filesize($dump) : null,
            ]);

            $prefix = $dumpExists
                ? MysqlDumpTablePrefixDetector::fromGzipFile($dump)
                : 'wp_';
            $prefixSource = $dumpExists ? 'dump_gzip' : 'default_wp_';

            if ($prefixSource === 'default_wp_') {
                $log->warning('fell back to default wp_ prefix (PDO failed and no dump file)');
            } elseif ($prefix !== 'wp_') {
                $this->warn('Used table prefix from dump file (could not read prefix via forwarded DB port).');
            }
        }

        $log->info('table prefix resolved', ['prefix' => $prefix, 'source' => $prefixSource]);

        if (! preg_match('/^[a-zA-Z0-9_]+$/', $prefix)) {
            $log->error('unsafe table prefix rejected', ['prefix' => $prefix]);
            $this->error("Refusing unsafe table prefix: {$prefix}");

            return 1;
        }

        $dbName = (string) config('lando_dev.defaults.db_name');
        $dbUser = (string) config('lando_dev.defaults.db_user');
        $dbPass = (string) config('lando_dev.defaults.db_password');
        $dbHost = (string) config('lando_dev.defaults.db_host');
        $lando = app(\App\Services\DependencyChecker::class)->getLandoPath() ?? 'lando';

        $wpConfigArgs = implode(' ', [
            '--dbname='.escapeshellarg($dbName),
            '--dbuser='.escapeshellarg($dbUser),
            '--dbpass='.escapeshellarg($dbPass),
            '--dbhost='.escapeshellarg($dbHost),
            '--dbprefix='.escapeshellarg($prefix),
            '--force',
            '--skip-check',
            '--path=wp',
        ]);

        $log->info('attempting lando wp config create', ['prefix' => $prefix]);
        $result = \Illuminate\Support\Facades\Process::path($path)
            ->timeout(60)
            ->run("{$lando} wp config create {$wpConfigArgs}");

        if ($result->successful()) {
            $log->info('wp config create succeeded');
            $this->info('Generated wp/wp-config.php via lando wp config create.');
        } else {
            $log->warning('lando wp config create failed, falling back to PHP generator', [
                'exit_code' => $result->exitCode(),
                'output' => trim($result->output().$result->errorOutput()),
            ]);
            app(WpConfigGenerator::class)->writeForClone($path, $prefix);
            $this->info('Generated wp/wp-config.php (PHP fallback — lando wp config create failed).');
        }
    } catch (Throwable $e) {
        $log->error('clone-wp-config failed', [
            'message' => $e->getMessage(),
            'exception' => $e::class,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
        $this->error($e->getMessage());

        return 1;
    }

    return 0;
})->purpose('Write wp-config.php for a cloned site (prefix from DB; lando wp config create with detected prefix, PHP generator fallback)');

Artisan::command('sites:purge-local {--force : Skip confirmation}', function () {
    if (! $this->option('force') && ! $this->confirm('Run lando destroy for every site, delete project folders, and remove all site rows?')) {
        return 1;
    }

    $seenPaths = [];
    foreach (ApplicationDatabaseReset::allSitesAcrossSqliteConnections() as $site) {
        $path = $site->path;
        if ($path === '' || isset($seenPaths[$path])) {
            continue;
        }
        $seenPaths[$path] = true;

        if (is_dir($path)) {
            $this->line("Lando destroy: {$path}");
            $result = Process::path($path)->timeout(900)->run('lando destroy -y');
            if (! $result->successful()) {
                $this->warn(trim($result->errorOutput().$result->output()) ?: 'lando destroy exited non-zero');
            }
            if (is_dir($path)) {
                try {
                    File::deleteDirectory($path);
                } catch (Throwable $e) {
                    $this->warn("Could not delete {$path}: {$e->getMessage()}");
                }
            }
        }
    }

    ApplicationDatabaseReset::wipeLocalSitesOnly();
    $this->info('Site rows removed from every app SQLite database (nativephp + sqlite, etc.).');

    return 0;
})->purpose('Destroy all Lando apps from DB paths, delete directories, clear sites');

Artisan::command('sites:remove {names* : Site names (slugs) as stored in the database}', function (array $names) {
    $names = array_values(array_unique(array_filter(array_map('trim', $names))));
    if ($names === []) {
        $this->error('Pass at least one name, e.g. php artisan sites:remove test no-sage');

        return 1;
    }

    $total = 0;
    foreach (ApplicationDatabaseReset::resolvedSqliteConnectionNames() as $conn) {
        if (! ApplicationDatabaseReset::connectionHasSitesTable($conn)) {
            continue;
        }
        $deleted = Site::on($conn)->whereIn('name', $names)->delete();
        if ($deleted > 0) {
            $this->line("Connection [{$conn}]: removed {$deleted} row(s) matching: ".implode(', ', $names));
            $total += $deleted;
        }
    }

    if ($total === 0) {
        $this->warn('No matching sites in any app SQLite database. Check Settings → Application data for DB paths.');
    } else {
        $this->info("Removed {$total} site row(s) (command_logs cascade per connection).");
    }

    return 0;
})->purpose('Remove specific sites by name from every app SQLite database');
