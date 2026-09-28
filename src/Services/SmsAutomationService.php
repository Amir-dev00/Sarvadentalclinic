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

    public function enqueueDue(int $lookAheadHours = 48): int
    {
        if (!$this->sms->isEnabled()) {
            return 0;
        }
        $tz = (string) setting('timezone', config('app.timezone', 'Asia/Tehran'));
        if ($tz !== '') {
            date_default_timezone_set($tz);
        }

        $rules = $this->db->query(
            "SELECT r.*, t.body AS template_body, t.slug AS template_slug
             FROM sms_automation_rules r
             JOIN sms_templates t ON t.id = r.template_id AND t.is_active=1 AND t.deleted_at IS NULL
             WHERE r.is_active=1 AND r.trigger_type='appointment'"
        )->fetchAll(PDO::FETCH_ASSOC);

        $queued = 0;
        foreach ($rules as $rule) {
            $queued += $this->enqueueRule($rule);
        }
        return $queued;
    }

    /** @param array<string, mixed> $rule */
    private function enqueueRule(array $rule): int
    {
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

        if ($unit === 'days') {
            $sql = "SELECT a.id, a.starts_at, a.patient_id, p.mobile, p.first_name, p.last_name, p.file_number, p.public_code,
                           CONCAT(d.first_name,' ',d.last_name) doctor_name, s.name service_name
                    FROM appointments a
                    JOIN patients p ON p.id=a.patient_id AND p.deleted_at IS NULL
                    JOIN doctors d ON d.id=a.doctor_id
                    JOIN services s ON s.id=a.service_id
                    WHERE a.deleted_at IS NULL
                      AND a.reminder_sent_at IS NULL
                      AND a.status IN ($placeholders)
                      $extra
                      AND DATE(a.starts_at) = DATE(DATE_ADD(NOW(), INTERVAL {$offset} DAY))";
            $sendTime = substr((string) ($rule['send_time'] ?: '18:00:00'), 0, 8);
            if ($sendTime !== '' && date('H:i:s') < $sendTime) {
                return 0;
            }
        } else {
            $sql = "SELECT a.id, a.starts_at, a.patient_id, p.mobile, p.first_name, p.last_name, p.file_number, p.public_code,
                           CONCAT(d.first_name,' ',d.last_name) doctor_name, s.name service_name
                    FROM appointments a
                    JOIN patients p ON p.id=a.patient_id AND p.deleted_at IS NULL
                    JOIN doctors d ON d.id=a.doctor_id
                    JOIN services s ON s.id=a.service_id
                    WHERE a.deleted_at IS NULL
                      AND a.reminder_sent_at IS NULL
                      AND a.status IN ($placeholders)
                      $extra
                      AND a.starts_at > NOW()
                      AND a.starts_at <= DATE_ADD(NOW(), INTERVAL {$offset} HOUR)";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $count = 0;
        $type = 'appointment_reminder';
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = 'auto:' . (int) $rule['id'] . ':' . (int) $row['id'] . ':' . $type;
            $message = SmsTemplateRenderer::render((string) $rule['template_body'], SmsTemplateRenderer::varsFrom($row, $row));
            $id = $this->sms->enqueue([
                'patient_id' => (int) $row['patient_id'],
                'appointment_id' => (int) $row['id'],
                'template_id' => (int) $rule['template_id'],
                'automation_rule_id' => (int) $rule['id'],
                'mobile' => (string) $row['mobile'],
                'message' => $message,
                'message_type' => $type,
                'source' => 'automation',
                'idempotency_key' => $key,
                'appointment_starts_at' => $row['starts_at'],
            ]);
            if ($id > 0) {
                $count++;
            }
        }
        $this->db->prepare('UPDATE sms_automation_rules SET last_enqueued_at=NOW() WHERE id=?')->execute([(int) $rule['id']]);
        return $count;
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
        $body = (string) ($template['body'] ?? 'سلام {full_name}\nیادآوری نوبت: {appointment_time}');
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
