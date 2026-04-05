<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\RemoteSite;
use App\Services\RemoteConnectionVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class RemoteConnectionVerifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_succeeds_when_ssh_and_schema_dump_ok(): void
    {
        $remote = RemoteSite::query()->create([
            'remote_domain' => 'https://example.com',
            'local_domain' => null,
            'ssh_server_ip' => '10.0.0.1',
            'ssh_user' => 'u',
            'ssh_password' => 's',
            'db_name' => 'd',
            'db_user' => 'du',
            'db_password' => 'dp',
            'theme_name' => null,
            'repo_url' => null,
        ]);

        Process::fake([
            '*' => Process::sequence()
                ->push(Process::result(output: 'LANDODEV_SSH_OK'))
                ->push(Process::result(output: "-- MariaDB dump 10.6\n")),
        ]);

        $r = app(RemoteConnectionVerifier::class)->verify($remote);

        $this->assertTrue($r->ok);
    }

    public function test_fails_when_ssh_does_not_return_marker(): void
    {
        $remote = RemoteSite::query()->create([
            'remote_domain' => 'https://example.com',
            'local_domain' => null,
            'ssh_server_ip' => '10.0.0.1',
            'ssh_user' => 'u',
            'ssh_password' => 's',
            'db_name' => 'd',
            'db_user' => 'du',
            'db_password' => 'dp',
            'theme_name' => null,
            'repo_url' => null,
        ]);

        Process::fake([
            '*' => Process::result(output: 'Permission denied'),
        ]);

        $r = app(RemoteConnectionVerifier::class)->verify($remote);

        $this->assertFalse($r->ok);
        $this->assertSame('ssh', $r->phase);
    }

    public function test_fails_when_mysql_access_denied(): void
    {
        $remote = RemoteSite::query()->create([
            'remote_domain' => 'https://example.com',
            'local_domain' => null,
            'ssh_server_ip' => '10.0.0.1',
            'ssh_user' => 'u',
            'ssh_password' => 's',
            'db_name' => 'd',
            'db_user' => 'du',
            'db_password' => 'dp',
            'theme_name' => null,
            'repo_url' => null,
        ]);

        Process::fake([
            '*' => Process::sequence()
                ->push(Process::result(output: 'LANDODEV_SSH_OK'))
                ->push(Process::result(output: 'ERROR 1045 (28000): Access denied for user')),
        ]);

        $r = app(RemoteConnectionVerifier::class)->verify($remote);

        $this->assertFalse($r->ok);
        $this->assertSame('database', $r->phase);
    }
}
