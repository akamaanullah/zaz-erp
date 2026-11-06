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
use App\Models\UserModel;
use App\Services\DemoSeeder;

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

$seeder = new DemoSeeder();
$seeder->seedFoundation();
$hash = password_hash($password, PASSWORD_DEFAULT);
$users = [
    ['ZAZ Owner', 'owner', 'owner', null],
    ['Main Office', 'office', 'office', null],
    ['Jahaniya Accountant 1', 'jahaniya.a1', 'accountant', 'Jahaniya'],
    ['Jahaniya Supervisor', 'jahaniya.sup', 'supervisor', 'Jahaniya'],
    ['Zareen Supervisor', 'zareen.sup', 'supervisor', 'Zareen'],
];
foreach ($users as [$name, $username, $role, $farmName]) {
    $farmId = $farmName ? (int)\App\Models\FarmModel::findByName($farmName)['id'] : null;
    UserModel::insert($name, $username, $hash, $role, $farmId, 1000, 'Monthly salary');
}
$seeder->seedSample();
$enabled = (int)Database::pdo()->query('SELECT COUNT(*) c FROM users WHERE totp_enabled = 1')->fetch()['c'];
if ($enabled !== 0) {
    fwrite(STDERR, "E2E users must stay without authenticator enrollment\n");
    exit(1);
}
fwrite(STDOUT, "SEEDED\n");
