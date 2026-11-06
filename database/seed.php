<?php
declare(strict_types=1);

if (!class_exists(\App\Core\Autoload::class, false)) {
    require dirname(__DIR__) . '/app/Core/Autoload.php';
    App\Core\Autoload::register();
}

$name = (string)config()['db']['name'];
if ($name !== 'zaz_agriculture') {
    fwrite(STDERR, "Refusing to seed database '{$name}'.\n");
    exit(1);
}

$seeded = (new App\Services\DemoSeeder())->seedIfEmpty();
if ($seeded) {
    fwrite(STDOUT, "Seeded zaz_agriculture. Account passwords were written to storage/dev-credentials.local.txt\n");
} else {
    fwrite(STDOUT, "Users already exist. Seed was not changed.\n");
}
