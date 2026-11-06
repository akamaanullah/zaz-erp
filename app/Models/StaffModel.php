<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class StaffModel extends Model
{
    public static function all(): array
    {
        return self::select('SELECT * FROM staff ORDER BY id');
    }

    public static function find(int $id): ?array
    {
        return self::selectOne('SELECT * FROM staff WHERE id = ?', [$id]);
    }

    public static function countActiveOnFarm(int $farmId): int
    {
        return (int)self::selectOne('SELECT COUNT(*) AS c FROM staff WHERE farm_id = ? AND is_active = 1', [$farmId])['c'];
    }

    public static function insert(string $name, ?int $farmId, float $salary, string $payType): int
    {
        self::execute(
            'INSERT INTO staff (name, role, farm_id, salary, pay_type, is_active, created_at, updated_at)
             VALUES (?, \'kamdar\', ?, ?, ?, 1, NOW(), NOW())',
            [$name, $farmId, $salary, $payType]
        );
        return self::insertId();
    }

    public static function setActive(int $id, int $active): void
    {
        self::execute('UPDATE staff SET is_active = ?, updated_at = NOW() WHERE id = ?', [$active, $id]);
    }

    public static function setFarm(int $id, int $farmId): void
    {
        self::execute('UPDATE staff SET farm_id = ?, updated_at = NOW() WHERE id = ?', [$farmId, $id]);
    }

    public static function setSalary(int $id, float $salary, string $payType): void
    {
        self::execute('UPDATE staff SET salary = ?, pay_type = ?, updated_at = NOW() WHERE id = ?', [$salary, $payType, $id]);
    }
}
