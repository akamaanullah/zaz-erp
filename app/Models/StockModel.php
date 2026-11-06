<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class StockModel extends Model
{
    public static function receipts(): array
    {
        return self::select('SELECT * FROM godown_receipts ORDER BY id');
    }

    public static function latestRate(string $item): ?float
    {
        $row = self::selectOne(
            'SELECT rate FROM godown_receipts WHERE item_name = ? ORDER BY received_on DESC, id DESC LIMIT 1',
            [$item]
        );
        return $row ? num($row['rate']) : null;
    }

    public static function godownReceived(string $item): float
    {
        $row = self::selectOne('SELECT COALESCE(SUM(qty), 0) AS q FROM godown_receipts WHERE item_name = ?', [$item]);
        return num($row['q'] ?? 0);
    }

    public static function godownSent(string $item): float
    {
        $row = self::selectOne('SELECT COALESCE(SUM(qty_sent), 0) AS q FROM transfers WHERE item_name = ?', [$item]);
        return num($row['q'] ?? 0);
    }

    public static function godownQty(string $item): float
    {
        return num(self::godownReceived($item) - self::godownSent($item));
    }

    public static function farmReceived(int $farmId, string $item): float
    {
        $row = self::selectOne(
            'SELECT COALESCE(SUM(qty_received), 0) AS q FROM transfers WHERE farm_id = ? AND item_name = ? AND qty_received IS NOT NULL',
            [$farmId, $item]
        );
        return num($row['q'] ?? 0);
    }

    public static function farmUsed(int $farmId, string $item): float
    {
        $row = self::selectOne(
            'SELECT COALESCE(SUM(qty), 0) AS q FROM stock_usages WHERE farm_id = ? AND item_name = ?',
            [$farmId, $item]
        );
        return num($row['q'] ?? 0);
    }

    public static function farmQty(int $farmId, string $item): float
    {
        return num(self::farmReceived($farmId, $item) - self::farmUsed($farmId, $item));
    }

    public static function insertReceipt(string $item, float $qty, float $rate, string $date, int $userId): int
    {
        self::execute(
            'INSERT INTO godown_receipts (item_name, qty, rate, received_on, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [$item, $qty, $rate, $date, $userId]
        );
        return self::insertId();
    }

    public static function transfers(?int $farmId = null): array
    {
        if ($farmId === null) {
            return self::select('SELECT * FROM transfers ORDER BY id');
        }
        return self::select('SELECT * FROM transfers WHERE farm_id = ? ORDER BY id', [$farmId]);
    }

    public static function findTransfer(int $id, bool $lock = false): ?array
    {
        $sql = 'SELECT * FROM transfers WHERE id = ?' . ($lock ? ' FOR UPDATE' : '');
        return self::selectOne($sql, [$id]);
    }

    public static function insertTransfer(int $farmId, string $item, float $qty, string $date, int $userId): int
    {
        self::execute(
            'INSERT INTO transfers (farm_id, item_name, qty_sent, qty_received, sent_on, created_by, created_at)
             VALUES (?, ?, ?, NULL, ?, ?, NOW())',
            [$farmId, $item, $qty, $date, $userId]
        );
        return self::insertId();
    }

    public static function markReceived(int $id, float $qty, int $userId): int
    {
        return self::execute(
            'UPDATE transfers SET qty_received = ?, received_by = ?, received_at = NOW() WHERE id = ? AND qty_received IS NULL',
            [$qty, $userId, $id]
        );
    }

    public static function usages(?int $farmId = null): array
    {
        if ($farmId === null) {
            return self::select('SELECT * FROM stock_usages ORDER BY id');
        }
        return self::select('SELECT * FROM stock_usages WHERE farm_id = ? ORDER BY id', [$farmId]);
    }

    public static function findUsage(int $id): ?array
    {
        return self::selectOne('SELECT * FROM stock_usages WHERE id = ?', [$id]);
    }

    public static function insertUsage(array $row): int
    {
        self::execute(
            'INSERT INTO stock_usages
            (farm_id, season_id, item_name, qty, rate_snapshot, amount, target_type, hari_id, crop, share_snapshot, status, stock_deducted, by_label, created_by, approved_by, approved_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, NOW())',
            [
                $row['farm_id'], $row['season_id'], $row['item_name'], $row['qty'], $row['rate_snapshot'], $row['amount'],
                $row['target_type'], $row['hari_id'], $row['crop'], $row['share_snapshot'], $row['status'],
                $row['by_label'], $row['created_by'], $row['approved_by'], $row['approved_at'],
            ]
        );
        return self::insertId();
    }

    public static function approveUsage(int $id, int $userId): int
    {
        return self::execute(
            "UPDATE stock_usages SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ? AND status = 'pending'",
            [$userId, $id]
        );
    }
}
