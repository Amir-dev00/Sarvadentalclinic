<?php

declare(strict_types=1);

/**
 * cPanel Cron Job #1 — enqueue tomorrow appointment reminders (SMS.ir template).
 *
 * Recommended schedule: every 30 minutes
 * Command:
 * /usr/bin/php /home/ydlknymz/public_html/cron/send-tomorrow-reminders.php
 *
 * Safe, non-interactive, absolute-path based. No SSH/terminal/Supervisor required.
 */

require __DIR__ . '/_bootstrap.php';
$ctx = sarva_cron_boot('send-tomorrow-reminders');
/** @var callable(string):void $log */
$log = $ctx['log'];
/** @var callable():void $release */
$release = $ctx['release'];

use Sarva\Core\Database;
use Sarva\Services\AppointmentTomorrowReminderService;
use Sarva\Services\SmsService;

try {
    if (!Database::connected()) {
        $log('send-tomorrow-reminders FATAL: database not connected');
        $release();
        exit(1);
    }

    $tz = (string) setting('timezone', config('app.timezone', 'Asia/Tehran'));
    if ($tz !== '') {
        date_default_timezone_set($tz);
    }

    $pdo = db();
    $sms = new SmsService($pdo);
    $reminders = new AppointmentTomorrowReminderService($pdo, $sms);

    if ($reminders->templateId() <= 0) {
        $log('send-tomorrow-reminders WARN: SMSIR_APPOINTMENT_REMINDER_TEMPLATE_ID is not set');
    }

    $enqueue = $reminders->enqueueTomorrow(false, 0, null);

    // Also drain a batch of the queue so reminders are delivered even if the
    // second cron is delayed — duplicates are blocked by idempotency + reminder_sent_at.
    $processed = $sms->processQueue(50);

    $log(sprintf(
        'send-tomorrow-reminders OK candidates=%d queued=%d skipped=%d processed=%d sent=%d failed=%d cancelled=%d retried=%d template=%d',
        (int) ($enqueue['candidates'] ?? 0),
        (int) ($enqueue['queued'] ?? 0),
        (int) ($enqueue['skipped'] ?? 0),
        (int) ($processed['processed'] ?? 0),
        (int) ($processed['sent'] ?? 0),
        (int) ($processed['failed'] ?? 0),
        (int) ($processed['cancelled'] ?? 0),
        (int) ($processed['retried'] ?? 0),
        $reminders->templateId()
    ));

    if (!empty($enqueue['errors'])) {
        foreach ($enqueue['errors'] as $err) {
            $log('send-tomorrow-reminders ERROR ' . $err);
        }
    }
} catch (Throwable $e) {
    $log('send-tomorrow-reminders FATAL: ' . $e->getMessage());
    $release();
    exit(1);
}

$release();
exit(0);
