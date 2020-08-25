<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotEnv = Dotenv::createImmutable(getcwd(), '.env');
$dotEnv->load();

return [
    'paths'         => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds'      => '%%PHINX_CONFIG_DIR%%/db/seeds',
    ],
    'environments'  => [
        'default_migration_table' => 'migrations',
        'default_environment'     => 'production',
        'production'              => [
            'adapter' => 'mysql',
            'host'    => env('DB_HOST'),
            'name'    => env('DB_NAME'),
            'user'    => env('DB_USER'),
            'pass'    => env('DB_PASSWORD'),
            'port'    => '3306',
            'charset' => 'utf8',
        ],
    ],
    'version_order' => 'creation',
];
