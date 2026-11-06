<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Access;
use App\Core\Audit;
use App\Core\Database;
use App\Core\HttpException;
use App\Models\CatalogModel;
use App\Models\FarmModel;
use App\Models\StaffModel;
use App\Models\UserModel;
use App\Models\WorkModel;

final class OfficeOps
{
    public function saveCategory(array $user, array $in): void
    {
        Access::assert($user, 'admin.rates');
        $name = Support::text($in, 'name', 60);
        $unit = (string)($in['unit'] ?? '');
        if (!in_array($unit, CatalogModel::UNITS, true)) {
            throw new HttpException(422, 'Choose a valid unit');
        }
        $rate = Support::positive($in['rate'] ?? 0, 'rate', true);
        $share = Support::share($in['share'] ?? 50);
        $id = (int)($in['id'] ?? 0);
        if ($id > 0) {
            $existing = null;
            foreach (CatalogModel::all() as $row) {
                if ((int)$row['id'] === $id) {
                    $existing = $row;
                }
            }
            if (!$existing) {
                throw new HttpException(404, 'Category not found');
            }
            if ((int)$existing['is_stock'] === 1) {
                $name = $existing['name'];
            }
            $other = CatalogModel::findByName($name);
            if ($other && (int)$other['id'] !== $id) {
                throw new HttpException(422, 'Category already exists');
            }
            CatalogModel::update($id, $name, $unit, $rate, $share);
            Audit::add($user, 'rate.update', 'Updated category ' . $name . ': rate ' . $existing['default_rate'] . ' → ' . $rate . ', ' . $unit . ', Hari ' . $share . '%');
            return;
        }
        if (CatalogModel::findByName($name)) {
            throw new HttpException(422, 'Category already exists');
        }
        CatalogModel::insert($name, $unit, $rate, $share, in_array($name, CatalogModel::ITEMS, true) ? 1 : 0);
        Audit::add($user, 'rate.create', 'Added category ' . $name . ' (' . $unit . ', Rs ' . $rate . ', Hari ' . $share . '%)');
    }

    public function deleteCategory(array $user, array $in): void
    {
        Access::assert($user, 'admin.rates');
        $id = (int)($in['id'] ?? 0);
        $existing = null;
        foreach (CatalogModel::all() as $row) {
            if ((int)$row['id'] === $id) {
                $existing = $row;
            }
        }
        if (!$existing) {
            throw new HttpException(404, 'Category not found');
        }
        if ((int)$existing['is_stock'] === 1 || in_array($existing['name'], CatalogModel::ITEMS, true)) {
            throw new HttpException(422, 'Stock item categories cannot be deleted');
        }
        CatalogModel::delete($id);
        Audit::add($user, 'rate.delete', 'Deleted category ' . $existing['name']);
    }

    public function updateRule(array $user, array $in): void
    {
        Access::assert($user, 'admin.rules');
        $name = Support::text($in, 'category', 60);
        $share = Support::share($in['share'] ?? 50);
        $category = CatalogModel::findByName($name);
        if (!$category) {
            throw new HttpException(422, 'Unknown category');
        }
        CatalogModel::update((int)$category['id'], $category['name'], $category['unit'], num($category['default_rate']), $share);
        Audit::add($user, 'rule.update', 'Rule ' . $name . ': Hari ' . $share . '%');
    }

    public function daily(array $user, array $in): void
    {
        Access::assert($user, 'farm.daily.write');
        $farm = Support::writableFarm($user, (string)($in['farm'] ?? ($user['farm_name'] ?? '')));
        if (!Access::isOffice($user) && $farm['name'] !== ($user['farm_name'] ?? '')) {
            throw new HttpException(403, 'Farm access denied');
        }
        $type = (string)($in['type'] ?? '');
        if (!in_array($type, CatalogModel::WORK_TYPES, true)) {
            throw new HttpException(422, 'Choose a work type');
        }
        $desc = Support::text($in, 'desc', 500);
        $date = Support::dateOrToday($in);
        $resources = Support::text($in, 'resources', 160, false);
        $notes = Support::text($in, 'notes', 255, false);
        $acres = trim((string)($in['acres'] ?? '')) === '' ? null : Support::positive($in['acres'], 'acres', true);
        $hours = trim((string)($in['hours'] ?? '')) === '' ? null : Support::positive($in['hours'], 'hours', true);
        $at = $date === date('Y-m-d') ? date('c') : $date . 'T12:00:00' . date('P');
        WorkModel::insertDaily([
            'farm_id' => (int)$farm['id'],
            'work_date' => $date,
            'occurred_at' => date('Y-m-d H:i:s', strtotime($at)),
            'work_type' => $type,
            'description' => $desc,
            'resources' => $resources,
            'acres' => $acres,
            'hours' => $hours,
            'notes' => $notes,
            'created_by' => (int)$user['id'],
        ]);
        Audit::add($user, 'daily.create', 'Daily work on ' . $farm['name'] . ': ' . $type . ' — ' . mb_substr($desc, 0, 60), (int)$farm['id']);
    }

