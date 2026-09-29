<?php

declare(strict_types=1);

namespace Sarva\Services;

use PDO;
use PDOException;
use Sarva\Sms\LogSmsProvider;
use Sarva\Sms\SmsIrProvider;
use Sarva\Sms\SmsManager;

final class AppointmentService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return list<string> HH:MM slots */
    public function availableSlots(int $doctorId, string $date, int $durationMinutes): array
    {
        $weekday = (int) date('w', strtotime($date));
        $sched = $this->db->prepare(
            'SELECT * FROM doctor_schedules WHERE doctor_id = :d AND weekday = :w AND is_active = 1 LIMIT 1'
        );
        $sched->execute(['d' => $doctorId, 'w' => $weekday]);
        $schedule = $sched->fetch(PDO::FETCH_ASSOC);
        if (!$schedule) {
            return [];
        }

        $off = $this->db->prepare(
            'SELECT 1 FROM doctor_time_off WHERE doctor_id = :d
             AND start_datetime < :day_end AND end_datetime > :day_start LIMIT 1'
        );
        $off->execute([
            'd' => $doctorId,
            'day_start' => $date . ' 00:00:00',
            'day_end' => $date . ' 23:59:59',
        ]);
        if ($off->fetchColumn()) {
            return [];
        }

        $slotDur = (int) ($schedule['slot_duration'] ?: $durationMinutes);
        $start = strtotime($date . ' ' . $schedule['start_time']);
        $end = strtotime($date . ' ' . $schedule['end_time']);
        $breakStart = $schedule['break_start'] ? strtotime($date . ' ' . $schedule['break_start']) : null;
        $breakEnd = $schedule['break_end'] ? strtotime($date . ' ' . $schedule['break_end']) : null;

        $booked = $this->db->prepare(
            "SELECT starts_at, ends_at FROM appointments
             WHERE doctor_id = :d
               AND DATE(starts_at) = :date
               AND status IN ('awaiting_payment','confirmed','completed')
               AND (status <> 'awaiting_payment' OR hold_expires_at IS NULL OR hold_expires_at > NOW())
               AND deleted_at IS NULL"
        );
        $booked->execute(['d' => $doctorId, 'date' => $date]);
        $busy = $booked->fetchAll(PDO::FETCH_ASSOC);

        $slots = [];
        for ($t = $start; $t + ($durationMinutes * 60) <= $end; $t += $slotDur * 60) {
            $slotEnd = $t + ($durationMinutes * 60);
            if ($breakStart && $breakEnd && $t < $breakEnd && $slotEnd > $breakStart) {
                continue;
            }
            if ($t < time()) {
                continue;
            }
            $conflict = false;
            foreach ($busy as $b) {
                $bs = strtotime($b['starts_at']);
                $be = strtotime($b['ends_at']);
                if ($t < $be && $slotEnd > $bs) {
                    $conflict = true;
                    break;
                }
            }
            if (!$conflict) {
                $slots[] = date('H:i', $t);
            }
        }
        return $slots;
    }

    public function createHold(int $patientId, int $doctorId, int $serviceId, string $startsAt, int $fee): array
    {
        $duration = (int) config('app.default_appointment_duration', 30);
        $svc = $this->db->prepare('SELECT duration_minutes, price FROM services WHERE id = :id AND is_active = 1');
        $svc->execute(['id' => $serviceId]);
        $service = $svc->fetch(PDO::FETCH_ASSOC);
        if (!$service) {
            return ['ok' => false, 'message' => 'خدمت انتخاب‌شده معتبر نیست.'];
        }
        $duration = (int) ($service['duration_minutes'] ?: $duration);
        $fee = $fee > 0 ? $fee : (int) ($service['price'] ?: setting('consultation_fee', 500000));

        $endsAt = date('Y-m-d H:i:s', strtotime($startsAt) + $duration * 60);
        $holdMinutes = (int) config('app.appointment_hold_minutes', 15);

        try {
            $this->db->beginTransaction();

            // Lock potential conflicts
            $lock = $this->db->prepare(
                "SELECT id FROM appointments
                 WHERE doctor_id = :d AND starts_at = :s
                   AND status IN ('awaiting_payment','confirmed','completed')
                   AND (status <> 'awaiting_payment' OR hold_expires_at IS NULL OR hold_expires_at > NOW())
                   AND deleted_at IS NULL
                 FOR UPDATE"
            );
            $lock->execute(['d' => $doctorId, 's' => $startsAt]);
            if ($lock->fetch()) {
                $this->db->rollBack();
                return ['ok' => false, 'message' => 'این بازه زمانی قبلاً رزرو شده است.'];
            }

            $ins = $this->db->prepare(
                "INSERT INTO appointments
                 (patient_id, doctor_id, service_id, starts_at, ends_at, status, payment_status, fee_amount, hold_expires_at)
                 VALUES (:p,:d,:s,:st,:en,'awaiting_payment','pending',:fee, DATE_ADD(NOW(), INTERVAL :hold MINUTE))"
            );
            $ins->bindValue('p', $patientId, PDO::PARAM_INT);
            $ins->bindValue('d', $doctorId, PDO::PARAM_INT);
            $ins->bindValue('s', $serviceId, PDO::PARAM_INT);
            $ins->bindValue('st', $startsAt);
            $ins->bindValue('en', $endsAt);
            $ins->bindValue('fee', $fee);
            $ins->bindValue('hold', $holdMinutes, PDO::PARAM_INT);
            $ins->execute();
            $id = (int) $this->db->lastInsertId();

            $hist = $this->db->prepare(
                'INSERT INTO appointment_status_history (appointment_id, from_status, to_status, changed_by_patient, note)
                 VALUES (:id, NULL, :to, :pid, :note)'
            );
            $hist->execute([
                'id' => $id,
                'to' => 'awaiting_payment',
                'pid' => $patientId,
                'note' => 'ایجاد رزرو موقت',
            ]);

            $this->db->commit();
            return ['ok' => true, 'appointment_id' => $id, 'fee' => $fee, 'ends_at' => $endsAt];
        } catch (PDOException $e) {
            $this->db->rollBack();
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'message' => 'این بازه زمانی قبلاً رزرو شده است.'];
            }
            throw $e;
        }
    }

    public function confirmPaid(int $appointmentId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE appointments SET status = 'confirmed', payment_status = 'paid', hold_expires_at = NULL WHERE id = :id"
        );
        $stmt->execute(['id' => $appointmentId]);
        $hist = $this->db->prepare(
            "INSERT INTO appointment_status_history (appointment_id, from_status, to_status, note)
             VALUES (:id, 'awaiting_payment', 'confirmed', 'پرداخت موفق')"
        );
        $hist->execute(['id' => $appointmentId]);

        $this->sendAppointmentConfirmationNow($appointmentId);
    }

    /**
     * Admin-created appointment: status=confirmed. After commit, sends confirmation SMS directly (never reminder).
     *
     * @return array{ok:bool, message?:string, appointment_id?:int, sms_sent?:bool, sms_duplicate?:bool}
     */
    public function createConfirmedByAdmin(
        int $patientId,
        int $doctorId,
        int $serviceId,
        string $startsAt,
        int $adminId,
        ?string $notes = null,
        string $paymentStatus = 'unpaid'
    ): array {
        $patient = $this->db->prepare('SELECT id FROM patients WHERE id = ? AND deleted_at IS NULL');
        $patient->execute([$patientId]);
        if (!$patient->fetchColumn()) {
            return ['ok' => false, 'message' => 'بیمار یافت نشد.'];
        }

        $doctor = $this->db->prepare('SELECT id FROM doctors WHERE id = ? AND deleted_at IS NULL');
        $doctor->execute([$doctorId]);
        if (!$doctor->fetchColumn()) {
            return ['ok' => false, 'message' => 'پزشک یافت نشد.'];
        }

        $duration = (int) config('app.default_appointment_duration', 30);
        $svc = $this->db->prepare('SELECT duration_minutes, price FROM services WHERE id = :id AND deleted_at IS NULL');
        $svc->execute(['id' => $serviceId]);
        $service = $svc->fetch(PDO::FETCH_ASSOC);
        if (!$service) {
            return ['ok' => false, 'message' => 'خدمت انتخاب‌شده معتبر نیست.'];
        }
        $duration = (int) ($service['duration_minutes'] ?: $duration);
        $fee = (int) ($service['price'] ?: setting('consultation_fee', 500000));

        $startsTs = strtotime($startsAt);
        if ($startsTs === false) {
            return ['ok' => false, 'message' => 'زمان نوبت نامعتبر است.'];
        }
        $startsAt = date('Y-m-d H:i:s', $startsTs);
        $endsAt = date('Y-m-d H:i:s', $startsTs + $duration * 60);

        $allowedPayment = ['unpaid', 'paid', 'pending'];
        if (!in_array($paymentStatus, $allowedPayment, true)) {
            $paymentStatus = 'unpaid';
        }

        $notes = $notes !== null ? trim($notes) : null;
        if ($notes === '') {
            $notes = null;
        }

        try {
            $this->db->beginTransaction();

            $lock = $this->db->prepare(
                "SELECT id FROM appointments
                 WHERE doctor_id = :d
                   AND starts_at < :en AND ends_at > :st
                   AND status IN ('awaiting_payment','confirmed','completed')
                   AND (status <> 'awaiting_payment' OR hold_expires_at IS NULL OR hold_expires_at > NOW())
                   AND deleted_at IS NULL
                 FOR UPDATE"
            );
            $lock->execute(['d' => $doctorId, 'st' => $startsAt, 'en' => $endsAt]);
            if ($lock->fetch()) {
                $this->db->rollBack();
                return ['ok' => false, 'message' => 'این بازه زمانی قبلاً رزرو شده است.'];
            }

            $ins = $this->db->prepare(
                "INSERT INTO appointments
                 (patient_id, doctor_id, service_id, starts_at, ends_at, status, payment_status, fee_amount, notes, hold_expires_at, created_by_admin)
                 VALUES (:p,:d,:s,:st,:en,'confirmed',:pay,:fee,:notes,NULL,:admin)"
            );
            $ins->bindValue('p', $patientId, PDO::PARAM_INT);
            $ins->bindValue('d', $doctorId, PDO::PARAM_INT);
            $ins->bindValue('s', $serviceId, PDO::PARAM_INT);
            $ins->bindValue('st', $startsAt);
            $ins->bindValue('en', $endsAt);
            $ins->bindValue('pay', $paymentStatus);
            $ins->bindValue('fee', $fee);
            $ins->bindValue('notes', $notes);
            $ins->bindValue('admin', $adminId, PDO::PARAM_INT);
            $ins->execute();
            $id = (int) $this->db->lastInsertId();

            $hist = $this->db->prepare(
                'INSERT INTO appointment_status_history (appointment_id, from_status, to_status, changed_by_admin, note)
                 VALUES (:id, NULL, :to, :aid, :note)'
            );
            $hist->execute([
                'id' => $id,
                'to' => 'confirmed',
                'aid' => $adminId,
                'note' => 'ثبت دستی نوبت توسط مدیریت',
            ]);

            $this->db->commit();
            $sms = $this->sendAppointmentConfirmationNow($id);

            return [
                'ok' => true,
                'appointment_id' => $id,
                'sms_sent' => $sms['sent'],
                'sms_duplicate' => $sms['duplicate'],
                'message' => $sms['sent']
                    ? 'نوبت با وضعیت تأییدشده ثبت شد و پیامک تأیید ارسال شد.'
                    : ($sms['duplicate']
                        ? 'نوبت ثبت شد. پیامک تأیید قبلاً ارسال شده است.'
                        : 'نوبت با موفقیت ثبت شد، اما ارسال پیامک تأیید با خطا مواجه شد.'),
            ];
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'message' => 'این بازه زمانی قبلاً رزرو شده است.'];
            }
            throw $e;
        }
    }

    /**
     * Direct SMS.ir confirmation. Call only after the appointment transaction is committed.
     * Does not enqueue and does not set reminder_sent_at.
     *
     * @return array{ok:bool,sent:bool,duplicate:bool,message:string,message_id:?string,sms_api_ms:int,total_ms:int}
     */
    public function sendAppointmentConfirmationNow(int $appointmentId): array
    {
        $started = hrtime(true);
        $fail = static function (string $message, int $startedAt, int $apiMs = 0) use ($appointmentId): array {
            return [
                'ok' => false,
                'sent' => false,
                'duplicate' => false,
                'message' => $message,
                'message_id' => null,
                'sms_api_ms' => $apiMs,
                'total_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            ];
        };

        if ($this->db->inTransaction()) {
            return $fail('تراکنش دیتابیس هنوز باز است.', $started);
        }

        $templateId = SmsService::smsIrTemplateIdForMessageType('appointment_confirmation');
        $idempotencyKey = 'appointment_confirmation:' . $appointmentId;
        $legacyKey = 'confirm:' . $appointmentId;

        if ($templateId <= 0) {
            SmsTemplateRenderer::logEvent('smsir_confirmation_template_missing', [
                'appointment_id' => $appointmentId,
                'message_type' => 'appointment_confirmation',
            ]);
            return $fail('شناسه قالب تأیید نوبت تنظیم نشده است.', $started);
        }

        try {
            if ($this->confirmationAlreadyAccepted($appointmentId, $idempotencyKey, $legacyKey)) {
                SmsTemplateRenderer::logEvent('APPOINTMENT_CONFIRMATION_SKIPPED_DUPLICATE', [
                    'appointment_id' => $appointmentId,
                    'template_id' => $templateId,
                    'idempotency_key' => $idempotencyKey,
                ]);
                $total = (int) round((hrtime(true) - $started) / 1_000_000);
                return [
                    'ok' => true,
                    'sent' => false,
                    'duplicate' => true,
                    'message' => 'پیامک تأیید قبلاً ارسال شده است.',
                    'message_id' => null,
                    'sms_api_ms' => 0,
                    'total_ms' => $total,
                ];
            }

            $this->cancelPendingConfirmationQueue($idempotencyKey, $legacyKey);

            $row = $this->db->prepare(
                "SELECT a.id, a.starts_at, a.patient_id, p.mobile, p.first_name, p.last_name
                 FROM appointments a
                 JOIN patients p ON p.id=a.patient_id
                 WHERE a.id=? AND a.deleted_at IS NULL"
            );
            $row->execute([$appointmentId]);
            $data = $row->fetch(PDO::FETCH_ASSOC);
            if (!$data) {
                return $fail('نوبت برای ارسال پیامک یافت نشد.', $started);
            }

            $mobile = normalize_mobile((string) ($data['mobile'] ?? ''));
            if ($mobile === null) {
                return $fail('شماره موبایل بیمار معتبر نیست.', $started);
            }

            $params = SmsTemplateRenderer::buildSmsIrAppointmentParameters($data, $data);
            $missing = SmsTemplateRenderer::missingSmsIrParameters($params);
            if ($missing !== []) {
                SmsTemplateRenderer::logSmsIrParameterError([
                    'template_id' => $templateId,
                    'appointment_id' => $appointmentId,
                    'missing_parameter_names' => $missing,
                    'message_type' => 'appointment_confirmation',
                ]);
                return $fail('پارامترهای پیامک تأیید ناقص است.', $started);
            }

            $claimId = $this->claimConfirmationSend($appointmentId, (int) $data['patient_id'], $mobile, $templateId, $idempotencyKey, $params);
            if ($claimId === 0) {
                $total = (int) round((hrtime(true) - $started) / 1_000_000);
                return [
                    'ok' => true,
                    'sent' => false,
                    'duplicate' => true,
                    'message' => 'پیامک تأیید قبلاً ارسال شده است.',
                    'message_id' => null,
                    'sms_api_ms' => 0,
                    'total_ms' => $total,
                ];
            }

            $requestStarted = date('c');
            $provider = SmsManager::make();
            $parameterList = SmsTemplateRenderer::toSmsIrParameterList($params);
            if ($provider instanceof SmsIrProvider || $provider instanceof LogSmsProvider) {
                $send = $provider->sendTemplateNow($mobile, $templateId, $parameterList);
            } else {
                $send = $provider->sendTemplate($mobile, $templateId, $parameterList);
            }
            $responseAt = date('c');
            $apiMs = (int) ($send['duration_ms'] ?? 0);
            $ok = !empty($send['ok']);
            $messageId = isset($send['message_id']) ? (string) $send['message_id'] : null;
            $summary = sprintf(
                'تأیید نوبت (SMS.ir #%d): %s — %s %s',
                $templateId,
                $params['FULL_NAME'],
                $params['APPOINTMENT_DATE'],
                $params['APPOINTMENT_TIME']
            );

            $this->finishConfirmationLog($claimId, $ok, $messageId, $summary, $send, $ok ? $idempotencyKey : null);
            $total = (int) round((hrtime(true) - $started) / 1_000_000);
            SmsTemplateRenderer::logEvent('APPOINTMENT_CONFIRMATION_SMS', [
                'appointment_id' => $appointmentId,
                'template_id' => $templateId,
                'sms_request_started_at' => $requestStarted,
                'sms_provider_response_at' => $responseAt,
                'sms_api_ms' => $apiMs,
                'total_ms' => $total,
                'provider_status' => $ok ? 'success' : 'failed',
                'message_id' => $messageId,
                'http' => $send['http'] ?? null,
            ]);

            return [
                'ok' => $ok,
                'sent' => $ok,
                'duplicate' => false,
                'message' => $ok ? 'پیامک تأیید ارسال شد.' : 'نوبت با موفقیت ثبت شد، اما ارسال پیامک تأیید با خطا مواجه شد.',
                'message_id' => $ok ? $messageId : null,
                'sms_api_ms' => $apiMs,
                'total_ms' => $total,
            ];
        } catch (\Throwable $e) {
            SmsTemplateRenderer::logEvent('APPOINTMENT_CONFIRMATION_SMS_ERROR', [
                'appointment_id' => $appointmentId,
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);
            return $fail('نوبت با موفقیت ثبت شد، اما ارسال پیامک تأیید با خطا مواجه شد.', $started);
        }
    }

    private function confirmationAlreadyAccepted(int $appointmentId, string $key, string $legacyKey): bool
    {
        $log = $this->db->prepare(
            "SELECT id FROM sms_logs
             WHERE (idempotency_key IN (?, ?) OR (appointment_id=? AND message_type='appointment_confirmation'))
               AND status='sent'
             LIMIT 1"
        );
        $log->execute([$key, $legacyKey, $appointmentId]);
        if ($log->fetchColumn()) {
            return true;
        }
        try {
            $q = $this->db->prepare(
                "SELECT id FROM sms_queue
                 WHERE idempotency_key IN (?, ?) AND message_type='appointment_confirmation' AND status='sent'
                 LIMIT 1"
            );
            $q->execute([$key, $legacyKey]);
            return (bool) $q->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private function cancelPendingConfirmationQueue(string $key, string $legacyKey): void
    {
        try {
            $this->db->prepare(
                "UPDATE sms_queue SET status='cancelled'
                 WHERE idempotency_key IN (?, ?)
                   AND message_type='appointment_confirmation'
                   AND status IN ('pending','retrying')"
            )->execute([$key, $legacyKey]);
        } catch (\Throwable) {
        }
    }

    /** @param array<string, string> $params */
    private function claimConfirmationSend(
        int $appointmentId,
        int $patientId,
        string $mobile,
        int $templateId,
        string $idempotencyKey,
        array $params
    ): int {
        $body = sprintf('تأیید نوبت #%d — %s %s', $templateId, $params['APPOINTMENT_DATE'], $params['APPOINTMENT_TIME']);
        try {
            $this->db->prepare(
                "INSERT INTO sms_logs
                 (patient_id, appointment_id, mobile, message_type, source, message_body, provider, status, attempts, idempotency_key)
                 VALUES (?, ?, ?, 'appointment_confirmation', 'system', ?, 'smsir', 'sending', 1, ?)"
            )->execute([$patientId, $appointmentId, $mobile, $body, $idempotencyKey]);
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000' && !str_contains($e->getMessage(), 'Duplicate')) {
                throw $e;
            }
        }
        return 0;
    }

    /** @param array<string, mixed> $send */
    private function finishConfirmationLog(int $logId, bool $ok, ?string $messageId, string $summary, array $send, ?string $idempotencyKey): void
    {
        unset($send['api_key'], $send['headers'], $send['request'], $send['raw']);
        $this->db->prepare(
            "UPDATE sms_logs
             SET message_body=?, provider=?, provider_message_id=?, status=?, last_error=?, provider_response=?, idempotency_key=?, sent_at=?
             WHERE id=?"
        )->execute([
            $summary,
            (string) ($send['provider'] ?? SmsManager::driver()),
            $ok ? $messageId : null,
            $ok ? 'sent' : 'failed',
            $ok ? null : mb_substr((string) ($send['error'] ?? 'ارسال ناموفق'), 0, 500),
            json_encode([
                'provider' => $send['provider'] ?? null,
                'provider_status' => $send['provider_status'] ?? ($send['status_code'] ?? null),
                'http' => $send['http'] ?? null,
                'message_id' => $messageId,
                'duration_ms' => $send['duration_ms'] ?? null,
                'template_id' => $send['provider_template_id'] ?? null,
                'ok' => $ok,
            ], JSON_UNESCAPED_UNICODE),
            $ok ? $idempotencyKey : null,
            $ok ? date('Y-m-d H:i:s') : null,
            $logId,
        ]);
    }

    /** @return list<string> */
    public static function cancellableStatuses(): array
    {
        return ['confirmed', 'awaiting_payment'];
    }

    public function ensureCancelSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $cols = [
            'cancelled_at' => 'DATETIME NULL',
            'cancellation_reason' => 'VARCHAR(500) NULL',
            'cancelled_by_admin' => 'INT UNSIGNED NULL',
        ];
        foreach ($cols as $name => $ddl) {
            try {
                $exists = $this->db->query("SHOW COLUMNS FROM appointments LIKE " . $this->db->quote($name))->fetch(PDO::FETCH_ASSOC);
                if (!$exists) {
                    $this->db->exec("ALTER TABLE appointments ADD COLUMN `{$name}` {$ddl}");
                }
            } catch (\Throwable) {
            }
        }
        $this->ensureCancelledTemplate();
    }

    private function ensureCancelledTemplate(): void
    {
        $body = "سلام #full_name#\n"
            . "نوبت شما در کلینیک دندانپزشکی سروا برای تاریخ #appointment_date# ساعت #appointment_time# لغو شد.\n"
            . "#cancellation_reason#\n"
            . "در صورت نیاز با کلینیک تماس بگیرید.\n"
            . "#clinic_phone#";
        try {
            $st = $this->db->prepare("SELECT id, body FROM sms_templates WHERE slug='appointment_cancelled' AND deleted_at IS NULL LIMIT 1");
            $st->execute();
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                // Keep admin-edited bodies; only upgrade clearly legacy curly-brace seed without reason.
                $existing = (string) ($row['body'] ?? '');
                if ($existing !== '' && !str_contains($existing, 'cancellation_reason') && str_contains($existing, '{full_name}')) {
                    $this->db->prepare(
                        "UPDATE sms_templates SET body=?, type='appointment_cancellation', category='appointment', name=COALESCE(NULLIF(name,''),'لغو نوبت'), is_active=1 WHERE id=?"
                    )->execute([$body, (int) $row['id']]);
                }
                return;
            }
            $this->db->prepare(
                "INSERT INTO sms_templates (slug, name, type, category, body, is_active)
                 VALUES ('appointment_cancelled','لغو نوبت','appointment_cancellation','appointment',?,1)"
            )->execute([$body]);
        } catch (\Throwable) {
            try {
                $this->db->prepare(
                    "INSERT INTO sms_templates (slug, name, type, body, is_active)
                     VALUES ('appointment_cancelled','لغو نوبت','appointment_cancellation',?,1)"
                )->execute([$body]);
            } catch (\Throwable) {
            }
        }
    }

    /**
     * Cancel a single appointment. Idempotent: already-cancelled rows are skipped (no duplicate SMS).
     *
     * @return array{
     *   ok:bool,
     *   status:string,
     *   appointment_id:int,
     *   sms_sent?:bool,
     *   sms_duplicate?:bool,
     *   sms_status?:string,
     *   sms_message_id?:?string,
     *   sms_api_ms?:int,
     *   message?:string,
     *   from_status?:string
     * }
     */
    public function cancelAppointment(
        int $appointmentId,
        int $adminId,
        ?string $reason = null,
        bool $sendSms = true
    ): array {
        $this->ensureCancelSchema();
        $reason = $this->normalizeReason($reason);

        if ($appointmentId <= 0) {
            return ['ok' => false, 'status' => 'invalid', 'appointment_id' => $appointmentId, 'message' => 'نوبت نامعتبر است.'];
        }

        $historyId = 0;
        $fromStatus = '';

        try {
            $this->db->beginTransaction();

            $lock = $this->db->prepare(
                'SELECT id, status, starts_at, deleted_at FROM appointments WHERE id=? FOR UPDATE'
            );
            $lock->execute([$appointmentId]);
            $row = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['deleted_at'] !== null) {
                $this->db->rollBack();
                return ['ok' => false, 'status' => 'not_found', 'appointment_id' => $appointmentId, 'message' => 'نوبت یافت نشد.'];
            }

            $fromStatus = (string) $row['status'];

            if ($fromStatus === 'cancelled') {
                $this->db->rollBack();
                return [
                    'ok' => true,
                    'status' => 'already_cancelled',
                    'appointment_id' => $appointmentId,
                    'from_status' => $fromStatus,
                    'sms_sent' => false,
                    'sms_duplicate' => false,
                    'sms_status' => 'skipped',
                    'message' => 'این نوبت قبلاً لغو شده است.',
                ];
            }

            if (!in_array($fromStatus, self::cancellableStatuses(), true)) {
                $this->db->rollBack();
                return [
                    'ok' => false,
                    'status' => 'not_cancellable',
                    'appointment_id' => $appointmentId,
                    'from_status' => $fromStatus,
                    'message' => 'این نوبت قابل لغو نیست.',
                ];
            }

            $upd = $this->db->prepare(
                "UPDATE appointments
                 SET status='cancelled',
                     cancelled_at=NOW(),
                     cancellation_reason=?,
                     cancelled_by_admin=?,
                     hold_expires_at=NULL
                 WHERE id=? AND deleted_at IS NULL AND status IN ('confirmed','awaiting_payment')"
            );
            $upd->execute([$reason !== '' ? $reason : null, $adminId > 0 ? $adminId : null, $appointmentId]);
            if ($upd->rowCount() < 1) {
                $this->db->rollBack();
                return [
                    'ok' => false,
                    'status' => 'race',
                    'appointment_id' => $appointmentId,
                    'message' => 'وضعیت نوبت همزمان تغییر کرده است.',
                ];
            }

            $note = $reason !== '' ? $reason : 'لغو توسط مدیریت';
            $hist = $this->db->prepare(
                'INSERT INTO appointment_status_history (appointment_id, from_status, to_status, changed_by_admin, note)
                 VALUES (?,?,?,?,?)'
            );
            $hist->execute([$appointmentId, $fromStatus, 'cancelled', $adminId > 0 ? $adminId : null, mb_substr($note, 0, 500)]);
            $historyId = (int) $this->db->lastInsertId();

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logCancel('appointment_cancel_error', [
                'appointment_id' => $appointmentId,
                'admin_id' => $adminId,
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);
            return ['ok' => false, 'status' => 'error', 'appointment_id' => $appointmentId, 'message' => 'خطا در لغو نوبت.'];
        }

        // After commit: drop pending reminder/confirmation jobs, then send cancellation SMS directly.
        try {
            (new SmsService($this->db))->cancelPendingForAppointment($appointmentId);
        } catch (\Throwable) {
        }

        $this->logCancel('appointment_cancelled', [
            'appointment_id' => $appointmentId,
            'admin_id' => $adminId,
            'from_status' => $fromStatus,
            'history_id' => $historyId,
            'send_sms' => $sendSms,
        ]);

        $smsSent = false;
        $smsDuplicate = false;
        $smsStatus = 'disabled';
        $smsMessageId = null;
        $smsApiMs = 0;
        if ($sendSms) {
            $smsResult = $this->sendAppointmentCancellationNow($appointmentId);
            $smsSent = !empty($smsResult['sent']);
            $smsDuplicate = !empty($smsResult['duplicate']);
            $smsMessageId = $smsResult['message_id'] ?? null;
            $smsApiMs = (int) ($smsResult['sms_api_ms'] ?? 0);
            if ($smsSent) {
                $smsStatus = 'sent';
            } elseif ($smsDuplicate) {
                $smsStatus = 'duplicate';
            } else {
                $smsStatus = 'failed';
            }
        }

        return [
            'ok' => true,
            'status' => 'cancelled',
            'appointment_id' => $appointmentId,
            'from_status' => $fromStatus,
            'sms_sent' => $smsSent,
            'sms_duplicate' => $smsDuplicate,
            'sms_status' => $smsStatus,
            'sms_message_id' => $smsMessageId,
            'sms_api_ms' => $smsApiMs,
            'message' => 'نوبت با موفقیت لغو شد.',
        ];
    }

    /**
     * @param list<int> $appointmentIds
     * @return array{
     *   ok:bool,
     *   examined:int,
     *   cancelled:int,
     *   already_cancelled:int,
     *   not_cancellable:int,
     *   not_found:int,
     *   sms_sent:int,
     *   sms_duplicate:int,
     *   sms_failed:int,
     *   sms_disabled:int,
     *   results:list<array>
     * }
     */
    public function cancelAppointments(
        array $appointmentIds,
        int $adminId,
        ?string $reason = null,
        bool $sendSms = true
    ): array {
        $this->ensureCancelSchema();
        $ids = array_values(array_unique(array_filter(array_map('intval', $appointmentIds), static fn (int $id): bool => $id > 0)));
        $summary = [
            'ok' => true,
            'examined' => count($ids),
            'cancelled' => 0,
            'already_cancelled' => 0,
            'not_cancellable' => 0,
            'not_found' => 0,
            'sms_sent' => 0,
            'sms_duplicate' => 0,
            'sms_failed' => 0,
            'sms_disabled' => 0,
            'results' => [],
        ];

        if ($sendSms && count($ids) > 1) {
            @set_time_limit(max(60, 15 * count($ids)));
        }

        foreach (array_chunk($ids, 5) as $chunk) {
            foreach ($chunk as $id) {
                $r = $this->cancelAppointment($id, $adminId, $reason, $sendSms);
                $summary['results'][] = $r;
                $st = (string) ($r['status'] ?? '');
                if ($st === 'cancelled') {
                    $summary['cancelled']++;
                    if (!$sendSms) {
                        $summary['sms_disabled']++;
                    } elseif (!empty($r['sms_sent'])) {
                        $summary['sms_sent']++;
                    } elseif (!empty($r['sms_duplicate'])) {
                        $summary['sms_duplicate']++;
                    } else {
                        $summary['sms_failed']++;
                    }
                } elseif ($st === 'already_cancelled') {
                    $summary['already_cancelled']++;
                } elseif ($st === 'not_cancellable') {
                    $summary['not_cancellable']++;
                } else {
                    $summary['not_found']++;
                }
            }
        }

        $this->logCancel('appointment_bulk_cancelled', [
            'admin_id' => $adminId,
            'examined' => $summary['examined'],
            'cancelled' => $summary['cancelled'],
            'already_cancelled' => $summary['already_cancelled'],
            'not_cancellable' => $summary['not_cancellable'],
            'sms_sent' => $summary['sms_sent'],
            'sms_failed' => $summary['sms_failed'],
            'sms_duplicate' => $summary['sms_duplicate'],
            'send_sms' => $sendSms,
        ]);

        return $summary;
    }

    /**
     * Cancel all eligible appointments on a Gregorian calendar day (Y-m-d).
     *
     * @return array{ok:bool,date:string,examined:int,cancelled:int,already_cancelled:int,not_cancellable:int,not_found:int,sms_sent:int,sms_duplicate:int,sms_failed:int,sms_disabled:int,results:list<array>}
     */
    public function cancelAppointmentsForDay(
        string $date,
        int $adminId,
        ?string $reason = null,
        bool $sendSms = true
    ): array {
        $this->ensureCancelSchema();
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return [
                'ok' => false,
                'date' => $date,
                'examined' => 0,
                'cancelled' => 0,
                'already_cancelled' => 0,
                'not_cancellable' => 0,
                'not_found' => 0,
                'sms_sent' => 0,
                'sms_duplicate' => 0,
                'sms_failed' => 0,
                'sms_disabled' => 0,
                'results' => [],
                'message' => 'تاریخ نامعتبر است.',
            ];
        }

        $ids = $this->eligibleIdsForDay($date);
        $summary = $this->cancelAppointments($ids, $adminId, $reason, $sendSms);
        $summary['date'] = $date;
        $summary['ok'] = true;
        return $summary;
    }

    /** @return list<int> */
    public function eligibleIdsForDay(string $date): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return [];
        }
        $in = "'" . implode("','", self::cancellableStatuses()) . "'";
        $st = $this->db->prepare(
            "SELECT id FROM appointments
             WHERE deleted_at IS NULL
               AND DATE(starts_at)=?
               AND status IN ({$in})
             ORDER BY starts_at ASC, id ASC"
        );
        $st->execute([$date]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function countEligibleForDay(string $date): int
    {
        return count($this->eligibleIdsForDay($date));
    }

    /**
     * Direct SMS.ir cancellation. Call only after the appointment transaction is committed.
     * Does not enqueue. A provider failure must not roll back the cancellation.
     *
     * @return array{ok:bool,sent:bool,duplicate:bool,message:string,message_id:?string,sms_api_ms:int,total_ms:int}
     */
    public function sendAppointmentCancellationNow(int $appointmentId): array
    {
        $started = hrtime(true);
        $fail = static function (string $message, int $startedAt, int $apiMs = 0) use ($appointmentId): array {
            return [
                'ok' => false,
                'sent' => false,
                'duplicate' => false,
                'message' => $message,
                'message_id' => null,
                'sms_api_ms' => $apiMs,
                'total_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            ];
        };

        if ($this->db->inTransaction()) {
            return $fail('تراکنش دیتابیس هنوز باز است.', $started);
        }

        $templateId = SmsService::smsIrTemplateIdForMessageType('appointment_cancellation');
        $idempotencyKey = 'appointment_cancellation:' . $appointmentId;
        $legacyKey = 'appointment_cancel:' . $appointmentId;

        if ($templateId <= 0) {
            SmsTemplateRenderer::logEvent('smsir_cancellation_template_missing', [
                'appointment_id' => $appointmentId,
                'message_type' => 'appointment_cancellation',
            ]);
            return $fail('شناسه قالب لغو نوبت تنظیم نشده است.', $started);
        }

        try {
            if ($this->cancellationAlreadyAccepted($appointmentId, $idempotencyKey, $legacyKey)) {
                SmsTemplateRenderer::logEvent('APPOINTMENT_CANCELLATION_SKIPPED_DUPLICATE', [
                    'appointment_id' => $appointmentId,
                    'template_id' => $templateId,
                    'idempotency_key' => $idempotencyKey,
                ]);
                $total = (int) round((hrtime(true) - $started) / 1_000_000);
                return [
                    'ok' => true,
                    'sent' => false,
                    'duplicate' => true,
                    'message' => 'پیامک لغو قبلاً ارسال شده است.',
                    'message_id' => null,
                    'sms_api_ms' => 0,
                    'total_ms' => $total,
                ];
            }

            $this->cancelPendingCancellationQueue($appointmentId, $idempotencyKey, $legacyKey);

            $row = $this->db->prepare(
                "SELECT a.id, a.starts_at, a.patient_id, p.mobile, p.first_name, p.last_name
                 FROM appointments a
                 JOIN patients p ON p.id=a.patient_id
                 WHERE a.id=? AND a.deleted_at IS NULL"
            );
            $row->execute([$appointmentId]);
            $data = $row->fetch(PDO::FETCH_ASSOC);
            if (!$data) {
                return $fail('نوبت برای ارسال پیامک یافت نشد.', $started);
            }

            $mobile = normalize_mobile((string) ($data['mobile'] ?? ''));
            if ($mobile === null) {
                return $fail('شماره موبایل بیمار معتبر نیست.', $started);
            }

            $built = SmsTemplateRenderer::buildSmsIrAppointmentParameters($data, $data);
            $params = [
                'FULL_NAME' => (string) ($built['FULL_NAME'] ?? ''),
                'APPOINTMENT_DATE' => (string) ($built['APPOINTMENT_DATE'] ?? ''),
                'APPOINTMENT_TIME' => (string) ($built['APPOINTMENT_TIME'] ?? ''),
            ];
            $missing = SmsTemplateRenderer::missingSmsIrParameters($params);
            if ($missing !== []) {
                SmsTemplateRenderer::logSmsIrParameterError([
                    'template_id' => $templateId,
                    'appointment_id' => $appointmentId,
                    'missing_parameter_names' => $missing,
                    'message_type' => 'appointment_cancellation',
                ]);
                return $fail('پارامترهای پیامک لغو ناقص است.', $started);
            }

            $claimId = $this->claimCancellationSend($appointmentId, (int) $data['patient_id'], $mobile, $templateId, $idempotencyKey, $params);
            if ($claimId === 0) {
                $total = (int) round((hrtime(true) - $started) / 1_000_000);
                return [
                    'ok' => true,
                    'sent' => false,
                    'duplicate' => true,
                    'message' => 'پیامک لغو قبلاً ارسال شده است.',
                    'message_id' => null,
                    'sms_api_ms' => 0,
                    'total_ms' => $total,
                ];
            }

            $requestStarted = date('c');
            $provider = SmsManager::make();
            $parameterList = SmsTemplateRenderer::toSmsIrParameterList($params);
            if ($provider instanceof SmsIrProvider || $provider instanceof LogSmsProvider) {
                $send = $provider->sendTemplateNow($mobile, $templateId, $parameterList);
            } else {
                $send = $provider->sendTemplate($mobile, $templateId, $parameterList);
            }
            $responseAt = date('c');
            $apiMs = (int) ($send['duration_ms'] ?? 0);
            $ok = !empty($send['ok']);
            $messageId = isset($send['message_id']) ? (string) $send['message_id'] : null;
            $summary = sprintf(
                'لغو نوبت (SMS.ir #%d): %s — %s %s',
                $templateId,
                $params['FULL_NAME'],
                $params['APPOINTMENT_DATE'],
                $params['APPOINTMENT_TIME']
            );

            $this->finishCancellationLog($claimId, $ok, $messageId, $summary, $send, $ok ? $idempotencyKey : null);
            $total = (int) round((hrtime(true) - $started) / 1_000_000);
            SmsTemplateRenderer::logEvent('APPOINTMENT_CANCELLATION_SMS', [
                'appointment_id' => $appointmentId,
                'template_id' => $templateId,
                'sms_request_started_at' => $requestStarted,
                'sms_provider_response_at' => $responseAt,
                'sms_api_ms' => $apiMs,
                'total_ms' => $total,
                'provider_status' => $ok ? 'success' : 'failed',
                'message_id' => $messageId,
                'http' => $send['http'] ?? null,
            ]);

            return [
                'ok' => true,
                'sent' => $ok,
                'duplicate' => false,
                'message' => $ok ? 'پیامک لغو ارسال شد.' : 'نوبت لغو شد، اما ارسال پیامک لغو با خطا مواجه شد.',
                'message_id' => $ok ? $messageId : null,
                'sms_api_ms' => $apiMs,
                'total_ms' => $total,
            ];
        } catch (\Throwable $e) {
            SmsTemplateRenderer::logEvent('APPOINTMENT_CANCELLATION_SMS_ERROR', [
                'appointment_id' => $appointmentId,
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);
            return $fail('نوبت لغو شد، اما ارسال پیامک لغو با خطا مواجه شد.', $started);
        }
    }

    private function cancellationAlreadyAccepted(int $appointmentId, string $key, string $legacyKey): bool
    {
        $log = $this->db->prepare(
            "SELECT id FROM sms_logs
             WHERE (idempotency_key IN (?, ?) OR (appointment_id=? AND message_type='appointment_cancellation'))
               AND status='sent'
             LIMIT 1"
        );
        $log->execute([$key, $legacyKey, $appointmentId]);
        if ($log->fetchColumn()) {
            return true;
        }
        try {
            $q = $this->db->prepare(
                "SELECT id FROM sms_queue
                 WHERE idempotency_key IN (?, ?) AND message_type='appointment_cancellation' AND status='sent'
                 LIMIT 1"
            );
            $q->execute([$key, $legacyKey]);
            return (bool) $q->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private function cancelPendingCancellationQueue(int $appointmentId, string $key, string $legacyKey): void
    {
        try {
            $this->db->prepare(
                "UPDATE sms_queue SET status='cancelled', last_error='لغو مستقیم جایگزین صف شد'
                 WHERE message_type='appointment_cancellation'
                   AND status IN ('pending','processing','retrying')
                   AND (appointment_id=? OR idempotency_key IN (?, ?))"
            )->execute([$appointmentId, $key, $legacyKey]);
        } catch (\Throwable) {
        }
    }

    /** @param array<string, string> $params */
    private function claimCancellationSend(
        int $appointmentId,
        int $patientId,
        string $mobile,
        int $templateId,
        string $idempotencyKey,
        array $params
    ): int {
        $body = sprintf('لغو نوبت #%d — %s %s', $templateId, $params['APPOINTMENT_DATE'], $params['APPOINTMENT_TIME']);
        try {
            $this->db->prepare(
                "INSERT INTO sms_logs
                 (patient_id, appointment_id, mobile, message_type, source, message_body, provider, status, attempts, idempotency_key)
                 VALUES (?, ?, ?, 'appointment_cancellation', 'system', ?, 'smsir', 'sending', 1, ?)"
            )->execute([$patientId, $appointmentId, $mobile, $body, $idempotencyKey]);
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000' && !str_contains($e->getMessage(), 'Duplicate')) {
                throw $e;
            }
        }
        return 0;
    }

    /** @param array<string, mixed> $send */
    private function finishCancellationLog(int $logId, bool $ok, ?string $messageId, string $summary, array $send, ?string $idempotencyKey): void
    {
        unset($send['api_key'], $send['headers'], $send['request'], $send['raw']);
        $this->db->prepare(
            "UPDATE sms_logs
             SET message_body=?, provider=?, provider_message_id=?, status=?, last_error=?, provider_response=?, idempotency_key=?, sent_at=?
             WHERE id=?"
        )->execute([
            $summary,
            (string) ($send['provider'] ?? SmsManager::driver()),
            $ok ? $messageId : null,
            $ok ? 'sent' : 'failed',
            $ok ? null : mb_substr((string) ($send['error'] ?? 'ارسال ناموفق'), 0, 500),
            json_encode([
                'provider' => $send['provider'] ?? null,
                'provider_status' => $send['provider_status'] ?? ($send['status_code'] ?? null),
                'http' => $send['http'] ?? null,
                'message_id' => $messageId,
                'duration_ms' => $send['duration_ms'] ?? null,
                'template_id' => $send['provider_template_id'] ?? null,
                'ok' => $ok,
            ], JSON_UNESCAPED_UNICODE),
            $ok ? $idempotencyKey : null,
            $ok ? date('Y-m-d H:i:s') : null,
            $logId,
        ]);
    }

    private function normalizeReason(?string $reason): string
    {
        $reason = trim((string) $reason);
        if (mb_strlen($reason) > 500) {
            $reason = mb_substr($reason, 0, 500);
        }
        return $reason;
    }

    /** @param array<string, mixed> $meta */
    private function logCancel(string $event, array $meta = []): void
    {
        try {
            $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $payload = array_merge(['at' => date('c'), 'event' => $event], $meta);
            unset($payload['mobile'], $payload['full_name'], $payload['api_key'], $payload['message'], $payload['body']);
            @file_put_contents(
                $dir . DIRECTORY_SEPARATOR . 'appointments.log',
                json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        } catch (\Throwable) {
        }
    }
}
