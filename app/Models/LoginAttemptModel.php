<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class LoginAttemptModel extends Model
{
    public const LIMIT = 8;
    public const WINDOW_SECONDS = 600;

    public static function tooMany(string $ip): bool
    {
        if ($ip === '') {
            return false;
        }
        $row = self::selectOne(
            'SELECT COUNT(*) AS c FROM login_attempts WHERE ip = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ' . self::WINDOW_SECONDS . ' SECOND)',
            [$ip]
        );
        return (int)($row['c'] ?? 0) >= self::LIMIT;
    }

    public static function add(string $ip): void
    {
        if ($ip === '') {
            return;
        }
        self::execute('INSERT INTO login_attempts (ip, attempted_at) VALUES (?, NOW())', [$ip]);
    }

    public static function clear(string $ip): void
    {
        if ($ip === '') {
            return;
        }
        self::execute('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
    }
}
