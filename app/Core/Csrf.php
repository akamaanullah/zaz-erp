<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        $token = Session::get('_csrf');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::put('_csrf', $token);
        }
        return $token;
    }

    public static function assert(Request $request): void
    {
        $sent = $request->header('X-CSRF-Token');
        if ($sent === '') {
            $sent = (string)$request->input('_csrf', '');
        }
        $known = Session::get('_csrf');
        if (!is_string($known) || $known === '' || !hash_equals($known, $sent)) {
            throw new HttpException(419, 'Security token expired. Refresh the page and try again.');
        }
    }
}
