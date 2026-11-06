<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Access;
use App\Core\Audit;
use App\Core\Database;
use App\Core\HttpException;
use App\Models\CatalogModel;
use App\Models\FarmModel;
use App\Models\HariModel;
use App\Models\StaffModel;
use App\Models\UserModel;

final class PeopleOps
{
    public function createEmployee(array $user, array $in): string
    {
        Access::assert($user, 'admin.employees');
        $role = (string)($in['role'] ?? '');
        if ($role === 'kamdar') {
            $this->createKamdar($user, $in);
            return '';
        }
        if (!isset(CatalogModel::ROLES[$role])) {
            throw new HttpException(422, 'Choose a valid role');
        }
        if ($role === 'owner' && !Access::can($user, 'owner.manage')) {
            throw new HttpException(403, 'Only Owner can create Owner accounts.');
        }
        $name = Support::text($in, 'name', 80);
        $username = trim((string)($in['username'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9._-]{3,30}$/', $username)) {
            throw new HttpException(422, 'Username must be 3–30 letters, digits, dot, underscore or dash');
        }
        if (UserModel::findByUsername($username)) {
            throw new HttpException(422, 'Username already exists.');
        }
        $farmId = null;
        if (in_array($role, ['supervisor', 'accountant'], true)) {
            $farm = Support::farmByName((string)($in['farm'] ?? ''));
            Support::activeFarm($farm);
            $farmId = (int)$farm['id'];
        }
        $salary = Support::positive($in['salary'] ?? 0, 'salary', true);
        $payType = $this->payType($in);
        $password = $this->generatePassword();
        $id = UserModel::insert($name, $username, password_hash($password, PASSWORD_DEFAULT), $role, $farmId, $salary, $payType);
        Audit::add($user, 'employee.create', 'Created ' . $username . ', role ' . CatalogModel::ROLES[$role] . ', farm ' . ($farmId ? FarmModel::find($farmId)['name'] : 'All'), $farmId);
        unset($id);
        return $password;
    }

    public function createKamdar(array $user, array $in): void
    {
        Access::assert($user, 'admin.employees');
        $name = Support::text($in, 'name', 80);
        $farm = Support::farmByName((string)($in['farm'] ?? ''));
        Support::activeFarm($farm);
        $salary = Support::positive($in['salary'] ?? 0, 'salary', true);
        $payType = $this->payType($in);
        $id = StaffModel::insert($name, (int)$farm['id'], $salary, $payType);
        Audit::add($user, 'kamdar.create', 'Created Kamdar ' . $name . ' on ' . $farm['name'] . ' (no login)', (int)$farm['id']);
        unset($id);
    }

    public function setStatus(array $user, array $in): void
    {
        Access::assert($user, 'admin.employees');
        [$kind, $id] = $this->ref((string)($in['id'] ?? ''));
        if ($kind === 'staff') {
            $staff = StaffModel::find($id);
            if (!$staff) {
                throw new HttpException(404, 'Employee not found');
            }
            $next = (int)$staff['is_active'] === 1 ? 0 : 1;
            StaffModel::setActive($id, $next);
            Audit::add($user, 'employee.status', ($next ? 'Enabled' : 'Disabled') . ' Kamdar ' . $staff['name'], (int)$staff['farm_id']);
            return;
        }
        $target = UserModel::find($id);
        if (!$target) {
            throw new HttpException(404, 'Employee not found');
        }
        if ((int)$target['id'] === (int)$user['id']) {
            throw new HttpException(422, 'You cannot disable yourself');
        }
        $this->guardOwnerRecord($user, $target);
        $next = (int)$target['is_active'] === 1 ? 0 : 1;
        if ($next === 0 && $target['role'] === 'owner' && UserModel::countActiveOwners() <= 1) {
            throw new HttpException(422, 'The last active Owner cannot be disabled');
        }
        UserModel::setActive($id, $next);
        Audit::add($user, 'employee.status', ($next ? 'Enabled' : 'Disabled') . ' ' . $target['username']);
    }

    public function setFarm(array $user, array $in): void
    {
        Access::assert($user, 'admin.employees');
        [$kind, $id] = $this->ref((string)($in['id'] ?? ''));
        $farm = Support::farmByName((string)($in['farm'] ?? ''));
        Support::activeFarm($farm);
        if ($kind === 'staff') {
            $staff = StaffModel::find($id);
            if (!$staff) {
                throw new HttpException(404, 'Employee not found');
            }
            StaffModel::setFarm($id, (int)$farm['id']);
            Audit::add($user, 'employee.farm', 'Changed Kamdar ' . $staff['name'] . ' farm to ' . $farm['name'], (int)$farm['id']);
            return;
        }
        $target = UserModel::find($id);
        if (!$target || !in_array($target['role'], ['supervisor', 'accountant'], true)) {
            throw new HttpException(422, 'Only farm staff can be assigned a farm');
        }
        $this->guardOwnerRecord($user, $target);
        UserModel::setFarm($id, (int)$farm['id']);
        Audit::add($user, 'employee.farm', 'Changed ' . $target['username'] . ' farm to ' . $farm['name'], (int)$farm['id']);
    }

    public function setSalary(array $user, array $in): void
    {
        Access::assert($user, 'admin.employees');
        [$kind, $id] = $this->ref((string)($in['id'] ?? ''));
        $salary = Support::positive($in['salary'] ?? 0, 'salary', true);
        $payType = $this->payType($in);
        if ($kind === 'staff') {
            $staff = StaffModel::find($id);
            if (!$staff) {
                throw new HttpException(404, 'Employee not found');
            }
            StaffModel::setSalary($id, $salary, $payType);
            Audit::add($user, 'employee.salary', 'Salary Kamdar ' . $staff['name'] . ': ' . $salary . ' (' . $payType . ')', (int)$staff['farm_id']);
            return;
        }
        $target = UserModel::find($id);
        if (!$target) {
            throw new HttpException(404, 'Employee not found');
        }
        $this->guardOwnerRecord($user, $target);
        UserModel::setSalary($id, $salary, $payType);
        Audit::add($user, 'employee.salary', 'Salary ' . $target['username'] . ': ' . $salary . ' (' . $payType . ')');
    }

    public function createHari(array $user, array $in): void
    {
        Access::assert($user, 'farm.hari.write');
        $farm = Support::writableFarm($user, (string)($in['farm'] ?? ''));
        $name = Support::text($in, 'name', 80);
        $address = Support::text($in, 'address', 160, false);
        $acres = Support::positive($in['acres'] ?? null, 'acres');
        $crop = Support::text($in, 'crop', 80);
        $season = Support::season($in);
        if ($acres > CatalogModel::HARI_MAX_ACRES) {
            throw new HttpException(422, 'Maximum 25 acres per Hari');
        }
        Database::transaction(function () use ($user, $farm, $name, $address, $acres, $crop, $season): void {
            Support::assertHariLand((int)$farm['id'], num($farm['acres']), num($farm['seri_acres']), $acres);
            $hariId = HariModel::insert((int)$farm['id'], $name, $address);
            $plotId = HariModel::insertPlot((int)$farm['id'], $hariId, $crop . ' plot', $acres, 'hari');
            HariModel::insertAllocation($plotId, $hariId, (int)$season['id'], $crop);
            Audit::add($user, 'hari.create', 'Added Hari ' . $name . ' on ' . $farm['name'] . ' (' . $acres . ' ac ' . $crop . ')', (int)$farm['id']);
        });
    }

    public function allocate(array $user, array $in): void
    {
        Access::assert($user, 'farm.hari.write');
        $hari = HariModel::find((int)($in['hariId'] ?? 0));
        if (!$hari) {
            throw new HttpException(422, 'Select Hari');
        }
        $farm = FarmModel::find((int)$hari['farm_id']);
        if (!$farm) {
            throw new HttpException(422, 'Farm not found');
        }
        Access::assertFarm($user, (int)$farm['id']);
        Support::activeFarm($farm);
        $crop = Support::text($in, 'crop', 80);
        $season = Support::season($in);
        $plotId = (int)($in['plotId'] ?? 0);
        Database::transaction(function () use ($user, $hari, $farm, $crop, $season, $plotId, $in): void {
            if ($plotId > 0) {
                $plot = HariModel::plot($plotId);
                if (!$plot || (int)$plot['hari_id'] !== (int)$hari['id']) {
                    throw new HttpException(422, 'Choose a plot that belongs to this Hari');
                }
                if (HariModel::allocationExists($plotId, (int)$season['id'], $crop)) {
                    throw new HttpException(422, 'That crop is already on this plot for the season');
                }
                HariModel::insertAllocation($plotId, (int)$hari['id'], (int)$season['id'], $crop);
                Audit::add($user, 'hari.allocate', 'Added ' . $crop . ' on existing plot ' . $plot['name'] . ' for ' . $hari['name'] . ' (acres unchanged)', (int)$farm['id']);
                return;
            }
            $acres = Support::positive($in['acres'] ?? null, 'acres');
            $physical = HariModel::physicalAcres((int)$hari['id']);
            if (num($physical + $acres) > CatalogModel::HARI_MAX_ACRES) {
                throw new HttpException(422, 'Hari exceeds 25 acres; assignment blocked');
            }
            Support::assertHariLand((int)$farm['id'], num($farm['acres']), num($farm['seri_acres']), $acres);
            $newPlot = HariModel::insertPlot((int)$farm['id'], (int)$hari['id'], $crop . ' plot', $acres, 'hari');
            HariModel::insertAllocation($newPlot, (int)$hari['id'], (int)$season['id'], $crop);
            Audit::add($user, 'hari.allocate', 'Added ' . $acres . ' ac ' . $crop . ' to Hari ' . $hari['name'], (int)$farm['id']);
        });
    }

    private function guardOwnerRecord(array $actor, array $target): void
    {
        if ($target['role'] === 'owner' && !Access::can($actor, 'owner.manage')) {
            throw new HttpException(403, 'Office cannot modify Owner records');
        }
    }

    private function payType(array $in): string
    {
        $pay = (string)($in['payType'] ?? 'Monthly salary');
        if (!in_array($pay, CatalogModel::PAY_TYPES, true)) {
            throw new HttpException(422, 'Choose a valid pay type');
        }
        return $pay;
    }

    /** @return array{0:string,1:int} */
    private function ref(string $id): array
    {
        if (str_starts_with($id, 'k') && ctype_digit(substr($id, 1))) {
            return ['staff', (int)substr($id, 1)];
        }
        if (!ctype_digit($id)) {
            throw new HttpException(422, 'Employee not found');
        }
        return ['user', (int)$id];
    }

    private function generatePassword(): string
    {
        return bin2hex(random_bytes(4)) . 'Aa1';
    }
}
