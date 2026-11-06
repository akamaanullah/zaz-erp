<?php
declare(strict_types=1);

/**
 * Default local configuration.
 * Override with environment variables or config/config.local.php (not committed).
 * Do not put production passwords in this file.
 */
$cfg = [
    'db' => [
        'host' => getenv('ZAZ_DB_HOST') !== false ? (string)getenv('ZAZ_DB_HOST') : '127.0.0.1',
        'port' => getenv('ZAZ_DB_PORT') !== false ? (string)getenv('ZAZ_DB_PORT') : '3306',
        'name' => getenv('ZAZ_DB_NAME') !== false ? (string)getenv('ZAZ_DB_NAME') : 'zaz_agriculture',
        'user' => getenv('ZAZ_DB_USER') !== false ? (string)getenv('ZAZ_DB_USER') : 'root',
        'pass' => getenv('ZAZ_DB_PASS') !== false ? (string)getenv('ZAZ_DB_PASS') : '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'env' => getenv('ZAZ_APP_ENV') !== false ? (string)getenv('ZAZ_APP_ENV') : 'local',
        'name' => 'ZAZ AGRICULTURE',
        'allow_demo_reset' => (getenv('ZAZ_ALLOW_DEMO_RESET') !== false
            ? (string)getenv('ZAZ_ALLOW_DEMO_RESET') === '1'
            : ((getenv('ZAZ_APP_ENV') !== false ? (string)getenv('ZAZ_APP_ENV') : 'local') === 'local')),
        'session_idle_seconds' => 28800,
        'session_name' => 'zaz_erp_sid',
        'timezone' => 'Asia/Karachi',
    ],
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $override = require $local;
    if (is_array($override)) {
        $cfg = array_replace_recursive($cfg, $override);
    }
}

return $cfg;
