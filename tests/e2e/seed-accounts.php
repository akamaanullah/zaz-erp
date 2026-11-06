<?php
declare(strict_types=1);

putenv('ZAZ_DB_NAME=zaz_agriculture_e2e');
putenv('ZAZ_APP_ENV=local');
putenv('ZAZ_ALLOW_DEMO_RESET=0');
$_SERVER['SCRIPT_NAME'] = '/index.php';

require dirname(__DIR__, 2) . '/app/Core/Autoload.php';
App\Core\Autoload::register();
require dirname(__DIR__, 2) . '/database/migrate.php';

use App\Core\Database;
use App\Models\SeasonModel;
use App\Models\UserModel;

if ((string)config()['db']['name'] !== 'zaz_agriculture_e2e') {
    fwrite(STDERR, "Refusing to seed a database that is not zaz_agriculture_e2e\n");
    exit(1);
}
$password = trim((string)stream_get_contents(STDIN));
if (strlen($password) < 12) {
    fwrite(STDERR, "E2E password missing\n");
    exit(1);
}

$cfg = config()['db'];
$server = new PDO(
    sprintf('mysql:host=%s;port=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['charset']),
    (string)$cfg['user'],
    (string)$cfg['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$server->exec('DROP DATABASE IF EXISTS `zaz_agriculture_e2e`');
$server->exec('CREATE DATABASE `zaz_agriculture_e2e` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
Database::reset();
zaz_migrate();
Database::reset();
SeasonModel::insert('Kharif 2026', 0);
SeasonModel::insert('Rabi 2026-27', 1);
$hash = password_hash($password, PASSWORD_DEFAULT);
UserModel::insert('ZAZ Owner', 'owner', $hash, 'owner', null, 0, 'Monthly salary');
UserModel::insert('Main Office', 'office', $hash, 'office', null, 0, 'Monthly salary');
$enabled = (int)Database::pdo()->query('SELECT COUNT(*) c FROM users WHERE totp_enabled = 1')->fetch()['c'];
if ($enabled !== 0) {
    fwrite(STDERR, "E2E accounts must stay without authenticator enrollment\n");
    exit(1);
}
fwrite(STDOUT, "SEEDED\n");
