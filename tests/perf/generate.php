<?php
declare(strict_types=1);

/**
 * Synthetic load for query measurement only.
 * Database: zaz_agriculture_perf. Never touches zaz_agriculture.
 *
 * Size (documented, bounded):
 *   8 farms, 2 seasons, 16 hari, 1 owner
 *   10000 stock usages, 2000 expenses, 2000 sales, 1000 advances
 *   400 transfers, 200 daily work rows, 80 godown receipts
 * Inserts are multi-row chunks of 500 inside one transaction per table.
 */
putenv('ZAZ_DB_NAME=zaz_agriculture_perf');
putenv('ZAZ_APP_ENV=local');
putenv('ZAZ_ALLOW_DEMO_RESET=0');
$_SERVER['SCRIPT_NAME'] = '/index.php';

require dirname(__DIR__, 2) . '/app/Core/Autoload.php';
App\Core\Autoload::register();
require dirname(__DIR__, 2) . '/database/migrate.php';

use App\Core\Database;
use App\Models\UserModel;

if ((string)config()['db']['name'] !== 'zaz_agriculture_perf') {
    fwrite(STDERR, "Refusing to generate into a database that is not zaz_agriculture_perf\n");
    exit(1);
}

$cfg = config()['db'];
$server = new PDO(
    sprintf('mysql:host=%s;port=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['charset']),
    (string)$cfg['user'],
    (string)$cfg['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$server->exec('DROP DATABASE IF EXISTS `zaz_agriculture_perf`');
$server->exec('CREATE DATABASE `zaz_agriculture_perf` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
Database::reset();
zaz_migrate();
Database::reset();
$pdo = Database::pdo();

$pdo->prepare('INSERT INTO seasons (name, sort_order) VALUES (?, ?)')->execute(['Kharif 2026', 0]);
$pdo->prepare('INSERT INTO seasons (name, sort_order) VALUES (?, ?)')->execute(['Rabi 2026-27', 1]);
$seasonIds = array_map('intval', $pdo->query('SELECT id FROM seasons ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));

$farmIds = [];
for ($i = 1; $i <= 8; $i++) {
    $pdo->prepare('INSERT INTO farms (name, acres, seri_acres, location, is_archived, created_at, updated_at) VALUES (?, 100, 20, ?, 0, NOW(), NOW())')
        ->execute(['Perf Farm ' . $i, 'Bench']);
    $farmIds[] = (int)$pdo->lastInsertId();
}
$hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
$ownerId = UserModel::insert('Perf Owner', 'perf.owner', $hash, 'owner', null, 0, 'Monthly salary');
$supId = UserModel::insert('Perf Supervisor', 'perf.sup', $hash, 'supervisor', $farmIds[0], 0, 'Monthly salary');

$hariByFarm = [];
foreach ($farmIds as $farmId) {
    for ($h = 1; $h <= 2; $h++) {
        $pdo->prepare('INSERT INTO haris (farm_id, name, address, is_active, created_at, updated_at) VALUES (?, ?, ?, 1, NOW(), NOW())')
            ->execute([$farmId, 'Hari ' . $farmId . '-' . $h, 'bench']);
        $hariByFarm[$farmId][] = (int)$pdo->lastInsertId();
    }
}

$insert = static function (PDO $pdo, string $sqlPrefix, array $rows, int $width) use (&$times): void {
    $started = microtime(true);
    $pdo->beginTransaction();
    $chunk = 500;
    for ($i = 0; $i < count($rows); $i += $chunk) {
        $slice = array_slice($rows, $i, $chunk);
        $marks = implode(',', array_fill(0, count($slice), '(' . implode(',', array_fill(0, $width, '?')) . ')'));
        $params = [];
        foreach ($slice as $row) {
            foreach ($row as $value) {
                $params[] = $value;
            }
        }
        $pdo->prepare($sqlPrefix . $marks)->execute($params);
    }
    $pdo->commit();
    $times[] = round((microtime(true) - $started) * 1000);
};

$times = [];
$usages = [];
for ($n = 0; $n < 10000; $n++) {
    $farmId = $farmIds[$n % 8];
    $hariId = $hariByFarm[$farmId][$n % 2];
    $pending = $n % 5 === 0;
    $usages[] = [$farmId, $seasonIds[$n % 2], 'Urea', 2, 10.50, 21.00, 'hari', $hariId, 'Bench', 50, $pending ? 'pending' : 'approved', 'bench', $ownerId, $pending ? null : $ownerId, $pending ? null : date('Y-m-d H:i:s'), date('Y-m-d H:i:s')];
}
$insert($pdo, 'INSERT INTO stock_usages (farm_id, season_id, item_name, qty, rate_snapshot, amount, target_type, hari_id, crop, share_snapshot, status, by_label, created_by, approved_by, approved_at, created_at) VALUES ', $usages, 16);

$expenses = [];
for ($n = 0; $n < 2000; $n++) {
    $farmId = $farmIds[$n % 8];
    $hariId = $hariByFarm[$farmId][$n % 2];
    $pending = $n % 5 === 0;
    $expenses[] = [$farmId, $seasonIds[$n % 2], 'Tractor', 2, 10, 'per acre', 20.00, 'hari', $hariId, 'Bench', 100, $pending ? 'pending' : 'approved', 'bench', $ownerId, $pending ? null : $ownerId, $pending ? null : date('Y-m-d H:i:s'), date('Y-m-d H:i:s')];
}
$insert($pdo, 'INSERT INTO expenses (farm_id, season_id, category_name, qty, rate_snapshot, unit_snapshot, amount, target_type, hari_id, crop, share_snapshot, status, by_label, created_by, approved_by, approved_at, created_at) VALUES ', $expenses, 17);

$sales = [];
for ($n = 0; $n < 2000; $n++) {
    $farmId = $farmIds[$n % 8];
    $hariId = $hariByFarm[$farmId][$n % 2];
    $pending = $n % 5 === 0;
    $sales[] = [$farmId, $seasonIds[$n % 2], 'Wheat', 'hari', $hariId, 'maund', 2, 100, 80, 200.00, 100.00, 100.00, $pending ? 'pending' : 'approved', 'bench', $ownerId, $pending ? null : $ownerId, $pending ? null : date('Y-m-d H:i:s'), date('Y-m-d H:i:s')];
}
$insert($pdo, 'INSERT INTO sales (farm_id, season_id, crop, target_type, hari_id, weight_unit, weight, rate_snapshot, kg, amount, hari_share_amount, owner_share_amount, status, by_label, created_by, approved_by, approved_at, created_at) VALUES ', $sales, 18);

$advances = [];
for ($n = 0; $n < 1000; $n++) {
    $farmId = $farmIds[$n % 8];
    $hariId = $hariByFarm[$farmId][$n % 2];
    $pending = $n % 5 === 0;
    $advances[] = [$farmId, $seasonIds[$n % 2], $hariId, 'Bench', 15.00, $pending ? 'pending' : 'approved', 'bench', $ownerId, $pending ? null : $ownerId, $pending ? null : date('Y-m-d H:i:s'), date('Y-m-d H:i:s')];
}
$insert($pdo, 'INSERT INTO advances (farm_id, season_id, hari_id, reason, amount, status, by_label, created_by, approved_by, approved_at, created_at) VALUES ', $advances, 11);

$transfers = [];
for ($n = 0; $n < 400; $n++) {
    $received = $n % 2 === 0 ? 18 : null;
    $transfers[] = [$farmIds[$n % 8], 'Urea', 20, $received, date('Y-m-d'), $ownerId, $received === null ? null : $supId, $received === null ? null : date('Y-m-d H:i:s'), date('Y-m-d H:i:s')];
}
$insert($pdo, 'INSERT INTO transfers (farm_id, item_name, qty_sent, qty_received, sent_on, created_by, received_by, received_at, created_at) VALUES ', $transfers, 9);

$daily = [];
for ($n = 0; $n < 200; $n++) {
    $daily[] = [$farmIds[$n % 8], date('Y-m-d'), date('Y-m-d H:i:s'), 'Irrigation', 'Bench work', '', null, null, '', $ownerId, date('Y-m-d H:i:s')];
}
$insert($pdo, 'INSERT INTO daily_work (farm_id, work_date, occurred_at, work_type, description, resources, acres, hours, notes, created_by, created_at) VALUES ', $daily, 11);

$receipts = [];
for ($n = 0; $n < 80; $n++) {
    $receipts[] = ['Urea', 100, 10.50, date('Y-m-d'), $ownerId, date('Y-m-d H:i:s')];
}
$insert($pdo, 'INSERT INTO godown_receipts (item_name, qty, rate, received_on, created_by, created_at) VALUES ', $receipts, 6);

$counts = $pdo->query('SELECT (SELECT COUNT(*) FROM stock_usages) usages, (SELECT COUNT(*) FROM expenses) expenses, (SELECT COUNT(*) FROM sales) sales, (SELECT COUNT(*) FROM advances) advances, (SELECT COUNT(*) FROM transfers) transfers, (SELECT COUNT(*) FROM daily_work) daily_work, (SELECT COUNT(*) FROM godown_receipts) receipts')->fetch();
$txn = (int)$counts['usages'] + (int)$counts['expenses'] + (int)$counts['sales'] + (int)$counts['advances'] + (int)$counts['transfers'] + (int)$counts['daily_work'] + (int)$counts['receipts'];
fwrite(STDOUT, json_encode([
    'database' => 'zaz_agriculture_perf',
    'farms' => 8,
    'seasons' => 2,
    'haris' => 16,
    'chunk' => 500,
    'insert_ms' => $times,
    'counts' => $counts,
    'transaction_rows' => $txn,
    'supervisor_farm_id' => $farmIds[0],
    'owner_id' => $ownerId,
], JSON_UNESCAPED_SLASHES) . PHP_EOL);
