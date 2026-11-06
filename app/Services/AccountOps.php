<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Access;
use App\Core\Audit;
use App\Core\Database;
use App\Core\HttpException;
use App\Models\AccountModel;
use App\Models\CatalogModel;
use App\Models\FarmModel;
use App\Models\StockModel;

final class AccountOps
{
    public function expense(array $user, array $in): void
    {
        Access::assert($user, 'farm.expense.write');
        $farm = Support::writableFarm($user, (string)($in['farm'] ?? ''));
        $categoryName = Support::text($in, 'category', 60);
        $category = CatalogModel::findByName($categoryName);
        if (!$category) {
            throw new HttpException(422, 'Unknown expense category');
        }
        $qty = Support::positive($in['qty'] ?? null, 'quantity');
        $rate = Support::positive($in['rate'] ?? null, 'rate', true);
        $crop = Support::text($in, 'crop', 80);
        $season = Support::season($in);
        $target = Support::target($in, (int)$farm['id']);
        $amount = money_mul($qty, $rate);
        if ($amount <= 0) {
            throw new HttpException(422, 'Enter a quantity and rate so the total is greater than zero');
        }
        $share = $target['type'] === 'seri' ? 0 : (int)$category['hari_share'];
        $status = Support::statusFor($user);
        $stamp = Support::approvalStamp($user, $status);
        AccountModel::insertExpense([
            'farm_id' => (int)$farm['id'],
            'season_id' => (int)$season['id'],
            'category_name' => $category['name'],
            'qty' => $qty,
            'rate_snapshot' => $rate,
            'unit_snapshot' => $category['unit'],
            'amount' => $amount,
            'target_type' => $target['type'],
            'hari_id' => $target['hari_id'],
            'crop' => $crop,
            'share_snapshot' => $share,
            'status' => $status,
            'by_label' => $user['username'],
            'created_by' => (int)$user['id'],
            'approved_by' => $stamp['approved_by'],
            'approved_at' => $stamp['approved_at'],
        ]);
        Audit::add($user, 'expense.create', 'Expense ' . $category['name'] . ' ' . $qty . ' × ' . $rate . ' = ' . $amount . ' for ' . $target['label'] . ' on ' . $farm['name'], (int)$farm['id']);
    }

    public function sale(array $user, array $in): void
    {
        Access::assert($user, 'farm.sale.write');
        $farm = Support::writableFarm($user, (string)($in['farm'] ?? ''));
        $unit = (string)($in['unit'] ?? '');
        if (!in_array($unit, ['maund', 'kg'], true)) {
            throw new HttpException(422, 'Weight unit must be maund or kg');
        }
        $weight = Support::positive($in['weight'] ?? null, 'weight');
        $rate = Support::positive($in['rate'] ?? null, 'rate');
        $crop = Support::text($in, 'crop', 80);
        $season = Support::season($in);
        $target = Support::target($in, (int)$farm['id']);
        $amount = money_mul($weight, $rate);
        $kg = $unit === 'maund' ? num($weight * CatalogModel::MAUND_KG) : $weight;
        $hariShare = $target['type'] === 'seri' ? 0.0 : num($amount / 2);
        $ownerShare = num($amount - $hariShare);
        $status = Support::statusFor($user);
        $stamp = Support::approvalStamp($user, $status);
        AccountModel::insertSale([
            'farm_id' => (int)$farm['id'],
            'season_id' => (int)$season['id'],
            'crop' => $crop,
            'target_type' => $target['type'],
            'hari_id' => $target['hari_id'],
            'weight_unit' => $unit,
            'weight' => $weight,
            'rate_snapshot' => $rate,
            'kg' => $kg,
            'amount' => $amount,
            'hari_share_amount' => $hariShare,
            'owner_share_amount' => $ownerShare,
            'status' => $status,
            'by_label' => $user['username'],
            'created_by' => (int)$user['id'],
            'approved_by' => $stamp['approved_by'],
            'approved_at' => $stamp['approved_at'],
        ]);
        Audit::add($user, 'sale.create', 'Sale ' . $crop . ' ' . $weight . ' ' . $unit . ' @ ' . $rate . ' = ' . $amount . ' (' . $target['label'] . ') on ' . $farm['name'], (int)$farm['id']);
    }

    public function advance(array $user, array $in): void
    {
        Access::assert($user, 'farm.advance.write');
        $farm = Support::writableFarm($user, (string)($in['farm'] ?? ''));
        $hari = Support::hariOnFarm((int)($in['hariId'] ?? 0), (int)$farm['id']);
        $reason = Support::text($in, 'reason', 160);
        $amount = Support::positive($in['amount'] ?? null, 'advance');
        $season = Support::season($in);
        $status = Support::statusFor($user);
        $stamp = Support::approvalStamp($user, $status);
        AccountModel::insertAdvance([
            'farm_id' => (int)$farm['id'],
            'season_id' => (int)$season['id'],
            'hari_id' => (int)$hari['id'],
            'reason' => $reason,
            'amount' => $amount,
            'status' => $status,
            'by_label' => $user['username'],
            'created_by' => (int)$user['id'],
            'approved_by' => $stamp['approved_by'],
            'approved_at' => $stamp['approved_at'],
        ]);
        Audit::add($user, 'advance.create', 'Advance ' . $amount . ' to ' . $hari['name'], (int)$farm['id']);
    }

