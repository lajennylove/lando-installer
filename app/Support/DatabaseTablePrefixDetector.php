<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Site;
use PDO;
use PDOException;

/**
 * Reads $table_prefix from the imported DB over the host-forwarded MariaDB port.
 * Avoids `lando mysql` inside artisan (Docker/WP-CLI can leak stderr into the step log).
 */
final class DatabaseTablePrefixDetector
{
    public static function fromSiteForwardedPort(Site $site): ?string
    {
        $port = $site->db_port;
        if ($port === null || $port < 1) {
            return null;
        }

        if (! extension_loaded('pdo_mysql')) {
            return null;
        }

        $dbName = (string) config('lando_dev.defaults.db_name');
        $dbUser = (string) config('lando_dev.defaults.db_user');
        $dbPass = (string) config('lando_dev.defaults.db_password');

        $dsn = sprintf(
            'mysql:host=127.0.0.1;port=%d;dbname=%s;charset=utf8mb4',
            (int) $port,
            $dbName
        );

        $maxAttempts = 3;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $pdo = new PDO($dsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_NUM,
                    PDO::ATTR_TIMEOUT => 5,
                ]);
                $stmt = $pdo->query("SHOW TABLES LIKE '%options'");
                if ($stmt === false) {
                    return null;
                }
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                $text = implode("\n", array_map(static fn ($row): string => (string) $row, $tables));

                return ImportedDatabaseTablePrefixParser::fromShowTablesOutput($text);
            } catch (PDOException $e) {
                $lastException = $e;
                if ($attempt < $maxAttempts) {
                    usleep(500_000); // 0.5s before retry
                }
            }
        }

        return null;
    }
}
