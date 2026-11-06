<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Access;
use App\Services\Totp;
use App\Services\ReadModel;
use App\Models\AccountModel;
use App\Models\AuditModel;
use App\Models\CatalogModel;
use App\Models\FarmModel;
use App\Models\HariModel;
use App\Models\SeasonModel;
use App\Models\StaffModel;
use App\Models\StockModel;
use App\Models\UserModel;
use App\Models\WorkModel;

final class Snapshot
{
    public function forUser(array $user): array
    {
        $office = Access::isOffice($user);
        $farmName = $office ? null : (string)($user['farm_name'] ?? '');
        $scopeId = $office ? null : (int)($user['farm_id'] ?? 0);
        $farms = [];
        $farmNames = [];
        foreach (FarmModel::all() as $farm) {
            if ($farmName !== null && $farm['name'] !== $farmName) {
                continue;
            }
            $farms[] = [
                'id' => (string)$farm['id'],
                'name' => $farm['name'],
                'acres' => num($farm['acres']),
                'seri' => num($farm['seri_acres']),
                'location' => $farm['location'],
                'purchase' => $farm['purchase_date'] ?: '',
                'archived' => (bool)$farm['is_archived'],
            ];
            $farmNames[(int)$farm['id']] = $farm['name'];
        }
        $seasonNames = [];
        $seasonById = [];
        foreach (SeasonModel::all() as $season) {
            $seasonNames[] = $season['name'];
            $seasonById[(int)$season['id']] = $season['name'];
        }
        $userNames = [];
        foreach (UserModel::all() as $row) {
            $userNames[(int)$row['id']] = $row['username'];
        }

        $allocations = [];
        foreach (HariModel::allocations($scopeId) as $row) {
            $allocations[(int)$row['hari_id']][] = [
                'crop' => $row['crop'],
                'acres' => num($row['acres']),
                'season' => $row['season'],
                'plotId' => (string)$row['plot_id'],
                'plotName' => $row['plot_name'],
            ];
        }
        $plotsByHari = [];
        $physical = [];
        foreach (HariModel::plotsGrouped($scopeId) as $hariId => $plots) {
            foreach ($plots as $plot) {
                $plotsByHari[$hariId][] = [
                    'id' => (string)$plot['id'],
                    'name' => $plot['name'],
                    'acres' => num($plot['acres']),
                ];
                $physical[$hariId] = num(($physical[$hariId] ?? 0) + (float)$plot['acres']);
            }
        }
        $haris = [];
        foreach (HariModel::all($scopeId) as $hari) {
            $name = $farmNames[(int)$hari['farm_id']] ?? '';
            if ($name === '' || ($farmName !== null && $name !== $farmName)) {
                continue;
            }
            $haris[] = [
                'id' => (string)$hari['id'],
                'farm' => $name,
                'name' => $hari['name'],
                'address' => $hari['address'],
                'physicalAcres' => $physical[(int)$hari['id']] ?? 0,
                'plots' => $plotsByHari[(int)$hari['id']] ?? [],
                'alloc' => $allocations[(int)$hari['id']] ?? [],
            ];
        }

        $categories = [];
        $rules = [];
        foreach (CatalogModel::all() as $cat) {
            $categories[] = [
                'id' => (string)$cat['id'],
                'name' => $cat['name'],
                'unit' => $cat['unit'],
                'rate' => num($cat['default_rate']),
                'share' => (int)$cat['hari_share'],
                'stock' => (bool)$cat['is_stock'],
            ];
            $rules[$cat['name']] = (int)$cat['hari_share'];
        }
        $itemRates = [];
        foreach (CatalogModel::ITEMS as $item) {
            $itemRates[$item] = StockModel::latestRate($item) ?? 0;
        }

        $receipts = [];
        $transfers = [];
        $usage = [];
        $expenses = [];
        $sales = [];
        $advances = [];
        $daily = [];
        $payments = [];
        $audit = [];
        $summary = (new ReadModel())->summary($user, $farmNames);
        $users = [];
        $payments = [];
        if (Access::can($user, 'admin.employees') || Access::can($user, 'admin.salaries')) {
            foreach (UserModel::all() as $row) {
                if ($user['role'] !== 'owner' && $row['role'] === 'owner') {
                    continue;
                }
                $users[] = [
                    'id' => (string)$row['id'],
                    'name' => $row['name'],
                    'username' => $row['username'],
                    'role' => $row['role'],
                    'farm' => $farmNames[(int)$row['farm_id']] ?? '',
                    'active' => (bool)$row['is_active'],
                    'salary' => num($row['salary']),
                    'payType' => $row['pay_type'],
                    'login' => true,
                ];
            }
            foreach (StaffModel::all() as $row) {
                $users[] = [
                    'id' => 'k' . $row['id'],
                    'name' => $row['name'],
                    'username' => '',
                    'role' => 'kamdar',
                    'farm' => $farmNames[(int)$row['farm_id']] ?? '',
                    'active' => (bool)$row['is_active'],
                    'salary' => num($row['salary']),
                    'payType' => $row['pay_type'],
                    'login' => false,
                ];
            }
            foreach (WorkModel::payments() as $row) {
                if ($row['user_id'] && $user['role'] !== 'owner') {
                    $target = UserModel::find((int)$row['user_id']);
                    if ($target && $target['role'] === 'owner') {
                        continue;
                    }
                }
                $payments[] = [
                    'id' => (string)$row['id'],
                    'userId' => $row['user_id'] ? (string)$row['user_id'] : 'k' . $row['staff_id'],
                    'month' => $row['pay_month'],
                    'kind' => $row['kind'],
                    'amount' => num($row['amount']),
                    'status' => $row['status'],
                    'date' => $row['pay_date'],
                    'note' => $row['note'],
                ];
            }
        }

        $audit = [];

        $settings = WorkModel::settings((int)$user['id']);
        $godown = null;
        if ($office) {
            $godown = [];
            foreach (CatalogModel::ITEMS as $item) {
                $received = StockModel::godownReceived($item);
                $sent = StockModel::godownSent($item);
                $godown[$item] = [
                    'received' => $received,
                    'sent' => $sent,
                    'available' => num($received - $sent),
                ];
            }
        }
        return [
            'version' => 2,
            'me' => [
                'id' => (string)$user['id'],
                'name' => $user['name'],
                'username' => $user['username'],
                'role' => $user['role'],
                'farm' => (string)($user['farm_name'] ?? ''),
                'active' => true,
                'salary' => num($user['salary']),
                'payType' => $user['pay_type'],
                'totpEnabled' => (int)($user['totp_enabled'] ?? 0) === 1,
            ],
            'totpAvailable' => Totp::available() && in_array($user['role'], ['owner', 'office'], true),
            'access' => Access::pages((string)$user['role']),
            'abilities' => Access::names($user),
            'summary' => $summary,
            'users' => $users,
            'farms' => $farms,
            'haris' => $haris,
            'rules' => $rules,
            'categories' => $categories,
            'receipts' => $receipts,
            'transfers' => $transfers,
            'usage' => $usage,
            'expenses' => $expenses,
            'sales' => $sales,
            'advances' => $advances,
            'audit' => $audit,
            'dailyWork' => $daily,
            'salaryPayments' => $payments,
            'settings' => ['theme' => $settings['theme'] ?: 'light', 'language' => $settings['language'] ?: 'en'],
            'seasons' => $seasonNames,
            'items' => CatalogModel::ITEMS,
            'itemRates' => $itemRates,
            'godown' => $godown,
        ];
    }

    private function moneyRow(array $row, array $farmNames, array $seasonById, string $labelKey): ?array
    {
        $name = $farmNames[(int)$row['farm_id']] ?? '';
        if ($name === '') {
            return null;
        }
        return [
            'id' => (string)$row['id'],
            'farm' => $name,
            'season' => $seasonById[(int)$row['season_id']] ?? '',
            'item' => $row[$labelKey] ?? '',
            'qty' => num($row['qty']),
            'rate' => num($row['rate_snapshot']),
            'amount' => num($row['amount']),
            'target' => $row['target_type'] === 'seri' ? 'seri' : (string)$row['hari_id'],
            'hariId' => $row['hari_id'] ? (string)$row['hari_id'] : null,
            'crop' => $row['crop'],
            'share' => (int)$row['share_snapshot'],
            'by' => $row['by_label'],
            'approved' => $row['status'] === 'approved',
        ];
    }
}
