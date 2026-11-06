<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var list<array{0:string,1:string,2:array,3:array}> */
    private array $routes = [];

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function add(string $method, string $path, array $handler, array $middleware = []): void
    {
        $this->routes[] = [$method, $this->compile($path), $handler, $middleware];
    }

    public function dispatch(Request $request): array
    {
        foreach ($this->routes as [$method, $regex, $handler, $middleware]) {
            if ($method !== $request->method) {
                continue;
            }
            if (!preg_match($regex, $request->path, $matches)) {
                continue;
            }
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }
            return [$handler, $middleware, $request->withParams($params)];
        }
        throw new HttpException(404, 'Page not found');
    }

    private function compile(string $path): string
    {
        $pattern = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}#',
            static function (array $m): string {
                return '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')';
            },
            $path
        );
        return '#^' . $pattern . '$#';
    }
}
