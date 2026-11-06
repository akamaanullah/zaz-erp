<?php
declare(strict_types=1);

/**
 * Copy to config.local.php to override config.php.
 * config.local.php is gitignored. Do not commit passwords.
 */
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'name' => 'zaz_agriculture',
        'user' => 'root',
        'pass' => '',
    ],
    'app' => [
        'env' => 'local',
        'allow_demo_reset' => true,
    ],
];
