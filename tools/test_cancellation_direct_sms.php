<?php

declare(strict_types=1);

/**
 * Cancellation SMS is a direct SMS.ir send after commit, not a queue job.
 * Run: php tools/test_cancellation_direct_sms.php
 */

$root = dirname(__DIR__);
$appt = file_get_contents($root . '/src/Services/AppointmentService.php') ?: '';
$provider = file_get_contents($root . '/src/Sms/SmsIrProvider.php') ?: '';
$routes = file_get_contents($root . '/admin/routes.php') ?: '';
$helpers = file_get_contents($root . '/includes/helpers.php') ?: '';
$cron = file_get_contents($root . '/cron/process-sms-automation.php') ?: '';

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

function slice_fn(string $src, string $name): string
{
    $start = strpos($src, 'function ' . $name);
    if ($start === false) {
        return '';
    }
    $next = strpos($src, "\n    public function ", $start + 10);
    $nextPrivate = strpos($src, "\n    private function ", $start + 10);
    $end = $next === false ? $nextPrivate : ($nextPrivate === false ? $next : min($next, $nextPrivate));
    return $end === false ? substr($src, $start) : substr($src, $start, $end - $start);
}

$cancel = slice_fn($appt, 'cancelAppointment');
$send = slice_fn($appt, 'sendAppointmentCancellationNow');
$bulk = slice_fn($appt, 'cancelAppointments');
$day = slice_fn($appt, 'cancelAppointmentsForDay');
$statusRoute = substr($routes, (int) strpos($routes, "\$router->post('/admin/appointments/status'"));
$statusRoute = substr($statusRoute, 0, (int) strpos($statusRoute, "\$router->post('/admin/appointments/cancel'"));

assert_true('direct cancellation method exists', $send !== '');
assert_true('queue enqueue method is gone', !str_contains($appt, 'function enqueueCancellationSms') && !str_contains($appt, 'enqueueSmsIrTemplate'));
assert_true('cancel commits before the SMS call', (bool) preg_match('/function cancelAppointment[\s\S]*?\$this->db->commit\(\);[\s\S]*?sendAppointmentCancellationNow\(\$appointmentId\)/', $appt));
assert_true('pending reminders are cancelled before the direct send', (bool) preg_match('/cancelPendingForAppointment\(\$appointmentId\);[\s\S]*?sendAppointmentCancellationNow\(\$appointmentId\)/', $cancel));
assert_true('direct send refuses an open transaction', str_contains($send, 'inTransaction()'));
assert_true('template id comes from the cancellation message type', str_contains($send, "smsIrTemplateIdForMessageType('appointment_cancellation')"));
assert_true('idempotency key is appointment_cancellation', str_contains($send, "'appointment_cancellation:' . \$appointmentId"));
assert_true('legacy cancel key is still recognized', str_contains($send, "'appointment_cancel:' . \$appointmentId"));
assert_true('direct send uses sendTemplateNow', str_contains($send, 'sendTemplateNow'));
assert_true('parameters are only the three template fields', str_contains($send, "'FULL_NAME'") && str_contains($send, "'APPOINTMENT_DATE'") && str_contains($send, "'APPOINTMENT_TIME'") && !str_contains($send, 'CANCELLATION_REASON'));
assert_true('timing log is APPOINTMENT_CANCELLATION_SMS', str_contains($send, 'APPOINTMENT_CANCELLATION_SMS'));
assert_true('provider failure does not throw the cancellation away', str_contains($send, 'نوبت لغو شد، اما ارسال پیامک لغو با خطا مواجه شد.'));
assert_true('already-cancelled path returns before commit and SMS', (bool) preg_match("/status' => 'already_cancelled'[\\s\\S]*?\\];[\\s\\S]*?\\\$this->db->commit\\(\\)/", $cancel));
assert_true('bulk cancel calls single cancel', str_contains($bulk, 'cancelAppointment('));
assert_true('bulk sends are chunked', str_contains($bulk, 'array_chunk($ids, 5)'));
assert_true('day cancel uses the same batch', str_contains($day, 'cancelAppointments('));
assert_true('status dropdown sends the cancellation SMS', str_contains($statusRoute, 'cancelAppointment(') && !str_contains($statusRoute, "false // status dropdown"));
assert_true('admin copy no longer says the cancellation is queued', !str_contains($routes, 'پیام لغو برای بیمار در صف ارسال قرار گرفت') && !str_contains($helpers, 'پیامک در صف ارسال قرار گرفت'));
assert_true('success flash mentions an immediate send', str_contains($helpers, 'نوبت با موفقیت لغو شد و پیامک لغو برای بیمار ارسال شد.'));
assert_true('duplicate flash is distinct', str_contains($helpers, 'پیامک لغو قبلاً ارسال شده است.'));
assert_true('explicit no-sms flash remains', str_contains($helpers, 'نوبت لغو شد؛ پیامک ارسال نشد.'));
assert_true('direct verify timeout is 10 seconds', str_contains($provider, 'CONFIRM_TIMEOUT = 10'));
assert_true('direct verify connect timeout is 5 seconds', str_contains($provider, 'CONFIRM_CONNECT_TIMEOUT = 5'));
assert_true('cron runner is not referenced by cancellation', !str_contains($send, 'process-sms-automation') && !str_contains($cancel, 'process-sms-automation'));
assert_true('cron file still exists for reminders', str_contains($cron, 'enqueueDue') || str_contains($cron, 'SmsAutomationService'));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
