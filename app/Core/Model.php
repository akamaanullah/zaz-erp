<?php
declare(strict_types=1);

namespace App\Core;

abstract class Model
{
    protected static function select(string $sql, array $params = []): array
    {
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    protected static function selectOne(string $sql, array $params = []): ?array
    {
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    protected static function execute(string $sql, array $params = []): int
    {
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    protected static function insertId(): int
    {
        return (int)Database::pdo()->lastInsertId();
    }
}
