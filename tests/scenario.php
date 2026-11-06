<?php
declare(strict_types=1);

/**
 * Isolated arithmetic scenario. Drops only zaz_agriculture_test.
 * Prints measured EXPLAIN rows and timings. Not a browser test.
 */
putenv('ZAZ_TEST=1');
putenv('ZAZ_DB_NAME=zaz_agriculture_test');
putenv('ZAZ_APP_ENV=local');
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require dirname(__DIR__) . '/app/Core/Autoload.php';
App\Core\Autoload::register();
require dirname(__DIR__) . '/database/migrate.php';

use App\Core\Database;
use App\Models\AccountModel;
use App\Models\CatalogModel;
use App\Models\FarmModel;
use App\Models\HariModel;
use App\Models\SeasonModel;
use App\Models\StockModel;
use App\Models\UserModel;
use App\Services\AccountOps;

$pass = 0;
$fail = 0;
function check(bool $ok, string $message): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  OK  {$message}\n";
        return;
    }
    $fail++;
    echo "  FAIL {$message}\n";
}

if ((string)config()['db']['name'] !== 'zaz_agriculture_test') {
    fwrite(STDERR, "Refusing scenario outside the test database\n");
    exit(1);
}
$cfg = config()['db'];
$server = new PDO(
    sprintf('mysql:host=%s;port=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['charset']),
    (string)$cfg['user'],
    (string)$cfg['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$server->exec('DROP DATABASE IF EXISTS `zaz_agriculture_test`');
$server->exec('CREATE DATABASE `zaz_agriculture_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
Database::reset();
zaz_migrate();
Database::reset();

SeasonModel::insert('Kharif 2026', 0);
$farm = FarmModel::insert('Scenario Farm', 100, 20, 'Test', null);
CatalogModel::insert('Urea', 'per bag', 0, 50, 1);
CatalogModel::insert('Tractor Preparation', 'per acre', 10, 100, 0);
$owner = UserModel::insert('Owner', 'owner', password_hash('not-used', PASSWORD_DEFAULT), 'owner', null, 0, 'Monthly salary');
$accountant = UserModel::insert('Accountant', 'acc', password_hash('not-used', PASSWORD_DEFAULT), 'accountant', $farm, 0, 'Monthly salary');
$supervisor = UserModel::insert('Supervisor', 'sup', password_hash('not-used', PASSWORD_DEFAULT), 'supervisor', $farm, 0, 'Monthly salary');
$ownerUser = UserModel::find($owner);
$accUser = UserModel::find($accountant);
$supUser = UserModel::find($supervisor);
$hariId = HariModel::insert($farm, 'Ali', 'Goth');
$plot = HariModel::insertPlot($farm, $hariId, 'Wheat plot', 10, 'hari');
HariModel::insertAllocation($plot, $hariId, (int)SeasonModel::findByName('Kharif 2026')['id'], 'Wheat');
check(HariModel::physicalAcres($hariId) === 10.0, '10 acre plot counts once');
check(FarmModel::hariPlotAcres($farm) === 10.0, 'hari land used is 10 of 80');

$stock = new App\Services\StockOps();
$accounts = new AccountOps();
$stock->receiveGodown($ownerUser, ['item' => 'Urea', 'qty' => 100, 'rate' => 10.5, 'date' => '2026-11-01']);
$stock->send($ownerUser, ['farm' => 'Scenario Farm', 'item' => 'Urea', 'qty' => 20]);
$stock->receive($supUser, ['id' => 1, 'qty' => 18]);
check((string)StockModel::findTransfer(1)['qty_sent'] === '20.00', 'sent quantity stored as 20.00');
check((string)StockModel::findTransfer(1)['qty_received'] === '18.00', 'received quantity stored as 18.00');
check(AccountModel::countExpenses() === 0, 'the transfer is not an expense');
$stock->useStock($accUser, ['farm' => 'Scenario Farm', 'season' => 'Kharif 2026', 'item' => 'Urea', 'qty' => 5, 'target' => (string)$hariId, 'crop' => 'Wheat']);
$stock->useStock($accUser, ['farm' => 'Scenario Farm', 'season' => 'Kharif 2026', 'item' => 'Urea', 'qty' => 2, 'target' => 'seri', 'crop' => 'Seri']);
$hariUse = StockModel::findUsage(1);
$seriUse = StockModel::findUsage(2);
check($hariUse['amount'] === '52.50' && $hariUse['rate_snapshot'] === '10.50' && (int)$hariUse['share_snapshot'] === 50 && $hariUse['status'] === 'pending', 'Hari usage cost is 5 x 10.50 and stays pending');
check($seriUse['amount'] === '21.00' && (int)$seriUse['share_snapshot'] === 0, 'Seri usage has no Hari share');
$accounts->expense($accUser, ['farm' => 'Scenario Farm', 'category' => 'Tractor Preparation', 'qty' => 2, 'rate' => 10.25, 'crop' => 'Wheat', 'season' => 'Kharif 2026', 'target' => (string)$hariId]);
check(AccountModel::findExpense(1)['amount'] === '20.50', 'expense total is 2 x 10.25');
$accounts->sale($accUser, ['farm' => 'Scenario Farm', 'season' => 'Kharif 2026', 'target' => (string)$hariId, 'crop' => 'Wheat', 'unit' => 'maund', 'weight' => 2, 'rate' => 100, 'amount' => 1]);
$accounts->sale($accUser, ['farm' => 'Scenario Farm', 'season' => 'Kharif 2026', 'target' => 'seri', 'crop' => 'Wheat', 'unit' => 'kg', 'weight' => 10, 'rate' => 3, 'amount' => 999]);
check(AccountModel::findSale(1)['amount'] === '200.00' && AccountModel::findSale(1)['kg'] === '80.00' && AccountModel::findSale(1)['hari_share_amount'] === '100.00', '2 maund at 100 is 200, 80 kg, Hari share 100');
check(AccountModel::findSale(2)['amount'] === '30.00' && AccountModel::findSale(2)['hari_share_amount'] === '0.00', 'Seri kg sale is not converted twice and Hari share is 0');
$accounts->advance($accUser, ['farm' => 'Scenario Farm', 'hariId' => $hariId, 'reason' => 'Medical', 'amount' => 15, 'season' => 'Kharif 2026']);
$before = AccountOps::ledger($hariId, true);
check($before['gross'] === 0.0 && $before['ded'] === 0.0 && $before['advance'] === 0.0, 'final ledger is zero before approval');
$accounts->approveBulk($ownerUser, ['farm' => 'Scenario Farm']);
$after = AccountOps::ledger($hariId, true);
check($after['gross'] === 100.0, 'approved Hari sale share is 100');
check($after['ded'] === 46.75, 'approved Hari costs are 26.25 usage plus 20.50 expense');
check($after['advance'] === 15.0, 'approved advance is 15');
check($after['balance'] === 38.25, 'settlement is 100 - 46.75 - 15');
check(AccountOps::operatingExpenseTotal($farm) === 94.0, 'operating cost is 52.50 + 21.00 + 20.50 and excludes the advance');
CatalogModel::update((int)CatalogModel::findByName('Urea')['id'], 'Urea', 'per bag', 99, 0);
check(StockModel::findUsage(1)['rate_snapshot'] === '10.50' && (int)StockModel::findUsage(1)['share_snapshot'] === 50, 'later rate and share edits do not rewrite the usage');

$pdo = Database::pdo();
$started = hrtime(true);
$stmt = $pdo->prepare('SELECT SUM(amount) AS total FROM stock_usages WHERE farm_id = ? AND status = ?');
$stmt->execute([$farm, 'approved']);
$elapsedUs = (int)((hrtime(true) - $started) / 1000);
$total = $stmt->fetch()['total'];
check($total === '73.50', 'SQL sum of approved usage is 73.50');
$explain = $pdo->prepare('EXPLAIN SELECT SUM(amount) AS total FROM stock_usages WHERE farm_id = ? AND status = ?');
$explain->execute([$farm, 'approved']);
echo "EXPLAIN stock_usages farm+status ({$elapsedUs} microseconds for the SUM on this fixture):\n";
foreach ($explain->fetchAll() as $row) {
    echo '  type=' . $row['type'] . ' key=' . ($row['key'] ?? '') . ' rows=' . $row['rows'] . ' extra=' . ($row['Extra'] ?? '') . "\n";
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
