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

        if (str_contains($raw, "\0")) {
            return '[Output contained binary data and was omitted so the UI can keep updating.]';
        }

        // Normalise line endings: CRLF → LF, then bare CR → LF.
        // git --progress writes \r-separated progress lines (no \n between them).
        // Without this the blade explode("\n", …) keeps them all on one long line.
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);

        return mb_scrub($raw, 'UTF-8');
    }
}
