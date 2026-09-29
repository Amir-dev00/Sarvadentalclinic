<?php

declare(strict_types=1);

/**
 * Canonical SMS cron. Schedule ONLY this file, every 5 minutes.
 * Do not put a clock time in cPanel. send_time comes from the database.
 *
 * Command:
 * /usr/local/bin/php -q /home3/ydlknymz/sarvadentalclinic.ir/cron/process-sms-automation.php >> /home3/ydlknymz/sarvadentalclinic.ir/storage/sms-cron.log 2>&1
 *
 * The tomorrow-reminder time is NOT set here. Each active rule's send_time
 * is read from sms_automation_rules. Changing it in the admin panel takes
 * effect on the next run. The batch runs once per day, only inside a
 * 10-minute window starting at that send_time (application timezone).
 *
 * Do not also schedule cron/send-tomorrow-reminders.php.
 */

require __DIR__ . '/_bootstrap.php';
$ctx = sarva_cron_boot('process-sms-automation');
/** @var callable(string):void $log */
$log = $ctx['log'];
/** @var callable():void $release */
$release = $ctx['release'];

use Sarva\Core\Database;
use Sarva\Services\SmsAutomationService;
use Sarva\Services\SmsService;

try {
    if (!Database::connected()) {
        $log('process-sms-automation FATAL: database not connected');
        $release();
        exit(1);
    }

    $pdo = db();
    $sms = new SmsService($pdo);
    $auto = new SmsAutomationService($pdo, $sms);
    $now = SmsAutomationService::appNow();
    date_default_timezone_set($now->getTimezone()->getName());

    $queuedAuto = 0;
    try {
        $queuedAuto = $auto->enqueueDue();
    } catch (Throwable $e) {
        $log('process-sms-automation WARN automation enqueue failed: ' . $e->getMessage());
    }

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

    $stats = $auto->lastCronStats();
    $log(sprintf(
        'SMS_AUTOMATION_CRON checked_rules=%d daily_reminder_due=%d reminder_queued=%d queue_processed=%d sent=%d failed=%d',
        (int) ($stats['checked_rules'] ?? 0),
        (int) ($stats['daily_reminder_due'] ?? 0),
        (int) ($stats['reminder_queued'] ?? $queuedAuto),
        (int) ($processed['processed'] ?? 0),
        (int) ($processed['sent'] ?? 0),
        (int) ($processed['failed'] ?? 0)
    ));
} catch (Throwable $e) {
    $log('process-sms-automation FATAL: ' . $e->getMessage());
    $release();
    exit(1);
}

$release();
exit(0);
