<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    private static bool $started = false;
    private static int $regenerations = 0;
    /** @var array<string,mixed> */
    private static array $test = [];

    public static function start(): void
    {
        if (self::$started) {
            return;
        }
        if (self::isTest()) {
            self::$started = true;
            return;
        }
        if (session_status() === PHP_SESSION_NONE) {
            $name = (string)(config()['app']['session_name'] ?? 'zaz_erp_sid');
            session_name($name);
            $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => $secure,
            ]);
            session_start();
        }
        self::$started = true;
    }

    public static function isTest(): bool
    {
        return getenv('ZAZ_TEST') === '1';
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        if (self::isTest()) {
            return self::$test[$key] ?? $default;
        }
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        self::start();
        if (self::isTest()) {
            self::$test[$key] = $value;
            return;
        }
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        self::start();
        if (self::isTest()) {
            unset(self::$test[$key]);
            return;
        }
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        self::start();
        self::$regenerations++;
        if (self::isTest()) {
            return;
        }
        session_regenerate_id(true);
    }

    public static function regenerations(): int
    {
        return self::$regenerations;
    }

    public static function destroy(): void
    {
        self::start();
        if (self::isTest()) {
            self::$test = [];
            return;
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool)$p['secure'], (bool)$p['httponly']);
        }
        session_destroy();
        self::$started = false;
    }

    public static function resetTest(): void
    {
        self::$test = [];
        self::$started = false;
    }

    public static function flash(string $key, string $message): void
    {
        $all = self::get('_flash', []);
        if (!is_array($all)) {
            $all = [];
        }
        $all[$key] = $message;
        self::put('_flash', $all);
    }

    public static function pullFlash(string $key): string
    {
        $all = self::get('_flash', []);
        if (!is_array($all) || !isset($all[$key])) {
            return '';
        }
        $message = (string)$all[$key];
        unset($all[$key]);
        self::put('_flash', $all);
        return $message;
    }
}
