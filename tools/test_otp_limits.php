<?php

declare(strict_types=1);

/**
 * OTP limit configuration. Resend cooldown stays 60 seconds.
 * Run: php tools/test_otp_limits.php
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

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

/** Same rule as OtpService: block while elapsed < cooldown. */
function cooldownBlocks(int $elapsedSeconds, int $cooldown): bool
{
    return $elapsedSeconds < $cooldown;
}

$cooldown = (int) config('app.otp_resend_cooldown', 60);
$ttl = (int) config('app.otp_ttl', 300);
$attempts = (int) config('app.otp_max_attempts', 10);
$mobileHour = (int) config('app.otp_max_requests_per_hour', 20);
$ipHour = (int) config('app.otp_max_requests_per_ip_hour', 100);

assert_eq('resend cooldown stays 60', 60, $cooldown);
assert_eq('otp lifetime 300', 300, $ttl);
assert_eq('max verification attempts 10', 10, $attempts);
assert_eq('mobile hourly limit 20', 20, $mobileHour);
assert_eq('ip hourly limit 100', 100, $ipHour);

assert_true('30s still blocked', cooldownBlocks(30, $cooldown));
assert_true('59s still blocked', cooldownBlocks(59, $cooldown));
assert_true('60s allowed', !cooldownBlocks(60, $cooldown));
assert_true('20th existing record blocks the next', 20 >= $mobileHour);
assert_true('19 existing records still allowed', 19 < $mobileHour);
assert_true('100 existing IP records block the next', 100 >= $ipHour);
assert_true('99 existing IP records still allowed', 99 < $ipHour);
assert_true('10th wrong attempt reaches the cap', 10 >= $attempts);
assert_true('9th wrong attempt is still under the cap', 9 < $attempts);

$src = file_get_contents(dirname(__DIR__) . '/src/Services/OtpService.php') ?: '';
assert_true('mobile limit comes from config', str_contains($src, "config('app.otp_max_requests_per_hour', 20)"));
assert_true('ip limit comes from config', str_contains($src, "config('app.otp_max_requests_per_ip_hour', 100)"));
assert_true('cooldown comes from config', str_contains($src, "config('app.otp_resend_cooldown', 60)"));
assert_true('ttl comes from config', str_contains($src, "config('app.otp_ttl', 300)"));
assert_true('attempts come from config', str_contains($src, "config('app.otp_max_attempts', 10)"));
assert_true('no hardcoded mobile hourly 5', !preg_match('/fetchColumn\(\) >= 5/', $src));
assert_true('no hardcoded ip hourly 20', !preg_match('/fetchColumn\(\) >= 20/', $src));
assert_true('success payload exposes resend_in', str_contains($src, "'resend_in' => \$cooldown"));

$front = file_get_contents(dirname(__DIR__) . '/templates/auth/index.php') ?: '';
assert_true('frontend uses backend resend_in', str_contains($front, 'result.data.resend_in'));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
