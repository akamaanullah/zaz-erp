<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    public function __construct(protected Kernel $kernel)
    {
    }

    protected function user(): ?array
    {
        return $this->kernel->authUser();
    }

    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        $data['base'] = $this->kernel->basePath();
        $data['csrf'] = Csrf::token();
        $data['appName'] = (string)(config()['app']['name'] ?? 'ZAZ AGRICULTURE');
        $file = project_root() . '/views/' . $template . '.php';
        if (!is_file($file)) {
            throw new HttpException(500, 'View missing');
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return Response::html((string)ob_get_clean(), $status);
    }

    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $path): Response
    {
        if (!str_starts_with($path, 'http')) {
            $path = $this->kernel->basePath() . '/' . ltrim($path, '/');
        }
        return Response::redirect($path);
    }
}
