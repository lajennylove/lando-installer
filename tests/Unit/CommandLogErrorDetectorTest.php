<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CommandLogErrorDetector;
use PHPUnit\Framework\TestCase;

class CommandLogErrorDetectorTest extends TestCase
{
    public function test_detects_wp_not_installed_message(): void
    {
        $log = "Success: something\nError: The site you have requested is not installed.\nRun `wp core install`";
        $this->assertTrue(CommandLogErrorDetector::indicatesFailure($log));
    }
}
