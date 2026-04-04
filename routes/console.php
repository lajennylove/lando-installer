<?php

use App\Models\Site;
use App\Services\ApplicationDatabaseReset;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
