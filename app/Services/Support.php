<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Access;
use App\Core\HttpException;
use App\Models\CatalogModel;
use App\Models\FarmModel;
use App\Models\HariModel;
use App\Models\SeasonModel;

final class Support
{
    public static function text(array $in, string $key, int $max, bool $required = true): string
    {
        $value = trim((string)($in[$key] ?? ''));
        if ($required && $value === '') {
            throw new HttpException(422, 'Required field missing');
        }
        if (mb_strlen($value) > $max) {
            throw new HttpException(422, 'Value is too long');
        }
        return $value;
    }

    public static function positive(mixed $value, string $label, bool $allowZero = false): float
    {
        if (!is_numeric($value)) {
            throw new HttpException(422, 'Invalid ' . $label);
        }
        $n = num($value);
        if ($allowZero) {
            if ($n < 0) {
                throw new HttpException(422, 'Invalid ' . $label);
            }
        } elseif ($n <= 0) {
            throw new HttpException(422, 'Invalid ' . $label);
        }
        return $n;
    }

    public static function share(mixed $value): int
    {
        $n = (int)$value;
        if (!in_array($n, [0, 50, 100], true)) {
            throw new HttpException(422, 'Hari share must be 0, 50 or 100');
        }
        return $n;
    }

    public static function season(array $in): array
    {
        $name = trim((string)($in['season'] ?? ''));
        $row = SeasonModel::findByName($name);
        if (!$row) {
            throw new HttpException(422, 'Choose a valid season');
        }
        return $row;
    }

    public static function farmByName(string $name): array
    {
        $farm = FarmModel::findByName(trim($name));
        if (!$farm) {
            throw new HttpException(422, 'Choose a valid farm');
        }
        return $farm;
    }

    public static function writableFarm(array $user, string $name): array
    {
        $farm = self::farmByName($name);
        Access::assertFarm($user, (int)$farm['id']);
        if ((int)$farm['is_archived'] === 1) {
            throw new HttpException(422, 'This farm is archived');
        }
        return $farm;
    }

    public static function activeFarm(array $farm): void
    {
        if ((int)$farm['is_archived'] === 1) {
            throw new HttpException(422, 'This farm is archived');
        }
    }

    public static function assertHariLand(int $farmId, float $acres, float $seri, float $extra = 0): void
    {
        $used = FarmModel::hariPlotAcres($farmId);
        $hariLand = num($acres - $seri);
        if (num($used + $extra) > $hariLand + 0.001) {
            throw new HttpException(422, 'Hari plots exceed the farm hari land (total acres minus Seri)');
        }
    }

    public static function hariOnFarm(int $hariId, int $farmId): array
    {
        $hari = HariModel::find($hariId);
        if (!$hari || (int)$hari['farm_id'] !== $farmId) {
            throw new HttpException(422, 'Select Hari');
        }
        return $hari;
    }

    public static function item(string $item): string
    {
        if (!in_array($item, CatalogModel::ITEMS, true)) {
            throw new HttpException(422, 'Unknown stock item');
        }
        return $item;
    }

    public static function statusFor(array $user): string
    {
        return Access::isOffice($user) ? 'approved' : 'pending';
    }

    public static function approvalStamp(array $user, string $status): array
    {
        if ($status === 'approved') {
            return ['approved_by' => (int)$user['id'], 'approved_at' => date('Y-m-d H:i:s')];
        }
        return ['approved_by' => null, 'approved_at' => null];
    }

    public static function target(array $in, int $farmId): array
    {
        $target = (string)($in['target'] ?? '');
        if ($target === 'seri') {
            return ['type' => 'seri', 'hari_id' => null, 'label' => 'Seri'];
        }
        if (!ctype_digit($target)) {
            throw new HttpException(422, 'Select Hari');
        }
        $hari = self::hariOnFarm((int)$target, $farmId);
        return ['type' => 'hari', 'hari_id' => (int)$hari['id'], 'label' => $hari['name']];
    }

    public static function dateOrToday(array $in, string $key = 'date'): string
    {
        $value = trim((string)($in[$key] ?? ''));
        if ($value === '') {
            return date('Y-m-d');
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $value);
        if (!$dt || $dt->format('Y-m-d') !== $value) {
            throw new HttpException(422, 'Invalid date');
        }
        return $value;
    }
}
