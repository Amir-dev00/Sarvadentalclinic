<?php

declare(strict_types=1);

/**
 * Tests for appointment cancellation SMS rendering + cancel eligibility helpers.
 * Run: php tools/test_appointment_cancellation.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Sarva\Services\AppointmentService;
use Sarva\Services\SmsTemplateRenderer;

$failed = 0;
$passed = 0;

function assert_eq(string $label, string $expected, string $actual): void
{
    global $failed, $passed;
    if ($expected === $actual) {
        $passed++;
        echo "PASS  {$label}\n";
        return;
    }
    $failed++;
    echo "FAIL  {$label}\n";
    echo "  expected: {$expected}\n";
    echo "  actual:   {$actual}\n";
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

assert_true('cancellable includes confirmed', in_array('confirmed', AppointmentService::cancellableStatuses(), true));
assert_true('cancellable includes awaiting_payment', in_array('awaiting_payment', AppointmentService::cancellableStatuses(), true));
assert_true('cancellable excludes cancelled', !in_array('cancelled', AppointmentService::cancellableStatuses(), true));
assert_true('cancellable excludes completed', !in_array('completed', AppointmentService::cancellableStatuses(), true));

$patient = [
    'first_name' => 'امیرحسین',
    'last_name' => 'زمان زاده',
    'mobile' => '09121111111',
    'file_number' => 'P1',
];
$appt = [
    'starts_at' => '2026-09-29 18:30:00',
    'doctor_name' => 'دکتر زمان زاده',
    'service_name' => 'ترمیم دندان',
    'cancellation_reason' => 'تعطیلی کلینیک',
];
$vars = SmsTemplateRenderer::varsFrom($patient, $appt);
assert_eq('cancellation_reason var', 'تعطیلی کلینیک', $vars['cancellation_reason']);
assert_eq('appointment_date from starts_at', '1405/07/07', $vars['appointment_date']);
assert_eq('appointment_time from starts_at', '18:30', $vars['appointment_time']);
assert_eq('full_name', 'امیرحسین زمان زاده', $vars['full_name']);

$tpl = "سلام #full_name#\nنوبت شما برای #appointment_date# ساعت #appointment_time# لغو شد.\n#cancellation_reason#\nتماس: #clinic_phone#";
$body = SmsTemplateRenderer::stripEmptyOptionalPlaceholders($tpl, $vars);
$rendered = SmsTemplateRenderer::renderForSend($body, $vars);
assert_true('render ok with reason', $rendered['ok']);
assert_true('has name', str_contains($rendered['message'], 'امیرحسین زمان زاده'));
assert_true('has date', str_contains($rendered['message'], '1405/07/07'));
assert_true('has time', str_contains($rendered['message'], '18:30'));
assert_true('has reason', str_contains($rendered['message'], 'تعطیلی کلینیک'));
assert_true('no raw placeholders', SmsTemplateRenderer::findUnresolvedPlaceholders($rendered['message']) === []);

echo "WITH REASON:\n{$rendered['message']}\n\n";

$varsEmpty = SmsTemplateRenderer::varsFrom($patient, [
    'starts_at' => '2026-09-29 18:30:00',
    'doctor_name' => 'دکتر زمان زاده',
    'service_name' => 'ترمیم',
    'cancellation_reason' => '',
]);
$tpl2 = "سلام #full_name#\nنوبت شما برای #appointment_date# ساعت #appointment_time# لغو شد.\n#cancellation_reason#\nدر صورت نیاز تماس بگیرید.";
$stripped = SmsTemplateRenderer::stripEmptyOptionalPlaceholders($tpl2, $varsEmpty);
assert_true('stripped removes empty reason token', !str_contains($stripped, '#cancellation_reason#'));
$r2 = SmsTemplateRenderer::renderForSend($stripped, $varsEmpty);
assert_true('empty reason render ok', $r2['ok']);
assert_true('empty reason no raw token', !str_contains($r2['message'], '#cancellation_reason#'));
assert_true('empty reason no ugly label leftover', !preg_match('/دلیل لغو\s*$/u', $r2['message']));

echo "WITHOUT REASON:\n{$r2['message']}\n\n";

$defaultTpl = "سلام #full_name#\nنوبت شما در کلینیک دندانپزشکی سروا برای تاریخ #appointment_date# ساعت #appointment_time# لغو شد.\n#cancellation_reason#\nدر صورت نیاز با کلینیک تماس بگیرید.\n#clinic_phone#";
$v = SmsTemplateRenderer::varsFrom($patient, $appt + ['cancellation_reason' => 'پزشک در این روز حضور ندارد']);
$v['clinic_phone'] = '02100000000';
$out = SmsTemplateRenderer::renderForSend(
    SmsTemplateRenderer::stripEmptyOptionalPlaceholders($defaultTpl, $v),
    $v
);
assert_true('default template ok', $out['ok']);
echo "EXAMPLE cancellation SMS:\n{$out['message']}\n\n";

echo "{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
