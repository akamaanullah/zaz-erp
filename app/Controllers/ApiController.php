<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AccountOps;
use App\Services\FarmOps;
use App\Services\OfficeOps;
use App\Services\PeopleOps;
use App\Services\Snapshot;
use App\Services\StockOps;

final class ApiController extends Controller
{
    public function bootstrap(Request $request): Response
    {
        return $this->json(['ok' => true, 'db' => (new Snapshot())->forUser($this->user())]);
    }

    public function backup(Request $request): Response
    {
        $payload = (new OfficeOps())->export($this->user());
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}';
        return new Response(200, $body, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="zaz-erp-backup.json"',
        ]);
    }

    public function post(Request $request): Response
    {
        $action = (string)($request->params['action'] ?? '');
        $user = $this->user();
        $in = $request->json ?: $request->post;
        $extra = [];
        switch ($action) {
            case 'farms/save':
                (new FarmOps())->save($user, $in);
                break;
            case 'farms/archive':
                (new FarmOps())->archive($user, $in);
                break;
            case 'employees/create':
                $password = (new PeopleOps())->createEmployee($user, $in);
                if ($password !== '') {
                    $extra['temporaryPassword'] = $password;
                }
                break;
            case 'employees/status':
                (new PeopleOps())->setStatus($user, $in);
                break;
            case 'employees/farm':
                (new PeopleOps())->setFarm($user, $in);
                break;
            case 'employees/username':
                (new PeopleOps())->setUsername($user, $in);
                break;
            case 'employees/salary':
                (new PeopleOps())->setSalary($user, $in);
                break;
            case 'hari/create':
                (new PeopleOps())->createHari($user, $in);
                break;
            case 'hari/allocate':
                (new PeopleOps())->allocate($user, $in);
                break;
            case 'godown/receive':
                (new StockOps())->receiveGodown($user, $in);
                break;
            case 'transfers/send':
                (new StockOps())->send($user, $in);
                break;
            case 'transfers/receive':
                (new StockOps())->receive($user, $in);
                break;
            case 'usage/create':
                (new StockOps())->useStock($user, $in);
                break;
            case 'expenses/create':
                (new AccountOps())->expense($user, $in);
                break;
            case 'sales/create':
                (new AccountOps())->sale($user, $in);
                break;
            case 'advances/create':
                (new AccountOps())->advance($user, $in);
                break;
            case 'approvals/one':
                $extra['changed'] = (new AccountOps())->approveOne($user, $in);
                break;
            case 'approvals/bulk':
                $extra['count'] = (new AccountOps())->approveBulk($user, $in);
                break;
            case 'rates/save':
                (new OfficeOps())->saveCategory($user, $in);
                break;
            case 'rates/delete':
                (new OfficeOps())->deleteCategory($user, $in);
                break;
            case 'rules/update':
                (new OfficeOps())->updateRule($user, $in);
                break;
            case 'daily/create':
                (new OfficeOps())->daily($user, $in);
                break;
            case 'salaries/pay':
                (new OfficeOps())->pay($user, $in);
                break;
            case 'salaries/status':
                (new OfficeOps())->togglePay($user, $in);
                break;
            case 'settings/prefs':
                (new OfficeOps())->prefs($user, $in);
                break;
            case 'settings/import':
                (new OfficeOps())->import($user, $in);
                break;
            case 'settings/reset':
                (new OfficeOps())->reset($user);
                break;
            default:
                throw new HttpException(404, 'Unknown action');
        }
        $fresh = $this->kernel->authUser() ?? $user;
        return $this->json(['ok' => true, 'db' => (new Snapshot())->forUser($fresh)] + $extra);
    }
}
