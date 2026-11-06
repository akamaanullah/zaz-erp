<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Access;
use App\Core\Audit;
use App\Core\Database;
use App\Core\HttpException;
use App\Models\FarmModel;
use App\Models\StaffModel;
use App\Models\UserModel;

final class FarmOps
{
    public function save(array $user, array $in): void
    {
        Access::assert($user, 'admin.farms');
        $acres = Support::positive($in['acres'] ?? null, 'acreage');
        $seri = Support::positive($in['seri'] ?? 0, 'Seri acres', true);
        if ($seri > $acres) {
            throw new HttpException(422, 'Check acreage: Seri must be between zero and total acres.');
        }
        $location = Support::text($in, 'location', 120, false);
        $purchase = trim((string)($in['purchase'] ?? ''));
        $purchase = $purchase === '' ? null : Support::dateOrToday(['date' => $purchase], 'date');
        $id = (int)($in['id'] ?? 0);
        Database::transaction(function () use ($user, $in, $acres, $seri, $location, $purchase, $id): void {
            if ($id > 0) {
                $farm = FarmModel::find($id);
                if (!$farm) {
                    throw new HttpException(404, 'Farm not found');
                }
                Support::assertHariLand($id, $acres, $seri);
                FarmModel::update($id, $acres, $seri, $location, $purchase);
                Audit::add($user, 'farm.update', sprintf('Updated farm %s: %s acres, Seri %s', $farm['name'], $acres, $seri), $id);
                return;
            }
            $name = Support::text($in, 'name', 80);
            if (FarmModel::findByName($name)) {
                throw new HttpException(422, 'A farm with this name already exists.');
            }
            $newId = FarmModel::insert($name, $acres, $seri, $location, $purchase);
            Audit::add($user, 'farm.create', sprintf('Added farm %s: %s acres, Seri %s, Hari %s', $name, $acres, $seri, num($acres - $seri)), $newId);
        });
    }

    public function archive(array $user, array $in): void
    {
        Access::assert($user, 'admin.farms');
        $farm = FarmModel::find((int)($in['id'] ?? 0));
        if (!$farm) {
            throw new HttpException(404, 'Farm not found');
        }
        $id = (int)$farm['id'];
        $next = (int)$farm['is_archived'] === 1 ? 0 : 1;
        if ($next === 1 && (UserModel::countActiveOnFarm($id) > 0 || StaffModel::countActiveOnFarm($id) > 0)) {
            throw new HttpException(422, 'Disable or reassign active farm employees before archiving.');
        }
        FarmModel::setArchived($id, $next);
        Audit::add($user, 'farm.archive', ($next ? 'Archived' : 'Restored') . ' farm ' . $farm['name'], $id);
    }
}
