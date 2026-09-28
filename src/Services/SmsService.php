<?php

declare(strict_types=1);

namespace Sarva\Services;

use PDO;
use Sarva\Sms\SmsManager;

final class SmsService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function isEnabled(): bool
    {
        return (string) setting('sms_enabled', '1') !== '0';
    }

    /**
     * Queue an SMS.ir Verify/template job (no local text rendering required for provider send).
     *
     * @param array{
     *   patient_id?:?int,
     *   appointment_id?:?int,
     *   admin_user_id?:?int,
     *   mobile:string,
     *   message_type:string,
     *   provider_template_id:int,
     *   template_parameters:array<string,string>,
     *   source?:string,
     *   scheduled_at?:?string,
     *   idempotency_key?:?string,
     *   appointment_starts_at?:?string,
     *   log_message?:string
     * } $job
     */
    public function enqueueSmsIrTemplate(array $job): int
    {
        $this->ensureQueueTemplateColumns();

        $mobile = normalize_mobile((string) ($job['mobile'] ?? ''));
        if ($mobile === null) {
            return 0;
        }

        $templateId = (int) ($job['provider_template_id'] ?? 0);
        if ($templateId <= 0) {
            SmsTemplateRenderer::logSmsIrParameterError([
                'appointment_id' => !empty($job['appointment_id']) ? (int) $job['appointment_id'] : null,
                'message_type' => (string) ($job['message_type'] ?? ''),
                'missing_parameter_names' => ['provider_template_id'],
            ]);
            return 0;
        }

        /** @var array<string, string> $params */
        $params = [];
        foreach ((array) ($job['template_parameters'] ?? []) as $k => $v) {
            $params[(string) $k] = trim((string) $v);
        }
        $messageType = (string) ($job['message_type'] ?? 'general');
        $requiredParams = $messageType === 'appointment_reminder'
            ? ['FULL_NAME', 'APPOINTMENT_TIME']
            : ['FULL_NAME', 'APPOINTMENT_DATE', 'APPOINTMENT_TIME'];
        $missing = SmsTemplateRenderer::missingSmsIrParameters($params, $requiredParams);
        if ($missing !== []) {
            SmsTemplateRenderer::logSmsIrParameterError([
                'template_id' => $templateId,
                'appointment_id' => !empty($job['appointment_id']) ? (int) $job['appointment_id'] : null,
                'message_type' => $messageType,
                'missing_parameter_names' => $missing,
            ]);
            return 0;
        }

        $summary = trim((string) ($job['log_message'] ?? ''));
        if ($summary === '') {
            $summary = sprintf(
                '[SMS.ir template %d] %s | %s | %s',
                $templateId,
                $params['FULL_NAME'] ?? '',
                $params['APPOINTMENT_DATE'] ?? '',
                $params['APPOINTMENT_TIME'] ?? ''
            );
        }

        return $this->enqueue([
            'patient_id' => $job['patient_id'] ?? null,
            'appointment_id' => $job['appointment_id'] ?? null,
            'template_id' => $job['template_id'] ?? null,
            'automation_rule_id' => $job['automation_rule_id'] ?? null,
            'admin_user_id' => $job['admin_user_id'] ?? null,
            'mobile' => $mobile,
            'message' => $summary,
            'message_type' => $messageType,
            'source' => $job['source'] ?? 'automation',
            'scheduled_at' => $job['scheduled_at'] ?? null,
            'idempotency_key' => $job['idempotency_key'] ?? null,
            'appointment_starts_at' => $job['appointment_starts_at'] ?? null,
            'send_mode' => 'smsir_template',
            'provider_template_id' => $templateId,
            'template_parameters' => $params,
            'skip_placeholder_check' => true,
        ]);
    }

    /**
     * Enqueue one SMS. Returns queue id or 0 if skipped/duplicate.
     *
     * @param array{
     *   patient_id?:?int,
     *   appointment_id?:?int,
     *   template_id?:?int,
     *   automation_rule_id?:?int,
     *   admin_user_id?:?int,
     *   batch_id?:?int,
     *   mobile:string,
     *   message:string,
     *   message_type?:string,
     *   source?:string,
     *   scheduled_at?:?string,
     *   idempotency_key?:?string,
     *   appointment_starts_at?:?string,
     *   send_mode?:string,
     *   provider_template_id?:?int,
     *   template_parameters?:array<string,string>,
     *   skip_placeholder_check?:bool
     * } $job
     */
    public function enqueue(array $job): int
    {
        $this->ensureQueueTemplateColumns();

        $mobile = normalize_mobile((string) ($job['mobile'] ?? ''));
        if ($mobile === null) {
            return 0;
        }
        $message = trim((string) ($job['message'] ?? ''));
        if ($message === '') {
            return 0;
        }

        $sendMode = (string) ($job['send_mode'] ?? 'text');
        if ($sendMode !== 'smsir_template' && empty($job['skip_placeholder_check'])) {
            $unresolved = SmsTemplateRenderer::findUnresolvedKnownPlaceholders($message);
            if ($unresolved !== []) {
                SmsTemplateRenderer::logRenderError('enqueue_blocked', [
                    'template_id' => !empty($job['template_id']) ? (int) $job['template_id'] : null,
                    'appointment_id' => !empty($job['appointment_id']) ? (int) $job['appointment_id'] : null,
                    'message_type' => (string) ($job['message_type'] ?? ''),
                    'source' => (string) ($job['source'] ?? ''),
                    'unresolved' => $unresolved,
                ]);
                return 0;
            }
        }

        $messageType = (string) ($job['message_type'] ?? 'general');
        $appointmentId = !empty($job['appointment_id']) ? (int) $job['appointment_id'] : null;
        if ($appointmentId && $messageType === 'appointment_reminder') {
            if ($this->reminderAlreadyHandled($appointmentId)) {
                return 0;
            }
        }

        $key = $job['idempotency_key'] ?? null;
        if ($key === '') {
            $key = null;
        }
        if ($key) {
            $exists = $this->db->prepare(
                "SELECT id FROM sms_queue WHERE idempotency_key = ? AND status IN ('pending','processing','retrying','sent') LIMIT 1"
            );
            $exists->execute([$key]);
            if ($exists->fetchColumn()) {
                return 0;
            }
            $log = $this->db->prepare(
                "SELECT id FROM sms_logs WHERE idempotency_key = ? AND status = 'sent' LIMIT 1"
            );
            $log->execute([$key]);
            if ($log->fetchColumn()) {
                return 0;
            }
        }

        $max = max(1, min(5, (int) setting('sms_max_retries', 3)));
        $scheduled = $job['scheduled_at'] ?? date('Y-m-d H:i:s');
        $batchId = !empty($job['batch_id']) ? (int) $job['batch_id'] : null;
        $providerTemplateId = !empty($job['provider_template_id']) ? (int) $job['provider_template_id'] : null;
        $paramsJson = null;
        if (!empty($job['template_parameters']) && is_array($job['template_parameters'])) {
            $paramsJson = json_encode($job['template_parameters'], JSON_UNESCAPED_UNICODE);
        }

        try {
            $hasBatch = $this->hasBatchColumn();
            $hasTplCols = $this->hasTemplateQueueColumns();
            if ($hasBatch && $hasTplCols) {
                $stmt = $this->db->prepare(
                    'INSERT INTO sms_queue
                     (patient_id, appointment_id, template_id, automation_rule_id, admin_user_id, batch_id, mobile, rendered_message,
                      message_type, source, status, max_attempts, scheduled_at, idempotency_key, appointment_starts_at,
                      send_mode, provider_template_id, template_parameters_json)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $job['patient_id'] ?? null,
                    $appointmentId,
                    $job['template_id'] ?? null,
                    $job['automation_rule_id'] ?? null,
                    $job['admin_user_id'] ?? null,
                    $batchId,
                    $mobile,
                    $message,
                    $messageType,
                    $job['source'] ?? 'manual',
                    'pending',
                    $max,
                    $scheduled,
                    $key,
                    $job['appointment_starts_at'] ?? null,
                    $sendMode === 'smsir_template' ? 'smsir_template' : 'text',
                    $providerTemplateId,
                    $paramsJson,
                ]);
            } elseif ($hasTplCols) {
                $stmt = $this->db->prepare(
                    'INSERT INTO sms_queue
                     (patient_id, appointment_id, template_id, automation_rule_id, admin_user_id, mobile, rendered_message,
                      message_type, source, status, max_attempts, scheduled_at, idempotency_key, appointment_starts_at,
                      send_mode, provider_template_id, template_parameters_json)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $job['patient_id'] ?? null,
                    $appointmentId,
                    $job['template_id'] ?? null,
                    $job['automation_rule_id'] ?? null,
                    $job['admin_user_id'] ?? null,
                    $mobile,
                    $message,
                    $messageType,
                    $job['source'] ?? 'manual',
                    'pending',
                    $max,
                    $scheduled,
                    $key,
                    $job['appointment_starts_at'] ?? null,
                    $sendMode === 'smsir_template' ? 'smsir_template' : 'text',
                    $providerTemplateId,
                    $paramsJson,
                ]);
            } elseif ($hasBatch) {
                $stmt = $this->db->prepare(
                    'INSERT INTO sms_queue
                     (patient_id, appointment_id, template_id, automation_rule_id, admin_user_id, batch_id, mobile, rendered_message,
                      message_type, source, status, max_attempts, scheduled_at, idempotency_key, appointment_starts_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $job['patient_id'] ?? null,
                    $appointmentId,
                    $job['template_id'] ?? null,
                    $job['automation_rule_id'] ?? null,
                    $job['admin_user_id'] ?? null,
                    $batchId,
                    $mobile,
                    $message,
                    $messageType,
                    $job['source'] ?? 'manual',
                    'pending',
                    $max,
                    $scheduled,
                    $key,
                    $job['appointment_starts_at'] ?? null,
                ]);
            } else {
                $stmt = $this->db->prepare(
                    'INSERT INTO sms_queue
                     (patient_id, appointment_id, template_id, automation_rule_id, admin_user_id, mobile, rendered_message,
                      message_type, source, status, max_attempts, scheduled_at, idempotency_key, appointment_starts_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $job['patient_id'] ?? null,
                    $appointmentId,
                    $job['template_id'] ?? null,
                    $job['automation_rule_id'] ?? null,
                    $job['admin_user_id'] ?? null,
                    $mobile,
                    $message,
                    $messageType,
                    $job['source'] ?? 'manual',
                    'pending',
                    $max,
                    $scheduled,
                    $key,
                    $job['appointment_starts_at'] ?? null,
                ]);
            }
            return (int) $this->db->lastInsertId();
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return 0;
            }
            throw $e;
        }
    }

    private function hasBatchColumn(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $st = $this->db->query("SHOW COLUMNS FROM sms_queue LIKE 'batch_id'");
            $cached = (bool) $st->fetch();
        } catch (\Throwable) {
            $cached = false;
        }
        return $cached;
    }

    private function hasTemplateQueueColumns(): bool
    {
        static $cached = null;
        if ($cached === true) {
            return true;
        }
        try {
            $st = $this->db->query("SHOW COLUMNS FROM sms_queue LIKE 'provider_template_id'");
            $cached = (bool) $st->fetch();
        } catch (\Throwable) {
            $cached = false;
        }
        return (bool) $cached;
    }

    public function ensureQueueTemplateColumns(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $cols = [
            'send_mode' => "VARCHAR(30) NOT NULL DEFAULT 'text'",
            'provider_template_id' => 'INT UNSIGNED NULL',
            'template_parameters_json' => 'TEXT NULL',
        ];
        foreach ($cols as $name => $ddl) {
            try {
                $exists = $this->db->query('SHOW COLUMNS FROM sms_queue LIKE ' . $this->db->quote($name))->fetch(PDO::FETCH_ASSOC);
                if (!$exists) {
                    $this->db->exec("ALTER TABLE sms_queue ADD COLUMN `{$name}` {$ddl}");
                }
            } catch (\Throwable) {
            }
        }
    }

    /** @param list<array<string, mixed>> $jobs */
    public function enqueueMany(array $jobs): int
    {
        $n = 0;
        foreach ($jobs as $job) {
            if ($this->enqueue($job) > 0) {
                $n++;
            }
        }
        return $n;
    }

    public function processQueue(int $limit = 40): array
    {
        $report = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0, 'retried' => 0];
        if (!$this->isEnabled()) {
            return $report;
        }

        $rows = [];
        $this->db->beginTransaction();
        try {
            $sql = "SELECT * FROM sms_queue
                 WHERE status IN ('pending','retrying')
                   AND scheduled_at <= NOW()
                   AND (processing_at IS NULL OR processing_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE))
                 ORDER BY id ASC
                 LIMIT " . (int) $limit;
            try {
                $stmt = $this->db->query($sql . ' FOR UPDATE SKIP LOCKED');
            } catch (\Throwable) {
                $stmt = $this->db->query($sql . ' FOR UPDATE');
            }
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $ids = array_map(static fn (array $r): int => (int) $r['id'], $rows);
            if ($ids) {
                $in = implode(',', $ids);
                $this->db->exec("UPDATE sms_queue SET status='processing', processing_at=NOW() WHERE id IN ({$in})");
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $provider = SmsManager::make();
        foreach ($rows as $row) {
            $report['processed']++;
            try {
                $result = $this->processOne($row, $provider);
                $report[$result]++;
            } catch (\Throwable $e) {
                $report['failed']++;
                try {
                    $this->finish((int) $row['id'], 'failed', 'خطای غیرمنتظره در ارسال');
                } catch (\Throwable) {
                }
                // Continue remaining queue items on shared hosting.
            }
        }
        return $report;
    }

    /** @param array<string, mixed> $row */
    private function processOne(array $row, \Sarva\Interfaces\SmsProviderInterface $provider): string
    {
        $id = (int) $row['id'];
        $appointmentId = $row['appointment_id'] ? (int) $row['appointment_id'] : null;
        $message = (string) $row['rendered_message'];
        $a = null;

        if ($appointmentId) {
            $appt = $this->db->prepare(
                "SELECT a.status, a.starts_at, a.deleted_at, a.reminder_sent_at,
                        CONCAT(d.first_name,' ',d.last_name) doctor_name, s.name service_name,
                        p.first_name, p.last_name, p.file_number, p.public_code, p.mobile
                 FROM appointments a
                 JOIN patients p ON p.id = a.patient_id
                 JOIN doctors d ON d.id = a.doctor_id
                 JOIN services s ON s.id = a.service_id
                 WHERE a.id = ?"
            );
            $appt->execute([$appointmentId]);
            $a = $appt->fetch(PDO::FETCH_ASSOC);
            $messageType = (string) ($row['message_type'] ?? '');
            $isCancellationSms = $messageType === 'appointment_cancellation';
            if (!$a || $a['deleted_at']) {
                $this->finish($id, 'cancelled', 'نوبت دیگر واجد شرایط ارسال نیست.');
                return 'cancelled';
            }
            // Cancellation SMS is intentionally sent after status=cancelled.
            if (
                !$isCancellationSms
                && in_array($a['status'], ['cancelled', 'expired', 'no_show', 'completed', 'awaiting_payment'], true)
            ) {
                $this->finish($id, 'cancelled', 'نوبت دیگر واجد شرایط ارسال نیست.');
                return 'cancelled';
            }
            if ($messageType === 'appointment_reminder' && !empty($a['reminder_sent_at'])) {
                $this->finish($id, 'cancelled', 'یادآوری این نوبت قبلاً ارسال شده است.');
                return 'cancelled';
            }
            if (!empty($row['automation_rule_id'])) {
                $st = $this->db->prepare('SELECT * FROM sms_automation_rules WHERE id=? AND is_active=1');
                $st->execute([(int) $row['automation_rule_id']]);
                $rule = $st->fetch(PDO::FETCH_ASSOC);
                if (!$rule) {
                    $this->finish($id, 'cancelled', 'قانون خودکار غیرفعال است.');
                    return 'cancelled';
                }
                $allowed = array_filter(array_map('trim', explode(',', (string) ($rule['appointment_statuses'] ?: 'confirmed'))));
                if ($allowed && !in_array((string) $a['status'], $allowed, true)) {
                    $this->finish($id, 'cancelled', 'وضعیت نوبت دیگر واجد شرایط نیست.');
                    return 'cancelled';
                }
                $queuedStart = (string) ($row['appointment_starts_at'] ?? '');
                if ($queuedStart !== '' && date('Y-m-d', strtotime($queuedStart) ?: 0) !== date('Y-m-d', strtotime((string) $a['starts_at']) ?: 0)) {
                    $this->finish($id, 'cancelled', 'زمان نوبت تغییر کرده است؛ یادآوری جدید در صورت نیاز دوباره صف می‌شود.');
                    return 'cancelled';
                }
            }
            if (!empty($row['template_id']) && !empty($a['starts_at']) && (string) $a['starts_at'] !== (string) ($row['appointment_starts_at'] ?? '')) {
                $tpl = $this->db->prepare('SELECT body FROM sms_templates WHERE id=?');
                $tpl->execute([(int) $row['template_id']]);
                $body = (string) $tpl->fetchColumn();
                if ($body !== '') {
                    $rendered = SmsTemplateRenderer::renderForSend($body, SmsTemplateRenderer::varsFrom($a, $a + [
                        'starts_at' => $a['starts_at'],
                    ]));
                    if (!$rendered['ok']) {
                        SmsTemplateRenderer::logRenderError('queue_re_render', [
                            'template_id' => (int) $row['template_id'],
                            'queue_id' => $id,
                            'appointment_id' => $appointmentId,
                            'unresolved' => $rendered['unresolved'],
                        ]);
                        $this->finish($id, 'failed', 'template_render_error: ' . implode(',', $rendered['unresolved']));
                        return 'failed';
                    }
                    $message = $rendered['message'];
                    $this->db->prepare('UPDATE sms_queue SET rendered_message=?, appointment_starts_at=? WHERE id=?')
                        ->execute([$message, $a['starts_at'], $id]);
                }
            }
        }

        $unresolvedQueued = SmsTemplateRenderer::findUnresolvedKnownPlaceholders($message);
        $sendMode = (string) ($row['send_mode'] ?? 'text');
        if ($sendMode !== 'smsir_template' && $unresolvedQueued !== []) {
            SmsTemplateRenderer::logRenderError('queue_send_blocked', [
                'template_id' => !empty($row['template_id']) ? (int) $row['template_id'] : null,
                'queue_id' => $id,
                'appointment_id' => $appointmentId,
                'unresolved' => $unresolvedQueued,
            ]);
            $this->finish($id, 'failed', 'template_render_error: ' . implode(',', $unresolvedQueued));
            return 'failed';
        }

        $attempts = (int) $row['attempts'] + 1;
        $max = (int) ($row['max_attempts'] ?: 3);
        $send = $this->dispatchSend($provider, $row, $a, $message);
        $ok = (bool) ($send['ok'] ?? false);

        $this->writeLog($row, $message, $send, $attempts, $ok);

        if ($ok) {
            $this->db->prepare(
                "UPDATE sms_queue SET status='sent', sent_at=NOW(), attempts=?, provider=?, provider_message_id=?, last_error=NULL, processing_at=NULL WHERE id=?"
            )->execute([$attempts, $send['provider'] ?? SmsManager::driver(), $send['message_id'] ?? null, $id]);
            if ($appointmentId && ($row['message_type'] ?? '') === 'appointment_reminder') {
                $this->db->prepare('UPDATE appointments SET reminder_sent_at=NOW() WHERE id=? AND reminder_sent_at IS NULL')
                    ->execute([$appointmentId]);
            }
            return 'sent';
        }

        $error = (string) ($send['error'] ?? 'ارسال ناموفق');
        $permanent = (bool) ($send['permanent'] ?? false);
        if ($permanent || $attempts >= $max) {
            $this->db->prepare(
                "UPDATE sms_queue SET status='failed', attempts=?, last_error=?, processing_at=NULL WHERE id=?"
            )->execute([$attempts, mb_substr($error, 0, 500), $id]);
            return 'failed';
        }

        $delay = max(5, (int) setting('sms_retry_delay_minutes', 10));
        $this->db->prepare(
            "UPDATE sms_queue SET status='retrying', attempts=?, last_error=?, processing_at=NULL, scheduled_at=DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id=?"
        )->execute([$attempts, mb_substr($error, 0, 500), $delay, $id]);
        return 'retried';
    }

    /**
     * Resolve SMS.ir Verify template ID strictly by message_type.
     * Never falls back across confirmation / reminder / cancellation.
     */
    public static function smsIrTemplateIdForMessageType(string $messageType): int
    {
        return match ($messageType) {
            'appointment_confirmation' => (int) (
                config('sms.appointment_confirmation_template_id', 0)
                ?: ($_ENV['SMSIR_APPOINTMENT_CONFIRMATION_TEMPLATE_ID'] ?? getenv('SMSIR_APPOINTMENT_CONFIRMATION_TEMPLATE_ID') ?: 0)
            ),
            'appointment_cancellation' => (int) (
                config('sms.appointment_cancellation_template_id', 0)
                ?: ($_ENV['SMSIR_APPOINTMENT_CANCELLATION_TEMPLATE_ID'] ?? getenv('SMSIR_APPOINTMENT_CANCELLATION_TEMPLATE_ID') ?: 0)
            ),
            'appointment_reminder' => (int) (
                config('sms.appointment_reminder_template_id', 0)
                ?: ($_ENV['SMSIR_APPOINTMENT_REMINDER_TEMPLATE_ID'] ?? getenv('SMSIR_APPOINTMENT_REMINDER_TEMPLATE_ID') ?: 0)
            ),
            default => 0,
        };
    }

    /**
     * Deliver via SMS.ir verify template when configured for reminder/confirmation/cancellation.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed>|null $appointment
     * @return array<string, mixed>
     */
    private function dispatchSend(
        \Sarva\Interfaces\SmsProviderInterface $provider,
        array $row,
        ?array $appointment,
        string $message
    ): array {
        $mobile = (string) $row['mobile'];
        $type = (string) ($row['message_type'] ?? 'general');

        if (in_array($type, ['appointment_confirmation', 'appointment_cancellation', 'appointment_reminder'], true)) {
            return $this->dispatchAppointmentTemplate($provider, $row, $appointment, $type, $mobile);
        }

        return $provider->sendMessage($mobile, $message);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed>|null $appointment
     * @return array<string, mixed>
     */
    private function dispatchAppointmentTemplate(
        \Sarva\Interfaces\SmsProviderInterface $provider,
        array $row,
        ?array $appointment,
        string $type,
        string $mobile
    ): array {
        $expectedId = self::smsIrTemplateIdForMessageType($type);
        $storedId = (int) ($row['provider_template_id'] ?? 0);

        // Always prefer the config ID for this message_type. Never use another type's template.
        $templateId = $expectedId;
        if ($templateId <= 0 && $storedId > 0) {
            $belongsToOtherType = false;
            foreach (['appointment_confirmation', 'appointment_cancellation', 'appointment_reminder'] as $otherType) {
                if ($otherType === $type) {
                    continue;
                }
                $otherId = self::smsIrTemplateIdForMessageType($otherType);
                if ($otherId > 0 && $storedId === $otherId) {
                    $belongsToOtherType = true;
                    break;
                }
            }
            if (!$belongsToOtherType) {
                $templateId = $storedId;
            }
        }

        if ($templateId <= 0) {
            $event = match ($type) {
                'appointment_confirmation' => 'smsir_confirmation_template_missing',
                'appointment_cancellation' => 'smsir_cancellation_template_missing',
                default => 'smsir_reminder_template_missing',
            };
            SmsTemplateRenderer::logEvent($event, [
                'message_type' => $type,
                'appointment_id' => !empty($row['appointment_id']) ? (int) $row['appointment_id'] : null,
                'queue_id' => !empty($row['id']) ? (int) $row['id'] : null,
                'stored_provider_template_id' => $storedId ?: null,
            ]);
            return [
                'ok' => false,
                'provider' => SmsManager::driver(),
                'error' => 'شناسه قالب SMS.ir برای ' . $type . ' تنظیم نشده است.',
                'permanent' => true,
                'message_channel' => 'verify',
                'event' => $event,
            ];
        }

        if ($storedId > 0 && $expectedId > 0 && $storedId !== $expectedId) {
            SmsTemplateRenderer::logEvent('smsir_template_id_mismatch_corrected', [
                'message_type' => $type,
                'appointment_id' => !empty($row['appointment_id']) ? (int) $row['appointment_id'] : null,
                'stored_provider_template_id' => $storedId,
                'expected_provider_template_id' => $expectedId,
            ]);
        }

        $paramsAssoc = $this->resolveTemplateParametersForType($type, $row, $appointment);
        $required = $type === 'appointment_reminder'
            ? ['FULL_NAME', 'APPOINTMENT_TIME']
            : ['FULL_NAME', 'APPOINTMENT_DATE', 'APPOINTMENT_TIME'];
        $missing = SmsTemplateRenderer::missingSmsIrParameters($paramsAssoc, $required);
        if ($missing !== []) {
            SmsTemplateRenderer::logSmsIrParameterError([
                'template_id' => $templateId,
                'appointment_id' => !empty($row['appointment_id']) ? (int) $row['appointment_id'] : null,
                'missing_parameter_names' => $missing,
                'message_type' => $type,
            ]);
            return [
                'ok' => false,
                'provider' => SmsManager::driver(),
                'error' => 'پارامترهای قالب SMS.ir ناقص است: ' . implode(',', $missing),
                'permanent' => true,
                'message_channel' => 'verify',
                'provider_template_id' => $templateId,
            ];
        }

        // Reminder template only needs FULL_NAME + APPOINTMENT_TIME
        if ($type === 'appointment_reminder') {
            $paramsAssoc = [
                'FULL_NAME' => $paramsAssoc['FULL_NAME'],
                'APPOINTMENT_TIME' => $paramsAssoc['APPOINTMENT_TIME'],
            ];
        }

        $result = $provider->sendTemplate(
            $mobile,
            $templateId,
            SmsTemplateRenderer::toSmsIrParameterList($paramsAssoc)
        );
        $result['provider_template_id'] = $templateId;
        $result['message_channel'] = $result['message_channel'] ?? 'verify';
        return $result;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed>|null $appointment
     * @return array<string, string>
     */
    private function resolveTemplateParametersForType(string $type, array $row, ?array $appointment): array
    {
        $fromJson = [];
        $raw = $row['template_parameters_json'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $k => $v) {
                    $fromJson[(string) $k] = trim((string) $v);
                }
            }
        }

        if ($appointment) {
            $built = SmsTemplateRenderer::buildSmsIrAppointmentParameters($appointment, $appointment);
            // Prefer explicit queued params when present; fill gaps from appointment.
            return [
                'FULL_NAME' => trim((string) ($fromJson['FULL_NAME'] ?? '')) !== ''
                    ? $fromJson['FULL_NAME']
                    : $built['FULL_NAME'],
                'APPOINTMENT_DATE' => trim((string) ($fromJson['APPOINTMENT_DATE'] ?? '')) !== ''
                    ? $fromJson['APPOINTMENT_DATE']
                    : $built['APPOINTMENT_DATE'],
                'APPOINTMENT_TIME' => trim((string) ($fromJson['APPOINTMENT_TIME'] ?? '')) !== ''
                    ? $fromJson['APPOINTMENT_TIME']
                    : $built['APPOINTMENT_TIME'],
            ];
        }

        return [
            'FULL_NAME' => (string) ($fromJson['FULL_NAME'] ?? ''),
            'APPOINTMENT_DATE' => (string) ($fromJson['APPOINTMENT_DATE'] ?? ''),
            'APPOINTMENT_TIME' => (string) ($fromJson['APPOINTMENT_TIME'] ?? ''),
        ];
    }

    private function reminderAlreadyHandled(int $appointmentId): bool
    {
        try {
            $st = $this->db->prepare(
                "SELECT reminder_sent_at FROM appointments WHERE id=? AND deleted_at IS NULL LIMIT 1"
            );
            $st->execute([$appointmentId]);
            $sentAt = $st->fetchColumn();
            if ($sentAt) {
                return true;
            }
        } catch (\Throwable) {
        }

        try {
            $q = $this->db->prepare(
                "SELECT id FROM sms_queue
                 WHERE appointment_id=? AND message_type='appointment_reminder'
                   AND status IN ('pending','processing','retrying','sent')
                 LIMIT 1"
            );
            $q->execute([$appointmentId]);
            if ($q->fetchColumn()) {
                return true;
            }
        } catch (\Throwable) {
        }

        try {
            $l = $this->db->prepare(
                "SELECT id FROM sms_logs
                 WHERE appointment_id=? AND message_type='appointment_reminder' AND status='sent'
                 LIMIT 1"
            );
            $l->execute([$appointmentId]);
            if ($l->fetchColumn()) {
                return true;
            }
        } catch (\Throwable) {
        }

        return false;
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $send */
    private function writeLog(array $row, string $message, array $send, int $attempts, bool $ok): void
    {
        $key = $row['idempotency_key'] ?: null;
        // Strip any accidental credential-like keys before persisting provider payload.
        $safeSend = $send;
        unset($safeSend['api_key'], $safeSend['headers'], $safeSend['request'], $safeSend['raw']);
        $messageType = (string) ($row['message_type'] ?? 'general');
        $source = (string) ($row['source'] ?? 'manual');
        $safeSend['provider'] = $safeSend['provider'] ?? SmsManager::driver();
        $safeSend['sender_line'] = $safeSend['sender_line'] ?? (string) (config('sms.line_number') ?: '');
        $safeSend['message_type'] = $messageType;
        $safeSend['source'] = $source;
        $safeSend['message_channel'] = $safeSend['message_channel']
            ?? ((($messageType === 'appointment_reminder' && (int) config('sms.appointment_reminder_template_id', 0) > 0)
                || ($messageType === 'appointment_confirmation' && (int) config('sms.appointment_confirmation_template_id', 0) > 0)
                || ($messageType === 'appointment_cancellation' && (int) config('sms.appointment_cancellation_template_id', 0) > 0)
                || (($row['send_mode'] ?? '') === 'smsir_template'))
                ? 'verify'
                : 'bulk');
        if (!empty($row['provider_template_id']) || !empty($safeSend['provider_template_id'])) {
            $safeSend['provider_template_id'] = (int) ($safeSend['provider_template_id'] ?? $row['provider_template_id']);
        }
        if ($source === 'automation' || !empty($row['automation_rule_id'])) {
            $safeSend['message_type'] = $safeSend['message_type'] ?: 'automation';
            $safeSend['automation_rule_id'] = $row['automation_rule_id'] ?: null;
        }
        $safeSend['provider_status'] = $safeSend['provider_status'] ?? ($safeSend['status_code'] ?? ($ok ? 1 : 0));
        $safeSend['message_id'] = $safeSend['message_id'] ?? null;
        try {
            $logPayload = [
                'provider' => $safeSend['provider'],
                'sender_line' => $safeSend['sender_line'] !== '' ? $safeSend['sender_line'] : null,
                'message_type' => $safeSend['message_type'],
                'source' => $source,
                'message_channel' => $safeSend['message_channel'],
                'provider_status' => $safeSend['provider_status'],
                'message_id' => $safeSend['message_id'],
                'ok' => $ok,
                'error' => $ok ? null : ($safeSend['error'] ?? null),
                'http' => $safeSend['http'] ?? null,
                'automation_rule_id' => $safeSend['automation_rule_id'] ?? null,
                'event' => $safeSend['event'] ?? null,
                'duration_ms' => $safeSend['duration_ms'] ?? null,
                'provider_template_id' => $safeSend['provider_template_id'] ?? ($row['provider_template_id'] ?? null),
            ];
            if ($this->hasLogBatchColumn()) {
                $this->db->prepare(
                    'INSERT INTO sms_logs
                     (patient_id, appointment_id, template_id, automation_rule_id, queue_id, batch_id, mobile, message_type, source,
                      admin_user_id, message_body, provider, provider_message_id, status, attempts, last_error,
                      provider_response, idempotency_key, sent_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([
                    $row['patient_id'] ?: null,
                    $row['appointment_id'] ?: null,
                    $row['template_id'] ?: null,
                    $row['automation_rule_id'] ?: null,
                    $row['id'] ?? null,
                    $row['batch_id'] ?: null,
                    $row['mobile'],
                    $messageType,
                    $source,
                    $row['admin_user_id'] ?: null,
                    $message,
                    $logPayload['provider'],
                    $logPayload['message_id'],
                    $ok ? 'sent' : 'failed',
                    $attempts,
                    $ok ? null : mb_substr((string) ($safeSend['error'] ?? ''), 0, 500),
                    json_encode($logPayload, JSON_UNESCAPED_UNICODE),
                    $ok ? $key : null,
                    $ok ? date('Y-m-d H:i:s') : null,
                ]);
            } else {
                $this->db->prepare(
                    'INSERT INTO sms_logs
                     (patient_id, appointment_id, template_id, automation_rule_id, queue_id, mobile, message_type, source,
                      admin_user_id, message_body, provider, provider_message_id, status, attempts, last_error,
                      provider_response, idempotency_key, sent_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([
                    $row['patient_id'] ?: null,
                    $row['appointment_id'] ?: null,
                    $row['template_id'] ?: null,
                    $row['automation_rule_id'] ?: null,
                    $row['id'] ?? null,
                    $row['mobile'],
                    $messageType,
                    $source,
                    $row['admin_user_id'] ?: null,
                    $message,
                    $logPayload['provider'],
                    $logPayload['message_id'],
                    $ok ? 'sent' : 'failed',
                    $attempts,
                    $ok ? null : mb_substr((string) ($safeSend['error'] ?? ''), 0, 500),
                    json_encode($logPayload, JSON_UNESCAPED_UNICODE),
                    $ok ? $key : null,
                    $ok ? date('Y-m-d H:i:s') : null,
                ]);
            }
        } catch (\Throwable) {
        }
    }

    private function hasLogBatchColumn(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $st = $this->db->query("SHOW COLUMNS FROM sms_logs LIKE 'batch_id'");
            $cached = (bool) $st->fetch();
        } catch (\Throwable) {
            $cached = false;
        }
        return $cached;
    }

    private function finish(int $id, string $status, string $error): void
    {
        $this->db->prepare('UPDATE sms_queue SET status=?, last_error=?, processing_at=NULL WHERE id=?')
            ->execute([$status, mb_substr($error, 0, 500), $id]);
    }

    public function cancelPendingForAppointment(int $appointmentId): void
    {
        $this->db->prepare(
            "UPDATE sms_queue SET status='cancelled', last_error='نوبت لغو یا نامعتبر شد'
             WHERE appointment_id=? AND status IN ('pending','retrying')
               AND (message_type IS NULL OR message_type <> 'appointment_cancellation')"
        )->execute([$appointmentId]);
    }

    public function stats(): array
    {
        $q = static function (PDO $db, string $sql): int {
            try {
                return (int) $db->query($sql)->fetchColumn();
            } catch (\Throwable) {
                return 0;
            }
        };
        return [
            'sent_today' => $q($this->db, "SELECT COUNT(*) FROM sms_logs WHERE status='sent' AND DATE(COALESCE(sent_at,created_at))=CURDATE()"),
            'queued' => $q($this->db, "SELECT COUNT(*) FROM sms_queue WHERE status IN ('pending','retrying','processing')"),
            'scheduled_today' => $q($this->db, "SELECT COUNT(*) FROM sms_queue WHERE DATE(scheduled_at)=CURDATE() AND status IN ('pending','retrying')"),
            'failed' => $q($this->db, "SELECT COUNT(*) FROM sms_logs WHERE status='failed' AND DATE(created_at)=CURDATE()"),
            'tomorrow_appts' => $q($this->db, "SELECT COUNT(*) FROM appointments WHERE status='confirmed' AND deleted_at IS NULL AND DATE(starts_at)=CURDATE()+INTERVAL 1 DAY"),
            'tomorrow_queued' => $q($this->db, "SELECT COUNT(*) FROM sms_queue WHERE message_type='appointment_reminder' AND status IN ('pending','retrying') AND appointment_id IN (SELECT id FROM appointments WHERE DATE(starts_at)=CURDATE()+INTERVAL 1 DAY)"),
            'tomorrow_sent' => $q($this->db, "SELECT COUNT(*) FROM sms_logs WHERE message_type='appointment_reminder' AND status='sent' AND DATE(COALESCE(sent_at,created_at))=CURDATE() AND appointment_id IN (SELECT id FROM appointments WHERE DATE(starts_at)=CURDATE()+INTERVAL 1 DAY)"),
            'tomorrow_failed' => $q($this->db, "SELECT COUNT(*) FROM sms_queue WHERE message_type='appointment_reminder' AND status='failed' AND appointment_id IN (SELECT id FROM appointments WHERE DATE(starts_at)=CURDATE()+INTERVAL 1 DAY)"),
        ];
    }
}