    public function pay(array $user, array $in): void
    {
        Access::assert($user, 'admin.salaries');
        [$userId, $staffId, $label, $targetRole] = $this->payee((string)($in['userId'] ?? ''));
        if ($targetRole === 'owner' && !Access::can($user, 'owner.manage')) {
            throw new HttpException(403, 'Office cannot modify Owner records');
        }
        $month = (string)($in['month'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new HttpException(422, 'Choose a valid month');
        }
        $kind = (string)($in['kind'] ?? '');
        if (!in_array($kind, ['Salary', 'Advance'], true)) {
            throw new HttpException(422, 'Choose a payment type');
        }
        $status = (string)($in['status'] ?? '');
        if (!in_array($status, ['Paid', 'Pending'], true)) {
            throw new HttpException(422, 'Choose a status');
        }
        $amount = Support::positive($in['amount'] ?? null, 'amount');
        $date = Support::dateOrToday($in);
        $note = Support::text($in, 'note', 160, false);
        WorkModel::insertPayment([
            'user_id' => $userId,
            'staff_id' => $staffId,
            'pay_month' => $month,
            'kind' => $kind,
            'amount' => $amount,
            'status' => $status,
            'pay_date' => $date,
            'note' => $note,
            'created_by' => (int)$user['id'],
        ]);
        Audit::add($user, 'salary.pay', $kind . ' ' . $amount . ' for ' . $label . ' (' . $month . ') — ' . $status);
    }

    public function togglePay(array $user, array $in): void
    {
        Access::assert($user, 'admin.salaries');
        $row = WorkModel::findPayment((int)($in['id'] ?? 0));
        if (!$row) {
            throw new HttpException(404, 'Payment not found');
        }
        if ($row['user_id']) {
            $target = UserModel::find((int)$row['user_id']);
            if ($target && $target['role'] === 'owner' && !Access::can($user, 'owner.manage')) {
                throw new HttpException(403, 'Office cannot modify Owner records');
            }
        }
        $next = $row['status'] === 'Paid' ? 'Pending' : 'Paid';
        WorkModel::setPaymentStatus((int)$row['id'], $next);
        Audit::add($user, 'salary.status', 'Salary entry ' . $row['id'] . ' marked ' . $next);
    }

    public function prefs(array $user, array $in): void
    {
        Access::assert($user, 'settings.prefs');
        $theme = (string)($in['theme'] ?? 'light');
        $language = (string)($in['language'] ?? 'en');
        if (!in_array($theme, ['light', 'dark'], true) || !in_array($language, ['en', 'ur', 'sd'], true)) {
            throw new HttpException(422, 'Invalid preference');
        }
        WorkModel::saveSettings((int)$user['id'], $theme, $language);
    }

    public function export(array $user): array
    {
        Access::assert($user, 'admin.backup');
        $snap = (new Snapshot())->forUser($user);
        unset($snap['me'], $snap['access'], $snap['abilities'], $snap['settings']);
        return [
            'format' => 'zaz-erp-mvc-1',
            'exportedAt' => date('c'),
            'db' => $snap,
        ];
    }

    public function import(array $user, array $in): void
    {
        Access::assert($user, 'admin.backup');
        if (($in['format'] ?? '') !== 'zaz-erp-mvc-1' || !isset($in['db']) || !is_array($in['db'])) {
            throw new HttpException(422, 'Not a ZAZ ERP backup');
        }
        Database::transaction(function () use ($user, $in): void {
            $this->wipeBusiness();
            (new DemoSeeder())->restoreBackup($in['db'], (int)$user['id']);
            Audit::add($user, 'backup.import', 'Imported JSON backup');
        });
    }

    public function reset(array $user): void
    {
        Access::assert($user, 'owner.manage');
        if (!(config()['app']['allow_demo_reset'] ?? false)) {
            throw new HttpException(403, 'Demo reset is disabled');
        }
        Database::transaction(function () use ($user): void {
            $this->wipeBusiness();
            $seeder = new DemoSeeder();
            $seeder->seedFoundation();
            $seeder->seedSample();
            Audit::add($user, 'demo.reset', 'Reset demo business data');
        });
    }

    public function wipeBusiness(): void
    {
        $pdo = Database::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ([
            'crop_allocations', 'plots', 'haris', 'stock_usages', 'expenses', 'sales', 'advances',
            'transfers', 'godown_receipts', 'daily_work', 'salary_payments', 'categories', 'seasons',
        ] as $table) {
            $pdo->exec('DELETE FROM ' . $table);
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    /** @return array{0:?int,1:?int,2:string,3:string} */
    private function payee(string $id): array
    {
        if (str_starts_with($id, 'k') && ctype_digit(substr($id, 1))) {
            $staff = StaffModel::find((int)substr($id, 1));
            if (!$staff || !(int)$staff['is_active']) {
                throw new HttpException(422, 'Choose an active staff member');
            }
            return [null, (int)$staff['id'], $staff['name'], 'kamdar'];
        }
        if (!ctype_digit($id)) {
            throw new HttpException(422, 'Choose a staff member');
        }
        $target = UserModel::find((int)$id);
        if (!$target || !(int)$target['is_active']) {
            throw new HttpException(422, 'Choose an active staff member');
        }
        return [(int)$target['id'], null, $target['username'], $target['role']];
    }
}
