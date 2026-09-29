<?php

declare(strict_types=1);

namespace Sarva\Services;

use PDO;

final class SmsAutomationService
{
    public const PATIENT_FILTERS = [
        'all',
        'new',
        'old',
        'imported',
        'manual',
    ];

    public function __construct(private readonly PDO $db, private readonly SmsService $sms)
    {
        $this->ensureSchema();
    }

    /**
     * Labels must match SMS Center (SmsAudienceService::modeLabels) for shared keys.
     * imported/manual keep SMS Center «منبع» wording from the advanced filters.
     *
     * @return array<string, string>
     */
    public static function patientFilterLabels(): array
    {
        $modes = SmsAudienceService::modeLabels();
        return [
            'all' => $modes['all'],
            'new' => $modes['new'],
            'old' => $modes['old'],
            // Same wording as SMS Center advanced «منبع» options
            'imported' => 'واردشده از Excel',
            'manual' => 'ثبت دستی',
        ];
    }

    /** Mode chip order aligned with SMS Center (patient-scoped modes only). */
    public static function patientFilterOrder(): array
    {
        return ['all', 'new', 'old'];
    }

    public static function normalizePatientFilter(?string $value): string
    {
        $value = trim((string) $value);
        $labels = self::patientFilterLabels();
        return array_key_exists($value, $labels) ? $value : 'all';
    }

