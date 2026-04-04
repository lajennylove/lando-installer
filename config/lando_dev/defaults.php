<?php

/*
|--------------------------------------------------------------------------
| Lando / container defaults (WordPress projects)
|--------------------------------------------------------------------------
| These versions are written into .lando.yml and apply to PHP, DB, and Redis *inside* Lando.
| They are not the Homebrew PHP used to run this Laravel + NativePHP desktop app on the host.
|--------------------------------------------------------------------------
*/

return [
    'php_version' => '8.3',
    'php_versions' => ['8.4', '8.3', '8.2', '8.1', '8.0'],
    'db_type' => 'mariadb',
    'db_version' => '10.6',
    'db_versions' => ['11.7', '11.4', '10.11', '10.6', '10.5'],
    'redis_version' => '7.4',
    'redis_versions' => ['7.4', '7.2', '7.0', '6.2', '6.0'],
    // Host path for new Lando sites; default ~/code/sites/ is resolved in PlatformDetector (cross-platform).
    'code_path' => env('LANDODEV_CODE_PATH', null),
    'db_name' => 'wordpress',
    'db_user' => 'wordpress',
    'db_password' => 'wordpress',
    'db_host' => 'database',
    // Host port published for MariaDB (each Lando site must use a unique value).
    'database_forward_port_start' => (int) env('LANDO_DATABASE_FORWARD_PORT_START', 32_787),
];
