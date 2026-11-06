<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class HariModel extends Model
{
    public static function all(): array
    {
        return self::select('SELECT * FROM haris ORDER BY id');
    }

    public static function find(int $id): ?array
    {
        return self::selectOne('SELECT * FROM haris WHERE id = ?', [$id]);
    }

    public static function insert(int $farmId, string $name, string $address): int
    {
        self::execute(
            'INSERT INTO haris (farm_id, name, address, is_active, created_at, updated_at) VALUES (?, ?, ?, 1, NOW(), NOW())',
            [$farmId, $name, $address]
        );
        return self::insertId();
    }

    public static function plotsFor(int $hariId): array
    {
        return self::select('SELECT * FROM plots WHERE hari_id = ? ORDER BY id', [$hariId]);
    }

    public static function plot(int $id): ?array
    {
        return self::selectOne('SELECT * FROM plots WHERE id = ?', [$id]);
    }

    public static function insertPlot(int $farmId, ?int $hariId, string $name, float $acres, string $arrangement): int
    {
        self::execute(
            'INSERT INTO plots (farm_id, hari_id, name, acres, arrangement, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [$farmId, $hariId, $name, $acres, $arrangement]
        );
        return self::insertId();
    }

    public static function physicalAcres(int $hariId): float
    {
        $row = self::selectOne('SELECT COALESCE(SUM(acres), 0) AS acres FROM plots WHERE hari_id = ?', [$hariId]);
        return num($row['acres'] ?? 0);
    }

    public static function allocations(): array
    {
        return self::select(
            'SELECT a.id, a.plot_id, a.hari_id, a.crop, a.season_id, s.name AS season, p.acres, p.name AS plot_name, p.farm_id
             FROM crop_allocations a
             JOIN seasons s ON s.id = a.season_id
             JOIN plots p ON p.id = a.plot_id
             ORDER BY a.id'
        );
    }

    public static function allocationExists(int $plotId, int $seasonId, string $crop): bool
    {
        $row = self::selectOne(
            'SELECT id FROM crop_allocations WHERE plot_id = ? AND season_id = ? AND crop = ?',
            [$plotId, $seasonId, $crop]
        );
        return $row !== null;
    }

    public static function insertAllocation(int $plotId, ?int $hariId, int $seasonId, string $crop): int
    {
        self::execute(
            'INSERT INTO crop_allocations (plot_id, hari_id, season_id, crop, created_at) VALUES (?, ?, ?, ?, NOW())',
            [$plotId, $hariId, $seasonId, $crop]
        );
        return self::insertId();
    }
}
