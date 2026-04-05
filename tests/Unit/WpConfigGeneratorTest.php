<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\WpConfigGenerator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WpConfigGeneratorTest extends TestCase
{
    public function test_writes_wp_config_with_prefix_and_salts(): void
    {
        config([
            'lando_dev.defaults.db_name' => 'wordpress',
            'lando_dev.defaults.db_user' => 'wordpress',
            'lando_dev.defaults.db_password' => 'secret',
            'lando_dev.defaults.db_host' => 'database',
        ]);

        Http::fake([
            'api.wordpress.org/*' => Http::response(
                "define('AUTH_KEY', 'a');\ndefine('SECURE_AUTH_KEY', 'b');\ndefine('LOGGED_IN_KEY', 'c');\ndefine('NONCE_KEY', 'd');\ndefine('AUTH_SALT', 'e');\ndefine('SECURE_AUTH_SALT', 'f');\ndefine('LOGGED_IN_SALT', 'g');\ndefine('NONCE_SALT', 'h');",
                200
            ),
        ]);

        $root = sys_get_temp_dir().'/ld_wpcfg_'.uniqid();
        mkdir($root.DIRECTORY_SEPARATOR.'wp', 0755, true);

        try {
            app(WpConfigGenerator::class)->writeForClone($root, 'wp_');
            $path = $root.DIRECTORY_SEPARATOR.'wp'.DIRECTORY_SEPARATOR.'wp-config.php';
            $this->assertFileExists($path);
            $body = (string) file_get_contents($path);
            $this->assertStringContainsString('wordpress', $body);
            $this->assertStringContainsString("'secret'", $body);
            $this->assertStringContainsString('$table_prefix = \'wp_\';', $body);
            $this->assertStringContainsString('AUTH_KEY', $body);
            $this->assertStringContainsString('wp-settings.php', $body);
        } finally {
            @unlink($root.DIRECTORY_SEPARATOR.'wp'.DIRECTORY_SEPARATOR.'wp-config.php');
            @rmdir($root.DIRECTORY_SEPARATOR.'wp');
            @rmdir($root);
        }
    }
}