    private function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $col = $this->db->query("SHOW COLUMNS FROM sms_automation_rules LIKE 'patient_filter'")->fetch(PDO::FETCH_ASSOC);
            if (!$col) {
                $this->db->exec(
                    "ALTER TABLE sms_automation_rules
                     ADD COLUMN patient_filter VARCHAR(40) NOT NULL DEFAULT 'all'
                     AFTER appointment_statuses"
                );
            }
        } catch (\Throwable) {
        }
        try {
            $col = $this->db->query("SHOW COLUMNS FROM sms_automation_rules LIKE 'audience_json'")->fetch(PDO::FETCH_ASSOC);
            if (!$col) {
                $this->db->exec(
                    "ALTER TABLE sms_automation_rules
                     ADD COLUMN audience_json LONGTEXT NULL
                     AFTER patient_filter"
                );
            }
        } catch (\Throwable) {
        }
        try {
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS sms_automation_runs (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    rule_id INT UNSIGNED NOT NULL,
                    run_date DATE NOT NULL,
                    configured_send_time TIME NULL,
                    started_at DATETIME NOT NULL,
                    finished_at DATETIME NULL,
                    eligible_count INT UNSIGNED NOT NULL DEFAULT 0,
                    queued_count INT UNSIGNED NOT NULL DEFAULT 0,
                    skipped_count INT UNSIGNED NOT NULL DEFAULT 0,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_rule_run_date_time (rule_id, run_date, configured_send_time)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (\Throwable) {
        }
        $this->ensureRunScheduleUnique();
    }

    /**
     * One batch per rule + calendar day + configured send time.
     * A same-day send_time change may run again; repeats of the same time may not.
     */
    private function ensureRunScheduleUnique(): void
    {
        try {
            $rows = $this->db->query('SHOW INDEX FROM sms_automation_runs')->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return;
        }
        $byName = [];
        foreach ($rows as $row) {
            $name = (string) ($row['Key_name'] ?? '');
            $seq = (int) ($row['Seq_in_index'] ?? 0);
            $byName[$name][$seq] = (string) ($row['Column_name'] ?? '');
        }
        $normalize = static function (array $cols): array {
            ksort($cols);
            return array_values(array_map(static fn ($col): string => strtolower((string) $col), $cols));
        };
        $hasNew = isset($byName['uniq_rule_run_date_time'])
            && $normalize($byName['uniq_rule_run_date_time']) === ['rule_id', 'run_date', 'configured_send_time'];
        $hasOld = isset($byName['uniq_rule_run_date']);
        if ($hasNew && !$hasOld) {
            return;
        }
        try {
            if ($hasOld) {
                $this->db->exec('ALTER TABLE sms_automation_runs DROP INDEX uniq_rule_run_date');
            }
            if (!$hasNew) {
                $this->db->exec(
                    'ALTER TABLE sms_automation_runs ADD UNIQUE KEY uniq_rule_run_date_time (rule_id, run_date, configured_send_time)'
                );
            }
        } catch (\Throwable) {
        }
    }

    /** Grace window after the admin-selected daily send time (cron is frequent; batch is not). */
    public const DAILY_WINDOW_MINUTES = 10;

    /**
     * True only inside [send_time, send_time + window). Not "any time after send_time".
     */
    public static function isWithinDailySendWindow(string $nowHis, string $sendTime, int $windowMinutes = self::DAILY_WINDOW_MINUTES): bool
    {
        $now = self::secondsOfDay($nowHis);
        $start = self::secondsOfDay($sendTime);
        if ($now === null || $start === null) {
            return false;
        }
        $end = $start + max(1, $windowMinutes) * 60;
        return $now >= $start && $now < $end;
    }

    /**
     * Day-offset reminder batch is eligible only inside the current send_time window.
     * Same-day repeats of one schedule are blocked by sms_automation_runs, not last_enqueued_at.
     */
    public static function dailyBatchShouldRun(
        string $nowHis,
        string $sendTime,
        ?string $lastEnqueuedAt = null,
        string $todayYmd = '',
        int $windowMinutes = self::DAILY_WINDOW_MINUTES
    ): bool {
        unset($lastEnqueuedAt, $todayYmd);
        return self::isWithinDailySendWindow($nowHis, $sendTime, $windowMinutes);
    }

    private static function secondsOfDay(string $his): ?int
    {
        $his = substr(trim($his), 0, 8);
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $his, $m) !== 1) {
            return null;
        }
        $h = (int) $m[1];
        $i = (int) $m[2];
        $s = isset($m[3]) ? (int) $m[3] : 0;
        if ($h > 23 || $i > 59 || $s > 59) {
            return null;
        }
        return $h * 3600 + $i * 60 + $s;
    }

    /** @return array{mode:string,filters:array,include_ids:list<int>,exclude_ids:list<int>,select_all_filtered:bool} */
    public static function defaultAudience(): array
    {
        return [
            'mode' => 'all',
            'filters' => ['mode' => 'all', 'status' => 'active'],
            'include_ids' => [],
            'exclude_ids' => [],
            'select_all_filtered' => true,
        ];
    }

    /**
     * @param array<string, mixed>|string|null $raw
     * @return array{mode:string,filters:array,include_ids:list<int>,exclude_ids:list<int>,select_all_filtered:bool}
     */
    public static function normalizeAudience(array|string|null $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }
        $base = self::defaultAudience();
        if (!is_array($raw)) {
            return $base;
        }
        $mode = (string) ($raw['mode'] ?? $raw['filters']['mode'] ?? 'all');
        if (!in_array($mode, SmsAudienceService::MODES, true)) {
            $mode = 'all';
        }
        $filters = is_array($raw['filters'] ?? null) ? $raw['filters'] : [];
        $filters['mode'] = $mode;
        if (empty($filters['status'])) {
            $filters['status'] = 'active';
        }
        return [
            'mode' => $mode,
            'filters' => $filters,
            'include_ids' => array_values(array_filter(array_map('intval', (array) ($raw['include_ids'] ?? [])))),
            'exclude_ids' => array_values(array_filter(array_map('intval', (array) ($raw['exclude_ids'] ?? [])))),
            'select_all_filtered' => !empty($raw['select_all_filtered']) || (!isset($raw['select_all_filtered']) && $mode !== 'manual' && $mode !== 'selected' && $mode !== 'search'),
        ];
    }

    /** @var array{checked_rules:int,daily_reminder_due:int,reminder_queued:int,skipped_already:int} */
    private array $cronStats = [
        'checked_rules' => 0,
        'daily_reminder_due' => 0,
        'reminder_queued' => 0,
        'skipped_already' => 0,
    ];

    /** @return array{checked_rules:int,daily_reminder_due:int,reminder_queued:int,skipped_already:int} */
    public function lastCronStats(): array
    {
        return $this->cronStats;
    }

    public static function appNow(): \DateTimeImmutable
    {
        $tzName = (string) setting('timezone', config('app.timezone', 'Asia/Tehran'));
        try {
            $tz = new \DateTimeZone($tzName !== '' ? $tzName : 'Asia/Tehran');
        } catch (\Throwable) {
            $tz = new \DateTimeZone('Asia/Tehran');
        }
        return new \DateTimeImmutable('now', $tz);
    }

    public static function reminderIdempotencyKey(int $ruleId, int $appointmentId, string $runDate): string
    {
        return 'appointment_reminder:' . $ruleId . ':' . $appointmentId . ':' . $runDate;
    }

    public function enqueueDue(int $lookAheadHours = 48): int
    {
        $this->cronStats = [
            'checked_rules' => 0,
            'daily_reminder_due' => 0,
            'reminder_queued' => 0,
            'skipped_already' => 0,
        ];
        if (!$this->sms->isEnabled()) {
            return 0;
        }

        $now = self::appNow();
        date_default_timezone_set($now->getTimezone()->getName());

        $rules = $this->db->query(
            "SELECT r.*, t.body AS template_body, t.slug AS template_slug
             FROM sms_automation_rules r
             JOIN sms_templates t ON t.id = r.template_id AND t.is_active=1 AND t.deleted_at IS NULL
             WHERE r.is_active=1 AND r.trigger_type='appointment'"
        )->fetchAll(PDO::FETCH_ASSOC);

        $queued = 0;
        foreach ($rules as $rule) {
            $this->cronStats['checked_rules']++;
            $queued += $this->enqueueRule($rule, $now);
        }
        $this->cronStats['reminder_queued'] = $queued;
        return $queued;
    }

    /** @param array<string, mixed> $rule */
    private function enqueueRule(array $rule, ?\DateTimeImmutable $now = null): int
    {
        $now ??= self::appNow();
        $statuses = array_filter(array_map('trim', explode(',', (string) ($rule['appointment_statuses'] ?: 'confirmed'))));
        if ($statuses === []) {
            $statuses = ['confirmed'];
        }
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $params = $statuses;
        $extra = '';
        if (!empty($rule['doctor_id'])) {
            $extra .= ' AND a.doctor_id = ?';
            $params[] = (int) $rule['doctor_id'];
        }
        if (!empty($rule['service_id'])) {
            $extra .= ' AND a.service_id = ?';
            $params[] = (int) $rule['service_id'];
        }

        // Full SMS Center audience (preferred) — intersect with appointment trigger window.
        $audience = self::normalizeAudience($rule['audience_json'] ?? null);
        $hasAudience = !empty($rule['audience_json']);
        if ($hasAudience) {
            try {
                $ids = (new SmsAudienceService($this->db))->resolvePatientIds($audience);
                if ($ids === []) {
                    return 0;
                }
                // Cap IN() size for shared hosting safety.
                if (count($ids) > 8000) {
                    $ids = array_slice($ids, 0, 8000);
                }
                $in = implode(',', array_map('intval', $ids));
                $extra .= " AND p.id IN ({$in})";
            } catch (\Throwable) {
                $hasAudience = false;
            }
        }

        if (!$hasAudience) {
            $patientFilter = self::normalizePatientFilter($rule['patient_filter'] ?? 'all');
            if ($patientFilter === 'imported') {
                $extra .= ' AND p.is_imported=1';
            } elseif ($patientFilter === 'manual') {
                $extra .= ' AND p.is_imported=0';
            } elseif ($patientFilter === 'new') {
                $extra .= ' AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            } elseif ($patientFilter === 'old') {
                $extra .= ' AND p.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)';
            }
        }

        $unit = $rule['offset_unit'] === 'hours' ? 'hours' : 'days';
        $offset = max(1, (int) $rule['offset_value']);
        $manual = !empty($rule['_manual_batch']);

        $sendTime = substr((string) ($rule['send_time'] ?: '18:00:00'), 0, 8);
        $today = $now->format('Y-m-d');
        $nowHis = $now->format('H:i:s');
        $nowSql = $now->format('Y-m-d H:i:s');

        if ($unit === 'days') {
            if (!$manual && !self::isWithinDailySendWindow($nowHis, $sendTime)) {
                return 0;
            }
            if (!$manual) {
                $this->cronStats['daily_reminder_due']++;
            }
            if (!$manual && !$this->claimDailyRun((int) $rule['id'], $today, $sendTime, $nowSql)) {
                $this->cronStats['skipped_already']++;
                SmsTemplateRenderer::logEvent('TOMORROW_REMINDER_SKIP', [
                    'rule_id' => (int) $rule['id'],
                    'run_date' => $today,
                    'configured_send_time' => substr($sendTime, 0, 8),
                    'reason' => 'schedule_already_executed',
                ]);
                return 0;
            }
            $targetDate = $now->modify('+' . $offset . ' days')->format('Y-m-d');
            $params[] = $targetDate;
            $sql = "SELECT a.id, a.starts_at, a.patient_id, p.mobile, p.first_name, p.last_name, p.file_number, p.public_code,
                           CONCAT(d.first_name,' ',d.last_name) doctor_name, s.name service_name
                    FROM appointments a
                    JOIN patients p ON p.id=a.patient_id AND p.deleted_at IS NULL
                    JOIN doctors d ON d.id=a.doctor_id
                    JOIN services s ON s.id=a.service_id
                    WHERE a.deleted_at IS NULL
                      AND a.reminder_sent_at IS NULL
                      AND a.status IN ($placeholders)
                      AND a.status NOT IN ('cancelled','completed','expired')
                      $extra
                      AND DATE(a.starts_at) = ?";
        } else {
            $until = $now->modify('+' . $offset . ' hours')->format('Y-m-d H:i:s');
            $params[] = $nowSql;
            $params[] = $until;
            $sql = "SELECT a.id, a.starts_at, a.patient_id, p.mobile, p.first_name, p.last_name, p.file_number, p.public_code,
                           CONCAT(d.first_name,' ',d.last_name) doctor_name, s.name service_name
                    FROM appointments a
                    JOIN patients p ON p.id=a.patient_id AND p.deleted_at IS NULL
                    JOIN doctors d ON d.id=a.doctor_id
                    JOIN services s ON s.id=a.service_id
                    WHERE a.deleted_at IS NULL
                      AND a.reminder_sent_at IS NULL
                      AND a.status IN ($placeholders)
                      AND a.status NOT IN ('cancelled','completed','expired')
                      $extra
                      AND a.starts_at > ?
                      AND a.starts_at <= ?";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $count = 0;
        $skipped = 0;
        $type = 'appointment_reminder';
        $smsIrTemplateId = SmsService::smsIrTemplateIdForMessageType($type);
        $startedAt = date('c');
        foreach ($rows as $row) {
            $apptDay = date('Y-m-d', strtotime((string) $row['starts_at']) ?: time());
            $ruleId = (int) $rule['id'];
            $appointmentId = (int) $row['id'];
            $key = self::reminderIdempotencyKey($ruleId, $appointmentId, $today);
            $legacyKeys = [
                'appointment_reminder:' . $appointmentId . ':rule:' . $ruleId,
                'auto:' . $ruleId . ':' . $appointmentId . ':' . $type,
                'appointment_reminder:' . $appointmentId . ':tomorrow:' . $apptDay,
                'tomorrow_reminder:' . $appointmentId . ':' . $apptDay,
            ];

            if ($smsIrTemplateId <= 0) {
                $skipped++;
                SmsTemplateRenderer::logEvent('smsir_reminder_template_missing', [
                    'automation_rule_id' => $ruleId,
                    'appointment_id' => $appointmentId,
                ]);
                continue;
            }

            $tplParams = SmsTemplateRenderer::buildSmsIrAppointmentParameters($row, $row);
            $missing = SmsTemplateRenderer::missingSmsIrParameters($tplParams, ['FULL_NAME', 'APPOINTMENT_TIME']);
            if ($missing !== []) {
                $skipped++;
                SmsTemplateRenderer::logSmsIrParameterError([
                    'template_id' => $smsIrTemplateId,
                    'template_slug' => (string) ($rule['template_slug'] ?? ''),
                    'automation_rule_id' => $ruleId,
                    'appointment_id' => $appointmentId,
                    'missing_parameter_names' => $missing,
                    'message_type' => $type,
                ]);
                continue;
            }

            try {
                $marks = implode(',', array_fill(0, count($legacyKeys) + 1, '?'));
                $dup = $this->db->prepare(
                    "SELECT id FROM sms_queue WHERE idempotency_key IN ($marks) AND status IN ('pending','processing','retrying','sent') LIMIT 1"
                );
                $dup->execute([$key, ...$legacyKeys]);
                if ($dup->fetchColumn()) {
                    $skipped++;
                    continue;
                }
            } catch (\Throwable) {
            }

            $queueId = $this->sms->enqueueSmsIrTemplate([
                'patient_id' => (int) $row['patient_id'],
                'appointment_id' => $appointmentId,
                'template_id' => (int) $rule['template_id'],
                'automation_rule_id' => $ruleId,
                'mobile' => (string) $row['mobile'],
                'message_type' => $type,
                'source' => 'automation',
                'provider_template_id' => $smsIrTemplateId,
                'template_parameters' => $tplParams,
                'idempotency_key' => $key,
                'appointment_starts_at' => $row['starts_at'],
                'log_message' => sprintf(
                    'یادآوری نوبت (SMS.ir #%d): %s — %s %s',
                    $smsIrTemplateId,
                    $tplParams['FULL_NAME'],
                    $tplParams['APPOINTMENT_DATE'],
                    $tplParams['APPOINTMENT_TIME']
                ),
            ]);

            if ($queueId > 0) {
                $count++;
                SmsTemplateRenderer::logEvent('APPOINTMENT_REMINDER_QUEUED', [
                    'appointment_id' => $appointmentId,
                    'rule_id' => $ruleId,
                    'appointment_starts_at' => $row['starts_at'],
                    'current_time' => date('c'),
                    'template_id' => $smsIrTemplateId,
                    'idempotency_key' => $key,
                    'reason_due' => $manual
                        ? 'manual_daily_batch'
                        : (($rule['offset_unit'] === 'hours')
                            ? ('within_' . (int) $rule['offset_value'] . '_hours')
                            : 'daily_batch_window'),
                ]);
            } else {
                $skipped++;
            }
        }

        if ($unit === 'days') {
            if (!$manual) {
                $this->finishDailyRun((int) $rule['id'], $today, $sendTime, count($rows), $count, $skipped);
            }
            SmsTemplateRenderer::logEvent('TOMORROW_REMINDER_BATCH', [
                'rule_id' => (int) $rule['id'],
                'run_date' => $today,
                'configured_send_time' => $sendTime,
                'started_at' => $startedAt,
                'eligible_count' => count($rows),
                'queued_count' => $count,
                'skipped_count' => $skipped,
                'manual' => $manual,
            ]);
        }

        // Manual catch-up must not consume today's automatic batch lock.
        if (!$manual) {
            $this->db->prepare('UPDATE sms_automation_rules SET last_enqueued_at=? WHERE id=?')->execute([$nowSql, (int) $rule['id']]);
        }
        return $count;
    }

    /**
     * Explicit admin catch-up. Does not run from appointment creation or from cron outside the window.
     *
     * @return array{ok:bool, queued:int, eligible:int, message:string}
     */
    public function runManualDailyBatch(int $ruleId): array
    {
        $stmt = $this->db->prepare(
            "SELECT r.*, t.body AS template_body, t.slug AS template_slug
             FROM sms_automation_rules r
             JOIN sms_templates t ON t.id = r.template_id AND t.deleted_at IS NULL
             WHERE r.id=? LIMIT 1"
        );
        $stmt->execute([$ruleId]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$rule) {
            return ['ok' => false, 'queued' => 0, 'eligible' => 0, 'message' => 'قانون یادآوری یافت نشد.'];
        }
        if (($rule['offset_unit'] ?? 'days') === 'hours') {
            return ['ok' => false, 'queued' => 0, 'eligible' => 0, 'message' => 'ارسال دستی فقط برای یادآوری روزانه است.'];
        }
        $eligible = $this->countEligibleForRule($rule);
        $rule['_manual_batch'] = true;
        $queued = $this->enqueueRule($rule, self::appNow());
        return [
            'ok' => true,
            'queued' => $queued,
            'eligible' => $eligible,
            'message' => $queued > 0
                ? ($queued . ' یادآوری در صف قرار گرفت.')
                : 'یادآوری جدیدی برای ارسال نبود.',
        ];
    }

    /** @param array<string, mixed> $rule */
    public function countEligibleForRule(array $rule): int
    {
        if (($rule['offset_unit'] ?? 'days') === 'hours') {
            return 0;
        }
        $offset = max(1, (int) ($rule['offset_value'] ?? 1));
        $targetDate = self::appNow()->modify('+' . $offset . ' days')->format('Y-m-d');
        $statuses = array_filter(array_map('trim', explode(',', (string) ($rule['appointment_statuses'] ?: 'confirmed'))));
        if ($statuses === []) {
            $statuses = ['confirmed'];
        }
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $params = $statuses;
        $extra = '';
        if (!empty($rule['doctor_id'])) {
            $extra .= ' AND a.doctor_id = ?';
            $params[] = (int) $rule['doctor_id'];
        }
        if (!empty($rule['service_id'])) {
            $extra .= ' AND a.service_id = ?';
            $params[] = (int) $rule['service_id'];
        }
        $sql = "SELECT COUNT(*) FROM appointments a
                JOIN patients p ON p.id=a.patient_id AND p.deleted_at IS NULL
                WHERE a.deleted_at IS NULL
                  AND a.reminder_sent_at IS NULL
                  AND a.status IN ($placeholders)
                  AND a.status NOT IN ('cancelled','completed','expired')
                  $extra
                  AND DATE(a.starts_at) = ?";
        $params[] = $targetDate;
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn();
    }

    private function claimDailyRun(int $ruleId, string $runDate, string $sendTime, string $startedAt): bool
    {
        try {
            $this->db->prepare(
                'INSERT INTO sms_automation_runs (rule_id, run_date, configured_send_time, started_at)
                 VALUES (?, ?, ?, ?)'
            )->execute([$ruleId, $runDate, $sendTime, $startedAt]);
            return true;
        } catch (\PDOException $e) {
            $sqlState = (string) $e->getCode();
            if ($sqlState === '23000' || str_contains($e->getMessage(), 'Duplicate')) {
                return false;
            }
            return true;
        } catch (\Throwable) {
            return true;
        }
    }

    private function finishDailyRun(int $ruleId, string $runDate, string $sendTime, int $eligible, int $queued, int $skipped): void
    {
        try {
            $this->db->prepare(
                'UPDATE sms_automation_runs
                 SET finished_at=NOW(), eligible_count=?, queued_count=?, skipped_count=?
                 WHERE rule_id=? AND run_date=? AND configured_send_time=?'
            )->execute([$eligible, $queued, $skipped, $ruleId, $runDate, substr($sendTime, 0, 8)]);
        } catch (\Throwable) {
        }
    }

    /** @return array{send_at:string, appointment_at:string, preview:string} */
    public function preview(array $rule, ?array $template = null): array
    {
        $tz = (string) setting('timezone', 'Asia/Tehran');
        $now = new \DateTimeImmutable('now', new \DateTimeZone($tz));
        $unit = ($rule['offset_unit'] ?? 'days') === 'hours' ? 'hours' : 'days';
        $offset = max(1, (int) ($rule['offset_value'] ?? 1));
        $sampleAppt = $now->modify('+1 day')->setTime(10, 30);
        if ($unit === 'days') {
            $send = $sampleAppt->modify("-{$offset} days");
            $st = (string) ($rule['send_time'] ?? '18:00:00');
            [$h, $m] = array_map('intval', explode(':', $st . ':00'));
            $send = $send->setTime($h ?: 18, $m);
        } else {
            $send = $sampleAppt->modify("-{$offset} hours");
        }
        $body = (string) ($template['body'] ?? "سلام #full_name#\nیادآوری نوبت: #appointment_time#");
        $preview = SmsTemplateRenderer::render($body, [
            'full_name' => 'علی رضایی',
            'first_name' => 'علی',
            'last_name' => 'رضایی',
            'patient_number' => 'P1001',
            'appointment_date' => to_jalali($sampleAppt->format('Y-m-d H:i:s'), 'Y/m/d'),
            'appointment_time' => $sampleAppt->format('H:i'),
            'doctor_name' => 'دکتر نمونه',
            'service_name' => 'ویزیت',
            'clinic_name' => (string) setting('clinic_name', 'کلینیک دندانپزشکی سروا'),
            'clinic_phone' => (string) setting('phone', ''),
        ]);
        return [
            'appointment_at' => to_jalali($sampleAppt->format('Y-m-d H:i:s'), 'Y/m/d H:i'),
            'send_at' => to_jalali($send->format('Y-m-d H:i:s'), 'Y/m/d H:i'),
            'preview' => $preview,
        ];
    }
}
