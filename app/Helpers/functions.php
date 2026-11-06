<?php
declare(strict_types=1);

function config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require dirname(__DIR__, 2) . '/config/config.php';
    }
    return $cfg;
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function num(mixed $value): float
{
    return round((float)$value, 2);
}

function money_mul(float $a, float $b): float
{
    return round($a * $b, 2);
}

function project_root(): string
{
    return dirname(__DIR__, 2);
}

function app_log(string $message): void
{
    $dir = project_root() . '/storage/logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $line = date('Y-m-d H:i:s') . ' ' . $message . PHP_EOL;
    file_put_contents($dir . '/app.log', $line, FILE_APPEND);
}
