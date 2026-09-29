<?php

declare(strict_types=1);

/**
 * Unified patient phone + OTP flow.
 * Run: php tools/test_patient_auth_flow.php
 */

$root = dirname(__DIR__);
$routes = file_get_contents($root . '/includes/routes.php') ?: '';
$auth = file_get_contents($root . '/templates/auth/index.php') ?: '';
$dash = file_get_contents($root . '/templates/patient/dashboard.php') ?: '';
$helpers = file_get_contents($root . '/includes/helpers.php') ?: '';
$authClass = file_get_contents($root . '/src/Core/Auth.php') ?: '';

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

function slice_between(string $src, string $start, string $end): string
{
    $from = strpos($src, $start);
    if ($from === false) {
        return '';
    }
    $to = strpos($src, $end, $from + strlen($start));
    if ($to === false) {
        return substr($src, $from);
    }
    return substr($src, $from, $to - $from);
}

$verify = slice_between($routes, "\$router->post('/api/auth/otp/verify'", "\$router->post('/api/auth/register'");
$register = slice_between($routes, "\$router->post('/api/auth/register'", "\$router->post('/auth/logout'");
$appointment = slice_between($routes, "\$router->get('/appointment'", "\$router->get('/api/appointment/slots'");
$authGet = slice_between($routes, "\$router->get('/auth'", "\$router->post('/api/auth/otp/request'");
$profile = slice_between($routes, "\$router->post('/patient/profile'", "\$router->get('/appointment'");

assert_true('auth page has no login/register switch', !str_contains($auth, 'authSwitch') && !str_contains($auth, "data-mode=\"login\"") && !str_contains($auth, 'setMode'));
assert_true('auth title is the patient panel', str_contains($auth, 'ورود به پنل بیمار'));
assert_true('otp request is unchanged', str_contains($auth, "postForm('/api/auth/otp/request'"));
assert_true('otp verify is unchanged', str_contains($auth, "postForm('/api/auth/otp/verify'"));
assert_true('frontend still uses resend_in', str_contains($auth, 'result.data.resend_in'));
assert_true('new patient sees the name form', str_contains($auth, 'result.data.is_new || result.data.needs_profile') && str_contains($auth, 'showStep(\'profile\')'));
assert_true('existing patient goes to the panel', str_contains($auth, "window.location.href = apiUrl('/patient')"));
assert_true('registration button label', str_contains($auth, 'تکمیل ثبت‌نام و ورود'));
assert_true('auth page does not send users to appointment', !str_contains($auth, '/appointment'));

assert_true('existing verify logs in and returns the panel', str_contains($verify, 'Auth::loginPatient') && str_contains($verify, "url('/patient')") && str_contains($verify, "'is_new' => false"));
assert_true('new verify stores only the mobile', str_contains($verify, "\$_SESSION['otp_verified_mobile'] = \$normalized") && str_contains($verify, "'needs_profile' => true"));
assert_true('verify clears a stale appointment redirect', str_contains($verify, "unset(\$_SESSION['auth_redirect'])") || str_contains($verify, "unset(\$_SESSION['otp_verified_mobile'], \$_SESSION['auth_redirect'])"));
assert_true('verify does not read auth_redirect as a destination', !str_contains($verify, "\$_SESSION['auth_redirect'] ??"));

assert_true('register requires the verified mobile', str_contains($register, 'otp_verified_mobile'));
assert_true('register checks the mobile again before insert', str_contains($register, 'SELECT id FROM patients WHERE mobile = ? AND deleted_at IS NULL'));
assert_true('register creates an incomplete profile', str_contains($register, 'profile_completed) VALUES (?,?,?,?,0)'));
assert_true('register uses loginPatient', str_contains($register, 'Auth::loginPatient'));
assert_true('register audits patient.register', str_contains($register, "audit('patient.register'"));
assert_true('register clears the otp mobile', str_contains($register, "unset(\$_SESSION['otp_verified_mobile'], \$_SESSION['auth_redirect'])"));
assert_true('register always returns the panel', str_contains($register, "url('/patient')") && !str_contains($register, '/appointment'));
assert_true('duplicate mobile logs in instead of inserting', substr_count($register, 'Auth::loginPatient') >= 2);

assert_true('guest appointment does not store a post-auth destination', !str_contains($appointment, "auth_redirect'] = '/appointment'") && str_contains($appointment, "redirect('/auth')"));
assert_true('auth page drops a stale redirect', str_contains($authGet, "unset(\$_SESSION['auth_redirect'])") && str_contains($authGet, "redirect('/patient')"));
assert_true('booking CTA no longer points auth at appointment', !str_contains($helpers, '/auth?next=/appointment'));

assert_true('incomplete profile badge links to the form', str_contains($dash, 'href="#patientProfileForm"') && str_contains($dash, 'پروفایل ناقص — تکمیل اطلاعات'));
assert_true('complete profile label remains', str_contains($dash, 'پروفایل کامل است'));
assert_true('profile form anchor exists', str_contains($dash, 'id="patientProfileForm"'));
assert_true('login mobile is not a posted field', !preg_match('/name="mobile"/', $dash));
assert_true('profile save still requires both names', str_contains($profile, "empty(\$data['first_name']) || empty(\$data['last_name'])"));
assert_true('profile save still marks the form complete', str_contains($profile, 'profile_completed=1'));
assert_true('profile save does not update the login mobile', !str_contains($profile, 'mobile=:mobile') && !preg_match("/'mobile'/", $profile));
assert_true('patient session still regenerates', str_contains($authClass, 'function loginPatient') && str_contains($authClass, 'Session::regenerate()'));
assert_true('logged-out patient panel still goes to auth', str_contains($authClass, "redirect('/auth')"));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
