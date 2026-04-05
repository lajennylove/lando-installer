<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PlatformDetector;
use Tests\TestCase;

class PlatformDetectorShellWrapTest extends TestCase
{
    public function test_wrap_puts_log_redirect_outside_pipeline(): void
    {
        $p = new PlatformDetector;
        $log = '/tmp/lando-step.log';
        $inner = 'echo hello | gzip > /tmp/out.gz';

        $wrapped = $p->wrapCommandWithLogRedirect($inner, $log);

        $this->assertStringStartsWith('(', $wrapped);
        $this->assertStringContainsString(') > ', $wrapped);
        $this->assertStringEndsWith(' 2>&1', $wrapped);
        $this->assertStringContainsString($inner, $wrapped);
    }
}
