<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\FarmModel;
use App\Models\UserModel;

final class Kernel
{
    private Router $router;

    public function __construct()
    {
        Session::start();
        Csrf::token();
        $this->router = new Router();
        $routes = require project_root() . '/config/routes.php';
        $routes($this->router);
    }

    public function handle(string $method, string $uri, ?string $rawBody = null, array $headers = []): Response
    {
        try {
            $request = Request::capture($method, $uri, $rawBody, $headers);
            [$handler, $middleware, $request] = $this->router->dispatch($request);
            foreach ($middleware as $name) {
                $this->runMiddleware($name, $request);
            }
            [$class, $action] = $handler;
            $controller = new $class($this);
            return $controller->$action($request);
        } catch (HttpException $e) {
            return $this->errorResponse($e->status, $e->getMessage(), $this->wantsJson($uri));
        } catch (\Throwable $e) {
            app_log($e::class . ' ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $message = (config()['app']['env'] ?? 'local') === 'local'
                ? 'Server error'
                : 'Server error';
            return $this->errorResponse(500, $message, $this->wantsJson($uri));
        }
    }

    public function send(Response $response): void
    {
        $response->send();
    }

    public function basePath(): string
    {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        if ($dir === '/' || $dir === '.' || $dir === '\\') {
            return '';
        }
        return rtrim($dir, '/');
    }

    public function authUser(): ?array
    {
        $id = Session::get('user_id');
        if (!$id) {
            return null;
        }
        $user = UserModel::find((int)$id);
        if (!$user || !(int)$user['is_active']) {
            Session::destroy();
            return null;
        }
        $idle = (int)(config()['app']['session_idle_seconds'] ?? 28800);
        $seen = Session::get('auth_seen');
        if ($seen !== null && (time() - (int)$seen) > $idle) {
            Session::destroy();
            return null;
        }
        $boundRole = Session::get('auth_role');
        $boundFarm = Session::get('auth_farm_id');
        if (is_string($boundRole) && ($boundRole !== (string)$user['role'] || (int)$boundFarm !== (int)($user['farm_id'] ?? 0))) {
            Session::destroy();
            return null;
        }
        if (is_string($boundRole)) {
            Session::put('auth_seen', time());
        }
        $user['farm_name'] = '';
        if (!empty($user['farm_id'])) {
            $farm = FarmModel::find((int)$user['farm_id']);
            $user['farm_name'] = $farm['name'] ?? '';
        }
        return $user;
    }

    public function actingAs(int $userId): void
    {
        Session::put('user_id', $userId);
        Csrf::token();
    }

    private function runMiddleware(string $name, Request $request): void
    {
        if ($name === 'auth') {
            if ($this->authUser() === null) {
                if ($request->wantsJson()) {
                    throw new HttpException(401, 'Sign in required');
                }
                throw new HttpException(302, $this->basePath() . '/login');
            }
            return;
        }
        if ($name === 'guest') {
            if ($this->authUser() !== null) {
                throw new HttpException(302, $this->basePath() . '/dashboard');
            }
            return;
        }
        if ($name === 'csrf') {
            Csrf::assert($request);
        }
    }

    private function wantsJson(string $uri): bool
    {
        $type = (string)($_SERVER['CONTENT_TYPE'] ?? '');
        $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
        if (str_contains($accept, 'application/json')) {
            return true;
        }
        if ((str_contains($type, 'application/x-www-form-urlencoded') || str_contains($type, 'multipart/form-data'))
            && !str_contains($accept, 'application/json')) {
            return false;
        }
        return str_contains($uri, '/api/');
    }

    private function errorResponse(int $status, string $message, bool $json): Response
    {
        if ($status === 302) {
            return Response::redirect($message);
        }
        if ($json) {
            return Response::json(['ok' => false, 'error' => $message], $status);
        }
        if ($status >= 400 && $status < 500 && !empty($_SERVER['HTTP_REFERER'])) {
            Session::flash('error', $message);
            return Response::redirect((string)$_SERVER['HTTP_REFERER']);
        }
        $base = $this->basePath();
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . e($message) . '</title>'
            . '<link rel="stylesheet" href="' . e($base) . '/assets/css/variables.css">'
            . '<link rel="stylesheet" href="' . e($base) . '/assets/css/layout.css">'
            . '<link rel="stylesheet" href="' . e($base) . '/assets/css/components.css">'
            . '<link rel="stylesheet" href="' . e($base) . '/assets/css/theme.css"></head><body>'
            . '<main class="content" style="max-width:720px;margin:48px auto"><div class="card"><h1>' . e($message) . '</h1>'
            . '<p class="muted">This action is not available for your account.</p>'
            . '<p><a class="btn" href="' . e($base) . '/dashboard">Back to dashboard</a></p></div></main></body></html>';
        if ($status === 403) {
            $html = str_replace('This action is not available for your account.', 'Access restricted. Your role cannot open this page.', $html);
        }
        return Response::html($html, $status);
    }
}
