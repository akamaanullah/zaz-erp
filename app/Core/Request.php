<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $post,
        public readonly array $json,
        public readonly array $headers,
        public readonly array $params = []
    ) {
    }

    public static function capture(string $method, string $uri, ?string $rawBody = null, array $headerOverrides = []): self
    {
        $method = strtoupper($method);
        $parts = parse_url($uri);
        $path = $parts['path'] ?? '/';
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $prefixes = [];
        if ($scriptDir !== '/' && $scriptDir !== '.' && $scriptDir !== '') {
            $prefixes[] = rtrim($scriptDir, '/');
            if (str_ends_with($prefixes[0], '/public')) {
                $prefixes[] = substr($prefixes[0], 0, -strlen('/public'));
            }
        }
        foreach ($prefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix)) ?: '/';
                break;
            }
        }
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = (string)$value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = (string)$_SERVER['CONTENT_TYPE'];
        }
        foreach ($headerOverrides as $key => $value) {
            $headers[(string)$key] = (string)$value;
        }

        $raw = $rawBody;
        if ($raw === null && $method !== 'GET' && $method !== 'HEAD') {
            $raw = file_get_contents('php://input') ?: '';
        }
        $raw = (string)$raw;
        $json = [];
        $contentType = $headers['Content-Type'] ?? '';
        if (str_contains($contentType, 'application/json') && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $json = $decoded;
            }
        }
        $post = $_POST;
        if ($raw !== '' && str_contains($contentType, 'application/x-www-form-urlencoded') && $post === []) {
            parse_str($raw, $parsed);
            if (is_array($parsed)) {
                $post = $parsed;
            }
        }

        return new self($method, $path === '' ? '/' : $path, $query, $post, $json, $headers);
    }

    public function withParams(array $params): self
    {
        return new self($this->method, $this->path, $this->query, $this->post, $this->json, $this->headers, $params);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->json)) {
            return $this->json[$key];
        }
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }
        return $default;
    }

    public function header(string $name): string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }
        return '';
    }

    public function wantsJson(): bool
    {
        $type = $this->header('Content-Type');
        $accept = $this->header('Accept');
        if ((str_contains($type, 'application/x-www-form-urlencoded') || str_contains($type, 'multipart/form-data'))
            && !str_contains($accept, 'application/json')) {
            return false;
        }
        return str_starts_with($this->path, '/api')
            || str_contains($accept, 'application/json')
            || str_contains($type, 'application/json');
    }
}
