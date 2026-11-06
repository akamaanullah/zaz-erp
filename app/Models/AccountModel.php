<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class AccountModel extends Model
{
    public static function expenses(?int $farmId = null): array
    {
        return self::scoped('expenses', $farmId);
    }

    public static function sales(?int $farmId = null): array
    {
        return self::scoped('sales', $farmId);
    }

    public static function advances(?int $farmId = null): array
    {
        return self::scoped('advances', $farmId);
    }

    private static function scoped(string $table, ?int $farmId): array
    {
        if (!in_array($table, ['expenses', 'sales', 'advances'], true)) {
            return [];
        }
        if ($farmId === null) {
            return self::select('SELECT * FROM ' . $table . ' ORDER BY id');
        }
        return self::select('SELECT * FROM ' . $table . ' WHERE farm_id = ? ORDER BY id', [$farmId]);
    }

    public static function findExpense(int $id): ?array
    {
        return self::selectOne('SELECT * FROM expenses WHERE id = ?', [$id]);
    }

    public static function findSale(int $id): ?array
    {
        return self::selectOne('SELECT * FROM sales WHERE id = ?', [$id]);
    }

    public static function findAdvance(int $id): ?array
    {
        return self::selectOne('SELECT * FROM advances WHERE id = ?', [$id]);
    }

    public static function insertExpense(array $row): int
    {
        self::execute(
            'INSERT INTO expenses
            (farm_id, season_id, category_name, qty, rate_snapshot, unit_snapshot, amount, target_type, hari_id, crop, share_snapshot, status, by_label, created_by, approved_by, approved_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $row['farm_id'], $row['season_id'], $row['category_name'], $row['qty'], $row['rate_snapshot'], $row['unit_snapshot'],
                $row['amount'], $row['target_type'], $row['hari_id'], $row['crop'], $row['share_snapshot'], $row['status'],
                $row['by_label'], $row['created_by'], $row['approved_by'], $row['approved_at'],
            ]
        );
        return self::insertId();
    }

    public static function insertSale(array $row): int
    {
        self::execute(
            'INSERT INTO sales
            (farm_id, season_id, crop, target_type, hari_id, weight_unit, weight, rate_snapshot, kg, amount, hari_share_amount, owner_share_amount, status, by_label, created_by, approved_by, approved_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $row['farm_id'], $row['season_id'], $row['crop'], $row['target_type'], $row['hari_id'], $row['weight_unit'],
                $row['weight'], $row['rate_snapshot'], $row['kg'], $row['amount'], $row['hari_share_amount'], $row['owner_share_amount'],
                $row['status'], $row['by_label'], $row['created_by'], $row['approved_by'], $row['approved_at'],
            ]
        );
        return self::insertId();
    }

    public static function insertAdvance(array $row): int
    {
        self::execute(
            'INSERT INTO advances
            (farm_id, season_id, hari_id, reason, amount, status, by_label, created_by, approved_by, approved_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $row['farm_id'], $row['season_id'], $row['hari_id'], $row['reason'], $row['amount'], $row['status'],
                $row['by_label'], $row['created_by'], $row['approved_by'], $row['approved_at'],
            ]
        );
        return self::insertId();
    }

    public static function approveExpense(int $id, int $userId): int
    {
        return self::execute(
            "UPDATE expenses SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ? AND status = 'pending'",
            [$userId, $id]
        );
    }

    public static function approveSale(int $id, int $userId): int
    {
        return self::execute(
            "UPDATE sales SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ? AND status = 'pending'",
            [$userId, $id]
        );
    }

    public static function approveAdvance(int $id, int $userId): int
    {
        return self::execute(
            "UPDATE advances SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ? AND status = 'pending'",
            [$userId, $id]
        );
    }

    public static function countExpenses(): int
    {
        return (int)self::selectOne('SELECT COUNT(*) AS c FROM expenses')['c'];
    }
}
