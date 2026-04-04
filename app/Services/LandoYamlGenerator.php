<?php

namespace App\Services;

/**
 * Generates .lando.yml for each WordPress project. Service versions are Lando/container-side only.
 */
class LandoYamlGenerator
{
    public function generate(string $siteName, array $options = []): string
    {
        $stub = file_get_contents(resource_path('stubs/lando.yml.stub'));

        $replacements = [
            '{{SITE_NAME}}' => $siteName,
            '{{PHP_VERSION}}' => $options['php_version'] ?? config('lando_dev.defaults.php_version'),
            '{{DB_VERSION}}' => $options['db_version'] ?? config('lando_dev.defaults.db_version'),
            '{{REDIS_VERSION}}' => $options['redis_version'] ?? config('lando_dev.defaults.redis_version'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    public function write(string $siteName, string $path, array $options = []): void
    {
        $content = $this->generate($siteName, $options);
        $filePath = rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.lando.yml';

        file_put_contents($filePath, $content);
    }
}
