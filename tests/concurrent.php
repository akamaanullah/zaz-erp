<?php
declare(strict_types=1);

putenv('ZAZ_TEST=1');
putenv('ZAZ_DB_NAME=zaz_agriculture_test');
require dirname(__DIR__) . '/app/Core/Autoload.php';
App\Core\Autoload::register();

use App\Core\Database;
use App\Core\HttpException;
use App\Models\FarmModel;
use App\Models\SeasonModel;
use App\Models\StockModel;
use App\Models\UserModel;
use App\Services\StockOps;

if ((string)config()['db']['name'] !== 'zaz_agriculture_test') {
    fwrite(STDERR, "Refusing concurrent test\n");
    exit(1);
}

$pass = 0;
$fail = 0;
function expect(bool $cond, string $message): void
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        fwrite(STDOUT, "  OK  {$message}\n");
        return;
    }
    $fail++;
    fwrite(STDOUT, "  FAIL {$message}\n");
}

$before = (int)Database::pdo()->query('SELECT COUNT(*) c FROM godown_receipts')->fetch()['c'];
try {
    Database::transaction(function (): void {
        StockModel::insertReceipt('Urea', 1, 1, date('Y-m-d'), 1);
        throw new RuntimeException('stop');
    });
    expect(false, 'partial write rolls back');
} catch (RuntimeException $e) {
    $after = (int)Database::pdo()->query('SELECT COUNT(*) c FROM godown_receipts')->fetch()['c'];
    expect($after === $before && $e->getMessage() === 'stop', 'partial write rolls back');
}

$farm = FarmModel::findByName('Farm A');
$owner = UserModel::findByUsername('owner');
$season = SeasonModel::findByName('Kharif 2026');
if (!$farm || !$owner || !$season) {
    fwrite(STDERR, "Test database is missing Farm A. Run php tests/run.php first.\n");
    exit(1);
}
$left = StockModel::farmQty((int)$farm['id'], 'Zinc');
if ($left < 1) {
    $id = StockModel::insertTransfer((int)$farm['id'], 'Zinc', 4, date('Y-m-d'), (int)$owner['id']);
    StockModel::markReceived($id, 4, (int)$owner['id']);
    $left = StockModel::farmQty((int)$farm['id'], 'Zinc');
}
$ready = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'zaz-hold-ready-' . getmypid();
$job = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'zaz-hold-job-' . getmypid() . '.json';
@unlink($ready);
file_put_contents($job, json_encode([
    'farm_id' => (int)$farm['id'],
    'season_id' => (int)$season['id'],
    'user_id' => (int)$owner['id'],
    'item' => 'Zinc',
    'qty' => $left,
    'ready' => $ready,
]));
$php = PHP_BINARY;
$cmd = escapeshellarg($php) . ' ' . escapeshellarg(__DIR__ . '/concurrent-hold.php') . ' ' . escapeshellarg($job);
$proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
$deadline = microtime(true) + 8;
while (!is_file($ready) && microtime(true) < $deadline) {
    usleep(50000);
}
$started = microtime(true);
$blocked = false;
try {
    (new StockOps())->useStock($owner, [
        'farm' => 'Farm A',
        'item' => 'Zinc',
        'qty' => 1,
        'crop' => 'Race',
        'season' => 'Kharif 2026',
        'target' => 'seri',
    ]);
    expect(false, 'second stock use is rejected after the locked use takes the remainder');
} catch (HttpException $e) {
    $waited = (microtime(true) - $started) * 1000;
    $blocked = $waited > 500;
    expect($e->status === 422 && str_contains($e->getMessage(), 'Insufficient'), 'second stock use is rejected after the locked use takes the remainder');
    fwrite(STDOUT, '  measured lock wait ' . round($waited) . " ms\n");
}
if (!$blocked) {
    expect(false, 'the second connection waited on the farm row lock');
} else {
    expect(true, 'the second connection waited on the farm row lock');
}
proc_close($proc);
@unlink($ready);
@unlink($job);
$final = StockModel::farmQty((int)$farm['id'], 'Zinc');
expect($final < 1, 'farm stock is not negative after the overlapping requests');

fwrite(STDOUT, "\n{$pass} passed, {$fail} failed\n");
exit($fail > 0 ? 1 : 0);
