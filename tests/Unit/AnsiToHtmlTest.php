<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AnsiToHtml;
use PHPUnit\Framework\TestCase;

class AnsiToHtmlTest extends TestCase
{
    public function test_strips_cursor_sequences_but_keeps_sgr(): void
    {
        $line = "\x1b[2J\x1b[33mok\x1b[0m";
        $html = AnsiToHtml::lineToHtml($line);
        $this->assertStringContainsString('color:', $html);
        $this->assertStringNotContainsString('[2J', $html);
        $this->assertStringContainsString('ok', $html);
    }

    public function test_bold_and_cyan(): void
    {
        $line = "\x1b[1;36mAPPSERVER\x1b[0m";
        $html = AnsiToHtml::lineToHtml($line);
        $this->assertStringContainsString('font-weight:700', $html);
        $this->assertStringContainsString('#11a8cd', $html);
    }
}
