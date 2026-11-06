<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class FarmModel extends Model
{
    public static function all(): array
    {
        return self::select('SELECT * FROM farms ORDER BY id');
    }

    public static function find(int $id): ?array
    {
        return self::selectOne('SELECT * FROM farms WHERE id = ?', [$id]);
    }

    public static function findByName(string $name): ?array
    {
        return self::selectOne('SELECT * FROM farms WHERE name = ?', [$name]);
    }

    public static function insert(string $name, float $acres, float $seri, string $location, ?string $purchase): int
    {
        self::execute(
            'INSERT INTO farms (name, acres, seri_acres, location, purchase_date, is_archived, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 0, NOW(), NOW())',
            [$name, $acres, $seri, $location, $purchase]
        );
        return self::insertId();
    }

    public static function update(int $id, float $acres, float $seri, string $location, ?string $purchase): void
    {
        self::execute(
            'UPDATE farms SET acres = ?, seri_acres = ?, location = ?, purchase_date = ?, updated_at = NOW() WHERE id = ?',
            [$acres, $seri, $location, $purchase, $id]
        );
    }

    public static function setArchived(int $id, int $archived): void
    {
        self::execute('UPDATE farms SET is_archived = ?, updated_at = NOW() WHERE id = ?', [$archived, $id]);
    }

    public static function hariPlotAcres(int $farmId): float
    {
        $row = self::selectOne(
            "SELECT COALESCE(SUM(acres), 0) AS acres FROM plots WHERE farm_id = ? AND arrangement = 'hari'",
            [$farmId]
        );
        return num($row['acres'] ?? 0);
    }
}
