<?php

declare(strict_types=1);

namespace Sarva\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $pdo = null;

    /** @param array<string, string> $config */
    public static function boot(array $config): void
    {
        if (self::$pdo !== null) {
            return;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        try {
            self::$pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            self::$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            self::$pdo->exec("SET CHARACTER SET utf8mb4");
            self::applyClinicTimezone(self::$pdo);
        } catch (PDOException $e) {
            // Allow boot without DB (first install / MySQL stopped). connection() will throw later.
            self::$pdo = null;
            if (PHP_SAPI === 'cli') {
                // Soft notice for CLI tools that will open their own connection.
                fwrite(STDERR, 'Note: default DB connection unavailable during boot: ' . $e->getMessage() . PHP_EOL);
            }
        }
    }

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            throw new RuntimeException('اتصال به پایگاه داده برقرار نیست.');
        }
        return self::$pdo;
    }

    public static function connected(): bool
    {
        return self::$pdo !== null;
    }

    public static function applyClinicTimezone(?PDO $pdo = null): void
    {
        $pdo = $pdo ?? self::$pdo;
        if ($pdo === null) {
            return;
        }
        $tz = 'Asia/Tehran';
        try {
            $val = $pdo->query("SELECT `value` FROM site_settings WHERE `key`='timezone' LIMIT 1");
            $found = $val ? (string) $val->fetchColumn() : '';
            if ($found !== '') {
                $tz = $found;
            }
        } catch (\Throwable) {
        }
        try {
            date_default_timezone_set($tz);
            $zone = new \DateTimeZone($tz);
            $now = new \DateTime('now', $zone);
            $offset = $zone->getOffset($now);
            $sign = $offset >= 0 ? '+' : '-';
            $abs = abs($offset);
            $sqlTz = sprintf('%s%02d:%02d', $sign, intdiv($abs, 3600), ($abs % 3600) / 60);
            $pdo->exec("SET time_zone = '{$sqlTz}'");
        } catch (\Throwable) {
            try {
                $pdo->exec("SET time_zone = '+03:30'");
            } catch (\Throwable) {
            }
        }
    }
}
