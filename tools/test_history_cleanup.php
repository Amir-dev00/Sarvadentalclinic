<?php

declare(strict_types=1);

/**
 * History cleanup safety. Does not leave deleted rows behind: database checks roll back.
 * Run: php tools/test_history_cleanup.php
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

use Sarva\Services\HistoryCleanupService;

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

$svcFile = file_get_contents(dirname(__DIR__) . '/src/Services/HistoryCleanupService.php');
$routeFile = file_get_contents(dirname(__DIR__) . '/admin/routes.php');
$template = file_get_contents(dirname(__DIR__) . '/templates/admin/history-cleanup.php');

$categories = HistoryCleanupService::categories();
assert_true('whitelist has the seven history categories', count($categories) === 7);
assert_true('patients is not a category', !isset($categories['patients']));
assert_true('appointments is not a category', !isset($categories['appointments']));
assert_true('injected category is dropped', HistoryCleanupService::normalizeCategories(['patients', 'sms_logs', 'sms_logs; DROP TABLE patients']) === ['sms_logs']);
assert_true('unknown table name is dropped', HistoryCleanupService::normalizeCategories(['admin_users', 'site_settings']) === []);

foreach (HistoryCleanupService::PROTECTED_TABLES as $table) {
    assert_true('protected table is not a cleanup target: ' . $table, !in_array($table, array_column($categories, 'table'), true));
}

assert_true('queue filter keeps pending work', str_contains(HistoryCleanupService::safetyWhere('sms_queue'), "status IN ('sent','failed','cancelled')"));
assert_true('queue filter does not name pending', !str_contains(HistoryCleanupService::safetyWhere('sms_queue'), 'pending'));
assert_true('otp filter is consumed or expired', HistoryCleanupService::safetyWhere('otp_codes') === '(consumed_at IS NOT NULL OR expires_at < NOW())');
assert_true('automation never includes today', HistoryCleanupService::safetyWhere('sms_automation_runs') === 'run_date < CURDATE()');
assert_true('unknown category matches nothing', HistoryCleanupService::safetyWhere('patients') === '0=1');

assert_true('phrase must match exactly', HistoryCleanupService::phraseMatches('حذف همه تاریخچه‌ها'));
assert_true('near phrase is rejected', !HistoryCleanupService::phraseMatches('حذف همه تاریخچه'));
assert_true('all range requires the phrase', HistoryCleanupService::requiresPhrase('all'));
assert_true('90 day range does not require the phrase', !HistoryCleanupService::requiresPhrase('90_days'));

$now = new DateTimeImmutable('2026-09-29 12:00:00', new DateTimeZone('Asia/Tehran'));
$cut = HistoryCleanupService::cutoffForRange('90_days', $now);
assert_true('90 day cutoff is in the past', $cut !== null && $cut < $now);
assert_true('all range has no cutoff', HistoryCleanupService::cutoffForRange('all', $now) === null);
assert_true('invalid range has no cutoff', HistoryCleanupService::cutoffForRange('patients', $now) === null);

$eligible = HistoryCleanupService::eligibility('sms_queue', $cut);
assert_true('queue eligibility keeps the status filter', str_contains($eligible['where'], "status IN ('sent','failed','cancelled')"));
assert_true('date filter uses a placeholder', str_contains($eligible['where'], 'created_at < ?'));
assert_true('otp eligibility still excludes active codes when a date is set', str_contains(HistoryCleanupService::eligibility('otp_codes', $cut)['where'], 'consumed_at IS NOT NULL'));
assert_true('automation eligibility always excludes today', str_contains(HistoryCleanupService::eligibility('sms_automation_runs', null)['where'], 'run_date < CURDATE()'));
assert_true('invalid category eligibility is empty', HistoryCleanupService::eligibility('patients', null)['where'] === '0=1');

assert_true('service deletes in chunks', str_contains($svcFile, 'LIMIT \' . self::CHUNK'));
assert_true('service does not disable foreign keys', !str_contains($svcFile, 'FOREIGN_KEY_CHECKS'));
foreach (['patients', 'appointments', 'payments', 'doctors', 'services', 'patient_notes', 'site_settings', 'admin_users'] as $table) {
    assert_true('service source has no DELETE FROM ' . $table, !preg_match('/DELETE\s+FROM\s+' . $table . '\b/i', $svcFile));
}

assert_true('page is admin-only', str_contains($routeFile, "Auth::requireAdmin('admins.manage')"));
assert_true('cleanup route is POST', str_contains($routeFile, "\$router->post('/admin/maintenance/history/cleanup'"));
assert_true('cleanup validates CSRF', str_contains($routeFile, 'Csrf::assertValid()'));
$cleanupRoute = substr($routeFile, (int) strpos($routeFile, "/admin/maintenance/history/cleanup"));
assert_true('cleanup route does not read a table name from POST', !str_contains($cleanupRoute, "\$_POST['table']"));
assert_true('cleanup route whitelists categories', str_contains($cleanupRoute, 'normalizeCategories'));
assert_true('audit is written after cleanup', strpos($cleanupRoute, 'cleanupSelected') < strpos($cleanupRoute, "audit('maintenance.history_cleanup'"));
assert_true('confirmation phrase is checked on the server', str_contains($cleanupRoute, 'phraseMatches'));
assert_true('template asks for the exact phrase', str_contains($template, 'CONFIRM_PHRASE') && str_contains($svcFile, 'حذف همه تاریخچه‌ها'));
assert_true('template shows the in-progress label', str_contains($template, 'در حال پاک‌سازی...'));
assert_true('there is no GET cleanup route', !preg_match("#router->get\\('/admin/maintenance/history/cleanup'#", $routeFile));

echo "\n-- database --\n";
try {
    $db = db();
    $db->query('SELECT 1');
} catch (Throwable $e) {
    echo 'SKIP  database checks (' . $e->getMessage() . ")\n";
    echo "\n{$passed} passed, {$failed} failed\n";
    exit($failed > 0 ? 1 : 0);
}

$coreTables = ['patients', 'appointments', 'payments', 'doctors', 'services'];
$before = [];
foreach ($coreTables as $table) {
    $before[$table] = (int) $db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
}

$live = new HistoryCleanupService($db);
$db->beginTransaction();
try {
    $marker = 'histtest' . bin2hex(random_bytes(4));
    $old = '1990-01-01 00:00:00';
    $cutoff = new DateTimeImmutable('1990-01-02 00:00:00');

    $db->prepare(
        'INSERT INTO sms_logs (mobile, message_type, source, message_body, provider, status, idempotency_key, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute(['09000000000', 'general', 'system', $marker, 'test', 'sent', $marker . '-log', $old]);
    $logId = (int) $db->lastInsertId();

    $db->prepare(
        'INSERT INTO otp_codes (mobile, code_hash, purpose, expires_at, consumed_at, created_at) VALUES (?, ?, ?, ?, ?, ?)'
    )->execute(['09000000001', 'hash', 'login', '2099-01-01 00:00:00', null, $old]);
    $activeOtp = (int) $db->lastInsertId();
    $db->prepare(
        'INSERT INTO otp_codes (mobile, code_hash, purpose, expires_at, consumed_at, created_at) VALUES (?, ?, ?, ?, ?, ?)'
    )->execute(['09000000002', 'hash', 'login', '1989-01-01 00:00:00', null, $old]);
    $expiredOtp = (int) $db->lastInsertId();
    $db->prepare(
        'INSERT INTO otp_codes (mobile, code_hash, purpose, expires_at, consumed_at, created_at) VALUES (?, ?, ?, ?, ?, ?)'
    )->execute(['09000000003', 'hash', 'login', '2099-01-01 00:00:00', '1990-01-01 00:00:00', $old]);
    $consumedOtp = (int) $db->lastInsertId();

    $queueIds = [];
    $queueInsert = $db->prepare(
        'INSERT INTO sms_queue (mobile, rendered_message, message_type, source, status, scheduled_at, idempotency_key, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach (['sent', 'failed', 'cancelled', 'pending', 'processing', 'retrying'] as $status) {
        $queueInsert->execute(['09000000000', $marker, 'general', 'manual', $status, $old, $marker . '-q-' . $status, $old]);
        $queueIds[$status] = (int) $db->lastInsertId();
    }

    $appt = $db->query('SELECT id, status, starts_at, patient_id FROM appointments ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $historyId = 0;
    if ($appt) {
        $db->prepare(
            'INSERT INTO appointment_status_history (appointment_id, from_status, to_status, note, created_at) VALUES (?, ?, ?, ?, ?)'
        )->execute([(int) $appt['id'], 'confirmed', 'confirmed', $marker, $old]);
        $historyId = (int) $db->lastInsertId();
    }

    $db->prepare(
        'INSERT INTO audit_logs (action, entity_type, meta_json, created_at) VALUES (?, ?, ?, ?)'
    )->execute(['history.test_marker', 'maintenance', json_encode(['marker' => $marker]), $old]);
    $auditId = (int) $db->lastInsertId();

    $viewId = 0;
    $db->prepare('INSERT INTO page_views (path, viewed_at, session_hash) VALUES (?, ?, ?)')
        ->execute(['/history-test', $old, hash('sha256', $marker)]);
    $viewId = (int) $db->lastInsertId();

    $hasRuns = (bool) $db->query("SHOW TABLES LIKE 'sms_automation_runs'")->fetchColumn();
    $oldRun = 0;
    $todayRun = 0;
    if ($hasRuns) {
        $ruleId = 2147483000;
        $db->prepare(
            'INSERT INTO sms_automation_runs (rule_id, run_date, started_at, eligible_count, queued_count, skipped_count)
             VALUES (?, ?, ?, 0, 0, 0)'
        )->execute([$ruleId, '1990-01-01', '1990-01-01 00:00:00']);
        $oldRun = (int) $db->lastInsertId();
        $db->prepare(
            'INSERT INTO sms_automation_runs (rule_id, run_date, started_at, eligible_count, queued_count, skipped_count)
             VALUES (?, CURDATE(), NOW(), 0, 0, 0)'
        )->execute([$ruleId + 1]);
        $todayRun = (int) $db->lastInsertId();
    }

    $deletedLogs = $live->cleanupCategory('sms_logs', $cutoff);
    assert_true('sms_logs marker removed', $deletedLogs >= 1 && !row_exists($db, 'sms_logs', $logId));

    $live->cleanupCategory('otp_codes', $cutoff);
    assert_true('active OTP remains', row_exists($db, 'otp_codes', $activeOtp));
    assert_true('expired OTP removed', !row_exists($db, 'otp_codes', $expiredOtp));
    assert_true('consumed OTP removed', !row_exists($db, 'otp_codes', $consumedOtp));

    $live->cleanupCategory('sms_queue', $cutoff);
    assert_true('sent queue row removed', !row_exists($db, 'sms_queue', $queueIds['sent']));
    assert_true('failed queue row removed', !row_exists($db, 'sms_queue', $queueIds['failed']));
    assert_true('cancelled queue row removed', !row_exists($db, 'sms_queue', $queueIds['cancelled']));
    assert_true('pending queue row remains', row_exists($db, 'sms_queue', $queueIds['pending']));
    assert_true('processing queue row remains', row_exists($db, 'sms_queue', $queueIds['processing']));
    assert_true('retrying queue row remains', row_exists($db, 'sms_queue', $queueIds['retrying']));

    if ($historyId > 0 && $appt) {
        $live->cleanupCategory('appointment_status_history', $cutoff);
        $afterAppt = $db->prepare('SELECT status, starts_at, patient_id FROM appointments WHERE id = ?');
        $afterAppt->execute([(int) $appt['id']]);
        $row = $afterAppt->fetch(PDO::FETCH_ASSOC);
        assert_true('status history row removed', !row_exists($db, 'appointment_status_history', $historyId));
        assert_true('appointment status unchanged', $row && $row['status'] === $appt['status'] && (string) $row['starts_at'] === (string) $appt['starts_at'] && (int) $row['patient_id'] === (int) $appt['patient_id']);
    } else {
        echo "SKIP  appointment status history (no appointment row)\n";
    }

    if ($hasRuns) {
        $live->cleanupCategory('sms_automation_runs', $cutoff);
        assert_true('old automation run removed', !row_exists($db, 'sms_automation_runs', $oldRun));
        assert_true('today automation run remains', row_exists($db, 'sms_automation_runs', $todayRun));
    } else {
        echo "SKIP  sms_automation_runs table is not created yet\n";
    }

    $live->cleanupCategory('page_views', $cutoff);
    assert_true('old page view removed', !row_exists($db, 'page_views', $viewId));

    $live->cleanupCategory('audit_logs', $cutoff);
    assert_true('old audit marker removed', !row_exists($db, 'audit_logs', $auditId));
    $_SESSION['admin_id'] = $_SESSION['admin_id'] ?? null;
    audit('maintenance.history_cleanup', 'maintenance', null, [
        'admin_id' => null,
        'categories' => ['audit_logs'],
        'deleted_counts' => ['audit_logs' => 1],
        'older_than' => '90_days',
        'marker' => $marker,
    ]);
    $fresh = (int) $db->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'maintenance.history_cleanup'")->fetchColumn();
    assert_true('fresh cleanup audit remains after audit deletion', $fresh >= 1);

    $ignored = $live->cleanupSelected(['patients', 'appointments', 'not_a_table'], $cutoff);
    assert_true('injected categories delete nothing', $ignored === []);

    foreach ($coreTables as $table) {
        $count = (int) $db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        assert_true($table . ' count unchanged', $count === $before[$table]);
    }
} catch (Throwable $e) {
    $failed++;
    echo 'FAIL  database scenario threw ' . $e->getMessage() . "\n";
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    foreach ([
        'sms_logs' => [$logId ?? 0],
        'otp_codes' => [$activeOtp ?? 0, $expiredOtp ?? 0, $consumedOtp ?? 0],
        'sms_queue' => array_values($queueIds ?? []),
        'appointment_status_history' => [$historyId ?? 0],
        'page_views' => [$viewId ?? 0],
        'sms_automation_runs' => [$oldRun ?? 0, $todayRun ?? 0],
    ] as $table => $ids) {
        foreach ($ids as $id) {
            if ((int) $id < 1) {
                continue;
            }
            try {
                $db->prepare('DELETE FROM ' . $table . ' WHERE id = ?')->execute([(int) $id]);
            } catch (Throwable) {
            }
        }
    }
    if (!empty($marker)) {
        $db->prepare("DELETE FROM audit_logs WHERE action = 'maintenance.history_cleanup' AND meta_json LIKE ?")
            ->execute(['%' . $marker . '%']);
        $db->prepare("DELETE FROM audit_logs WHERE action = 'history.test_marker' AND meta_json LIKE ?")
            ->execute(['%' . $marker . '%']);
    }
}

foreach ($coreTables as $table) {
    $count = (int) $db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    assert_true($table . ' count restored after rollback', $count === $before[$table]);
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);

function row_exists(PDO $db, string $table, int $id): bool
{
    $allowed = ['sms_logs', 'otp_codes', 'sms_queue', 'appointment_status_history', 'audit_logs', 'page_views', 'sms_automation_runs'];
    if (!in_array($table, $allowed, true) || $id < 1) {
        return false;
    }
    $st = $db->prepare('SELECT 1 FROM ' . $table . ' WHERE id = ?');
    $st->execute([$id]);
    return (bool) $st->fetchColumn();
}
