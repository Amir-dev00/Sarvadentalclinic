<?php

declare(strict_types=1);

/**
 * CLI tests for SmsTemplateRenderer (#variable# canonical).
 * Run: php tools/test_sms_template_renderer.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

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

$vars = [
    'first_name' => 'امیرحسین',
    'last_name' => 'زمان زاده',
    'full_name' => 'امیرحسین زمان زاده',
    'patient_number' => 'P1001',
    'mobile' => '09120000000',
    'appointment_date' => '1405/07/07',
    'appointment_time' => '18:30',
    'doctor_name' => 'دکتر زمان زاده',
    'service_name' => 'ترمیم دندان',
    'clinic_name' => 'کلینیک دندانپزشکی سروا',
    'clinic_phone' => '02100000000',
];

// Individual variables
foreach ($vars as $key => $value) {
    assert_eq("single #{$key}#", $value, SmsTemplateRenderer::render('#' . $key . '#', $vars));
}

// Canonical multi-line example
$tpl = "سلام #full_name#\nنوبت شما در #clinic_name# برای تاریخ #appointment_date# ساعت #appointment_time# ثبت شد.";
$expected = "سلام امیرحسین زمان زاده\nنوبت شما در کلینیک دندانپزشکی سروا برای تاریخ 1405/07/07 ساعت 18:30 ثبت شد.";
assert_eq('canonical multi-line', $expected, SmsTemplateRenderer::render($tpl, $vars));

// Detailed appointment card
$tpl2 = "سلام #first_name# عزیز\nپزشک: #doctor_name#\nخدمت: #service_name#\nتاریخ: #appointment_date#\nساعت: #appointment_time#";
$expected2 = "سلام امیرحسین عزیز\nپزشک: دکتر زمان زاده\nخدمت: ترمیم دندان\nتاریخ: 1405/07/07\nساعت: 18:30";
assert_eq('appointment card', $expected2, SmsTemplateRenderer::render($tpl2, $vars));

// Legacy curly braces still work
assert_eq(
    'legacy {full_name}',
    'سلام امیرحسین زمان زاده',
    SmsTemplateRenderer::render('سلام {full_name}', $vars)
);

// Mixed syntax
assert_eq(
    'mixed # and {}',
    "سلام امیرحسین زمان زاده\nتاریخ 1405/07/07\nساعت 18:30",
    SmsTemplateRenderer::render("سلام #full_name#\nتاریخ {appointment_date}\nساعت #appointment_time#", $vars)
);

// Repeated placeholders
assert_eq(
    'repeated #full_name#',
    'امیرحسین زمان زاده عزیز، نوبت امیرحسین زمان زاده برای 1405/07/07 ثبت شد.',
    SmsTemplateRenderer::render('#full_name# عزیز، نوبت #full_name# برای #appointment_date# ثبت شد.', $vars)
);

// Normalize {known} → #known#
assert_eq(
    'normalizeToCanonical',
    'سلام #full_name# در #appointment_date#',
    SmsTemplateRenderer::normalizeToCanonical('سلام {full_name} در {appointment_date}')
);

// Do not rewrite unrelated braces
assert_eq(
    'preserve unrelated braces',
    'کد {promo2026} و #full_name#',
    SmsTemplateRenderer::normalizeToCanonical('کد {promo2026} و {full_name}')
);

// Unknown placeholder → empty + no raw leftover
assert_eq(
    'unknown → empty',
    'سلام',
    SmsTemplateRenderer::render('سلام #something_invalid#', $vars)
);

// Exact required confirmation template
$confirmTpl = "سلام #full_name#\nنوبت شما در کلینیک دندانپزشکی سروا (دکتر زمان زاده)\nبرای تاریخ #appointment_date#\nساعت #appointment_time#\nثبت شد.";
$confirmExpected = "سلام امیرحسین زمان زاده\nنوبت شما در کلینیک دندانپزشکی سروا (دکتر زمان زاده)\nبرای تاریخ 1405/07/07\nساعت 18:30\nثبت شد.";
$confirm = SmsTemplateRenderer::renderForSend($confirmTpl, $vars);
assert_true('confirm ok', $confirm['ok']);
assert_eq('confirm body', $confirmExpected, $confirm['message']);
assert_true('confirm no raw #', !preg_match('/#[a-zA-Z0-9_]+#/', $confirm['message']));
assert_true('confirm no raw {}', !preg_match('/\{[a-zA-Z0-9_]+\}/', $confirm['message']));

// varsFrom Jalali from starts_at
$patient = ['first_name' => 'امیرحسین', 'last_name' => 'زمان زاده', 'mobile' => '09121111111', 'file_number' => 'P1'];
$appt = [
    'starts_at' => '2026-09-29 18:30:00',
    'doctor_name' => 'دکتر زمان زاده',
    'service_name' => 'ترمیم دندان',
];
$from = SmsTemplateRenderer::varsFrom($patient, $appt);
assert_eq('varsFrom #full_name#', 'امیرحسین زمان زاده', $from['full_name']);
assert_eq('varsFrom #appointment_date#', '1405/07/07', $from['appointment_date']);
assert_eq('varsFrom #appointment_time#', '18:30', $from['appointment_time']);
assert_eq('varsFrom #doctor_name#', 'دکتر زمان زاده', $from['doctor_name']);
assert_eq('varsFrom #service_name#', 'ترمیم دندان', $from['service_name']);

$fromRendered = SmsTemplateRenderer::render(
    "سلام #full_name#\nنوبت شما در #clinic_name# برای تاریخ #appointment_date# ساعت #appointment_time# ثبت شد.",
    $from
);
assert_true('varsFrom render has name', str_contains($fromRendered, 'امیرحسین زمان زاده'));
assert_true('varsFrom render has date', str_contains($fromRendered, '1405/07/07'));
assert_true('varsFrom render has time', str_contains($fromRendered, '18:30'));
assert_true('varsFrom render no placeholders', SmsTemplateRenderer::findUnresolvedPlaceholders($fromRendered) === []);

echo "EXAMPLE:\n{$fromRendered}\n\n";

// Unresolved detection on raw template
$raw = SmsTemplateRenderer::findUnresolvedPlaceholders('سلام #full_name# در {appointment_date}');
assert_true('detects unresolved', in_array('full_name', $raw, true) && in_array('appointment_date', $raw, true));

// Admin preview parity
$sample = [
    'first_name' => 'علی', 'last_name' => 'رضایی', 'full_name' => 'علی رضایی',
    'patient_number' => 'P1001', 'mobile' => '09120000000',
    'appointment_date' => '1405/07/07', 'appointment_time' => '18:30',
    'doctor_name' => 'دکتر زمان زاده', 'service_name' => 'ویزیت',
    'clinic_name' => 'کلینیک دندانپزشکی سروا', 'clinic_phone' => '',
];
$previewTpl = 'سلام #full_name# نوبت {appointment_date} ساعت #appointment_time#';
$normalized = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '#$1#', $previewTpl) ?? $previewTpl;
$jsLike = preg_replace_callback('/#([a-zA-Z0-9_]+)#/', static function ($m) use ($sample) {
    return array_key_exists($m[1], $sample) ? $sample[$m[1]] : '';
}, $normalized) ?? $normalized;
assert_eq('admin preview parity', SmsTemplateRenderer::render($previewTpl, $sample), $jsLike);

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
