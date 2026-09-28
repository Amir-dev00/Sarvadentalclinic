<?php

declare(strict_types=1);

$root = require dirname(__DIR__) . '/includes/bootstrap.php';

use Sarva\Core\Router;

$router = new Router();
require $root . '/includes/routes.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = request_path();
if ($path === '/index.php') {
    $path = '/';
}

$router->dispatch($method, $path);
