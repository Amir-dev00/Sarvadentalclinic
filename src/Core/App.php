<?php

declare(strict_types=1);

namespace Sarva\Core;

final class App
{
    private static ?self $instance = null;

    /** @var array<string, mixed> */
    private array $config = [];

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function boot(string $root): void
    {
        $this->loadEnv($root);
        $this->config['app'] = require $root . '/config/app.php';
        $this->config['database'] = require $root . '/config/database.php';
        $this->config['sms'] = require $root . '/config/sms.php';
        $this->config['payment'] = require $root . '/config/payment.php';

        date_default_timezone_set((string) $this->config('app.timezone', 'Asia/Tehran'));

        Session::start(
            (string) $this->config('app.session.name', 'sarva_session'),
            (int) $this->config('app.session.lifetime', 7200)
        );

        Database::boot($this->config['database']);
    }

    private function loadEnv(string $root): void
    {
        $envFile = $root . '/.env';
        if (!is_file($envFile)) {
            return;
        }

        if (class_exists(\Dotenv\Dotenv::class)) {
            \Dotenv\Dotenv::createImmutable($root)->safeLoad();
            return;
        }

        // Minimal .env parser fallback before Composer install.
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\"'");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("$key=$value");
        }
    }

    public function config(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $value = $this->config;
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }
}
