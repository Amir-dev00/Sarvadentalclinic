<?php

declare(strict_types=1);

namespace Sarva\Core;

final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function validate(?string $token): bool
    {
        if ($token === null || empty($_SESSION[self::KEY])) {
            return false;
        }
        return hash_equals($_SESSION[self::KEY], $token);
    }

    public static function assertValid(): void
    {
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!self::validate(is_string($token) ? $token : null)) {
            http_response_code(419);
            if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'message' => 'نشست منقضی شده است. صفحه را تازه کنید.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            echo 'نشست منقضی شده است. صفحه را تازه کنید.';
            exit;
        }
    }
}
