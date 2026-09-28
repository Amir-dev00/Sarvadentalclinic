<?php

declare(strict_types=1);

/**
 * Integration: admin create → confirmation only; reminder cron only when due.
 * Run: php tools/test_appointment_sms_flow_separation.php
 *
 * Creates then soft-deletes test appointments. Does not call SMS.ir API
 * (queue inspection only; processQueue is not invoked).
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

use Sarva\Core\Database;
use Sarva\Services\AppointmentService;
use Sarva\Services\AppointmentTomorrowReminderService;
use Sarva\Services\SmsAutomationService;
use Sarva\Services\SmsService;

$failed = 0;
$passed = 0;
$createdIds = [];

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

function queueRows(PDO $pdo, int $appointmentId): array
{
    $st = $pdo->prepare(
        "SELECT message_type, provider_template_id, idempotency_key, send_mode, status
         FROM sms_queue WHERE appointment_id=? ORDER BY id ASC"
    );
    $st->execute([$appointmentId]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function cleanupAppointment(PDO $pdo, int $id): void
{
    try {
        $pdo->prepare('DELETE FROM sms_queue WHERE appointment_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM sms_logs WHERE appointment_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM appointment_status_history WHERE appointment_id=?')->execute([$id]);
        $pdo->prepare('UPDATE appointments SET deleted_at=NOW(), status=\'cancelled\' WHERE id=?')->execute([$id]);
    } catch (Throwable $e) {
        echo "cleanup warning #{$id}: " . $e->getMessage() . "\n";
    }
}

$confirmTpl = SmsService::smsIrTemplateIdForMessageType('appointment_confirmation');
$reminderTpl = SmsService::smsIrTemplateIdForMessageType('appointment_reminder');
$cancelTpl = SmsService::smsIrTemplateIdForMessageType('appointment_cancellation');

assert_eq('confirm tpl 159898', 159898, $confirmTpl);
assert_eq('reminder tpl 822711', 822711, $reminderTpl);
assert_eq('cancel tpl 296200', 296200, $cancelTpl);
assert_true(
    'no shared template ID across types',
    $confirmTpl !== $reminderTpl && $confirmTpl !== $cancelTpl && $reminderTpl !== $cancelTpl
);

if (!Database::connected()) {
    echo "\nSKIP DB integration (database unavailable). Config/template assertions above still ran.\n";
    echo "{$passed} passed, {$failed} failed\n";
    exit($failed > 0 ? 1 : 0);
}

$pdo = Database::connection();

$patientId = (int) $pdo->query('SELECT id FROM patients WHERE deleted_at IS NULL LIMIT 1')->fetchColumn();
$doctorId = (int) $pdo->query('SELECT id FROM doctors WHERE deleted_at IS NULL LIMIT 1')->fetchColumn();
$serviceId = (int) $pdo->query('SELECT id FROM services WHERE deleted_at IS NULL LIMIT 1')->fetchColumn();
$adminId = (int) ($pdo->query('SELECT id FROM admin_users WHERE deleted_at IS NULL LIMIT 1')->fetchColumn() ?: 1);

if ($patientId <= 0 || $doctorId <= 0 || $serviceId <= 0) {
    echo "SKIP: need at least one patient, doctor, and service in DB\n";
    echo "{$passed} passed, {$failed} failed\n";
    exit($failed > 0 ? 1 : 0);
}

$svc = new AppointmentService($pdo);
$sms = new SmsService($pdo);
$tomorrow = new AppointmentTomorrowReminderService($pdo, $sms);
$auto = new SmsAutomationService($pdo, $sms);

// --- 1) Admin creates appointment 5 days from now ---
$startsFar = date('Y-m-d H:i:s', strtotime('+5 days 18:30:00'));
for ($i = 0; $i < 40; $i++) {
    $try = date('Y-m-d H:i:s', strtotime($startsFar) + $i * 1800);
    $r = $svc->createConfirmedByAdmin($patientId, $doctorId, $serviceId, $try, $adminId, 'sms-flow-test-far');
    if ($r['ok'] ?? false) {
        $farId = (int) $r['appointment_id'];
        $createdIds[] = $farId;
        break;
    }
}
assert_true('created far appointment', isset($farId) && $farId > 0);

if (isset($farId)) {
    $rows = queueRows($pdo, $farId);
    $confirmRows = array_values(array_filter($rows, static fn ($r) => ($r['message_type'] ?? '') === 'appointment_confirmation'));
    $reminderRows = array_values(array_filter($rows, static fn ($r) => ($r['message_type'] ?? '') === 'appointment_reminder'));

    assert_eq('far: one confirmation', 1, count($confirmRows));
    assert_eq('far: zero reminders at create', 0, count($reminderRows));
    if ($confirmRows !== []) {
        assert_eq('far: confirm template', 159898, (int) $confirmRows[0]['provider_template_id']);
        assert_eq('far: confirm idempotency', 'appointment_confirmation:' . $farId, (string) $confirmRows[0]['idempotency_key']);
        assert_eq('far: send_mode', 'smsir_template', (string) $confirmRows[0]['send_mode']);
    }

    $remBefore = $tomorrow->enqueueTomorrow(false, 0, $farId);
    assert_eq('far: tomorrow cron queued 0', 0, (int) $remBefore['queued']);
    assert_eq('far: tomorrow cron candidates 0', 0, (int) $remBefore['candidates']);

    $auto->enqueueDue();
    $rowsAfterAuto = queueRows($pdo, $farId);
    $reminderAfterAuto = array_values(array_filter(
        $rowsAfterAuto,
        static fn ($r) => ($r['message_type'] ?? '') === 'appointment_reminder'
    ));
    assert_eq('far: automation did not add reminder', 0, count($reminderAfterAuto));

    $sentAt = $pdo->prepare('SELECT reminder_sent_at FROM appointments WHERE id=?');
    $sentAt->execute([$farId]);
    assert_true('far: reminder_sent_at still null', $sentAt->fetchColumn() === null);
}

// --- 2) Admin creates appointment tomorrow ---
$startsTomorrow = date('Y-m-d 19:00:00', strtotime('+1 day'));
for ($i = 0; $i < 40; $i++) {
    $try = date('Y-m-d H:i:s', strtotime($startsTomorrow) + $i * 1800);
    $r = $svc->createConfirmedByAdmin($patientId, $doctorId, $serviceId, $try, $adminId, 'sms-flow-test-tomorrow');
    if ($r['ok'] ?? false) {
        $tomId = (int) $r['appointment_id'];
        $createdIds[] = $tomId;
        break;
    }
}
assert_true('created tomorrow appointment', isset($tomId) && $tomId > 0);

if (isset($tomId)) {
    $rows = queueRows($pdo, $tomId);
    $confirmRows = array_values(array_filter($rows, static fn ($r) => ($r['message_type'] ?? '') === 'appointment_confirmation'));
    $reminderRows = array_values(array_filter($rows, static fn ($r) => ($r['message_type'] ?? '') === 'appointment_reminder'));
    assert_eq('tomorrow: confirmation only at create', 1, count($confirmRows));
    assert_eq('tomorrow: no reminder at create', 0, count($reminderRows));
    if ($confirmRows !== []) {
        assert_eq('tomorrow: confirm template 159898', 159898, (int) $confirmRows[0]['provider_template_id']);
    }

    $rem = $tomorrow->enqueueTomorrow(false, 0, $tomId);
    assert_eq('tomorrow: cron candidates 1', 1, (int) $rem['candidates']);
    assert_eq('tomorrow: cron queued 1', 1, (int) $rem['queued']);

    $rows2 = queueRows($pdo, $tomId);
    $reminderRows2 = array_values(array_filter($rows2, static fn ($r) => ($r['message_type'] ?? '') === 'appointment_reminder'));
    assert_eq('tomorrow: one reminder after cron', 1, count($reminderRows2));
    if ($reminderRows2 !== []) {
        assert_eq('tomorrow: reminder template', 822711, (int) $reminderRows2[0]['provider_template_id']);
        assert_true(
            'tomorrow: reminder idempotency prefix',
            str_starts_with((string) $reminderRows2[0]['idempotency_key'], 'appointment_reminder:' . $tomId . ':')
        );
    }

    $rem2 = $tomorrow->enqueueTomorrow(false, 0, $tomId);
    assert_eq('tomorrow: second cron queued 0', 0, (int) $rem2['queued']);
}

foreach ($createdIds as $id) {
    cleanupAppointment($pdo, $id);
}

echo "\n{$passed} passed, {$failed} failed\n";
echo 'cleaned appointments: ' . implode(',', $createdIds) . "\n";
exit($failed > 0 ? 1 : 0);
