<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CommandLogErrorDetector;
use PHPUnit\Framework\TestCase;

class CommandLogErrorDetectorTest extends TestCase
{
    public function test_detects_docker_error_buried_before_lando_banner(): void
    {
        $longPrefix = str_repeat("apt install line\n", 500);
        $content = $longPrefix
            ."Error response from daemon: failed to set up container networking: port is already allocated\n"
            ."more noise\n"
            ."ERROR ==>\n"
            ."██╗ ██╗\n"
            ."Or post your issue to Slack\n";

        $this->assertTrue(CommandLogErrorDetector::indicatesFailure($content));
    }

    public function test_detects_unrecoverable_lando_startup_message(): void
    {
        $content = "Building...\nAn unrecoverable error occurred while starting up your app!\nHelp text\n";

        $this->assertTrue(CommandLogErrorDetector::indicatesFailure($content));
    }

    public function test_detects_wp_cli_database_error(): void
    {
        $content = "Downloading WordPress...\nPHP Warning: mysqli...\nError: Database connection error (2002) getaddrinfo for database failed\n";

        $this->assertTrue(CommandLogErrorDetector::indicatesFailure($content));
    }

    public function test_clean_success_log_is_not_failure(): void
    {
        $content = "Success: WordPress downloaded.\nmd5 hash verified\n";

        $this->assertFalse(CommandLogErrorDetector::indicatesFailure($content));
    }

    public function test_last_exited_with_code_non_zero_fails(): void
    {
        $content = "step one exited with code 0\nstep two exited with code 1\n";

        $this->assertTrue(CommandLogErrorDetector::indicatesFailure($content));
    }

    public function test_last_exited_with_code_zero_passes(): void
    {
        $content = "retry exited with code 1\nfinal exited with code 0\n";

        $this->assertFalse(CommandLogErrorDetector::indicatesFailure($content));
    }
}
