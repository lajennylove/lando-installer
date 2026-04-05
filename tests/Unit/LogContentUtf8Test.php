<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\LogContentUtf8;
use PHPUnit\Framework\TestCase;

class LogContentUtf8Test extends TestCase
{
    public function test_empty_string(): void
    {
        $this->assertSame('', LogContentUtf8::forLivewire(''));
    }

    public function test_gzip_magic_replaced_with_message(): void
    {
        $gzip = "\x1f\x8b\x08\x00";
        $out = LogContentUtf8::forLivewire($gzip);
        $this->assertStringContainsString('dumpfile.sql.gz', $out);
        $this->assertSame(1, preg_match('//u', $out));
    }

    public function test_invalid_utf8_is_scrubbed(): void
    {
        $raw = "ok\xFF\xFEtext";
        $out = LogContentUtf8::forLivewire($raw);
        $this->assertSame(1, preg_match('//u', $out));
        $this->assertStringContainsString('ok', $out);
    }
}
