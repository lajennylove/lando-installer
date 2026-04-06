<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Ensures shell log content is safe for Livewire JSON (UTF-8) and human display.
 * mysqldump is piped through gzip; mis-redirected or captured binary must not enter the snapshot.
 */
final class LogContentUtf8
{
    public static function forLivewire(string $raw): string
    {
        if ($raw === '') {
            return '';
        }

        if (str_starts_with($raw, "\x1f\x8b")) {
            return '[Gzipped SQL is written to dumpfile.sql.gz — binary output is not shown here.]';
        }

        // PowerShell *> redirect writes UTF-16 LE with BOM (FF FE) by default.
        // Convert to UTF-8 before any further processing.
        if (str_starts_with($raw, "\xFF\xFE")) {
            $raw = mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE');
        } elseif (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        } elseif (str_contains($raw, "\0")) {
            // Unrecognised encoding with null bytes — attempt UTF-16 LE without BOM.
            $converted = mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
            if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                $raw = $converted;
            } else {
                return '[Output contained binary data and was omitted so the UI can keep updating.]';
            }
        }

        // Normalise line endings: CRLF → LF, then bare CR → LF.
        // git --progress writes \r-separated progress lines (no \n between them).
        // Without this the blade explode("\n", …) keeps them all on one long line.
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);

        return mb_scrub($raw, 'UTF-8');
    }
}
