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

    public static function normalize(string $username): string
    {
        $username = trim($username);
        return mb_strtolower($username, 'UTF-8');
    }

    public static function findByUsername(string $username): ?array
    {
        $username = self::normalize($username);
        if ($username === '') {
            return null;
        }
        return self::selectOne('SELECT * FROM users WHERE LOWER(username) = ? LIMIT 1', [$username]);
    }

    public static function dummyHash(): string
    {
        static $hash = null;
        if ($hash === null) {
            $hash = password_hash('timing-equalizer', PASSWORD_BCRYPT);
        }
        return $hash;
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
        $username = self::normalize($username);
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

    public static function setUsername(int $id, string $username): void
    {
        self::execute('UPDATE users SET username = ?, updated_at = NOW() WHERE id = ?', [self::normalize($username), $id]);
    }

    public static function setTotpPending(int $id, string $encrypted): void
    {
        self::execute('UPDATE users SET totp_secret = ?, totp_enabled = 0, totp_last_step = 0, updated_at = NOW() WHERE id = ?', [$encrypted, $id]);
    }

    public static function enableTotp(int $id): void
    {
        self::execute('UPDATE users SET totp_enabled = 1, updated_at = NOW() WHERE id = ?', [$id]);
    }

    public static function clearTotp(int $id): void
    {
        self::execute('UPDATE users SET totp_secret = NULL, totp_enabled = 0, totp_last_step = 0, updated_at = NOW() WHERE id = ?', [$id]);
    }

    public static function setTotpStep(int $id, int $step): void
    {
        self::execute('UPDATE users SET totp_last_step = ? WHERE id = ?', [$step, $id]);
    }
}
