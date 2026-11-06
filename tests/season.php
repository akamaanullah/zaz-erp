<?php
declare(strict_types=1);

putenv('ZAZ_TEST=1');
putenv('ZAZ_DB_NAME=zaz_agriculture_test');
putenv('ZAZ_APP_ENV=local');
$_SERVER['SCRIPT_NAME'] = '/index.php';

require dirname(__DIR__) . '/app/Core/Autoload.php';
App\Core\Autoload::register();
require dirname(__DIR__) . '/database/migrate.php';

use App\Core\Database;
use App\Models\FarmModel;
use App\Models\HariModel;
use App\Models\SeasonModel;
use App\Models\UserModel;
use App\Services\ReadModel;

if ((string)config()['db']['name'] !== 'zaz_agriculture_test') {
    fwrite(STDERR, "Refusing season test\n");
    exit(1);
}
$cfg = config()['db'];
$server = new PDO(sprintf('mysql:host=%s;port=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['charset']), (string)$cfg['user'], (string)$cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec('DROP DATABASE IF EXISTS `zaz_agriculture_test`');
$server->exec('CREATE DATABASE `zaz_agriculture_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
Database::reset();
zaz_migrate();
Database::reset();

$kharif = SeasonModel::insert('Kharif 2026', 0);
$rabi = SeasonModel::insert('Rabi 2026-27', 1);
$farmA = FarmModel::insert('Farm A', 100, 20, 'A', null);
$farmB = FarmModel::insert('Farm B', 50, 10, 'B', null);
$owner = UserModel::insert('Owner', 'owner', password_hash('x', PASSWORD_DEFAULT), 'owner', null, 0, 'Monthly salary');
$sup = UserModel::insert('Sup', 'sup.b', password_hash('x', PASSWORD_DEFAULT), 'supervisor', $farmB, 0, 'Monthly salary');
$hari = HariModel::insert($farmA, 'Ali', 'A');
$pdo = Database::pdo();
$insert = static function (string $sql, array $row) use ($pdo): void {
    $pdo->prepare($sql)->execute($row);
};
$insert('INSERT INTO expenses (farm_id, season_id, category_name, qty, rate_snapshot, unit_snapshot, amount, target_type, hari_id, crop, share_snapshot, status, by_label, created_by, approved_by, approved_at, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())', [$farmA, $kharif, 'Labour', 1, 10, 'per acre', 10, 'hari', $hari, 'KharifCrop', 100, 'approved', 't', $owner, $owner, date('Y-m-d H:i:s')]);
$insert('INSERT INTO expenses (farm_id, season_id, category_name, qty, rate_snapshot, unit_snapshot, amount, target_type, hari_id, crop, share_snapshot, status, by_label, created_by, approved_by, approved_at, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())', [$farmA, $rabi, 'Labour', 1, 40, 'per acre', 40, 'hari', $hari, 'RabiCrop', 100, 'pending', 't', $owner, null, null]);
$insert('INSERT INTO sales (farm_id, season_id, crop, target_type, hari_id, weight_unit, weight, rate_snapshot, kg, amount, hari_share_amount, owner_share_amount, status, by_label, created_by, approved_by, approved_at, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())', [$farmA, $kharif, 'KharifCrop', 'hari', $hari, 'kg', 10, 10, 10, 100, 50, 50, 'approved', 't', $owner, $owner, date('Y-m-d H:i:s')]);
$insert('INSERT INTO sales (farm_id, season_id, crop, target_type, hari_id, weight_unit, weight, rate_snapshot, kg, amount, hari_share_amount, owner_share_amount, status, by_label, created_by, approved_by, approved_at, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())', [$farmA, $rabi, 'RabiCrop', 'hari', $hari, 'kg', 1, 5, 1, 5, 2.5, 2.5, 'pending', 't', $owner, null, null]);

$reader = new ReadModel();
$ownerUser = UserModel::find($owner);
$k = $reader->table($ownerUser, ['resource' => 'expenses', 'farm' => 'Farm A', 'season' => 'Kharif 2026', 'page' => 1]);
$r = $reader->table($ownerUser, ['resource' => 'expenses', 'farm' => 'Farm A', 'season' => 'Rabi 2026-27', 'page' => 1]);
$snap = (new App\Services\Snapshot())->forUser($ownerUser);
$kLedger = $reader->ledger($ownerUser, $hari, true, 'Kharif 2026');
$rLedger = $reader->ledger($ownerUser, $hari, true, 'Rabi 2026-27');
$forged = $reader->table(UserModel::find($sup), ['resource' => 'expenses', 'farm' => 'Farm A', 'season' => 'Kharif 2026', 'page' => 1]);
$ok = (float)$k['totals']['amount'] === 10.0
    && (float)$r['totals']['amount'] === 40.0
    && (int)$k['total'] === 1
    && (int)$r['total'] === 1
    && (float)$snap['summary']['seasons']['Kharif 2026']['byFarm']['Farm A']['sales'] === 100.0
    && (float)$snap['summary']['seasons']['Rabi 2026-27']['byFarm']['Farm A']['sales'] === 5.0
    && (float)$kLedger['gross'] === 50.0
    && (float)$rLedger['gross'] === 0.0
    && $forged['total'] === 0;
fwrite(STDOUT, $ok ? "season scope passed\n" : "season scope failed\n");
exit($ok ? 0 : 1);
