<?php
declare(strict_types=1);

if (!class_exists(\App\Core\Autoload::class, false)) {
    require dirname(__DIR__) . '/app/Core/Autoload.php';
    App\Core\Autoload::register();
}

function zaz_migrate(): void
{
    $cfg = config()['db'];
    $name = (string)$cfg['name'];
    if (!in_array($name, ['zaz_agriculture', 'zaz_agriculture_test', 'zaz_agriculture_e2e'], true)) {
        fwrite(STDERR, "Refusing to migrate database '{$name}'.\n");
        exit(1);
    }
    $server = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['charset']),
        (string)$cfg['user'],
        (string)$cfg['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $server->exec('CREATE DATABASE IF NOT EXISTS `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['port'], $name, $cfg['charset']),
        (string)$cfg['user'],
        (string)$cfg['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(191) NOT NULL PRIMARY KEY,
            applied_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $dir = __DIR__ . '/migrations';
    $files = glob($dir . '/*.sql') ?: [];
    sort($files);
    foreach ($files as $file) {
        $version = basename($file);
        $stmt = $pdo->prepare('SELECT version FROM schema_migrations WHERE version = ?');
        $stmt->execute([$version]);
        if ($stmt->fetch()) {
            continue;
        }
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException('Cannot read ' . $version);
        }
        $sql = preg_replace('/CREATE TABLE schema_migrations[\s\S]*?;\s*/', '', $sql, 1) ?? $sql;
        $parts = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
        foreach ($parts as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            $pdo->exec($statement);
        }
        $ins = $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, NOW())');
        $ins->execute([$version]);
        fwrite(STDOUT, "Applied {$version}\n");
    }
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    zaz_migrate();
}
