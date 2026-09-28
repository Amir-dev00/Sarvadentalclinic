<?php
/**
 * Render a page inside the main Smilico layout.
 * Usage in page templates:
 *   $content = ob_get_clean after capturing; OR use layout_render.
 */
function layout_render(string $viewPath, array $data = []): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require $viewPath;
    $content = ob_get_clean();
    require dirname(__DIR__) . '/layouts/main.php';
}
