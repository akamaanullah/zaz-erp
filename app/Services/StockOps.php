<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Access;
use App\Core\Audit;
use App\Core\Database;
use App\Core\HttpException;
use App\Models\CatalogModel;
use App\Models\FarmModel;
use App\Models\StockModel;
use App\Traits\LocksRows;

final class StockOps
{
    use LocksRows;

    public function receiveGodown(array $user, array $in): void
    {
        Access::assert($user, 'admin.godown');
        $item = Support::item((string)($in['item'] ?? ''));
        $qty = Support::positive($in['qty'] ?? null, 'quantity');
        $rate = Support::positive($in['rate'] ?? null, 'rate', true);
        $date = Support::dateOrToday($in);
        Database::transaction(function () use ($user, $item, $qty, $rate, $date): void {
            $this->lockCategoryName($item);
            StockModel::insertReceipt($item, $qty, $rate, $date, (int)$user['id']);
            Audit::add($user, 'godown.receive', 'Godown received ' . $qty . ' ' . $item . ' @ ' . $rate);
        });
    }

    public function send(array $user, array $in): void
    {
        Access::assert($user, 'admin.transfer.send');
        $item = Support::item((string)($in['item'] ?? ''));
        $qty = Support::positive($in['qty'] ?? null, 'quantity');
        $farm = Support::farmByName((string)($in['farm'] ?? ''));
        Support::activeFarm($farm);
        Database::transaction(function () use ($user, $item, $qty, $farm): void {
            $this->lockCategoryName($item);
            $available = StockModel::godownQty($item);
            if ($qty > $available + 0.001) {
                throw new HttpException(422, 'Insufficient main godown stock');
            }
            StockModel::insertTransfer((int)$farm['id'], $item, $qty, date('Y-m-d'), (int)$user['id']);
            Audit::add($user, 'transfer.send', 'Sent ' . $qty . ' ' . $item . ' to ' . $farm['name'], (int)$farm['id']);
        });
    }

    public function receive(array $user, array $in): void
    {
        Access::assert($user, 'farm.receive');
        $id = (int)($in['id'] ?? 0);
        $qty = Support::positive($in['qty'] ?? null, 'received quantity', true);
        Database::transaction(function () use ($user, $id, $qty): void {
            $row = StockModel::findTransfer($id, true);
            if (!$row) {
                throw new HttpException(404, 'Transfer not found');
            }
            Access::assertFarm($user, (int)$row['farm_id']);
            if ($row['qty_received'] !== null) {
                throw new HttpException(409, 'This transfer was already received');
            }
            $sent = num($row['qty_sent']);
            if ($qty > $sent + 0.001) {
                throw new HttpException(422, 'Received quantity must be 0 to sent quantity');
            }
            $updated = StockModel::markReceived($id, $qty, (int)$user['id']);
            if ($updated !== 1) {
                throw new HttpException(409, 'This transfer was already received');
            }
            $farm = FarmModel::find((int)$row['farm_id']);
            $diff = num($sent - $qty);
            Audit::add(
                $user,
                'transfer.receive',
                'Received ' . $qty . '/' . $sent . ' ' . $row['item_name'] . ' at ' . ($farm['name'] ?? '') . ' (difference ' . $diff . ')',
                (int)$row['farm_id']
            );
        });
    }

    public function useStock(array $user, array $in): void
    {
        Access::assert($user, 'farm.usage.write');
        $farm = Support::writableFarm($user, (string)($in['farm'] ?? ''));
        $item = Support::item((string)($in['item'] ?? ''));
        $qty = Support::positive($in['qty'] ?? null, 'quantity');
        $crop = Support::text($in, 'crop', 80);
        $season = Support::season($in);
        $target = Support::target($in, (int)$farm['id']);
        Database::transaction(function () use ($user, $farm, $item, $qty, $crop, $season, $target): void {
            $this->lockFarm((int)$farm['id']);
            $this->lockCategoryName($item);
            $left = StockModel::farmQty((int)$farm['id'], $item);
            if ($qty > $left + 0.001) {
                throw new HttpException(422, 'Insufficient farm stock');
            }
            $rate = StockModel::latestRate($item);
            if ($rate === null) {
                throw new HttpException(422, 'No office rate has been set for this item');
            }
            $category = CatalogModel::findByName($item);
            $share = $target['type'] === 'seri' ? 0 : (int)($category['hari_share'] ?? 50);
            $status = Support::statusFor($user);
            $stamp = Support::approvalStamp($user, $status);
            StockModel::insertUsage([
                'farm_id' => (int)$farm['id'],
                'season_id' => (int)$season['id'],
                'item_name' => $item,
                'qty' => $qty,
                'rate_snapshot' => $rate,
                'amount' => money_mul($qty, $rate),
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
            Audit::add($user, 'usage.create', 'Used ' . $qty . ' ' . $item . ' for ' . $target['label'] . ' on ' . $farm['name'], (int)$farm['id']);
        });
    }
}
