<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SiteStatus;
use App\Models\RemoteSite;
use App\Models\Site;
use App\Services\ApplicationDatabaseReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationDatabaseResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_wipe_removes_sites_and_remote_sites(): void
    {
        Site::create([
            'name' => 'a',
            'path' => '/tmp/a',
            'status' => SiteStatus::Running,
        ]);
        RemoteSite::create([
            'remote_domain' => 'https://example.com',
            'ssh_server_ip' => '1.2.3.4',
            'ssh_user' => 'u',
            'remote_path' => '/home/u/applications/abc/public_html',
            'db_name' => 'd',
            'db_user' => 'du',
        ]);

        ApplicationDatabaseReset::wipe();

        $this->assertSame(0, Site::query()->count());
        $this->assertSame(0, RemoteSite::query()->count());
    }

    public function test_wipe_local_sites_only_preserves_remote_sites(): void
    {
        Site::create([
            'name' => 'a',
            'path' => '/tmp/a',
            'status' => SiteStatus::Running,
        ]);
        RemoteSite::create([
            'remote_domain' => 'https://example.com',
            'ssh_server_ip' => '1.2.3.4',
            'ssh_user' => 'u',
            'remote_path' => '/home/u/applications/abc/public_html',
            'db_name' => 'd',
            'db_user' => 'du',
        ]);

        ApplicationDatabaseReset::wipeLocalSitesOnly();

        $this->assertSame(0, Site::query()->count());
        $this->assertSame(1, RemoteSite::query()->count());
    }
}
