<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\AuditModel;

final class Audit
{
    public static function add(?array $user, string $action, string $message, ?int $farmId = null): void
    {
        $username = $user['username'] ?? 'System';
        $userId = isset($user['id']) ? (int)$user['id'] : null;
        if ($farmId === null && !empty($user['farm_id'])) {
            $farmId = (int)$user['farm_id'];
        }
        AuditModel::insert(
            $userId,
            substr((string)$username, 0, 30),
            $farmId,
            substr($action, 0, 60),
            substr($message, 0, 500),
            substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)
        );
    }
}
