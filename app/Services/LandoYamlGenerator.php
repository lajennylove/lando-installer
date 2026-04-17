<?php

namespace App\Services;

/**
 * Generates .lando.yml for each WordPress project. Service versions are Lando/container-side only.
 *
 * When a CA certificate is present in the site directory (lando-ca.crt), the generator
 * injects volume mounts and build steps to install it in the container's system CA store.
 * This enables HTTPS for curl, apt, and npm inside Docker on corporate proxy networks.
 */
class LandoYamlGenerator
{
    public function generate(string $siteName, string $sitePath, array $options = []): string
    {
        $stub = file_get_contents(resource_path('stubs/lando.yml.stub'));

        $hasCert = file_exists(rtrim($sitePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'lando-ca.crt');

        $replacements = [
            '{{SITE_NAME}}' => $siteName,
            '{{PHP_VERSION}}' => $options['php_version'] ?? config('lando_dev.defaults.php_version'),
            '{{DB_VERSION}}' => $options['db_version'] ?? config('lando_dev.defaults.db_version'),
            '{{REDIS_VERSION}}' => $options['redis_version'] ?? config('lando_dev.defaults.redis_version'),
            '{{DB_PORT}}' => (string) ($options['db_port'] ?? config('lando_dev.defaults.database_forward_port_start')),
            '{{CA_CERT_OVERRIDES}}' => $hasCert ? $this->certOverridesBlock() : '',
            '{{CA_CERT_BUILD_STEPS}}' => $hasCert ? $this->certBuildSteps() : '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    public function write(string $siteName, string $path, array $options = []): void
    {
        $content = $this->generate($siteName, $path, $options);
        $filePath = rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.lando.yml';

        file_put_contents($filePath, $content);
    }

    /**
     * Lando service overrides to mount the CA cert into the container.
     */
    private function certOverridesBlock(): string
    {
        return <<<'YAML'
    overrides:
      volumes:
        - ./lando-ca.crt:/usr/local/share/ca-certificates/lando-ca.crt:ro

YAML;
    }

    /**
     * Build steps that install the mounted CA cert into the system store before
     * any other curl/apt commands run. Appends the cert to the system CA bundle
     * so curl, apt, and node all trust it.
     */
    private function certBuildSteps(): string
    {
        return <<<'YAML'
      - cp /usr/local/share/ca-certificates/lando-ca.crt /usr/local/share/ca-certificates/lando-ca-corp.crt && update-ca-certificates 2>/dev/null || true

YAML;
    }
}
