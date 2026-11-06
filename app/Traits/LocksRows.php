<?php
declare(strict_types=1);

namespace App\Traits;

use App\Core\Database;

trait LocksRows
{
    protected function lockFarm(int $farmId): void
    {
        $stmt = Database::pdo()->prepare('SELECT id FROM farms WHERE id = ? FOR UPDATE');
        $stmt->execute([$farmId]);
    }

    protected function lockCategoryName(string $name): void
    {
        $stmt = Database::pdo()->prepare('SELECT id FROM categories WHERE name = ? FOR UPDATE');
        $stmt->execute([$name]);
    }
}
