<?php

declare(strict_types=1);

$root = require dirname(__DIR__) . '/includes/bootstrap.php';

use Sarva\Core\Router;

/**
 * When document root is /public, uploads may only exist under project /assets.
 * Serve (and self-heal copy into public/assets) so DB media paths stop 404ing.
 */
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
$assetPath = parse_url($requestUri, PHP_URL_PATH) ?: '';
$prefix = function_exists('url_path_prefix') ? url_path_prefix() : '';
if ($prefix !== '' && str_starts_with($assetPath, $prefix . '/')) {
    $assetPath = substr($assetPath, strlen($prefix)) ?: '/';
}
if (preg_match('#^/assets/(.+)$#', $assetPath, $m)) {
    $relative = str_replace('\\', '/', $m[1]);
    if ($relative !== '' && !str_contains($relative, '..')) {
        $publicFile = __DIR__ . '/assets/' . $relative;
        if (!is_file($publicFile)) {
            $source = asset_filesystem_path($relative);
            if ($source !== null && is_file($source)) {
                $destDir = dirname($publicFile);
                if (!is_dir($destDir)) {
                    @mkdir($destDir, 0755, true);
                }
                if (@copy($source, $publicFile)) {
                    @chmod($publicFile, 0644);
                }
                $serve = is_file($publicFile) ? $publicFile : $source;
                $mime = mime_content_type($serve) ?: 'application/octet-stream';
                header('Content-Type: ' . $mime);
                header('Content-Length: ' . (string) filesize($serve));
                header('Cache-Control: public, max-age=31536000, immutable');
                readfile($serve);
                exit;
            }
        }
    }
}

$router = new Router();
require $root . '/includes/routes.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = request_path();
if ($path === '/index.php') {
    $path = '/';
}

$router->dispatch($method, $path);
