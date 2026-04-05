<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\RemoteSite;
use App\Services\PlatformDetector;
use App\Services\SshService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SshServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function unix_build_writes_local_sql_then_gzip(): void
    {
        $platform = Mockery::mock(PlatformDetector::class);
        $platform->allows('isWindows')->andReturn(false);

        $remote = new RemoteSite([
            'ssh_user' => 'u',
            'ssh_server_ip' => '1.2.3.4',
            'ssh_password' => 'sp',
            'db_user' => 'du',
            'db_password' => 'dp',
            'db_name' => 'db1',
        ]);

        $svc = new SshService($platform);
        $cmd = $svc->buildMysqldumpCommand($remote, '/local/dumpfile.sql.gz');

        $this->assertStringContainsString('set -o pipefail', $cmd);
        $this->assertStringContainsString('SSHPASS=', $cmd);
        $this->assertStringContainsString('mysqldump', $cmd);
        $this->assertStringContainsString('/local/dumpfile.sql', $cmd);
        $this->assertStringContainsString('gzip -c', $cmd);
        $this->assertStringContainsString('/local/dumpfile.sql.gz', $cmd);
        $this->assertStringContainsString('LANDODEV_HB', $cmd);
        $this->assertStringContainsString('Database dump in progress', $cmd);
        $this->assertStringNotContainsString('| gzip >', $cmd);
        $this->assertStringContainsString('-T', $cmd);
        $this->assertStringNotContainsString('bash --norc', $cmd);
        $this->assertStringNotContainsString('sshpass -e scp', $cmd);
    }

    #[Test]
    public function windows_build_streams_without_pipefail(): void
    {
        $platform = Mockery::mock(PlatformDetector::class);
        $platform->allows('isWindows')->andReturn(true);

        $remote = new RemoteSite([
            'ssh_user' => 'u',
            'ssh_server_ip' => '1.2.3.4',
            'ssh_password' => 'sp',
            'db_user' => 'du',
            'db_password' => 'dp',
            'db_name' => 'db1',
        ]);

        $svc = new SshService($platform);
        $cmd = $svc->buildMysqldumpCommand($remote, '/local/dumpfile.sql.gz');

        $this->assertStringContainsString('| gzip >', $cmd);
        $this->assertStringNotContainsString('set -o pipefail', $cmd);
        $this->assertStringNotContainsString('sshpass -e scp', $cmd);
    }
}
