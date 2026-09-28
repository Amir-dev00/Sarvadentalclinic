<?php

declare(strict_types=1);

/**
 * Production cron: enqueue automation reminders and process SMS queue.
 *
 * cPanel example (every 10 minutes):
 * /usr/bin/php /home/USER/path/to/Sarvadental/cron/process-sms-automation.php
 */

$root = dirname(__DIR__);
require $root . '/includes/bootstrap.php';

use Sarva\Core\Database;
use Sarva\Services\SmsAutomationService;
use Sarva\Services\SmsService;

if (!Database::connected()) {
    fwrite(STDERR, "DB not connected\n");
    exit(1);
}

$tz = (string) setting('timezone', config('app.timezone', 'Asia/Tehran'));
if ($tz !== '') {
    date_default_timezone_set($tz);
}

$pdo = db();
$sms = new SmsService($pdo);
$auto = new SmsAutomationService($pdo, $sms);

$queued = $auto->enqueueDue();
$processed = $sms->processQueue(50);

$pdo->exec(
    "UPDATE appointments SET status='expired'
     WHERE status='awaiting_payment' AND hold_expires_at IS NOT NULL AND hold_expires_at < NOW() AND deleted_at IS NULL"
);

$line = sprintf(
    "[%s] queued=%d processed=%d sent=%d failed=%d cancelled=%d retried=%d\n",
    date('c'),
    $queued,
    $processed['processed'] ?? 0,
    $processed['sent'] ?? 0,
    $processed['failed'] ?? 0,
    $processed['cancelled'] ?? 0,
    $processed['retried'] ?? 0
);
$logDir = $root . '/storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
file_put_contents($logDir . '/cron.log', $line, FILE_APPEND | LOCK_EX);
echo $line;
