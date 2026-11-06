<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Access;
use App\Services\Totp;
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
        if (Access::can($user, 'admin.godown')) {
            foreach (StockModel::receipts() as $row) {
                $receipts[] = [
                    'id' => (string)$row['id'],
                    'item' => $row['item_name'],
                    'qty' => num($row['qty']),
                    'rate' => num($row['rate']),
                    'date' => $row['received_on'],
                ];
            }
        }

        $transfers = [];
        foreach (StockModel::transfers($scopeId) as $row) {
            $name = $farmNames[(int)$row['farm_id']] ?? '';
            if ($name === '' || ($farmName !== null && $name !== $farmName)) {
                continue;
            }
            $transfers[] = [
                'id' => (string)$row['id'],
                'farm' => $name,
                'item' => $row['item_name'],
                'qty' => num($row['qty_sent']),
                'received' => $row['qty_received'] === null ? null : num($row['qty_received']),
                'date' => $row['sent_on'],
                'receivedBy' => $row['received_by'] ? ($userNames[(int)$row['received_by']] ?? '') : '',
                'receivedAt' => $row['received_at'] ? date('Y-m-d H:i', strtotime((string)$row['received_at'])) : '',
            ];
        }

        $usage = [];
        foreach (StockModel::usages($scopeId) as $row) {
            $mapped = $this->moneyRow($row, $farmNames, $seasonById, 'item_name');
            if ($mapped === null || ($farmName !== null && $mapped['farm'] !== $farmName)) {
                continue;
            }
            $usage[] = $mapped;
        }
        $expenses = [];
        foreach (AccountModel::expenses($scopeId) as $row) {
            $mapped = $this->moneyRow($row, $farmNames, $seasonById, 'category_name');
            if ($mapped === null || ($farmName !== null && $mapped['farm'] !== $farmName)) {
                continue;
            }
            $mapped['category'] = $row['category_name'];
            $mapped['unit'] = $row['unit_snapshot'];
            $expenses[] = $mapped;
        }
        $sales = [];
        foreach (AccountModel::sales($scopeId) as $row) {
            $name = $farmNames[(int)$row['farm_id']] ?? '';
            if ($name === '' || ($farmName !== null && $name !== $farmName)) {
                continue;
            }
            $sales[] = [
                'id' => (string)$row['id'],
                'farm' => $name,
                'season' => $seasonById[(int)$row['season_id']] ?? '',
                'target' => $row['target_type'] === 'seri' ? 'seri' : (string)$row['hari_id'],
                'hariId' => $row['hari_id'] ? (string)$row['hari_id'] : null,
                'crop' => $row['crop'],
                'unit' => $row['weight_unit'],
                'weight' => num($row['weight']),
                'rate' => num($row['rate_snapshot']),
                'kg' => num($row['kg']),
                'amount' => num($row['amount']),
                'by' => $row['by_label'],
                'approved' => $row['status'] === 'approved',
            ];
        }
        $advances = [];
        foreach (AccountModel::advances($scopeId) as $row) {
            $name = $farmNames[(int)$row['farm_id']] ?? '';
            if ($name === '' || ($farmName !== null && $name !== $farmName)) {
                continue;
            }
            $advances[] = [
                'id' => (string)$row['id'],
                'farm' => $name,
                'season' => $seasonById[(int)$row['season_id']] ?? '',
                'hariId' => (string)$row['hari_id'],
                'reason' => $row['reason'],
                'amount' => num($row['amount']),
                'by' => $row['by_label'],
                'approved' => $row['status'] === 'approved',
            ];
        }

        $daily = [];
        foreach (WorkModel::daily($scopeId) as $row) {
            $name = $farmNames[(int)$row['farm_id']] ?? '';
            if ($name === '' || ($farmName !== null && $name !== $farmName)) {
                continue;
            }
            $daily[] = [
                'id' => (string)$row['id'],
                'farm' => $name,
                'date' => $row['work_date'],
                'at' => date('c', strtotime((string)$row['occurred_at'])),
                'type' => $row['work_type'],
                'desc' => $row['description'],
                'resources' => $row['resources'],
                'acres' => $row['acres'] === null ? null : num($row['acres']),
                'hours' => $row['hours'] === null ? null : num($row['hours']),
                'notes' => $row['notes'],
                'by' => $userNames[(int)$row['created_by']] ?? '',
            ];
        }

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
        foreach (AuditModel::recent() as $row) {
            if (!$office && (int)$row['farm_id'] !== (int)($user['farm_id'] ?? 0) && $row['username'] !== $user['username']) {
                continue;
            }
            $audit[] = [
                'at' => date('Y-m-d H:i:s', strtotime((string)$row['created_at'])),
                'by' => $row['username'],
                'msg' => $row['message'],
                'action' => $row['action'],
            ];
        }

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
