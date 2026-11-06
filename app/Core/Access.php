<?php
declare(strict_types=1);

namespace App\Core;

final class Access
{
    public const ABILITIES = [
        'admin.farms',
        'admin.employees',
        'admin.salaries',
        'admin.godown',
        'admin.transfer.send',
        'admin.approvals',
        'admin.rates',
        'admin.rules',
        'admin.backup',
        'owner.manage',
        'farm.hari.view',
        'farm.hari.write',
        'farm.usage.write',
        'farm.ledger.view',
        'farm.reports',
        'farm.transfer.view',
        'farm.receive',
        'farm.daily.view',
        'farm.daily.write',
        'farm.expense.write',
        'farm.sale.write',
        'farm.advance.write',
        'audit.view',
        'settings.prefs',
    ];

    public static function isOffice(array $user): bool
    {
        return in_array($user['role'] ?? '', ['owner', 'office'], true);
    }

    public static function pages(string $role): array
    {
        $field = ['dashboard', 'hari', 'transfers', 'usage', 'daily', 'settlements', 'reports', 'audit', 'settings'];
        $accounts = ['expenses', 'sales', 'advances'];
        $office = ['farms', 'employees', 'salaries', 'godown', 'approvals', 'rates', 'rules'];
        return match ($role) {
            'owner', 'office' => array_merge($field, $accounts, $office),
            'accountant' => array_merge($field, $accounts),
            'supervisor' => $field,
            default => [],
        };
    }

    public static function can(array $user, string $ability): bool
    {
        $role = (string)($user['role'] ?? '');
        $office = in_array($role, ['owner', 'office'], true);
        $login = in_array($role, ['owner', 'office', 'supervisor', 'accountant'], true);
        return match ($ability) {
            'admin.farms', 'admin.employees', 'admin.salaries', 'admin.godown',
            'admin.transfer.send', 'admin.approvals', 'admin.rates', 'admin.rules', 'admin.backup' => $office,
            'owner.manage' => $role === 'owner',
            'farm.hari.view', 'farm.usage.write', 'farm.ledger.view', 'farm.reports',
            'farm.transfer.view', 'farm.daily.view', 'audit.view', 'settings.prefs' => $login,
            'farm.hari.write', 'farm.expense.write', 'farm.sale.write', 'farm.advance.write' => in_array($role, ['owner', 'office', 'accountant'], true),
            'farm.receive' => in_array($role, ['owner', 'supervisor'], true),
            'farm.daily.write' => in_array($role, ['owner', 'office', 'supervisor'], true),
            default => false,
        };
    }

    public static function names(array $user): array
    {
        return array_values(array_filter(self::ABILITIES, static fn (string $ability): bool => self::can($user, $ability)));
    }

    public static function assert(array $user, string $ability): void
    {
        if (!self::can($user, $ability)) {
            throw new HttpException(403, 'Access restricted');
        }
    }

    public static function assertPage(array $user, string $page): void
    {
        if (!in_array($page, self::pages((string)$user['role']), true)) {
            throw new HttpException(403, 'Access restricted');
        }
    }

    public static function assertFarm(array $user, int $farmId): void
    {
        if (self::isOffice($user)) {
            return;
        }
        if ((int)($user['farm_id'] ?? 0) !== $farmId) {
            throw new HttpException(403, 'Farm access denied');
        }
    }
}
