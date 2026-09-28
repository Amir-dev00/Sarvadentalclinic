<?php

declare(strict_types=1);

namespace Sarva\Services;

use PDO;

/**
 * Enqueues SMS.ir verify-template reminders for appointments scheduled tomorrow.
 * Does not send directly — SmsService::processQueue delivers via SmsIrProvider::sendTemplate.
 */
final class AppointmentTomorrowReminderService
{
    public const MESSAGE_TYPE = 'appointment_reminder';
    public const SOURCE = 'tomorrow_reminder';

    public function __construct(
        private readonly PDO $db,
        private readonly SmsService $sms,
    ) {
    }

    public function templateId(): int
    {
        return (int) config('sms.appointment_reminder_template_id', 0);
    }

    public function isConfigured(): bool
    {
        return $this->templateId() > 0 && $this->sms->isEnabled();
    }

    /**
     * @return array{
     *   queued:int,
     *   skipped:int,
     *   candidates:int,
     *   dry_run:bool,
     *   rows:list<array<string,mixed>>,
     *   errors:list<string>
     * }
     */
    public function enqueueTomorrow(bool $dryRun = false, int $limit = 0, ?int $onlyAppointmentId = null): array
    {
        $report = [
            'queued' => 0,
            'skipped' => 0,
            'candidates' => 0,
            'dry_run' => $dryRun,
            'rows' => [],
            'errors' => [],
        ];

        if (!$this->sms->isEnabled()) {
            $report['errors'][] = 'SMS is disabled (sms_enabled=0).';
            return $report;
        }
        if ($this->templateId() <= 0) {
            $report['errors'][] = 'SMSIR_APPOINTMENT_REMINDER_TEMPLATE_ID is not set.';
            return $report;
        }

        $tz = (string) setting('timezone', config('app.timezone', 'Asia/Tehran'));
        if ($tz !== '') {
            date_default_timezone_set($tz);
        }

        $rows = $this->fetchTomorrowAppointments($limit, $onlyAppointmentId);
        $report['candidates'] = count($rows);

        foreach ($rows as $row) {
            $appointmentId = (int) $row['id'];
            $fullName = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
            if ($fullName === '') {
                $fullName = 'مراجع';
            }
            $apptTime = date('H:i', strtotime((string) $row['starts_at']) ?: time());
            $mobile = normalize_mobile((string) ($row['mobile'] ?? ''));

            $preview = sprintf(
                'سلام %s یادآوری نوبت شما در کلینیک دندانپزشکی سروا(دکتر زمان زاده) فردا ساعت %s منتظر حضور شما هستیم. sarvadentalclinic.ir',
                $fullName,
                $apptTime
            );

            $entry = [
                'appointment_id' => $appointmentId,
                'patient_id' => (int) $row['patient_id'],
                'full_name' => $fullName,
                'appointment_time' => $apptTime,
                'starts_at' => $row['starts_at'],
                'mobile' => $mobile,
                'preview' => $preview,
            ];

            if ($mobile === null) {
                $report['skipped']++;
                $entry['status'] = 'invalid_mobile';
                $report['rows'][] = $entry;
                $this->logLine('skip_invalid_mobile', $appointmentId, null);
                continue;
            }

            if ($dryRun) {
                $entry['status'] = 'dry_run';
                $report['rows'][] = $entry;
                continue;
            }

            try {
                $queueId = $this->sms->enqueue([
                    'patient_id' => (int) $row['patient_id'],
                    'appointment_id' => $appointmentId,
                    'mobile' => $mobile,
                    'message' => $preview,
                    'message_type' => self::MESSAGE_TYPE,
                    'source' => self::SOURCE,
                    'idempotency_key' => $this->idempotencyKey($appointmentId, (string) $row['starts_at']),
                    'appointment_starts_at' => $row['starts_at'],
                ]);
                if ($queueId > 0) {
                    $report['queued']++;
                    $entry['status'] = 'queued';
                    $entry['queue_id'] = $queueId;
                    $this->logLine('queued', $appointmentId, $mobile);
                } else {
                    $report['skipped']++;
                    $entry['status'] = 'duplicate_or_skipped';
                    $this->logLine('skip_duplicate', $appointmentId, $mobile);
                }
            } catch (\Throwable $e) {
                $report['skipped']++;
                $entry['status'] = 'error';
                $report['errors'][] = 'appointment #' . $appointmentId . ': enqueue failed';
                $this->logLine('enqueue_error', $appointmentId, $mobile, $e->getMessage());
            }

            $report['rows'][] = $entry;
        }

        return $report;
    }

    public function idempotencyKey(int $appointmentId, string $startsAt): string
    {
        $day = date('Y-m-d', strtotime($startsAt) ?: time());
        return 'tomorrow_reminder:' . $appointmentId . ':' . $day;
    }

    /**
     * Confirmed, not deleted, reminder not sent, tomorrow only.
     *
     * @return list<array<string, mixed>>
     */
    private function fetchTomorrowAppointments(int $limit = 0, ?int $onlyAppointmentId = null): array
    {
        $sql = "SELECT a.id, a.starts_at, a.patient_id, a.status, a.reminder_sent_at,
                       p.mobile, p.first_name, p.last_name, p.file_number, p.public_code
                FROM appointments a
                INNER JOIN patients p ON p.id = a.patient_id AND p.deleted_at IS NULL
                WHERE a.deleted_at IS NULL
                  AND a.status = 'confirmed'
                  AND a.reminder_sent_at IS NULL
                  AND DATE(a.starts_at) = DATE(DATE_ADD(NOW(), INTERVAL 1 DAY))";
        $params = [];

        if ($onlyAppointmentId !== null && $onlyAppointmentId > 0) {
            $sql .= ' AND a.id = ?';
            $params[] = $onlyAppointmentId;
        }

        // Already queued / sent elsewhere for this appointment reminder
        $sql .= " AND NOT EXISTS (
                    SELECT 1 FROM sms_queue q
                    WHERE q.appointment_id = a.id
                      AND q.message_type = 'appointment_reminder'
                      AND q.status IN ('pending','processing','retrying','sent')
                  )
                  AND NOT EXISTS (
                    SELECT 1 FROM sms_logs l
                    WHERE l.appointment_id = a.id
                      AND l.message_type = 'appointment_reminder'
                      AND l.status = 'sent'
                  )";

        $sql .= ' ORDER BY a.starts_at ASC, a.id ASC';
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function logLine(string $event, int $appointmentId, ?string $mobile, ?string $detail = null): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $payload = [
            'at' => date('c'),
            'event' => $event,
            'appointment_id' => $appointmentId,
            'mobile' => $mobile,
        ];
        if ($detail !== null && $detail !== '') {
            // Never log credentials; only short operational detail.
            $payload['detail'] = mb_substr($detail, 0, 200);
        }
        @file_put_contents(
            $dir . '/appointment-reminders.log',
            json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
