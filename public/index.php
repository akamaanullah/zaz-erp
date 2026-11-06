<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/Autoload.php';
App\Core\Autoload::register();
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$kernel = new App\Core\Kernel();
$kernel->send($kernel->handle(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_SERVER['REQUEST_URI'] ?? '/'
));
