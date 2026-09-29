<?php

declare(strict_types=1);

/**
 * Regression tests: confirmation vs reminder template separation.
 * Run: php tools/test_sms_type_separation.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Sarva\Services\SmsService;
use Sarva\Services\SmsTemplateRenderer;

$root = dirname(__DIR__);
if (is_file($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

$failed = 0;
$passed = 0;

function assert_eq(string $label, mixed $expected, mixed $actual): void
{
    global $failed, $passed;
    if ($expected === $actual) {
        $passed++;
        echo "PASS  {$label}\n";
        return;
    }
    $failed++;
    echo "FAIL  {$label}\n";
    echo '  expected: ' . var_export($expected, true) . "\n";
    echo '  actual:   ' . var_export($actual, true) . "\n";
}

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

$confirm = SmsService::smsIrTemplateIdForMessageType('appointment_confirmation');
$reminder = SmsService::smsIrTemplateIdForMessageType('appointment_reminder');
$cancel = SmsService::smsIrTemplateIdForMessageType('appointment_cancellation');

assert_eq('confirmation template', 159898, $confirm);
assert_eq('reminder template', 822711, $reminder);
assert_eq('cancellation template', 296200, $cancel);
assert_true('confirmation != reminder', $confirm !== $reminder);
assert_true('confirmation != cancellation', $confirm !== $cancel);
assert_true('reminder != cancellation', $reminder !== $cancel);

// Source must not be allowed to imply template: inspect that match arms are distinct
$src = file_get_contents(dirname(__DIR__) . '/src/Services/SmsService.php') ?: '';
assert_true(
    'no confirmation?:reminder fallback in SmsService',
    !preg_match('/appointment_confirmation_template_id[^\n]*\?:[^\n]*appointment_reminder_template_id/', $src)
);
assert_true(
    'dispatchAppointmentTemplate exists',
    str_contains($src, 'function dispatchAppointmentTemplate')
);
assert_true(
    'logs smsir_confirmation_template_missing',
    str_contains($src, 'smsir_confirmation_template_missing')
);

// Idempotency key shapes
assert_eq('confirm key', 'appointment_confirmation:42', 'appointment_confirmation:42');
assert_eq('reminder key shape', 'appointment_reminder:42:rule:7', 'appointment_reminder:42:rule:7');
assert_eq('cancel key', 'appointment_cancellation:42', 'appointment_cancellation:42');
assert_eq(
    'tomorrow reminder key shape',
    'appointment_reminder:42:tomorrow:2026-10-02',
    'appointment_reminder:42:tomorrow:2026-10-02'
);

$params = SmsTemplateRenderer::buildSmsIrAppointmentParameters(
    ['first_name' => 'علی', 'last_name' => 'رضایی'],
    ['starts_at' => '2026-10-02 18:30:00']
);
assert_eq('confirm params FULL_NAME', 'علی رضایی', $params['FULL_NAME']);
assert_true('confirm has DATE', $params['APPOINTMENT_DATE'] !== '');
assert_eq('confirm TIME', '18:30', $params['APPOINTMENT_TIME']);

// AppointmentService create path must only call enqueueConfirmationSms (never reminder)
$apptSrc = file_get_contents(dirname(__DIR__) . '/src/Services/AppointmentService.php') ?: '';
assert_true(
    'createConfirmedByAdmin calls sendAppointmentConfirmationNow after commit',
    (bool) preg_match('/function createConfirmedByAdmin[\s\S]*?\$this->db->commit\(\);[\s\S]*?sendAppointmentConfirmationNow\(\$id\)/', $apptSrc)
);
assert_true(
    'confirmPaid calls sendAppointmentConfirmationNow',
    str_contains($apptSrc, 'sendAppointmentConfirmationNow($appointmentId)')
);
assert_true(
    'confirmation path does not enqueue',
    !str_contains($apptSrc, 'enqueueConfirmationSms')
);
assert_true(
    'createConfirmedByAdmin does not call reminder service',
    !preg_match('/function createConfirmedByAdmin[\s\S]*?sendAppointmentConfirmationNow\(\$id\);[\s\S]*?(AppointmentTomorrowReminderService|enqueueDue|enqueueTomorrow)/', $apptSrc)
);
assert_true(
    'direct send uses sendTemplateNow',
    str_contains($apptSrc, 'sendTemplateNow')
);
$confirmFn = '';
if (preg_match('/function sendAppointmentConfirmationNow\(.*?\n    \}/s', $apptSrc, $m) === 1) {
    $confirmFn = $m[0];
}
assert_true('confirmation method does not enqueue', $confirmFn !== '' && !str_contains($confirmFn, 'enqueue'));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
