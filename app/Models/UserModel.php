<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class UserModel extends Model
{
    public static function find(int $id): ?array
    {
        return self::selectOne('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function findByUsername(string $username): ?array
    {
        return self::selectOne('SELECT * FROM users WHERE username = ?', [$username]);
    }

    public static function all(): array
    {
        return self::select('SELECT id, name, username, role, farm_id, salary, pay_type, is_active FROM users ORDER BY id');
    }

    public static function count(): int
    {
        return (int)self::selectOne('SELECT COUNT(*) AS c FROM users')['c'];
    }

    public static function countActiveOwners(): int
    {
        return (int)self::selectOne("SELECT COUNT(*) AS c FROM users WHERE role = 'owner' AND is_active = 1")['c'];
    }

    public static function countActiveOnFarm(int $farmId): int
    {
        return (int)self::selectOne('SELECT COUNT(*) AS c FROM users WHERE farm_id = ? AND is_active = 1', [$farmId])['c'];
    }

    public static function insert(string $name, string $username, string $hash, string $role, ?int $farmId, float $salary, string $payType): int
    {
        self::execute(
            'INSERT INTO users (name, username, password_hash, role, farm_id, salary, pay_type, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())',
            [$name, $username, $hash, $role, $farmId, $salary, $payType]
        );
        $id = self::insertId();
        self::execute('INSERT INTO user_settings (user_id, theme, language) VALUES (?, ?, ?)', [$id, 'light', 'en']);
        return $id;
    }

    public static function setActive(int $id, int $active): void
    {
        self::execute('UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?', [$active, $id]);
    }

    public static function setFarm(int $id, ?int $farmId): void
    {
        self::execute('UPDATE users SET farm_id = ?, updated_at = NOW() WHERE id = ?', [$farmId, $id]);
    }

    public static function setSalary(int $id, float $salary, string $payType): void
    {
        self::execute('UPDATE users SET salary = ?, pay_type = ?, updated_at = NOW() WHERE id = ?', [$salary, $payType, $id]);
    }
}
