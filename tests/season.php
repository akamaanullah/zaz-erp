<?php
declare(strict_types=1);

putenv('ZAZ_TEST=1');
putenv('ZAZ_DB_NAME=zaz_agriculture_test');
putenv('ZAZ_APP_ENV=local');
$_SERVER['SCRIPT_NAME'] = '/index.php';

require dirname(__DIR__) . '/app/Core/Autoload.php';
App\Core\Autoload::register();
require dirname(__DIR__) . '/database/migrate.php';

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Kernel;
use App\Core\Session;
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
$pdo->prepare('UPDATE haris SET name = ? WHERE id = ?')->execute(['Ali <b>x</b>', $hari]);
Session::resetTest();
Database::reset();
$app = new Kernel();
$app->actingAs($owner);
$dash = $app->handle('GET', '/dashboard?season=' . rawurlencode('Kharif 2026'));
$expenses = $app->handle('GET', '/expenses?farm=' . rawurlencode('Farm A') . '&season=' . rawurlencode('Kharif 2026'));
$hariPage = $app->handle('GET', '/hari?farm=' . rawurlencode('Farm A'));
$json = $app->handle('POST', '/api/settings/prefs', json_encode(['theme' => 'dark', 'language' => 'en']), [
    'Accept' => 'application/json', 'Content-Type' => 'application/json', 'X-CSRF-Token' => Csrf::token(),
]);
$form = $app->handle('POST', '/api/settings/prefs', http_build_query([
    'theme' => 'light', 'language' => 'en', '_csrf' => Csrf::token(),
]), ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'text/html', 'Referer' => '/settings']);
$dashHtml = $dash->body;
$expenseHtml = $expenses->body;
$hariHtml = $hariPage->body;
$checks = [
    'dash' => $dash->status === 200,
    'h1' => str_contains($dashHtml, '<h1'),
    'farm' => str_contains($dashHtml, 'Farm A'),
    'no-pages' => !str_contains($dashHtml, 'assets/js/pages/'),
    'no-store' => !str_contains($dashHtml, 'store.js'),
    'no-appjs' => !str_contains($dashHtml, '/assets/js/app.js'),
    'kharif' => str_contains($expenseHtml, 'KharifCrop'),
    'no-rabi' => !str_contains($expenseHtml, 'RabiCrop'),
    'escape' => str_contains($hariHtml, 'Ali &lt;b&gt;x&lt;/b&gt;'),
    'no-raw' => !str_contains($hariHtml, '<b>x</b>'),
    'json' => $json->status === 200 && str_contains($json->headers['Content-Type'] ?? '', 'application/json'),
    'form' => $form->status === 302,
    'exp-status' => $expenses->status === 200,
    'hari-status' => $hariPage->status === 200,
];
$ok = $ok && !in_array(false, $checks, true);
fwrite(STDOUT, $ok ? "season scope passed\n" : "season scope failed\n");
exit($ok ? 0 : 1);
