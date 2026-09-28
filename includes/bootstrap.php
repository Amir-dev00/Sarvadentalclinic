<?php

declare(strict_types=1);

$root = dirname(__DIR__);

if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class) use ($root): void {
        if (!str_starts_with($class, 'Sarva\\')) {
            return;
        }
        $path = $root . '/src/' . str_replace('\\', '/', substr($class, 6)) . '.php';
        if (is_file($path)) {
            require $path;
        }
    });
    require $root . '/includes/helpers.php';
    require $root . '/includes/jalali.php';
}

use Sarva\Core\App;

App::getInstance()->boot($root);

return $root;
