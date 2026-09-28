<?php

declare(strict_types=1);

/**
 * Shared, non-interactive bootstrap for cPanel Cron Jobs.
 * All paths are absolute via __DIR__ — never depends on cwd.
 *
 * @return array{
 *   root:string,
 *   log_dir:string,
 *   log:callable,
 *   lock:resource|null,
 *   release:callable
 * }
 */
function sarva_cron_boot(string $lockName): array
{
    // Hardening for unattended cPanel cron
    @ini_set('display_errors', '0');
    @ini_set('log_errors', '1');
    @error_reporting(E_ALL);
    @ignore_user_abort(true);
    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }

    $root = dirname(__DIR__);
    $logDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . DIRECTORY_SEPARATOR . 'cron.log';
    $log = static function (string $message) use ($logFile): void {
        $line = '[' . date('c') . '] ' . $message . PHP_EOL;
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        // cPanel email/output capture (optional)
        echo $line;
    };

    // Prevent overlapping runs of the same job on shared hosting
    $lockPath = $logDir . DIRECTORY_SEPARATOR . $lockName . '.lock';
    $lock = @fopen($lockPath, 'c+');
    if ($lock === false) {
        $log('FATAL unable to open lock file: ' . $lockPath);
        exit(1);
    }
    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        $log(strtoupper($lockName) . ' skipped: previous run still active');
        fclose($lock);
        exit(0);
    }

    // Ensure relative requires inside the app resolve predictably
    @chdir($root);

    require $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';

    $release = static function () use ($lock): void {
        if (is_resource($lock)) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    };

    return [
        'root' => $root,
        'log_dir' => $logDir,
        'log' => $log,
        'lock' => $lock,
        'release' => $release,
    ];
}
