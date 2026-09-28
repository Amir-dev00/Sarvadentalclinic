<?php

declare(strict_types=1);

namespace Sarva\Services;

use PDO;
use PDOException;

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

        $this->enqueueConfirmationSms($appointmentId);
    }

    /**
     * Admin-created appointment: status=confirmed so SMS reminder automation includes it automatically.
     *
     * @return array{ok:bool, message?:string, appointment_id?:int}
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
            $this->enqueueConfirmationSms($id);

            return ['ok' => true, 'appointment_id' => $id];
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

    private function enqueueConfirmationSms(int $appointmentId): void
    {
        try {
            $row = $this->db->prepare(
                "SELECT a.id, a.starts_at, a.patient_id, p.mobile, p.first_name, p.last_name, p.file_number, p.public_code,
                        CONCAT(d.first_name,' ',d.last_name) doctor_name, s.name service_name, t.id template_id, t.body
                 FROM appointments a
                 JOIN patients p ON p.id=a.patient_id
                 JOIN doctors d ON d.id=a.doctor_id
                 JOIN services s ON s.id=a.service_id
                 LEFT JOIN sms_templates t ON t.slug='appointment_confirmed' AND t.is_active=1
                 WHERE a.id=?"
            );
            $row->execute([$appointmentId]);
            $data = $row->fetch(PDO::FETCH_ASSOC);
            if ($data && !empty($data['body'])) {
                $sms = new SmsService($this->db);
                $sms->enqueue([
                    'patient_id' => (int) $data['patient_id'],
                    'appointment_id' => $appointmentId,
                    'template_id' => $data['template_id'] ? (int) $data['template_id'] : null,
                    'mobile' => (string) $data['mobile'],
                    'message' => SmsTemplateRenderer::render((string) $data['body'], SmsTemplateRenderer::varsFrom($data, $data)),
                    'message_type' => 'appointment_confirmation',
                    'source' => 'automation',
                    'idempotency_key' => 'confirm:' . $appointmentId,
                    'appointment_starts_at' => $data['starts_at'],
                ]);
            }
        } catch (\Throwable) {
        }
    }
}
