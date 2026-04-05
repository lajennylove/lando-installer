<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Derives WordPress $table_prefix from `SHOW TABLES LIKE '%options'` (post-import, no wp-config needed).
 */
final class ImportedDatabaseTablePrefixParser
{
    public static function fromShowTablesOutput(string $output): ?string
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R+/', $output) ?: [])));

        $optionsTables = array_values(array_filter(
            $lines,
            static fn (string $t): bool => (bool) preg_match('/^[a-zA-Z0-9_]+options$/', $t)
        ));

        if ($optionsTables === []) {
            return null;
        }

        if (in_array('wp_options', $optionsTables, true)) {
            return 'wp_';
        }

        // Multisite blog options (wp_2_options, …) share base prefix wp_; do not treat as $table_prefix.
        $nonBlogOptions = array_values(array_filter(
            $optionsTables,
            static fn (string $t): bool => ! preg_match('/^wp_\d+_options$/', $t)
        ));

        if ($nonBlogOptions === []) {
            return 'wp_';
        }

        $pickFrom = $nonBlogOptions;

        usort($pickFrom, static fn (string $a, string $b): int => strlen($a) <=> strlen($b));

        $first = $pickFrom[0];
        if (preg_match('/^([a-zA-Z0-9_]+)options$/', $first, $m)) {
            return $m[1];
        }

        return null;
    }
}
