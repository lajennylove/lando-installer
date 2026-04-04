<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Converts ANSI SGR escape sequences (\x1b[...m) to safe HTML for terminal-style output.
 */
final class AnsiToHtml
{
    /** @var array<string, mixed> */
    private const DEFAULT_STATE = [
        'fg' => null,
        'bg' => null,
        'bold' => false,
    ];

    public static function lineToHtml(string $line): string
    {
        $line = str_replace("\r", '', $line);
        $line = self::stripNonSgrEscapes($line);
        // Hyperlinks / OSC (e.g. iTerm) — strip so we do not show raw escape bytes
        $line = preg_replace('/\x1b\][^\x07\x1b]*(?:\x07|\x1b\\\\)/', '', $line) ?? $line;

        $parts = preg_split('/(\x1b\[[0-9;]*m)/', $line, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $state = self::DEFAULT_STATE;
        $html = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (preg_match('/^\x1b\[([0-9;]*)m$/', $part, $m)) {
                $codes = self::parseSgrCodes($m[1]);
                self::applySgrCodes($state, $codes);

                continue;
            }

            $escaped = htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if ($escaped === '') {
                continue;
            }

            $style = self::inlineStyle($state);
            $html .= $style !== '' ? '<span style="'.htmlspecialchars($style, ENT_QUOTES, 'UTF-8').'">'.$escaped.'</span>' : $escaped;
        }

        return $html;
    }

    private static function stripNonSgrEscapes(string $line): string
    {
        // Remove CSI sequences that are not SGR (…m). Keep \x1b[33m etc. for color parsing.
        return preg_replace_callback(
            '/\x1b\[(?:[\d;?]*)([a-zA-Z])/',
            static fn (array $m): string => $m[1] === 'm' ? $m[0] : '',
            $line
        ) ?? $line;
    }

    /** @return list<int> */
    private static function parseSgrCodes(string $inner): array
    {
        if ($inner === '') {
            return [0];
        }

        return array_map(intval(...), explode(';', $inner));
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  list<int>  $codes
     */
    private static function applySgrCodes(array &$state, array $codes): void
    {
        $i = 0;
        $n = count($codes);

        while ($i < $n) {
            $c = $codes[$i];

            if ($c === 0) {
                $state = self::DEFAULT_STATE;
                $i++;

                continue;
            }

            if ($c === 1) {
                $state['bold'] = true;
                $i++;

                continue;
            }

            if ($c === 22) {
                $state['bold'] = false;
                $i++;

                continue;
            }

            if ($c === 39) {
                $state['fg'] = null;
                $i++;

                continue;
            }

            if ($c === 49) {
                $state['bg'] = null;
                $i++;

                continue;
            }

            if ($c >= 30 && $c <= 37) {
                $state['fg'] = self::standardFg($c - 30);
                $i++;

                continue;
            }

            if ($c >= 90 && $c <= 97) {
                $state['fg'] = self::brightFg($c - 90);
                $i++;

                continue;
            }

            if ($c >= 40 && $c <= 47) {
                $state['bg'] = self::standardBg($c - 40);
                $i++;

                continue;
            }

            if ($c >= 100 && $c <= 107) {
                $state['bg'] = self::brightBg($c - 100);
                $i++;

                continue;
            }

            if ($c === 38 && $i + 2 < $n && $codes[$i + 1] === 5) {
                $state['fg'] = self::xterm256ToRgb($codes[$i + 2]);
                $i += 3;

                continue;
            }

            if ($c === 38 && $i + 4 < $n && $codes[$i + 1] === 2) {
                $state['fg'] = sprintf('rgb(%d,%d,%d)', $codes[$i + 2], $codes[$i + 3], $codes[$i + 4]);
                $i += 5;

                continue;
            }

            if ($c === 48 && $i + 2 < $n && $codes[$i + 1] === 5) {
                $state['bg'] = self::xterm256ToRgb($codes[$i + 2]);
                $i += 3;

                continue;
            }

            if ($c === 48 && $i + 4 < $n && $codes[$i + 1] === 2) {
                $state['bg'] = sprintf('rgb(%d,%d,%d)', $codes[$i + 2], $codes[$i + 3], $codes[$i + 4]);
                $i += 5;

                continue;
            }

            $i++;
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private static function inlineStyle(array $state): string
    {
        $parts = [];
        if ($state['fg'] !== null) {
            $parts[] = 'color:'.$state['fg'];
        }
        if ($state['bg'] !== null) {
            $parts[] = 'background-color:'.$state['bg'];
        }
        if ($state['bold']) {
            $parts[] = 'font-weight:700';
        }

        return implode(';', $parts);
    }

    private static function standardFg(int $n): string
    {
        return match ($n) {
            0 => '#000000',
            1 => '#cd3131',
            2 => '#0dbc79',
            3 => '#e5e510',
            4 => '#2472c8',
            5 => '#bc3fbc',
            6 => '#11a8cd',
            7 => '#e5e5e5',
            default => '#e5e5e5',
        };
    }

    private static function brightFg(int $n): string
    {
        return match ($n) {
            0 => '#666666',
            1 => '#f14c4c',
            2 => '#23d18b',
            3 => '#f5f543',
            4 => '#3b8eea',
            5 => '#d670d6',
            6 => '#29b8db',
            7 => '#ffffff',
            default => '#ffffff',
        };
    }

    private static function standardBg(int $n): string
    {
        return match ($n) {
            0 => '#000000',
            1 => '#cd3131',
            2 => '#0dbc79',
            3 => '#e5e510',
            4 => '#2472c8',
            5 => '#bc3fbc',
            6 => '#11a8cd',
            7 => '#e5e5e5',
            default => '#000000',
        };
    }

    private static function brightBg(int $n): string
    {
        return match ($n) {
            0 => '#666666',
            1 => '#f14c4c',
            2 => '#23d18b',
            3 => '#f5f543',
            4 => '#3b8eea',
            5 => '#d670d6',
            6 => '#29b8db',
            7 => '#ffffff',
            default => '#ffffff',
        };
    }

    private static function xterm256ToRgb(int $n): string
    {
        $n = max(0, min(255, $n));

        if ($n < 16) {
            $map = [
                '#000000', '#800000', '#008000', '#808000', '#000080', '#800080', '#008080', '#c0c0c0',
                '#808080', '#ff0000', '#00ff00', '#ffff00', '#0000ff', '#ff00ff', '#00ffff', '#ffffff',
            ];

            return $map[$n] ?? '#ffffff';
        }

        if ($n < 232) {
            $n -= 16;
            $cube = [0, 95, 135, 175, 215, 255];
            $r = intdiv($n, 36);
            $g = intdiv($n % 36, 6);
            $b = $n % 6;

            return sprintf('rgb(%d,%d,%d)', $cube[$r], $cube[$g], $cube[$b]);
        }

        $g = 8 + ($n - 232) * 10;

        return sprintf('rgb(%d,%d,%d)', $g, $g, $g);
    }
}
