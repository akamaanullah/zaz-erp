<?php
declare(strict_types=1);

namespace App\Core;

final class Database
{
    private static ?\PDO $pdo = null;

    public static function pdo(): \PDO
    {
        if (self::$pdo instanceof \PDO) {
            return self::$pdo;
        }
        $c = config()['db'];
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $c['host'],
            $c['port'],
            $c['name'],
            $c['charset']
        );
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $class = getenv('ZAZ_QUERY_COUNT') === '1' ? CountingPDO::class : \PDO::class;
        self::$pdo = new $class($dsn, (string)$c['user'], (string)$c['pass'], $options);
        return self::$pdo;
    }

    public static function queryCount(): int
    {
        return self::$pdo instanceof CountingPDO ? self::$pdo->queries : 0;
    }

    public static function resetQueryCount(): void
    {
        if (self::$pdo instanceof CountingPDO) {
            self::$pdo->queries = 0;
        }
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }

    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

/**
 * Counts prepare/query/exec calls. Used only when ZAZ_QUERY_COUNT=1.
 */
final class CountingPDO extends \PDO
{
    public int $queries = 0;

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->queries++;
        return parent::prepare($query, $options);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
    {
        $this->queries++;
        if ($fetchMode === null) {
            return parent::query($query);
        }
        return parent::query($query, $fetchMode, ...$fetchModeArgs);
    }

    public function exec(string $statement): int|false
    {
        $this->queries++;
        return parent::exec($statement);
    }
}
