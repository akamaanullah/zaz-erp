<?php
declare(strict_types=1);

namespace App\Core;

final class Autoload
{
    public static function register(): void
    {
        spl_autoload_register(static function (string $class): void {
            $prefix = 'App\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
            $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . $relative . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
        require dirname(__DIR__) . '/Helpers/functions.php';
        date_default_timezone_set((string)(config()['app']['timezone'] ?? 'Asia/Karachi'));
    }
}
