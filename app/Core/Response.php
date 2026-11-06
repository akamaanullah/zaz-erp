<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $headers = []
    ) {
    }

    public static function html(string $html, int $status = 200, array $headers = []): self
    {
        return new self($status, $html, ['Content-Type' => 'text/html; charset=utf-8'] + $headers);
    }

    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        return new self(
            $status,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"ok":false}',
            ['Content-Type' => 'application/json; charset=utf-8'] + $headers
        );
    }

    public static function redirect(string $to, int $status = 302): self
    {
        return new self($status, '', ['Location' => $to]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        $security = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; script-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'",
        ];
        foreach ($security as $name => $value) {
            if (!array_key_exists($name, $this->headers)) {
                header($name . ': ' . $value);
            }
        }
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
