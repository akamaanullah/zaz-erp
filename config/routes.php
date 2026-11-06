<?php
declare(strict_types=1);

use App\Controllers\ApiController;
use App\Controllers\AuthController;
use App\Controllers\PageController;
use App\Core\Access;
use App\Core\Router;

return static function (Router $router): void {
    $router->get('/', [AuthController::class, 'home']);
    $router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
    $router->post('/login', [AuthController::class, 'login'], ['guest', 'csrf']);
    $router->post('/logout', [AuthController::class, 'logout'], ['auth', 'csrf']);

    $router->get('/api/bootstrap', [ApiController::class, 'bootstrap'], ['auth']);
    $router->get('/api/backup', [ApiController::class, 'backup'], ['auth']);
    $router->post('/api/{action:.+}', [ApiController::class, 'post'], ['auth', 'csrf']);

    foreach (array_unique(array_merge(
        Access::pages('owner'),
        Access::pages('accountant'),
        Access::pages('supervisor')
    )) as $page) {
        $router->get('/' . $page, [PageController::class, 'page'], ['auth']);
        $router->get('/' . $page . '.html', [PageController::class, 'page'], ['auth']);
    }
};
