<?php
declare(strict_types=1);

/**
 * Isolated RBAC and business-rule tests.
 * Uses database zaz_agriculture_test only. Never touches other databases.
 */
putenv('ZAZ_TEST=1');
putenv('ZAZ_DB_NAME=zaz_agriculture_test');
putenv('ZAZ_ALLOW_DEMO_RESET=1');
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require dirname(__DIR__) . '/app/Core/Autoload.php';
App\Core\Autoload::register();
require dirname(__DIR__) . '/database/migrate.php';

use App\Core\Access;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Kernel;
use App\Core\Session;
use App\Controllers\AuthController;
use App\Models\AccountModel;
use App\Models\CatalogModel;
use App\Models\FarmModel;
use App\Models\HariModel;
use App\Models\LoginAttemptModel;
use App\Models\SeasonModel;
use App\Models\StockModel;
use App\Models\UserModel;
use App\Services\AccountOps;

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

$name = (string)config()['db']['name'];
if ($name !== 'zaz_agriculture_test') {
    fwrite(STDERR, "Refusing to run tests against {$name}\n");
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

$secrets = [];
function makeUser(string $name, string $username, string $role, ?int $farmId): int
{
    global $secrets;
    $secrets[$username] = bin2hex(random_bytes(8));
    return UserModel::insert($name, $username, password_hash($secrets[$username], PASSWORD_DEFAULT), $role, $farmId, 1000, 'Monthly salary');
}

SeasonModel::insert('Kharif 2026', 0);
SeasonModel::insert('Rabi 2026-27', 1);
$farmA = FarmModel::insert('Farm A', 100, 20, 'A', null);
$farmB = FarmModel::insert('Farm B', 80, 10, 'B', null);
foreach (CatalogModel::ITEMS as $item) {
    CatalogModel::insert($item, 'per bag', 100, 50, 1);
}
CatalogModel::insert('Tractor Preparation', 'per acre', 10, 100, 0);
$owner = makeUser('Owner', 'owner', 'owner', null);
$office = makeUser('Office', 'office', 'office', null);
$supA = makeUser('Sup A', 'sup.a', 'supervisor', $farmA);
$supB = makeUser('Sup B', 'sup.b', 'supervisor', $farmB);
$accA = makeUser('Acc A', 'acc.a', 'accountant', $farmA);
$owner2 = makeUser('Owner 2', 'owner2', 'owner', null);

function kernel(): Kernel
{
    Session::resetTest();
    Database::reset();
    return new Kernel();
}
function call(Kernel $app, string $method, string $uri, ?array $json = null, bool $csrf = true): App\Core\Response
{
    $headers = ['Accept' => 'application/json'];
    $body = null;
    if ($json !== null) {
        $headers['Content-Type'] = 'application/json';
        $body = json_encode($json);
    }
    if ($csrf) {
        $headers['X-CSRF-Token'] = Csrf::token();
    }
    return $app->handle($method, $uri, $body, $headers);
}
function asUser(int $id): Kernel
{
    $app = kernel();
    $app->actingAs($id);
    return $app;
}

fwrite(STDOUT, "RBAC and direct access\n");
$guest = kernel();
$dash = $guest->handle('GET', '/dashboard');
expect($dash->status === 302 && str_contains($dash->headers['Location'] ?? '', '/login'), 'guest dashboard redirects to login');
$apiGuest = $guest->handle('GET', '/api/bootstrap', null, ['Accept' => 'application/json']);
expect($apiGuest->status === 401, 'guest API is rejected');

$app = asUser($accA);
expect($app->handle('GET', '/employees')->status === 403, 'accountant direct /employees is forbidden');
expect($app->handle('GET', '/farms')->status === 403, 'accountant direct /farms is forbidden');
expect($app->handle('GET', '/approvals')->status === 403, 'accountant direct /approvals is forbidden');
expect($app->handle('GET', '/godown')->status === 403, 'accountant direct /godown is forbidden');
expect(call($app, 'POST', '/api/expenses/create', ['farm' => 'Farm B', 'category' => 'Tractor Preparation', 'qty' => 1, 'rate' => 10, 'crop' => 'Wheat', 'season' => 'Kharif 2026', 'target' => 'seri'])->status === 403, 'accountant cross-farm expense is forbidden');
expect(call($app, 'POST', '/api/daily/create', ['farm' => 'Farm A', 'date' => date('Y-m-d'), 'type' => 'Irrigation', 'desc' => 'Water'])->status === 403, 'accountant cannot submit daily work');
expect(call($app, 'POST', '/api/transfers/send', ['farm' => 'Farm A', 'item' => 'Urea', 'qty' => 1])->status === 403, 'accountant cannot dispatch stock');

$app = asUser($supA);
expect($app->handle('GET', '/expenses')->status === 403, 'supervisor direct /expenses is forbidden');
expect($app->handle('GET', '/sales')->status === 403, 'supervisor direct /sales is forbidden');
expect($app->handle('GET', '/advances')->status === 403, 'supervisor direct /advances is forbidden');
expect($app->handle('GET', '/rates')->status === 403, 'supervisor direct /rates is forbidden');
expect(call($app, 'POST', '/api/sales/create', ['farm' => 'Farm A', 'season' => 'Kharif 2026', 'target' => 'seri', 'crop' => 'Wheat', 'unit' => 'kg', 'weight' => 2, 'rate' => 5])->status === 403, 'supervisor cannot record sales');
expect(call($app, 'POST', '/api/expenses/create', ['farm' => 'Farm A', 'category' => 'Tractor Preparation', 'qty' => 1, 'rate' => 10, 'crop' => 'Wheat', 'season' => 'Kharif 2026', 'target' => 'seri'])->status === 403, 'supervisor cannot record expenses');
expect(call($app, 'POST', '/api/advances/create', ['farm' => 'Farm A', 'hariId' => 1, 'reason' => 'x', 'amount' => 5, 'season' => 'Kharif 2026'])->status === 403, 'supervisor cannot record advances');
expect(call($app, 'POST', '/api/hari/create', ['farm' => 'Farm A', 'name' => 'X', 'address' => '', 'acres' => 1, 'crop' => 'Wheat', 'season' => 'Kharif 2026'])->status === 403, 'supervisor cannot create Hari');
expect(call($app, 'POST', '/api/transfers/send', ['farm' => 'Farm A', 'item' => 'Urea', 'qty' => 1])->status === 403, 'supervisor cannot send stock');
expect(call($app, 'POST', '/api/usage/create', ['farm' => 'Farm B', 'season' => 'Kharif 2026', 'item' => 'Urea', 'qty' => 1, 'target' => 'seri', 'crop' => 'Seri'])->status === 403, 'supervisor cross-farm usage is forbidden');

$pages = [
    'owner' => Access::pages('owner'),
    'office' => Access::pages('office'),
    'supervisor' => Access::pages('supervisor'),
    'accountant' => Access::pages('accountant'),
];
expect(!in_array('expenses', $pages['supervisor'], true) && in_array('usage', $pages['supervisor'], true) && in_array('daily', $pages['supervisor'], true), 'supervisor navigation matches field scope');
expect(in_array('expenses', $pages['accountant'], true) && in_array('sales', $pages['accountant'], true) && !in_array('godown', $pages['accountant'], true), 'accountant navigation includes accounts and excludes office admin');
expect($pages['owner'] === $pages['office'], 'owner and office share the same page list');
expect(!Access::can(['role' => 'office'], 'owner.manage') && Access::can(['role' => 'owner'], 'owner.manage'), 'only owner can manage owner records');

fwrite(STDOUT, "CSRF, login, owner protections\n");
$app = asUser($owner);
$noCsrf = call($app, 'POST', '/api/settings/prefs', ['theme' => 'dark', 'language' => 'en'], false);
expect($noCsrf->status === 419, 'missing CSRF token is rejected');

$page = kernel()->handle('GET', '/login');
expect($page->status === 200 && !str_contains($page->body, '<select') && !str_contains($page->body, 'sup.a') && str_contains($page->body, 'autocomplete="username"') && str_contains($page->body, 'autocomplete="current-password"'), 'login form is username and password with no account list');
$missingCsrf = kernel();
$beforeRegen = Session::regenerations();
$csrfFail = $missingCsrf->handle('POST', '/login', http_build_query([
    'username' => 'owner',
    'password' => $secrets['owner'],
]), ['Content-Type' => 'application/x-www-form-urlencoded']);
expect($csrfFail->status === 419 && Session::get('user_id') === null && Session::regenerations() === $beforeRegen, 'login without CSRF does not start a session');

$login = kernel();
$token = Csrf::token();
$bad = $login->handle('POST', '/login', http_build_query([
    'username' => 'owner',
    'password' => 'wrong-password',
    'role' => 'owner',
    'id' => (string)$owner,
    '_csrf' => $token,
]), ['Content-Type' => 'application/x-www-form-urlencoded']);
$wrongPassword = Session::pullFlash('error');
expect($bad->status === 302 && str_contains($bad->headers['Location'] ?? '', '/login') && Session::get('user_id') === null, 'wrong password does not sign in');
$unknown = kernel();
$unknown->handle('POST', '/login', http_build_query([
    'username' => 'not-a-user',
    'password' => 'wrong-password',
    '_csrf' => Csrf::token(),
]), ['Content-Type' => 'application/x-www-form-urlencoded']);
$wrongUser = Session::pullFlash('error');
expect($wrongPassword === AuthController::GENERIC_ERROR && $wrongUser === $wrongPassword, 'unknown username and wrong password share one error');
UserModel::setActive($accA, 0);
$disabled = kernel();
$disabled->handle('POST', '/login', http_build_query([
    'username' => 'acc.a',
    'password' => $secrets['acc.a'],
    '_csrf' => Csrf::token(),
]), ['Content-Type' => 'application/x-www-form-urlencoded']);
expect(Session::pullFlash('error') === AuthController::GENERIC_ERROR && Session::get('user_id') === null, 'disabled account gets the same generic failure');
UserModel::setActive($accA, 1);

foreach ([
    ['owner', 'owner', ''],
    ['office', 'office', ''],
    ['sup.a', 'supervisor', 'Farm A'],
    ['acc.a', 'accountant', 'Farm A'],
] as [$loginName, $role, $farm]) {
    $app = kernel();
    $before = Session::regenerations();
    $res = $app->handle('POST', '/login', http_build_query([
        'username' => strtoupper($loginName),
        'password' => $secrets[$loginName],
        'role' => 'owner',
        'farm' => 'Farm B',
        'id' => (string)$owner,
        '_csrf' => Csrf::token(),
    ]), ['Content-Type' => 'application/x-www-form-urlencoded']);
    $boot = json_decode($app->handle('GET', '/api/bootstrap', null, ['Accept' => 'application/json'])->body, true);
    $me = $boot['db']['me'] ?? [];
    $scopeOk = $role === 'owner' || $role === 'office'
        ? count($boot['db']['farms'] ?? []) > 1
        : ($me['farm'] ?? '') === $farm && ($boot['db']['farms'][0]['name'] ?? '') === $farm;
    expect($res->status === 302 && str_contains($res->headers['Location'] ?? '', '/dashboard') && ($me['role'] ?? '') === $role && $scopeOk && Session::regenerations() === $before + 1, $role . ' signs in from username and keeps stored scope');
}
$dup = asUser($owner);
$taken = call($dup, 'POST', '/api/employees/create', [
    'name' => 'Copy', 'username' => 'OWNER', 'role' => 'office', 'salary' => 0, 'payType' => 'Monthly salary',
]);
expect($taken->status === 422, 'username uniqueness ignores case');
$made = call($dup, 'POST', '/api/employees/create', [
    'name' => 'Case User', 'username' => 'Case.User', 'role' => 'office', 'salary' => 0, 'payType' => 'Monthly salary',
]);
$madeBody = json_decode($made->body, true);
$temp = (string)($madeBody['temporaryPassword'] ?? '');
expect($made->status === 200 && UserModel::findByUsername('case.user') !== null, 'new usernames are stored in lowercase');
$renameClash = call($dup, 'POST', '/api/employees/username', ['id' => (string)UserModel::findByUsername('case.user')['id'], 'username' => 'Office']);
expect($renameClash->status === 422, 'renaming onto an existing username is rejected');
$renamed = call($dup, 'POST', '/api/employees/username', ['id' => (string)UserModel::findByUsername('case.user')['id'], 'username' => 'case.user2']);
expect($renamed->status === 200 && UserModel::findByUsername('case.user') === null, 'username can be edited when it is unique');
$samePass = kernel();
$same = $samePass->handle('POST', '/login', http_build_query([
    'username' => 'CASE.USER2',
    'password' => $temp,
    '_csrf' => Csrf::token(),
]), ['Content-Type' => 'application/x-www-form-urlencoded']);
expect($temp !== '' && $same->status === 302 && (Session::get('user_id') !== null), 'renamed account keeps its existing password');
LoginAttemptModel::clear('127.0.0.1');
for ($i = 0; $i < LoginAttemptModel::LIMIT; $i++) {
    LoginAttemptModel::add('127.0.0.1');
}
$throttled = kernel();
$blocked = $throttled->handle('POST', '/login', http_build_query([
    'username' => 'owner',
    'password' => $secrets['owner'],
    '_csrf' => Csrf::token(),
]), ['Content-Type' => 'application/x-www-form-urlencoded']);
expect($blocked->status === 302 && Session::get('user_id') === null && Session::pullFlash('error') === AuthController::GENERIC_ERROR, 'too many attempts uses the same generic failure');
LoginAttemptModel::clear('127.0.0.1');

$app = asUser($office);
$createOwner = call($app, 'POST', '/api/employees/create', [
    'name' => 'Third', 'username' => 'third.owner', 'role' => 'owner', 'salary' => 0, 'payType' => 'Monthly salary',
]);
expect($createOwner->status === 403, 'office cannot create an owner');
$disable = call($app, 'POST', '/api/employees/status', ['id' => (string)$owner]);
expect($disable->status === 403, 'office cannot disable an owner');
$app = asUser($owner);
$self = call($app, 'POST', '/api/employees/status', ['id' => (string)$owner]);
expect($self->status === 422, 'user cannot disable self');
$other = call($app, 'POST', '/api/employees/status', ['id' => (string)$owner2]);
expect($other->status === 200 && UserModel::countActiveOwners() === 1, 'owner can disable another owner and one active owner remains');
$last = call($app, 'POST', '/api/employees/status', ['id' => (string)$owner]);
expect($last->status === 422 && UserModel::countActiveOwners() === 1, 'the remaining owner cannot be disabled');

fwrite(STDOUT, "Stock, rates, acres, ledger, sales\n");
$app = asUser($office);
expect(call($app, 'POST', '/api/godown/receive', ['item' => 'Urea', 'qty' => 100, 'rate' => 10, 'date' => '2026-11-01'])->status === 200, 'office receives godown stock');
expect(call($app, 'POST', '/api/transfers/send', ['farm' => 'Farm A', 'item' => 'Urea', 'qty' => 20])->status === 200, 'office dispatches 20');
expect(StockModel::godownQty('Urea') === 80.0, 'godown fell by the sent quantity');
$app = asUser($office);
expect(call($app, 'POST', '/api/transfers/receive', ['id' => 1, 'qty' => 18])->status === 403, 'office cannot confirm farm receipt');
$app = asUser($accA);
expect(call($app, 'POST', '/api/transfers/receive', ['id' => 1, 'qty' => 18])->status === 403, 'accountant cannot confirm farm receipt');
$app = asUser($supB);
expect(call($app, 'POST', '/api/transfers/receive', ['id' => 1, 'qty' => 18])->status === 403, 'other farm supervisor cannot receive it');
$app = asUser($supA);
$recv = call($app, 'POST', '/api/transfers/receive', ['id' => 1, 'qty' => 18]);
expect($recv->status === 200, 'assigned supervisor records 18 received');
$row = StockModel::findTransfer(1);
expect($row && (float)$row['qty_sent'] === 20.0 && (float)$row['qty_received'] === 18.0, 'discrepancy 20 sent / 18 received is stored');
expect(AccountModel::countExpenses() === 0, 'transfer did not create an expense');
$again = call($app, 'POST', '/api/transfers/receive', ['id' => 1, 'qty' => 18]);
expect($again->status === 409, 'receiving the same transfer again is rejected');
expect(StockModel::farmQty($farmA, 'Urea') === 18.0, 'farm stock increased by received quantity only');

$use = call($app, 'POST', '/api/usage/create', [
    'farm' => 'Farm A', 'season' => 'Kharif 2026', 'item' => 'Urea', 'qty' => 5, 'target' => 'seri', 'crop' => 'Seri',
]);
expect($use->status === 200, 'supervisor records usage');
$usage = StockModel::findUsage(1);
expect($usage && (float)$usage['rate_snapshot'] === 10.0 && (int)$usage['share_snapshot'] === 0 && $usage['status'] === 'pending', 'usage snapshots office rate, Seri share is 0, and farm entry stays pending');
expect(StockModel::farmQty($farmA, 'Urea') === 13.0, 'usage reduced farm stock once');
$over = call($app, 'POST', '/api/usage/create', [
    'farm' => 'Farm A', 'season' => 'Kharif 2026', 'item' => 'Urea', 'qty' => 100, 'target' => 'seri', 'crop' => 'Seri',
]);
expect($over->status === 422, 'usage cannot drive stock negative');

$app = asUser($office);
$approve = call($app, 'POST', '/api/approvals/one', ['type' => 'usage', 'id' => 1]);
expect($approve->status === 200 && StockModel::farmQty($farmA, 'Urea') === 13.0, 'approval does not deduct stock');
$approve2 = call($app, 'POST', '/api/approvals/one', ['type' => 'usage', 'id' => 1]);
expect($approve2->status === 200 && StockModel::farmQty($farmA, 'Urea') === 13.0 && StockModel::findUsage(1)['status'] === 'approved', 'repeated approval is idempotent');

$app = asUser($accA);
$hari = call($app, 'POST', '/api/hari/create', [
    'farm' => 'Farm A', 'name' => 'Ali', 'address' => 'Goth', 'acres' => 10, 'crop' => 'Wheat', 'season' => 'Kharif 2026',
]);
expect($hari->status === 200, 'accountant creates Hari');
$hariId = (int)HariModel::all()[0]['id'];
$plotId = (int)HariModel::plotsFor($hariId)[0]['id'];
$reuse = call($app, 'POST', '/api/hari/allocate', [
    'hariId' => $hariId, 'plotId' => $plotId, 'crop' => 'Mustard', 'acres' => 10, 'season' => 'Rabi 2026-27',
]);
expect($reuse->status === 200 && HariModel::physicalAcres($hariId) === 10.0, 'reusing a plot for another season does not double-count acres');
$tooMuch = call($app, 'POST', '/api/hari/allocate', [
    'hariId' => $hariId, 'plotId' => 0, 'crop' => 'Cotton', 'acres' => 16, 'season' => 'Kharif 2026',
]);
expect($tooMuch->status === 422 && HariModel::physicalAcres($hariId) === 10.0, 'Hari cannot exceed 25 physical acres');
$app = asUser($office);
$shrink = call($app, 'POST', '/api/farms/save', ['id' => $farmA, 'acres' => 22, 'seri' => 10, 'location' => 'A', 'purchase' => '']);
expect($shrink->status === 200, 'farm acreage can shrink while existing plots still fit');
$app = asUser($accA);
$badFarm = call($app, 'POST', '/api/hari/create', [
    'farm' => 'Farm A', 'name' => 'Too Big', 'address' => '', 'acres' => 15, 'crop' => 'Wheat', 'season' => 'Kharif 2026',
]);
expect($badFarm->status === 422, 'Hari plots cannot exceed farm hari land');
$app = asUser($office);
$badAcres = call($app, 'POST', '/api/farms/save', ['id' => $farmA, 'acres' => 30, 'seri' => 40, 'location' => 'A', 'purchase' => '']);
expect($badAcres->status === 422, 'Seri acres cannot exceed total acres');

$app = asUser($accA);
$expense = call($app, 'POST', '/api/expenses/create', [
    'farm' => 'Farm A', 'category' => 'Tractor Preparation', 'qty' => 2, 'rate' => 10, 'crop' => 'Wheat', 'season' => 'Kharif 2026', 'target' => (string)$hariId,
]);
expect($expense->status === 200, 'accountant records expense');
$expRow = AccountModel::findExpense(1);
expect($expRow && (float)$expRow['rate_snapshot'] === 10.0 && (float)$expRow['amount'] === 20.0 && $expRow['status'] === 'pending', 'expense amount is qty x rate and stays pending');
$app = asUser($office);
call($app, 'POST', '/api/rates/save', ['id' => CatalogModel::findByName('Tractor Preparation')['id'], 'name' => 'Tractor Preparation', 'unit' => 'per acre', 'rate' => 99, 'share' => 50]);
$expRow = AccountModel::findExpense(1);
expect((float)$expRow['rate_snapshot'] === 10.0 && (int)$expRow['share_snapshot'] === 100, 'changing a category rate does not rewrite history');
$app = asUser($accA);
$seriExp = call($app, 'POST', '/api/expenses/create', [
    'farm' => 'Farm A', 'category' => 'Tractor Preparation', 'qty' => 1, 'rate' => 99, 'crop' => 'Seri', 'season' => 'Kharif 2026', 'target' => 'seri',
]);
expect($seriExp->status === 200 && (int)AccountModel::findExpense(2)['share_snapshot'] === 0 && (float)AccountModel::findExpense(2)['rate_snapshot'] === 99.0, 'new expense uses the new rate and Seri share stays 0');

$forged = call($app, 'POST', '/api/sales/create', [
    'farm' => 'Farm A', 'season' => 'Kharif 2026', 'target' => (string)$hariId, 'crop' => 'Wheat', 'unit' => 'maund', 'weight' => 2, 'rate' => 50, 'amount' => 1,
]);
expect($forged->status === 200, 'sale is accepted');
$sale = AccountModel::findSale(1);
expect($sale && (float)$sale['amount'] === 100.0 && (float)$sale['kg'] === 80.0 && (float)$sale['hari_share_amount'] === 50.0, 'maund sale is weight x rate, kg is weight x 40, forged amount ignored');
$kgSale = call($app, 'POST', '/api/sales/create', [
    'farm' => 'Farm A', 'season' => 'Kharif 2026', 'target' => 'seri', 'crop' => 'Wheat', 'unit' => 'kg', 'weight' => 10, 'rate' => 4, 'amount' => 9999,
]);
$kgRow = AccountModel::findSale(2);
expect($kgSale->status === 200 && (float)$kgRow['amount'] === 40.0 && (float)$kgRow['kg'] === 10.0 && (float)$kgRow['hari_share_amount'] === 0.0, 'kg sale is not converted twice and Seri hari share is 0');

$adv = call($app, 'POST', '/api/advances/create', [
    'farm' => 'Farm A', 'hariId' => $hariId, 'reason' => 'Medical', 'amount' => 15, 'season' => 'Kharif 2026',
]);
expect($adv->status === 200, 'advance recorded');
$operating = AccountOps::operatingExpenseTotal($farmA);
expect($operating === num(5 * 10 + 20 + 99), 'advances are excluded from farm operating expense');
$preview = AccountOps::ledger($hariId, false);
$final = AccountOps::ledger($hariId, true);
expect($preview['advance'] === 15.0 && $final['advance'] === 0.0, 'final ledger excludes unapproved advances');
expect($preview['gross'] === 50.0 && $final['gross'] === 0.0, 'final ledger excludes unapproved sale share');
$app = asUser($office);
call($app, 'POST', '/api/approvals/bulk', ['farm' => 'Farm A']);
$final = AccountOps::ledger($hariId, true);
expect($final['gross'] === 50.0 && $final['ded'] === 20.0 && $final['advance'] === 15.0 && $final['balance'] === 15.0, 'approved settlement is sale share minus costs minus advances');
$app = asUser($office);
$officeSale = call($app, 'POST', '/api/sales/create', [
    'farm' => 'Farm B', 'season' => 'Kharif 2026', 'target' => 'seri', 'crop' => 'Wheat', 'unit' => 'kg', 'weight' => 1, 'rate' => 5,
]);
expect($officeSale->status === 200 && AccountModel::findSale(3)['status'] === 'approved', 'office entries are auto-approved');

$app = asUser($supA);
$boot = json_decode($app->handle('GET', '/api/bootstrap', null, ['Accept' => 'application/json'])->body, true);
expect(isset($boot['db']) && !str_contains(json_encode($boot['db']), 'password'), 'bootstrap payload does not contain passwords');
$farms = array_column($boot['db']['farms'], 'name');
expect($farms === ['Farm A'], 'supervisor bootstrap is limited to the assigned farm');
expect(in_array('usage', $boot['db']['access'], true) && !in_array('expenses', $boot['db']['access'], true), 'supervisor access list hides expense pages');

try {
    Database::pdo()->exec('DELETE FROM audit_log WHERE id = 1');
    expect(false, 'audit log delete is rejected by the database');
} catch (Throwable $e) {
    expect(str_contains($e->getMessage(), 'append-only'), 'audit log delete is rejected by the database');
}

fwrite(STDOUT, "\n{$pass} passed, {$fail} failed\n");
exit($fail > 0 ? 1 : 0);
