<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class TotpRecoveryModel extends Model
{
    public static function replace(int $userId, array $hashes): void
    {
        self::execute('DELETE FROM totp_recovery_codes WHERE user_id = ?', [$userId]);
        foreach ($hashes as $hash) {
            self::execute(
                'INSERT INTO totp_recovery_codes (user_id, code_hash, used_at) VALUES (?, ?, NULL)',
                [$userId, $hash]
            );
        }
    }

    public static function consume(int $userId, string $code): bool
    {
        $rows = self::select(
            'SELECT id, code_hash FROM totp_recovery_codes WHERE user_id = ? AND used_at IS NULL',
            [$userId]
        );
        foreach ($rows as $row) {
            if (password_verify($code, (string)$row['code_hash'])) {
                self::execute('UPDATE totp_recovery_codes SET used_at = NOW() WHERE id = ? AND used_at IS NULL', [(int)$row['id']]);
                return true;
            }
        }
        return false;
    }
}
