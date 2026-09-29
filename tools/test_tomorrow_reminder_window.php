<?php

declare(strict_types=1);

/**
 * Daily reminder window: once around send_time, not "any time after send_time".
 * Run: php tools/test_tomorrow_reminder_window.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Sarva\Services\SmsAutomationService;

$failed = 0;
$passed = 0;

function assert_true(string $label, bool $cond): void
{
    global $failed, $passed;
    if ($cond) {
        $passed++;
        echo "PASS  {$label}\n";
        return;
    }
    $failed++;
    echo "FAIL  {$label}\n";
}

$send = '18:00:00';
$today = '2026-09-29';

assert_true('before window', !SmsAutomationService::dailyBatchShouldRun('17:59:59', $send, null, $today));
assert_true('at send time', SmsAutomationService::dailyBatchShouldRun('18:00:00', $send, null, $today));
assert_true('18:05 still in window if not run', SmsAutomationService::dailyBatchShouldRun('18:05:00', $send, null, $today));
assert_true('18:05 blocked after today run', !SmsAutomationService::dailyBatchShouldRun('18:05:00', $send, '2026-09-29 18:00:12', $today));
assert_true('inside grace', SmsAutomationService::dailyBatchShouldRun('18:09:59', $send, null, $today));
assert_true('window closed at +10m', !SmsAutomationService::dailyBatchShouldRun('18:10:00', $send, null, $today));
assert_true('19:00 does not run', !SmsAutomationService::dailyBatchShouldRun('19:00:00', $send, null, $today));
assert_true('19:05 does not run', !SmsAutomationService::dailyBatchShouldRun('19:05:00', $send, null, $today));
assert_true('19:10 does not run', !SmsAutomationService::dailyBatchShouldRun('19:10:00', $send, null, $today));
assert_true('20:00 does not run', !SmsAutomationService::dailyBatchShouldRun('20:00:00', $send, null, $today));
assert_true('19:35 does not run', !SmsAutomationService::dailyBatchShouldRun('19:35:00', $send, null, $today));
assert_true('22:00 does not run', !SmsAutomationService::dailyBatchShouldRun('22:00:00', $send, null, $today));
assert_true('17:55 skip', !SmsAutomationService::dailyBatchShouldRun('17:55:00', $send, null, $today));
assert_true(
    'idempotency includes rule appointment run date',
    SmsAutomationService::reminderIdempotencyKey(3, 91, '2026-09-29') === 'appointment_reminder:3:91:2026-09-29'
);
assert_true('already ran today', !SmsAutomationService::dailyBatchShouldRun('18:02:00', $send, '2026-09-29 18:00:05', $today));
assert_true('ran yesterday so today ok', SmsAutomationService::dailyBatchShouldRun('18:02:00', $send, '2026-09-28 18:01:00', $today));
assert_true('custom 09:30 window', SmsAutomationService::isWithinDailySendWindow('09:33:00', '09:30:00', 10));
assert_true('custom 09:30 closed', !SmsAutomationService::isWithinDailySendWindow('09:40:00', '09:30:00', 10));

$src = file_get_contents(dirname(__DIR__) . '/src/Services/SmsAutomationService.php') ?: '';
assert_true('no open-ended >= send_time gate', !str_contains($src, "date('H:i:s') < \$sendTime"));
assert_true('claims daily run', str_contains($src, 'function claimDailyRun'));

$tomorrow = file_get_contents(dirname(__DIR__) . '/src/Services/AppointmentTomorrowReminderService.php') ?: '';
assert_true('legacy tomorrow enqueue disabled', str_contains($tomorrow, 'canonical_owner_sms_automation_daily_batch'));

$cron = file_get_contents(dirname(__DIR__) . '/cron/process-sms-automation.php') ?: '';
assert_true('main cron does not call enqueueTomorrow', !str_contains($cron, 'enqueueTomorrow'));

$legacyCron = file_get_contents(dirname(__DIR__) . '/cron/send-tomorrow-reminders.php') ?: '';
assert_true('legacy cron does not call enqueueTomorrow', !str_contains($legacyCron, 'enqueueTomorrow'));
assert_true('legacy cron delegates to canonical runner', str_contains($legacyCron, 'process-sms-automation.php'));
assert_true('tomorrow service has no appointment SQL', !str_contains($tomorrow, 'FROM appointments'));

$appt = file_get_contents(dirname(__DIR__) . '/src/Services/AppointmentService.php') ?: '';
assert_true(
    'create does not call reminder services',
    !preg_match('/function createConfirmedByAdmin[\s\S]*?sendAppointmentConfirmationNow\(\$id\)[\s\S]*?(AppointmentTomorrowReminderService|enqueueDue|enqueueTomorrow)/', $appt)
);

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
