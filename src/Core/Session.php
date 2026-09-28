<?php

declare(strict_types=1);

namespace Sarva\Core;

final class Session
{
    public static function start(string $name, int $lifetime): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_name($name);
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }
}
