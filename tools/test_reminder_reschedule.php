<?php

declare(strict_types=1);

/**
 * Same-day reminder reschedule: one batch per rule + date + send time.
 * Run: php tools/test_reminder_reschedule.php
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

$today = '2026-09-29';
$src = file_get_contents(dirname(__DIR__) . '/src/Services/SmsAutomationService.php') ?: '';
$routes = file_get_contents(dirname(__DIR__) . '/admin/crm-routes.php') ?: '';

assert_true('18:00 window opens', SmsAutomationService::dailyBatchShouldRun('18:00:00', '18:00:00', null, $today));
assert_true('18:01 stays inside the same window', SmsAutomationService::dailyBatchShouldRun('18:01:00', '18:00:00', '2026-09-29 18:00:05', $today));
assert_true('18:10 closes the 18:00 window', !SmsAutomationService::dailyBatchShouldRun('18:10:00', '18:00:00', null, $today));
assert_true('19:15 is before 19:16', !SmsAutomationService::isWithinDailySendWindow('19:15:59', '19:16:00'));
assert_true('19:16 opens the rescheduled window', SmsAutomationService::isWithinDailySendWindow('19:16:00', '19:16:00'));
assert_true('19:17 stays inside 19:16', SmsAutomationService::isWithinDailySendWindow('19:17:00', '19:16:00'));
assert_true('19:26 closes 19:16', !SmsAutomationService::isWithinDailySendWindow('19:26:00', '19:16:00'));
assert_true('19:16 does not match a rule moved to 20:00', !SmsAutomationService::isWithinDailySendWindow('19:16:00', '20:00:00'));
assert_true('20:00 opens after the later change', SmsAutomationService::isWithinDailySendWindow('20:00:00', '20:00:00'));
assert_true('returning to 18:00 is outside the evening window', !SmsAutomationService::isWithinDailySendWindow('19:16:00', '18:00:00'));

assert_true('runs unique key includes send time', str_contains($src, 'uniq_rule_run_date_time (rule_id, run_date, configured_send_time)'));
assert_true('old day-only unique key is migrated away', str_contains($src, 'DROP INDEX uniq_rule_run_date'));
assert_true('claim inserts configured_send_time', str_contains($src, 'INSERT INTO sms_automation_runs (rule_id, run_date, configured_send_time, started_at)'));
assert_true('duplicate claim is rejected', str_contains($src, "\$sqlState === '23000'") && str_contains($src, 'return false;'));
assert_true('finish updates only that schedule', str_contains($src, 'AND configured_send_time=?'));
assert_true('skip reason is the schedule, not the whole day', str_contains($src, "'reason' => 'schedule_already_executed'") && !str_contains($src, 'already_executed_today'));
assert_true('last_enqueued_at date is not a gate', !str_contains($src, "substr(\$last, 0, 10) === \$today"));
assert_true('unreminded appointments stay the filter', str_contains($src, 'a.reminder_sent_at IS NULL'));
assert_true(
    'appointment idempotency does not include send time',
    SmsAutomationService::reminderIdempotencyKey(1, 42, $today) === 'appointment_reminder:1:42:' . $today
    && !str_contains(SmsAutomationService::reminderIdempotencyKey(1, 42, $today), '19:16')
);
assert_true('save logs a reschedule without sending', str_contains($routes, 'SMS_AUTOMATION_RESCHEDULED') && !str_contains($routes, 'enqueueDue'));
assert_true('save says the new time can run today', str_contains($routes, 'زمان جدید از همین امروز قابل اجرا است.') && !str_contains($routes, 'از فردا اعمال می‌شود'));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
