<?php

namespace App\Services;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads a .lando.yml file and extracts the service versions used by the project.
 */
class LandoYamlParser
{
    /**
     * Parse a .lando.yml file and return structured version data.
     *
     * @return array{name: string, php_version: string, db_type: string, db_version: string, redis_version: string|null, db_port: int|null}|null
     */
    public function parse(string $filePath): ?array
    {
        if (! file_exists($filePath)) {
            return null;
        }

        try {
            $data = Yaml::parseFile($filePath);
        } catch (ParseException) {
            return null;
        }

        if (! is_array($data)) {
            return null;
        }

        $name = $data['name'] ?? null;
        if (! $name) {
            return null;
        }

        // PHP version from config.php (may be parsed as float e.g. 8.3)
        $phpVersion = (string) ($data['config']['php'] ?? config('lando_dev.defaults.php_version'));

        // DB: config.database = "mariadb:10.6" or "mysql:8.0"
        $dbFull = $data['config']['database'] ?? ('mariadb:'.config('lando_dev.defaults.db_version'));
        [$dbType, $dbVersion] = array_pad(explode(':', (string) $dbFull, 2), 2, config('lando_dev.defaults.db_version'));
        $dbType = $dbType ?: 'mariadb';

        // Redis: services.cache.type = "redis:7.4"
        $redisVersion = null;
        $cacheType = $data['services']['cache']['type'] ?? null;
        if ($cacheType) {
            $redisParts = explode(':', (string) $cacheType, 2);
            $redisVersion = $redisParts[1] ?? null;
        }

        // DB host port: services.database.portforward (integer only; true/false means random)
        $portForward = $data['services']['database']['portforward'] ?? null;
        $dbPort = is_int($portForward) && $portForward > 0 ? $portForward : null;

        return [
            'name' => (string) $name,
            'php_version' => $phpVersion,
            'db_type' => $dbType,
            'db_version' => $dbVersion,
            'redis_version' => $redisVersion,
            'db_port' => $dbPort,
        ];
    }
}
