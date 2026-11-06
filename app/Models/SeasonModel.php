<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class SeasonModel extends Model
{
    public static function all(): array
    {
        return self::select('SELECT * FROM seasons ORDER BY sort_order, id');
    }

    public static function findByName(string $name): ?array
    {
        return self::selectOne('SELECT * FROM seasons WHERE name = ?', [$name]);
    }

    public static function insert(string $name, int $sort): int
    {
        self::execute('INSERT INTO seasons (name, sort_order) VALUES (?, ?)', [$name, $sort]);
        return self::insertId();
    }
}
