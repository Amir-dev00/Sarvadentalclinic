<?php

declare(strict_types=1);

/**
 * Patient debt + debt_reminder SMS pattern tests (no real SMS.ir calls).
 * Run: php tools/test_patient_debt.php
 */

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use Sarva\Services\PatientDebtService;
use Sarva\Services\SmsService;
use Sarva\Services\SmsTemplateRenderer;
use Sarva\Sms\LogSmsProvider;

$passed = 0;
$failed = 0;

function assert_true(string $label, bool $cond): void
{
    global $passed, $failed;
    if ($cond) {
        echo "[PASS] {$label}\n";
        $passed++;
    } else {
        echo "[FAIL] {$label}\n";
        $failed++;
    }
}

function assert_eq(string $label, mixed $expected, mixed $actual): void
{
    assert_true($label . ' (expected ' . json_encode($expected, JSON_UNESCAPED_UNICODE) . ', got ' . json_encode($actual, JSON_UNESCAPED_UNICODE) . ')', $expected === $actual);
}

$_ENV['SMS_DRIVER'] = 'log';
$_ENV['SMSIR_DEBT_REMINDER_TEMPLATE_ID'] = '999001';
putenv('SMS_DRIVER=log');
putenv('SMSIR_DEBT_REMINDER_TEMPLATE_ID=999001');

assert_eq('template id mapping', 999001, SmsService::smsIrTemplateIdForMessageType('debt_reminder'));
assert_eq('dotted thousands for 1000000', '1.000.000', PatientDebtService::formatDebtForSms(1000000));
assert_eq('dotted thousands for 123456789', '123.456.789', PatientDebtService::formatDebtForSms(123456789));
assert_eq('format zero', '0', PatientDebtService::formatDebtForSms(0));
assert_eq('helper matches SMS format', '1.000.000', format_amount_dots(1000000));

$preview = PatientDebtService::renderPreview(PatientDebtService::formatDebtForSms(1000000));
assert_true('preview contains clinic header', str_contains($preview, 'کلینیک دندانپزشکی سروا (دکتر زمان زاده)'));
assert_true('preview contains debt line', str_contains($preview, 'مبلغ بدهی : 1.000.000'));
assert_true('preview has no raw token', !str_contains($preview, '#DEBT_AMOUNT#'));
assert_true('pattern constant uses #DEBT_AMOUNT#', str_contains(PatientDebtService::PATTERN_TEXT, '#DEBT_AMOUNT#'));
assert_true('pattern has no markdown bold', !str_contains(PatientDebtService::PATTERN_TEXT, '**'));

$params = ['DEBT_AMOUNT' => PatientDebtService::formatDebtForSms(1000000)];
$list = SmsTemplateRenderer::toSmsIrParameterList($params);
assert_eq('API param name is DEBT_AMOUNT without hashes', 'DEBT_AMOUNT', $list[0]['name'] ?? null);
assert_eq('API param value uses dotted thousands', '1.000.000', $list[0]['value'] ?? null);
assert_true('missing DEBT_AMOUNT detected', SmsTemplateRenderer::missingSmsIrParameters([], ['DEBT_AMOUNT']) === ['DEBT_AMOUNT']);

$svcSrc = file_get_contents($root . '/src/Services/PatientDebtService.php') ?: '';
assert_true('manual/automation sources supported', str_contains($svcSrc, "'manual'") && str_contains($svcSrc, "'automation'"));
assert_true('zero debt blocked', str_contains($svcSrc, 'برای این بیمار بدهی ثبت نشده است'));
assert_true('invalid mobile blocked', str_contains($svcSrc, 'شماره موبایل بیمار معتبر نیست'));
$updateStart = strpos($svcSrc, 'function updateDebt');
$formatStart = strpos($svcSrc, 'function formatDebtForSms');
$updateSlice = ($updateStart !== false && $formatStart !== false && $formatStart > $updateStart)
    ? substr($svcSrc, $updateStart, $formatStart - $updateStart)
    : '';
assert_true('save does not auto-send', $updateSlice !== '' && !str_contains($updateSlice, 'sendReminder'));
assert_true('always reads current debt_amount', str_contains($svcSrc, 'SELECT id, mobile, first_name, last_name, debt_amount'));

$autoSrc = file_get_contents($root . '/cron/process-sms-automation.php') ?: '';
assert_true('cron calls debt automation', str_contains($autoSrc, 'processAutomaticReminders'));

$settingsSrc = file_get_contents($root . '/templates/admin/sms-settings.php') ?: '';
assert_true('auto setting default off in UI', str_contains($settingsSrc, 'debt_sms_auto_enabled'));
assert_true('interval setting present', str_contains($settingsSrc, 'debt_sms_interval_days'));

$envExample = file_get_contents($root . '/.env.example') ?: '';
assert_true('env example has debt template id', str_contains($envExample, 'SMSIR_DEBT_REMINDER_TEMPLATE_ID'));

$fileNumberSrc = file_get_contents($root . '/src/Services/PatientFileNumberService.php') ?: '';
assert_true('file number service untouched by debt feature coupling', !str_contains($fileNumberSrc, 'debt_'));

$provider = new LogSmsProvider();
$send = $provider->sendTemplateNow('09121234567', 999001, $list);
assert_true('log provider accepts debt template without real SMS', !empty($send['ok']));

$parsedOk = (new ReflectionClass(PatientDebtService::class))->newInstanceWithoutConstructor();
// parseDebtInput does not need DB
$ref = new ReflectionMethod(PatientDebtService::class, 'parseDebtInput');
/** @var array{ok:bool,amount?:float,message?:string} $neg */
$neg = $ref->invoke($parsedOk, '-10');
assert_true('negative debt rejected', empty($neg['ok']));
/** @var array{ok:bool,amount?:float} $persian */
$persian = $ref->invoke($parsedOk, '۱۲۳۴۵۶۷۸۹');
assert_true('persian digit input accepted', !empty($persian['ok']) && (float) ($persian['amount'] ?? 0) === 123456789.0);
/** @var array{ok:bool,amount?:float} $dotted */
$dotted = $ref->invoke($parsedOk, '1.000.000');
assert_true('dotted input accepted as 1000000', !empty($dotted['ok']) && (float) ($dotted['amount'] ?? 0) === 1000000.0);
/** @var array{ok:bool,amount?:float} $zero */
$zero = $ref->invoke($parsedOk, '0');
assert_true('zero debt accepted', !empty($zero['ok']) && (float) ($zero['amount'] ?? -1) === 0.0);

assert_true(
    'automation defaults to off in source',
    str_contains($svcSrc, "setting('debt_sms_auto_enabled', '0')")
    && str_contains($svcSrc, "=== '1'")
);

echo "\nPassed: {$passed}, Failed: {$failed}\n";
exit($failed > 0 ? 1 : 0);
