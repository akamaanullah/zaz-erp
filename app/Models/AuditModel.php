<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class AuditModel extends Model
{
    public static function insert(?int $userId, string $username, ?int $farmId, string $action, string $message, string $ip): void
    {
        self::execute(
            'INSERT INTO audit_log (user_id, username, farm_id, action, message, ip, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [$userId, $username, $farmId, $action, $message, $ip]
        );
    }

    public static function recent(int $limit = 300): array
    {
        $limit = max(1, min(300, $limit));
        return self::select(
            'SELECT id, user_id, username, farm_id, action, message, created_at FROM audit_log ORDER BY id DESC LIMIT ' . $limit
        );
    }
}
