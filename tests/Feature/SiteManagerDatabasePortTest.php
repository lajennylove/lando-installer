<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SiteStatus;
use App\Models\Site;
use App\Services\SiteManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteManagerDatabasePortTest extends TestCase
{
    use RefreshDatabase;

    public function test_allocate_database_forward_port_starts_at_config_default(): void
    {
        $manager = app(SiteManager::class);

        $this->assertSame(
            (int) config('lando_dev.defaults.database_forward_port_start'),
            $manager->allocateDatabaseForwardPort()
        );
    }

    public function test_allocate_database_forward_port_increments_past_max_stored(): void
    {
        Site::create([
            'name' => 'first',
            'path' => '/tmp/first',
            'status' => SiteStatus::Running,
            'db_port' => 32_790,
        ]);

        $manager = app(SiteManager::class);

        $this->assertSame(32_791, $manager->allocateDatabaseForwardPort());
    }

    public function test_allocate_never_drops_below_config_start(): void
    {
        config(['lando_dev.defaults.database_forward_port_start' => 32_800]);

        Site::create([
            'name' => 'low',
            'path' => '/tmp/low',
            'status' => SiteStatus::Running,
            'db_port' => 32_799,
        ]);

        $this->assertSame(32_800, app(SiteManager::class)->allocateDatabaseForwardPort());
    }
}
