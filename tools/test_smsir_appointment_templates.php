<?php

declare(strict_types=1);

/**
 * Tests for SMS.ir appointment confirmation/cancellation parameter mapping.
 * Run: php tools/test_smsir_appointment_templates.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;
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

$patient = [
    'first_name' => 'امیرحسین',
    'last_name' => 'زمان زاده',
    'mobile' => '09121111111',
];
$appt = ['starts_at' => '2026-10-02 18:30:00'];

$params = SmsTemplateRenderer::buildSmsIrAppointmentParameters($patient, $appt);
assert_eq('FULL_NAME', 'امیرحسین زمان زاده', $params['FULL_NAME']);
assert_true('APPOINTMENT_DATE non-empty', $params['APPOINTMENT_DATE'] !== '');
assert_eq('APPOINTMENT_TIME', '18:30', $params['APPOINTMENT_TIME']);
assert_true('keys uppercase only', array_keys($params) === ['FULL_NAME', 'APPOINTMENT_DATE', 'APPOINTMENT_TIME']);
assert_true('no hash in keys', !str_contains(implode(',', array_keys($params)), '#'));
assert_true('no hash in values', !str_contains(implode('|', $params), '#'));

$list = SmsTemplateRenderer::toSmsIrParameterList($params);
assert_eq('list count', 3, count($list));
assert_eq('list[0].name', 'FULL_NAME', $list[0]['name']);
assert_eq('list[0].value', 'امیرحسین زمان زاده', $list[0]['value']);

assert_eq('missing none', [], SmsTemplateRenderer::missingSmsIrParameters($params));
assert_eq(
    'missing FULL_NAME',
    ['FULL_NAME'],
    SmsTemplateRenderer::missingSmsIrParameters([
        'FULL_NAME' => '',
        'APPOINTMENT_DATE' => '1405/07/10',
        'APPOINTMENT_TIME' => '18:30',
    ])
);

$confirmId = (int) ($_ENV['SMSIR_APPOINTMENT_CONFIRMATION_TEMPLATE_ID'] ?? 0);
$cancelId = (int) ($_ENV['SMSIR_APPOINTMENT_CANCELLATION_TEMPLATE_ID'] ?? 0);
assert_eq('env confirmation template', 159898, $confirmId);
assert_eq('env cancellation template', 296200, $cancelId);

$jalaliDate = to_jalali('2026-10-02 18:30:00', 'Y/m/d');
assert_eq('params date matches to_jalali', $jalaliDate, $params['APPOINTMENT_DATE']);
assert_eq('expected sample date', '1405/07/10', $params['APPOINTMENT_DATE']);

echo "\nMapped SMS.ir params:\n";
echo json_encode($params, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
echo "Confirm template ID: {$confirmId}\nCancel template ID: {$cancelId}\n";
echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
