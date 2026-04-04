<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

final class ApplicationDatabaseReset
{
    /**
     * Connections that may hold LandoDEV data. NativePHP uses {@see 'nativephp'}; Artisan often
     * uses {@see 'sqlite'}. Those connections may point at the same file after aligning defaults
     * (see {@see config('database.connections.sqlite.database')}). Wiping only one connection can
     * still miss rows if another path exists (e.g. Application Support in a packaged app).
     *
     * @return list<string>
     */
    public static function appSqliteConnectionNames(): array
    {
        $names = array_unique(array_filter([
            (string) config('database.default'),
            'nativephp',
            'sqlite',
        ]));

        $out = [];
        foreach ($names as $name) {
            if (! config("database.connections.{$name}")) {
                continue;
            }
            if (config("database.connections.{$name}.driver") !== 'sqlite') {
                continue;
            }
            $out[] = $name;
        }

        return array_values(array_unique($out));
    }

    /**
     * Same as {@see appSqliteConnectionNames()} plus dynamic connections for SQLite files
     * that exist on disk but are not already referenced (e.g. {@see database_path('nativephp.sqlite')}
     * when Artisan has no {@see 'nativephp'} connection, or {@see config('nativephp-internal.database_path')}
     * when the packaged app stores data outside the project tree).
     *
     * @return list<string>
     */
    public static function resolvedSqliteConnectionNames(): array
    {
        $names = self::appSqliteConnectionNames();

        $resolvedPaths = [];
        foreach ($names as $name) {
            $path = config("database.connections.{$name}.database");
            if (! is_string($path) || $path === '' || $path === ':memory:') {
                continue;
            }
            $resolvedPaths[realpath($path) ?: $path] = true;
        }

        $internalPath = config('nativephp-internal.database_path');
        $candidates = array_unique(array_filter([
            database_path('nativephp.sqlite'),
            is_string($internalPath) && $internalPath !== '' ? $internalPath : null,
        ]));

        $i = 0;
        foreach ($candidates as $filePath) {
            if (! File::exists($filePath)) {
                continue;
            }
            $key = realpath($filePath) ?: $filePath;
            if (isset($resolvedPaths[$key])) {
                continue;
            }
            $resolvedPaths[$key] = true;
            $conn = '__landodev_sqlite_'.$i++;
            config([
                "database.connections.{$conn}" => [
                    'driver' => 'sqlite',
                    'database' => $filePath,
                    'prefix' => '',
                    'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
                ],
            ]);
            $names[] = $conn;
        }

        return array_values(array_unique($names));
    }

    public static function connectionHasSitesTable(string $connection): bool
    {
        try {
            return Schema::connection($connection)->hasTable('sites');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<array{connection: string, path: string, file_exists: bool, site_count: int|null, error?: string}>
     */
    public static function sqliteLocationsForDisplay(): array
    {
        $rows = [];
        foreach (self::resolvedSqliteConnectionNames() as $name) {
            $path = config("database.connections.{$name}.database");
            $pathStr = is_string($path) ? $path : '';

            try {
                $exists = $pathStr !== '' && File::exists($pathStr);
                $hasSites = self::connectionHasSitesTable($name);
                $count = $hasSites ? Site::on($name)->count() : null;
                $rows[] = [
                    'connection' => str_starts_with($name, '__landodev_sqlite_') ? 'extra SQLite file' : $name,
                    'path' => $pathStr,
                    'file_exists' => $exists,
                    'site_count' => $count,
                ];
            } catch (\Throwable $e) {
                $rows[] = [
                    'connection' => str_starts_with($name, '__landodev_sqlite_') ? 'extra SQLite file' : $name,
                    'path' => $pathStr,
                    'file_exists' => $pathStr !== '' && File::exists($pathStr),
                    'site_count' => null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return self::mergeSqliteDisplayRowsByPath($rows);
    }

    /**
     * One row per filesystem file so duplicate connection names (e.g. nativephp + sqlite → same path) are not listed twice.
     *
     * @param  list<array{connection: string, path: string, file_exists: bool, site_count: int|null, error?: string}>  $rows
     * @return list<array{connection: string, path: string, file_exists: bool, site_count: int|null, error?: string}>
     */
    private static function mergeSqliteDisplayRowsByPath(array $rows): array
    {
        /** @var array<string, array{labels: list<string>, path: string, file_exists: bool, site_count: int|null, error?: string}> $buckets */
        $buckets = [];
        foreach ($rows as $row) {
            $pathStr = $row['path'];
            $key = $pathStr;
            if ($pathStr !== '' && ($row['file_exists'] ?? false)) {
                $key = realpath($pathStr) ?: $pathStr;
            }

            $label = $row['connection'];
            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'labels' => [$label],
                    'path' => $pathStr,
                    'file_exists' => $row['file_exists'],
                    'site_count' => $row['site_count'],
                    'error' => $row['error'] ?? '',
                ];

                continue;
            }

            $buckets[$key]['labels'][] = $label;
            if (($row['error'] ?? '') !== '' && $buckets[$key]['error'] === '') {
                $buckets[$key]['error'] = $row['error'];
            }
        }

        $out = [];
        foreach ($buckets as $bucket) {
            $labels = array_values(array_unique($bucket['labels']));
            sort($labels);
            $line = [
                'connection' => implode(' + ', $labels),
                'path' => $bucket['path'],
                'file_exists' => $bucket['file_exists'],
                'site_count' => $bucket['site_count'],
            ];
            if ($bucket['error'] !== '') {
                $line['error'] = $bucket['error'];
            }
            $out[] = $line;
        }

        return $out;
    }

    /**
     * @return Collection<int, Site>
     */
    public static function allSitesAcrossSqliteConnections(): Collection
    {
        $byId = [];
        foreach (self::resolvedSqliteConnectionNames() as $name) {
            if (! self::connectionHasSitesTable($name)) {
                continue;
            }
            foreach (Site::on($name)->cursor() as $site) {
                $key = $name.'|'.$site->getKey();
                $byId[$key] = $site;
            }
        }

        return collect(array_values($byId));
    }

    /**
     * Clear all local sites; command_logs rows cascade. Does not remove remote_site presets.
     */
    public static function wipeLocalSitesOnly(): void
    {
        foreach (self::resolvedSqliteConnectionNames() as $name) {
            if (! self::connectionHasSitesTable($name)) {
                continue;
            }
            DB::connection($name)->transaction(fn () => DB::connection($name)->table('sites')->delete());
        }
    }

    /**
     * Clear local sites (command_logs cascade), remote site presets, and DB sessions.
     * Does not run lando destroy, delete disk folders, or remove landodev_defaults.json.
     */
    public static function wipe(): void
    {
        foreach (self::resolvedSqliteConnectionNames() as $name) {
            if (! self::connectionHasSitesTable($name)) {
                continue;
            }
            $schema = Schema::connection($name);
            DB::connection($name)->transaction(function () use ($name, $schema) {
                DB::connection($name)->table('sites')->delete();

                if ($schema->hasTable('remote_sites')) {
                    DB::connection($name)->table('remote_sites')->delete();
                }

                if ($schema->hasTable('sessions')) {
                    DB::connection($name)->table('sessions')->delete();
                }
            });
        }
    }
}
