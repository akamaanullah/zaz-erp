<?php
declare(strict_types=1);

putenv('ZAZ_QUERY_COUNT=1');
putenv('ZAZ_DB_NAME=zaz_agriculture_perf');
putenv('ZAZ_APP_ENV=local');
putenv('ZAZ_TEST=1');
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require dirname(__DIR__, 2) . '/app/Core/Autoload.php';
App\Core\Autoload::register();

use App\Core\Database;
use App\Core\Kernel;
use App\Models\UserModel;
use App\Services\ReadModel;
use App\Services\Snapshot;

if ((string)config()['db']['name'] !== 'zaz_agriculture_perf') {
    fwrite(STDERR, "Refusing to measure a database that is not zaz_agriculture_perf\n");
    exit(1);
}

$owner = UserModel::findByUsername('perf.owner');
$supervisor = UserModel::findByUsername('perf.sup');
if (!$owner || !$supervisor) {
    fwrite(STDERR, "Run tests/perf/generate.php first\n");
    exit(1);
}
$farm = Database::pdo()->query('SELECT id, name FROM farms ORDER BY id LIMIT 1')->fetch();
$pdo = Database::pdo();

$explains = [];
$plans = [
    'usage_page_all' => 'EXPLAIN SELECT x.id, f.name FROM stock_usages x JOIN farms f ON f.id = x.farm_id LEFT JOIN seasons s ON s.id = x.season_id ORDER BY x.id DESC LIMIT 25',
    'usage_page_farm' => 'EXPLAIN SELECT x.id, f.name FROM stock_usages x JOIN farms f ON f.id = x.farm_id LEFT JOIN seasons s ON s.id = x.season_id WHERE x.farm_id = ' . (int)$farm['id'] . ' ORDER BY x.id DESC LIMIT 25',
    'usage_sum_status' => 'EXPLAIN SELECT SUM(amount) FROM stock_usages WHERE farm_id = ' . (int)$farm['id'] . " AND status = 'approved'",
    'expense_sum_group' => 'EXPLAIN SELECT farm_id, SUM(amount) FROM expenses GROUP BY farm_id',
    'sales_sum_group' => 'EXPLAIN SELECT farm_id, SUM(amount) FROM sales GROUP BY farm_id',
];
foreach ($plans as $name => $sql) {
    $explains[$name] = array_map(static fn (array $plan): array => [
        'table' => $plan['table'] ?? '',
        'type' => $plan['type'] ?? '',
        'key' => $plan['key'] ?? '',
        'rows' => $plan['rows'] ?? '',
        'extra' => $plan['Extra'] ?? '',
    ], $pdo->query($sql)->fetchAll());
}
$sum = $pdo->prepare('SELECT SUM(amount) FROM stock_usages WHERE farm_id = ? AND status = ?');
$samples = [];
for ($i = 0; $i < 5; $i++) {
    $started = microtime(true);
    $sum->execute([(int)$farm['id'], 'approved']);
    $sum->fetch();
    $samples[] = round((microtime(true) - $started) * 1000, 3);
}
sort($samples);
$outSum = ['samples_ms' => $samples, 'median_ms' => $samples[2]];

$reader = new ReadModel();
$out = ['database' => 'zaz_agriculture_perf', 'explain' => $explains, 'approved_usage_sum' => $outSum, 'requests' => []];

$call = static function (string $label, int $userId, string $uri) use (&$out): void {
    Database::resetQueryCount();
    $kernel = new Kernel();
    $kernel->actingAs($userId);
    $started = microtime(true);
    $response = $kernel->handle('GET', $uri, null, ['Accept' => 'application/json']);
    $ms = round((microtime(true) - $started) * 1000, 2);
    $out['requests'][$label] = [
        'status' => $response->status,
        'bytes' => strlen($response->body),
        'ms' => $ms,
        'queries' => Database::queryCount(),
    ];
};

$call('owner_bootstrap', (int)$owner['id'], '/api/bootstrap');
$call('supervisor_bootstrap', (int)$supervisor['id'], '/api/bootstrap');
$call('usage_page1_all', (int)$owner['id'], '/api/table?resource=usage&page=1&per=25');
$call('usage_page2_all', (int)$owner['id'], '/api/table?resource=usage&page=2&per=25');
$call('usage_page1_farm', (int)$owner['id'], '/api/table?resource=usage&farm=' . rawurlencode($farm['name']) . '&page=1&per=25');
$call('usage_page1_supervisor', (int)$supervisor['id'], '/api/table?resource=usage&page=1&per=25');
$call('approvals_page1', (int)$owner['id'], '/api/table?resource=approvals&page=1&per=25');
$call('expenses_page1', (int)$owner['id'], '/api/table?resource=expenses&page=1&per=25');

Database::resetQueryCount();
$started = microtime(true);
$farms = [];
foreach ($pdo->query('SELECT id, name FROM farms') as $row) {
    $farms[(int)$row['id']] = $row['name'];
}
$summary = (new Snapshot())->forUser($owner);
$out['owner_snapshot_keys'] = [
    'usage' => count($summary['usage'] ?? []),
    'expenses' => count($summary['expenses'] ?? []),
    'sales' => count($summary['sales'] ?? []),
    'advances' => count($summary['advances'] ?? []),
    'transfers' => count($summary['transfers'] ?? []),
    'audit' => count($summary['audit'] ?? []),
    'haris' => count($summary['haris'] ?? []),
    'summary_farms' => count($summary['summary']['byFarm'] ?? []),
];
$out['owner_snapshot_build_ms'] = round((microtime(true) - $started) * 1000, 2);
$out['owner_snapshot_queries_including_name_lookup'] = Database::queryCount();

$page1 = $reader->table($owner, ['resource' => 'usage', 'page' => 1, 'per' => 25]);
$page2 = $reader->table($owner, ['resource' => 'usage', 'page' => 2, 'per' => 25]);
$scoped = $reader->table($supervisor, ['resource' => 'usage', 'farm' => 'Perf Farm 99', 'page' => 1, 'per' => 25]);
$out['totals_page1'] = $page1['totals'];
$out['totals_page2'] = $page2['totals'];
$out['totals_match_across_pages'] = $page1['totals'] == $page2['totals'];
$out['page1_rows'] = count($page1['rows']);
$out['page2_rows'] = count($page2['rows']);
$out['usage_total_rows'] = $page1['total'];
$out['supervisor_forged_farm_rows_belong_to'] = array_values(array_unique(array_column($scoped['rows'], 'farm')));
$out['supervisor_total'] = $scoped['total'];

fwrite(STDOUT, json_encode($out, JSON_UNESCAPED_SLASHES) . PHP_EOL);
