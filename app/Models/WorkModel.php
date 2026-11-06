<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class WorkModel extends Model
{
    public static function daily(?int $farmId = null): array
    {
        if ($farmId === null) {
            return self::select('SELECT * FROM daily_work ORDER BY occurred_at DESC, id DESC');
        }
        return self::select('SELECT * FROM daily_work WHERE farm_id = ? ORDER BY occurred_at DESC, id DESC', [$farmId]);
    }

    public static function insertDaily(array $row): int
    {
        self::execute(
            'INSERT INTO daily_work (farm_id, work_date, occurred_at, work_type, description, resources, acres, hours, notes, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $row['farm_id'], $row['work_date'], $row['occurred_at'], $row['work_type'], $row['description'],
                $row['resources'], $row['acres'], $row['hours'], $row['notes'], $row['created_by'],
            ]
        );
        return self::insertId();
    }

    public static function payments(): array
    {
        return self::select('SELECT * FROM salary_payments ORDER BY id');
    }

    public static function findPayment(int $id): ?array
    {
        return self::selectOne('SELECT * FROM salary_payments WHERE id = ?', [$id]);
    }

    public static function insertPayment(array $row): int
    {
        self::execute(
            'INSERT INTO salary_payments (user_id, staff_id, pay_month, kind, amount, status, pay_date, note, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $row['user_id'], $row['staff_id'], $row['pay_month'], $row['kind'], $row['amount'],
                $row['status'], $row['pay_date'], $row['note'], $row['created_by'],
            ]
        );
        return self::insertId();
    }

    public static function setPaymentStatus(int $id, string $status): void
    {
        self::execute('UPDATE salary_payments SET status = ? WHERE id = ?', [$status, $id]);
    }

    public static function settings(int $userId): array
    {
        return self::selectOne('SELECT theme, language FROM user_settings WHERE user_id = ?', [$userId])
            ?? ['theme' => 'light', 'language' => 'en'];
    }

    public static function saveSettings(int $userId, string $theme, string $language): void
    {
        self::execute(
            'INSERT INTO user_settings (user_id, theme, language) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE theme = VALUES(theme), language = VALUES(language)',
            [$userId, $theme, $language]
        );
    }
}
