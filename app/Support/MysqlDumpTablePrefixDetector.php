<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Reads a gzipped mysqldump until the first options table definition to learn $table_prefix.
 */
final class MysqlDumpTablePrefixDetector
{
    private const READ_LIMIT_BYTES = 786_432;

    public static function fromGzipFile(string $path): string
    {
        if (! is_readable($path)) {
            return 'wp_';
        }

        $size = filesize($path);
        if ($size === false || $size < 100) {
            return 'wp_';
        }

        $handle = @gzopen($path, 'rb');
        if ($handle === false) {
            return 'wp_';
        }

        $buffer = '';
        while (! gzeof($handle) && strlen($buffer) < self::READ_LIMIT_BYTES) {
            $chunk = gzread($handle, 65_536);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $buffer .= $chunk;
            $prefix = self::matchPrefix($buffer);
            if ($prefix !== null) {
                gzclose($handle);

                return $prefix;
            }
        }

        gzclose($handle);

        return 'wp_';
    }

    private static function matchPrefix(string $haystack): ?string
    {
        // Backticked: `wp_options` or `db`.`wp_options` → prefix wp_
        if (preg_match('/CREATE TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+(?:`[^`]+`\.)?`([a-zA-Z0-9_]+)options`\s*\(/i', $haystack, $m)) {
            return $m[1];
        }

        // Unquoted: wp_options or db.wp_options
        if (preg_match('/CREATE TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+(?:[a-zA-Z0-9_]+\.)?([a-zA-Z0-9_]+)_options\s*\(/i', $haystack, $m)) {
            return $m[1].'_';
        }

        return null;
    }
}
