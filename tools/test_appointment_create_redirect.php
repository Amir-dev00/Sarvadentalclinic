<?php

declare(strict_types=1);

/**
 * Redirect after admin appointment create must not use booked patient_id as a list filter.
 * Run: php tools/test_appointment_create_redirect.php
 */

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

function appointmentCreateRedirect(int $patientId, int $returnPatientId, string $returnTo): string
{
    if ($returnTo !== '' && preg_match('#^/admin/patients/\d+(\?[\w=&%-]*)?$#', $returnTo)) {
        return $returnTo;
    }
    if ($returnPatientId > 0) {
        return '/admin/appointments?patient_id=' . $returnPatientId;
    }
    return '/admin/appointments';
}

assert_true(
    'general page ignores booked patient_id',
    appointmentCreateRedirect(3014, 0, '') === '/admin/appointments'
);
assert_true(
    'explicit return_patient_id is kept',
    appointmentCreateRedirect(3014, 3014, '') === '/admin/appointments?patient_id=3014'
);
assert_true(
    'explicit patient profile return_to is kept',
    appointmentCreateRedirect(3014, 0, '/admin/patients/3014?tab=appointments') === '/admin/patients/3014?tab=appointments'
);
assert_true(
    'open redirect is rejected',
    appointmentCreateRedirect(3014, 0, 'https://evil.example/admin/patients/1') === '/admin/appointments'
);
assert_true(
    'validation failure on general page stays unfiltered',
    appointmentCreateRedirect(3014, 0, '') === '/admin/appointments'
);

$routes = file_get_contents(dirname(__DIR__) . '/admin/routes.php') ?: '';
$create = '';
if (preg_match("/post\\('\\/admin\\/appointments\\/create'[\\s\\S]*?redirect\\(\\\$redirectTo\\);\\s*\\}\\);/", $routes, $m) === 1) {
    $create = $m[0];
}
assert_true('create route found', $create !== '');
assert_true(
    'create route does not filter by booked patient_id',
    $create !== '' && !str_contains($create, "\$redirectTo = '/admin/appointments?patient_id=' . \$patientId")
);

$tpl = file_get_contents(dirname(__DIR__) . '/templates/admin/appointments.php') ?: '';
assert_true(
    'return_patient_id only when page already has a selected patient',
    (bool) preg_match('/if \(\$selectedPatientId > 0\):[\s\S]*?name="return_patient_id"/', $tpl)
);
assert_true(
    'autocomplete sets patient_id only',
    str_contains($tpl, 'patientIdEl.value = String(item.id || \'\');')
        && !preg_match('/function selectPatient[\s\S]*?return_patient_id/', $tpl)
);

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
