<?php

declare(strict_types=1);

/**
 * cPanel Cron Job #2 — process SMS queue (send via SMS.ir) + automation rules.
 *
 * Recommended schedule: every 10 minutes
 * Command:
 * /usr/bin/php /home/ydlknymz/public_html/cron/process-sms-automation.php
 *
 * Safe, non-interactive, absolute-path based. No SSH/terminal/Supervisor required.
 */

require __DIR__ . '/_bootstrap.php';
$ctx = sarva_cron_boot('process-sms-automation');
/** @var callable(string):void $log */
$log = $ctx['log'];
/** @var callable():void $release */
$release = $ctx['release'];

use Sarva\Core\Database;
use Sarva\Services\AppointmentTomorrowReminderService;
use Sarva\Services\SmsAutomationService;
use Sarva\Services\SmsService;

try {
    if (!Database::connected()) {
        $log('process-sms-automation FATAL: database not connected');
        $release();
        exit(1);
    }

    $tz = (string) setting('timezone', config('app.timezone', 'Asia/Tehran'));
    if ($tz !== '') {
        date_default_timezone_set($tz);
    }

    $pdo = db();
    $sms = new SmsService($pdo);
    $auto = new SmsAutomationService($pdo, $sms);
    $tomorrow = new AppointmentTomorrowReminderService($pdo, $sms);

    $queuedAuto = 0;
    $queuedTomorrow = 0;

    try {
        $queuedAuto = $auto->enqueueDue();
    } catch (Throwable $e) {
        $log('process-sms-automation WARN automation enqueue failed: ' . $e->getMessage());
    }

    try {
        $tomorrowReport = $tomorrow->enqueueTomorrow(false, 0, null);
        $queuedTomorrow = (int) ($tomorrowReport['queued'] ?? 0);
        if (!empty($tomorrowReport['errors'])) {
            foreach ($tomorrowReport['errors'] as $err) {
                $log('process-sms-automation WARN tomorrow enqueue: ' . $err);
            }
        }
    } catch (Throwable $e) {
        $log('process-sms-automation WARN tomorrow enqueue failed: ' . $e->getMessage());
    }

    // Send queued SMS via SMS.ir (continues on per-message failure inside SmsService)
    $processed = $sms->processQueue(50);

    try {
        $pdo->exec(
            "UPDATE appointments SET status='expired'
             WHERE status='awaiting_payment' AND hold_expires_at IS NOT NULL
               AND hold_expires_at < NOW() AND deleted_at IS NULL"
        );
    } catch (Throwable $e) {
        $log('process-sms-automation WARN expire holds failed: ' . $e->getMessage());
    }

    $log(sprintf(
        'process-sms-automation OK queued_auto=%d queued_tomorrow=%d processed=%d sent=%d failed=%d cancelled=%d retried=%d',
        $queuedAuto,
        $queuedTomorrow,
        (int) ($processed['processed'] ?? 0),
        (int) ($processed['sent'] ?? 0),
        (int) ($processed['failed'] ?? 0),
        (int) ($processed['cancelled'] ?? 0),
        (int) ($processed['retried'] ?? 0)
    ));
} catch (Throwable $e) {
    $log('process-sms-automation FATAL: ' . $e->getMessage());
    $release();
    exit(1);
}

$release();
exit(0);
