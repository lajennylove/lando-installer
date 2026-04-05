<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Site;
use App\Support\DatabaseTablePrefixDetector;
use Tests\TestCase;

class DatabaseTablePrefixDetectorTest extends TestCase
{
    public function test_returns_null_when_db_port_missing(): void
    {
        $site = new Site(['db_port' => null]);

        $this->assertNull(DatabaseTablePrefixDetector::fromSiteForwardedPort($site));
    }

    public function test_returns_null_when_db_port_invalid(): void
    {
        $site = new Site(['db_port' => 0]);

        $this->assertNull(DatabaseTablePrefixDetector::fromSiteForwardedPort($site));
    }
}
