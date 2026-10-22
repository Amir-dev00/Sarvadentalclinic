<?php

declare(strict_types=1);

/**
 * 19:00 slot eligibility + sequential file_number allocation.
 * Run: php tools/test_file_number_and_slot_1900.php
 */

$root = dirname(__DIR__);
require $root . '/includes/bootstrap.php';

use Sarva\Services\PatientFileNumberService;

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

$slotsSrc = file_get_contents($root . '/src/Services/AppointmentService.php') ?: '';
$migration = file_get_contents($root . '/database/add_visit_slot_1900.sql') ?: '';
$routes = file_get_contents($root . '/includes/routes.php') ?: '';
$dash = file_get_contents($root . '/templates/patient/dashboard.php') ?: '';
$fileSvc = file_get_contents($root . '/src/Services/PatientFileNumberService.php') ?: '';
$crm = file_get_contents($root . '/src/Services/PatientCrmService.php') ?: '';
$seed = file_get_contents($root . '/database/sarva_production.sql') ?: '';

assert_true(
    'slot loop requires start+duration <= end_time',
    str_contains($slotsSrc, '$t + ($durationMinutes * 60) <= $end')
);
assert_true(
    'migration extends 19:00 schedules to 19:30 only',
    str_contains($migration, "end_time = '19:30:00'")
    && str_contains($migration, "WHERE end_time = '19:00:00'")
    && str_contains($migration, 'AND is_active = 1')
);
assert_true(
    'migration uses site_settings key/value',
    str_contains($migration, 'UPDATE site_settings')
    && str_contains($migration, '`key` = \'working_hours\'')
    && !str_contains($migration, 'UPDATE settings')
);
assert_true('seed schedules already close at 19:30', str_contains($seed, "'10:00:00', '19:30:00', 30"));
assert_true('closed Friday remains closed in seed', (bool) preg_match("/\\(6, 5, 0, NULL, NULL\\)/", $seed));

assert_true('registration uses PatientFileNumberService', str_contains($routes, 'PatientFileNumberService') && str_contains($routes, 'createRegisteredPatient'));
assert_true('registration no longer inserts without file_number', !preg_match("/INSERT INTO patients \\(public_code, first_name, last_name, mobile, profile_completed\\)/", $routes));
$maxFn = '';
if (preg_match('/function highestNumericFileNumber\(.*?\n    \}/s', $fileSvc, $m) === 1) {
    $maxFn = $m[0];
}
assert_true(
    'allocator MAX includes soft-deleted rows',
    $maxFn !== ''
    && str_contains($maxFn, "WHERE file_number REGEXP '^[0-9]+$'")
    && !str_contains($maxFn, 'deleted_at')
);
assert_true('allocator uses GET_LOCK', str_contains($fileSvc, 'GET_LOCK'));
assert_true('allocator holds lock through insert', str_contains($fileSvc, 'withFileNumberLock'));
assert_true('CRM suggest uses shared service', str_contains($crm, 'PatientFileNumberService') && str_contains($crm, 'suggestNext()'));
assert_true('admin create allocates when file blank', str_contains($crm, 'createWithAllocatedNumber'));
assert_true('dashboard shows شماره پرونده', str_contains($dash, 'شماره پرونده'));
assert_true('dashboard does not label public_code as patient code', !str_contains($dash, 'کد بیمار:'));
assert_true('legacy empty file number shows ثبت نشده', str_contains($dash, 'ثبت نشده'));
assert_true('Persian digit helper exists', function_exists('normalize_digits') && normalize_digits('۲۶۱۰') === '2610');

echo "\n-- database --\n";
try {
    $db = db();
    $db->query('SELECT 1');
} catch (Throwable $e) {
    echo 'SKIP  database checks (' . $e->getMessage() . ")\n";
    echo "\n{$passed} passed, {$failed} failed\n";
    exit($failed > 0 ? 1 : 0);
}

$svc = new PatientFileNumberService($db);
try {
    $before = $svc->highestNumericFileNumber();
    assert_true('highest numeric is non-negative', $before >= 0);

    $marker = 'fn' . bin2hex(random_bytes(3));
    $db->beginTransaction();
    try {
        $a = $svc->createRegisteredPatient('0900' . substr((string) time(), -7), 'تست', 'الف' . $marker);
        $b = $svc->createRegisteredPatient('0901' . substr((string) (time() + 1), -7), 'تست', 'ب' . $marker);
        assert_true('first registration allocated a file number', !empty($a['ok']) && !empty($a['file_number']) && ctype_digit((string) $a['file_number']));
        assert_true('second registration allocated a file number', !empty($b['ok']) && !empty($b['file_number']) && ctype_digit((string) $b['file_number']));
        assert_true(
            'consecutive registrations are sequential',
            (int) $b['file_number'] === (int) $a['file_number'] + 1
        );
        assert_true(
            'allocated numbers are above previous max',
            (int) $a['file_number'] > $before
        );

        $loginAgain = $svc->createRegisteredPatient(
            // reuse mobile from first insert
            (string) $db->query('SELECT mobile FROM patients WHERE id=' . (int) $a['id'])->fetchColumn(),
            'تست',
            'دوباره'
        );
        assert_true('existing mobile does not allocate another number', !empty($loginAgain['already_exists']) && (int) $loginAgain['id'] === (int) $a['id']);
    } finally {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $failed++;
    echo 'FAIL  database scenario threw ' . $e->getMessage() . "\n";
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