    public function approveOne(array $user, array $in): bool
    {
        Access::assert($user, 'admin.approvals');
        $type = (string)($in['type'] ?? '');
        $id = (int)($in['id'] ?? 0);
        return Database::transaction(function () use ($user, $type, $id): bool {
            $changed = $this->approveRow($user, $type, $id);
            if ($changed) {
                Audit::add($user, 'approval.one', 'Approved ' . $type . ' entry ' . $id);
            }
            return $changed;
        });
    }

    public function approveBulk(array $user, array $in): int
    {
        Access::assert($user, 'admin.approvals');
        $farm = Support::farmByName((string)($in['farm'] ?? ''));
        $count = 0;
        Database::transaction(function () use ($user, $farm, &$count): void {
            foreach (StockModel::usages() as $row) {
                if ((int)$row['farm_id'] === (int)$farm['id'] && $row['status'] === 'pending' && $this->approveRow($user, 'usage', (int)$row['id'])) {
                    $count++;
                }
            }
            foreach (AccountModel::expenses() as $row) {
                if ((int)$row['farm_id'] === (int)$farm['id'] && $row['status'] === 'pending' && $this->approveRow($user, 'expenses', (int)$row['id'])) {
                    $count++;
                }
            }
            foreach (AccountModel::sales() as $row) {
                if ((int)$row['farm_id'] === (int)$farm['id'] && $row['status'] === 'pending' && $this->approveRow($user, 'sales', (int)$row['id'])) {
                    $count++;
                }
            }
            foreach (AccountModel::advances() as $row) {
                if ((int)$row['farm_id'] === (int)$farm['id'] && $row['status'] === 'pending' && $this->approveRow($user, 'advances', (int)$row['id'])) {
                    $count++;
                }
            }
            Audit::add($user, 'approval.bulk', 'Bulk approved ' . $count . ' entries for ' . $farm['name'], (int)$farm['id']);
        });
        return $count;
    }

    public static function ledger(int $hariId, bool $approvedOnly): array
    {
        $gross = 0.0;
        $ded = 0.0;
        $advance = 0.0;
        $sales = [];
        $costs = [];
        $advances = [];
        foreach (AccountModel::sales() as $row) {
            if ((int)$row['hari_id'] !== $hariId) {
                continue;
            }
            if ($approvedOnly && $row['status'] !== 'approved') {
                continue;
            }
            $sales[] = $row;
            $gross += (float)$row['hari_share_amount'];
        }
        foreach (array_merge(self::usageCosts(), AccountModel::expenses()) as $row) {
            if ((int)($row['hari_id'] ?? 0) !== $hariId) {
                continue;
            }
            if ($approvedOnly && $row['status'] !== 'approved') {
                continue;
            }
            $costs[] = $row;
            $ded += num((float)$row['amount'] * (int)$row['share_snapshot'] / 100);
        }
        foreach (AccountModel::advances() as $row) {
            if ((int)$row['hari_id'] !== $hariId) {
                continue;
            }
            if ($approvedOnly && $row['status'] !== 'approved') {
                continue;
            }
            $advances[] = $row;
            $advance += (float)$row['amount'];
        }
        return [
            'gross' => num($gross),
            'ded' => num($ded),
            'advance' => num($advance),
            'balance' => num($gross - $ded - $advance),
            'sales' => $sales,
            'costs' => $costs,
            'advances' => $advances,
        ];
    }

    public static function operatingExpenseTotal(?int $farmId = null): float
    {
        $total = 0.0;
        foreach (self::usageCosts() as $row) {
            if ($farmId !== null && (int)$row['farm_id'] !== $farmId) {
                continue;
            }
            $total += (float)$row['amount'];
        }
        foreach (AccountModel::expenses() as $row) {
            if ($farmId !== null && (int)$row['farm_id'] !== $farmId) {
                continue;
            }
            $total += (float)$row['amount'];
        }
        return num($total);
    }

    private static function usageCosts(): array
    {
        return StockModel::usages();
    }

    private function approveRow(array $user, string $type, int $id): bool
    {
        $map = [
            'usage' => [StockModel::class, 'findUsage', 'approveUsage'],
            'expenses' => [AccountModel::class, 'findExpense', 'approveExpense'],
            'sales' => [AccountModel::class, 'findSale', 'approveSale'],
            'advances' => [AccountModel::class, 'findAdvance', 'approveAdvance'],
        ];
        if (!isset($map[$type])) {
            throw new HttpException(422, 'Unknown approval type');
        }
        [$class, $find, $approve] = $map[$type];
        $row = $class::$find($id);
        if (!$row) {
            throw new HttpException(404, 'Entry not found');
        }
        $farm = FarmModel::find((int)$row['farm_id']);
        if (!$farm) {
            throw new HttpException(404, 'Farm not found');
        }
        Access::assertFarm($user, (int)$farm['id']);
        if ($row['status'] === 'approved') {
            return false;
        }
        $changed = $class::$approve($id, (int)$user['id']);
        return $changed === 1;
    }
}
