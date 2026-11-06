<?php
declare(strict_types=1);

putenv('ZAZ_TEST=1');
putenv('ZAZ_DB_NAME=zaz_agriculture_test');
require dirname(__DIR__) . '/app/Core/Autoload.php';
App\Core\Autoload::register();

use App\Core\Database;
use App\Models\StockModel;

$job = json_decode((string)file_get_contents($argv[1]), true);
$pdo = Database::pdo();
$pdo->beginTransaction();
$lock = $pdo->prepare('SELECT id FROM farms WHERE id = ? FOR UPDATE');
$lock->execute([(int)$job['farm_id']]);
file_put_contents($job['ready'], '1');
sleep(3);
StockModel::insertUsage([
    'farm_id' => (int)$job['farm_id'],
    'season_id' => (int)$job['season_id'],
    'item_name' => (string)$job['item'],
    'qty' => (float)$job['qty'],
    'rate_snapshot' => 1,
    'amount' => (float)$job['qty'],
    'target_type' => 'seri',
    'hari_id' => null,
    'crop' => 'Hold',
    'share_snapshot' => 0,
    'status' => 'approved',
    'by_label' => 'hold',
    'created_by' => (int)$job['user_id'],
    'approved_by' => (int)$job['user_id'],
    'approved_at' => date('Y-m-d H:i:s'),
]);
$pdo->commit();
