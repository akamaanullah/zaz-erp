<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class CatalogModel extends Model
{
    public const ITEMS = ['DAP', 'Urea', 'Zinc', 'Pesticide'];
    public const UNITS = ['per acre', 'per hour', 'per bag', 'per trip', 'per litre', 'per day', 'lump sum'];
    public const WORK_TYPES = [
        'Land preparation', 'Ploughing / Gobble', 'Sowing / Planting', 'Irrigation', 'Fertilizer application',
        'Pesticide spray', 'Weeding', 'Harvesting', 'Threshing', 'Transport', 'Maintenance / Repairs', 'Other',
    ];
    public const PAY_TYPES = ['Monthly salary', 'Daily wage', 'Contract'];
    public const MAUND_KG = 40;
    public const HARI_MAX_ACRES = 25.0;
    public const ROLES = [
        'owner' => 'Owner',
        'office' => 'Office',
        'supervisor' => 'Farm Supervisor',
        'accountant' => 'Farm Accountant',
    ];

    public static function all(): array
    {
        return self::select('SELECT * FROM categories ORDER BY id');
    }

    public static function findByName(string $name): ?array
    {
        return self::selectOne('SELECT * FROM categories WHERE name = ?', [$name]);
    }

    public static function insert(string $name, string $unit, float $rate, int $share, int $isStock): int
    {
        self::execute(
            'INSERT INTO categories (name, unit, default_rate, hari_share, is_stock, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())',
            [$name, $unit, $rate, $share, $isStock]
        );
        return self::insertId();
    }

    public static function update(int $id, string $name, string $unit, float $rate, int $share): void
    {
        self::execute(
            'UPDATE categories SET name = ?, unit = ?, default_rate = ?, hari_share = ?, updated_at = NOW() WHERE id = ?',
            [$name, $unit, $rate, $share, $id]
        );
    }

    public static function delete(int $id): void
    {
        self::execute('DELETE FROM categories WHERE id = ?', [$id]);
    }
}
