<?php

declare(strict_types=1);

namespace Sarva\Services;

use PDO;

/** Clinical visits, treatment plans, documents, medical profile. */
final class PatientClinicalService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return list<array<string,mixed>> */
    public function listVisits(int $patientId, array $filters = []): array
    {
        $where = ['v.patient_id=?', 'v.deleted_at IS NULL'];
        $params = [$patientId];
        if (!empty($filters['doctor_id'])) {
            $where[] = 'v.doctor_id=?';
            $params[] = (int) $filters['doctor_id'];
        }
        if (!empty($filters['service_id'])) {
            $where[] = 'v.service_id=?';
            $params[] = (int) $filters['service_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(v.visit_at) >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(v.visit_at) <= ?';
            $params[] = $filters['date_to'];
        }
        $sql = 'SELECT v.*, CONCAT(d.first_name,\' \',d.last_name) doctor_name, s.name service_name,
                       CONCAT(u.first_name,\' \',u.last_name) created_by_name
                FROM patient_visits v
                LEFT JOIN doctors d ON d.id=v.doctor_id
                LEFT JOIN services s ON s.id=v.service_id
                LEFT JOIN admin_users u ON u.id=v.created_by
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY v.visit_at DESC, v.id DESC LIMIT 200';
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findVisit(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM patient_visits WHERE id=? AND deleted_at IS NULL');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    public function saveVisit(array $data, ?int $id, int $adminId): array
    {
        $patientId = (int) ($data['patient_id'] ?? 0);
        if ($patientId <= 0) {
            return ['ok' => false, 'message' => 'بیمار نامعتبر است.'];
        }
        $visitAt = trim((string) ($data['visit_at'] ?? ''));
        $visitAt = str_replace('T', ' ', $visitAt);
        if ($visitAt === '') {
            $visitAt = date('Y-m-d H:i:s');
        } else {
            $ts = strtotime($visitAt);
            $visitAt = $ts ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');
        }
        $fields = [
            'patient_id' => $patientId,
            'doctor_id' => !empty($data['doctor_id']) ? (int) $data['doctor_id'] : null,
            'appointment_id' => !empty($data['appointment_id']) ? (int) $data['appointment_id'] : null,
            'service_id' => !empty($data['service_id']) ? (int) $data['service_id'] : null,
            'visit_at' => $visitAt,
            'visit_type' => trim((string) ($data['visit_type'] ?? 'session')) ?: 'session',
            'chief_complaint' => trim((string) ($data['chief_complaint'] ?? '')) ?: null,
            'session_notes' => trim((string) ($data['session_notes'] ?? '')) ?: null,
            'treatment_performed' => trim((string) ($data['treatment_performed'] ?? '')) ?: null,
            'teeth' => $this->normalizeTeeth($data['teeth'] ?? ''),
            'diagnosis' => trim((string) ($data['diagnosis'] ?? '')) ?: null,
            'materials' => trim((string) ($data['materials'] ?? '')) ?: null,
            'recommendations' => trim((string) ($data['recommendations'] ?? '')) ?: null,
            'next_treatment' => trim((string) ($data['next_treatment'] ?? '')) ?: null,
            'follow_up' => trim((string) ($data['follow_up'] ?? '')) ?: null,
            'internal_notes' => trim((string) ($data['internal_notes'] ?? '')) ?: null,
        ];
        if ($id) {
            $fields['updated_by'] = $adminId;
            $set = [];
            $vals = [];
            foreach ($fields as $k => $v) {
                if ($k === 'patient_id') {
                    continue;
                }
                $set[] = "{$k}=?";
                $vals[] = $v;
            }
            $vals[] = $id;
            $this->db->prepare('UPDATE patient_visits SET ' . implode(',', $set) . ' WHERE id=? AND deleted_at IS NULL')->execute($vals);
            audit('patient.visit_update', 'patient_visits', $id, ['patient_id' => $patientId]);
            return ['ok' => true, 'id' => $id];
        }
        $fields['created_by'] = $adminId;
        $cols = array_keys($fields);
        $this->db->prepare(
            'INSERT INTO patient_visits (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')'
        )->execute(array_values($fields));
        $newId = (int) $this->db->lastInsertId();
        audit('patient.visit_create', 'patient_visits', $newId, ['patient_id' => $patientId]);
        return ['ok' => true, 'id' => $newId];
    }

    public function deleteVisit(int $visitId, int $patientId): bool
    {
        $st = $this->db->prepare(
            'UPDATE patient_visits SET deleted_at=NOW() WHERE id=? AND patient_id=? AND deleted_at IS NULL'
        );
        $st->execute([$visitId, $patientId]);
        if ($st->rowCount() < 1) {
            return false;
        }
        audit('patient.visit_delete', 'patient_visits', $visitId, ['patient_id' => $patientId]);
        return true;
    }

    /** @param mixed $teeth */
    private function normalizeTeeth(mixed $teeth): ?string
    {
        if (is_array($teeth)) {
            $teeth = implode(',', array_map('strval', $teeth));
        }
        $teeth = trim((string) $teeth);
        if ($teeth === '') {
            return null;
        }
        $parts = preg_split('/[,\s،]+/u', $teeth) ?: [];
        $clean = [];
        foreach ($parts as $p) {
            $p = normalize_digits(trim($p));
            if ($p !== '' && preg_match('/^\d{1,2}$/', $p)) {
                $n = (int) $p;
                if ($n >= 11 && $n <= 48) {
                    $clean[] = (string) $n;
                }
            }
        }
        $clean = array_values(array_unique($clean));
        return $clean ? implode(',', $clean) : null;
    }

    public function medicalProfile(int $patientId): array
    {
        $st = $this->db->prepare('SELECT * FROM patient_medical_profiles WHERE patient_id=?');
        $st->execute([$patientId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: ['patient_id' => $patientId];
    }

    /** @param array<string, mixed> $data */
    public function saveMedical(int $patientId, array $data, int $adminId): void
    {
        $exists = $this->db->prepare('SELECT patient_id FROM patient_medical_profiles WHERE patient_id=?');
        $exists->execute([$patientId]);
        $fields = [
            'drug_allergies' => trim((string) ($data['drug_allergies'] ?? '')) ?: null,
            'current_medications' => trim((string) ($data['current_medications'] ?? '')) ?: null,
            'major_diseases' => trim((string) ($data['major_diseases'] ?? '')) ?: null,
            'surgery_history' => trim((string) ($data['surgery_history'] ?? '')) ?: null,
            'pregnancy_note' => trim((string) ($data['pregnancy_note'] ?? '')) ?: null,
            'important_conditions' => trim((string) ($data['important_conditions'] ?? '')) ?: null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'updated_by' => $adminId,
        ];
        if ($exists->fetchColumn()) {
            $set = [];
            $vals = [];
            foreach ($fields as $k => $v) {
                $set[] = "{$k}=?";
                $vals[] = $v;
            }
            $vals[] = $patientId;
            $this->db->prepare('UPDATE patient_medical_profiles SET ' . implode(',', $set) . ' WHERE patient_id=?')->execute($vals);
        } else {
            $this->db->prepare(
                'INSERT INTO patient_medical_profiles
                 (patient_id, drug_allergies, current_medications, major_diseases, surgery_history, pregnancy_note, important_conditions, notes, updated_by)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            )->execute([
                $patientId, $fields['drug_allergies'], $fields['current_medications'], $fields['major_diseases'],
                $fields['surgery_history'], $fields['pregnancy_note'], $fields['important_conditions'], $fields['notes'], $adminId,
            ]);
        }
        audit('patient.medical_update', 'patients', $patientId);
    }

    /** @return list<array<string,mixed>> */
    public function listDocuments(int $patientId): array
    {
        $st = $this->db->prepare(
            'SELECT * FROM patient_documents WHERE patient_id=? AND deleted_at IS NULL ORDER BY id DESC LIMIT 100'
        );
        $st->execute([$patientId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string, mixed> $meta */
    public function addDocument(int $patientId, array $meta, int $adminId): array
    {
        $path = (string) ($meta['file_path'] ?? '');
        $title = trim((string) ($meta['title'] ?? ''));
        if ($path === '' || $title === '') {
            return ['ok' => false, 'message' => 'عنوان و فایل الزامی است.'];
        }
        $this->db->prepare(
            'INSERT INTO patient_documents
             (patient_id, category, title, description, file_path, original_name, mime_type, file_size, document_date, source, uploaded_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $patientId,
            (string) ($meta['category'] ?? 'other'),
            $title,
            trim((string) ($meta['description'] ?? '')) ?: null,
            $path,
            $meta['original_name'] ?? null,
            $meta['mime_type'] ?? null,
            $meta['file_size'] ?? null,
            !empty($meta['document_date']) ? $meta['document_date'] : null,
            'upload',
            $adminId,
        ]);
        $id = (int) $this->db->lastInsertId();
        audit('patient.document_upload', 'patient_documents', $id, ['patient_id' => $patientId]);
        return ['ok' => true, 'id' => $id];
    }

    /** @return list<array<string,mixed>> */
    public function listPlans(int $patientId): array
    {
        $st = $this->db->prepare(
            'SELECT p.*, CONCAT(d.first_name,\' \',d.last_name) doctor_name
             FROM patient_treatment_plans p
             LEFT JOIN doctors d ON d.id=p.doctor_id
             WHERE p.patient_id=? AND p.deleted_at IS NULL ORDER BY p.id DESC'
        );
        $st->execute([$patientId]);
        $plans = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($plans as &$plan) {
            $items = $this->db->prepare('SELECT * FROM patient_treatment_items WHERE plan_id=? ORDER BY sort_order, id');
            $items->execute([(int) $plan['id']]);
            $plan['items'] = $items->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        return $plans;
    }

    /** @param array<string, mixed> $data */
    public function savePlan(array $data, ?int $id, int $adminId): array
    {
        $patientId = (int) ($data['patient_id'] ?? 0);
        $title = trim((string) ($data['title'] ?? ''));
        if ($patientId <= 0 || $title === '') {
            return ['ok' => false, 'message' => 'عنوان طرح درمان الزامی است.'];
        }
        $status = (string) ($data['status'] ?? 'proposed');
        $allowed = ['proposed', 'approved', 'in_progress', 'completed', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            $status = 'proposed';
        }
        if ($id) {
            $this->db->prepare(
                'UPDATE patient_treatment_plans SET title=?, doctor_id=?, status=?, notes=?, updated_by=? WHERE id=? AND patient_id=?'
            )->execute([
                $title,
                !empty($data['doctor_id']) ? (int) $data['doctor_id'] : null,
                $status,
                trim((string) ($data['notes'] ?? '')) ?: null,
                $adminId,
                $id,
                $patientId,
            ]);
            audit('patient.plan_update', 'patient_treatment_plans', $id);
            return ['ok' => true, 'id' => $id];
        }
        $this->db->prepare(
            'INSERT INTO patient_treatment_plans (patient_id, title, doctor_id, status, notes, created_by, updated_by)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([
            $patientId, $title,
            !empty($data['doctor_id']) ? (int) $data['doctor_id'] : null,
            $status,
            trim((string) ($data['notes'] ?? '')) ?: null,
            $adminId, $adminId,
        ]);
        $newId = (int) $this->db->lastInsertId();
        audit('patient.plan_create', 'patient_treatment_plans', $newId);
        return ['ok' => true, 'id' => $newId];
    }

    /** @param array<string, mixed> $data */
    public function savePlanItem(array $data, ?int $id): array
    {
        $planId = (int) ($data['plan_id'] ?? 0);
        if ($planId <= 0) {
            return ['ok' => false, 'message' => 'طرح درمان نامعتبر است.'];
        }
        $status = (string) ($data['status'] ?? 'proposed');
        $fields = [
            'service_id' => !empty($data['service_id']) ? (int) $data['service_id'] : null,
            'service_name' => trim((string) ($data['service_name'] ?? '')) ?: null,
            'tooth' => trim((string) ($data['tooth'] ?? '')) ?: null,
            'doctor_id' => !empty($data['doctor_id']) ? (int) $data['doctor_id'] : null,
            'estimated_cost' => $data['estimated_cost'] !== '' && $data['estimated_cost'] !== null ? (float) $data['estimated_cost'] : null,
            'status' => $status,
            'priority' => max(1, min(5, (int) ($data['priority'] ?? 3))),
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ];
        if ($id) {
            $this->db->prepare(
                'UPDATE patient_treatment_items SET service_id=?, service_name=?, tooth=?, doctor_id=?, estimated_cost=?, status=?, priority=?, notes=? WHERE id=? AND plan_id=?'
            )->execute([...array_values($fields), $id, $planId]);
            return ['ok' => true, 'id' => $id];
        }
        $this->db->prepare(
            'INSERT INTO patient_treatment_items (plan_id, service_id, service_name, tooth, doctor_id, estimated_cost, status, priority, notes)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([$planId, ...array_values($fields)]);
        return ['ok' => true, 'id' => (int) $this->db->lastInsertId()];
    }

    public function visitCount(int $patientId): int
    {
        $st = $this->db->prepare('SELECT COUNT(*) FROM patient_visits WHERE patient_id=? AND deleted_at IS NULL');
        $st->execute([$patientId]);
        return (int) $st->fetchColumn();
    }

    public function lastVisitAt(int $patientId): ?string
    {
        $st = $this->db->prepare(
            'SELECT visit_at FROM patient_visits WHERE patient_id=? AND deleted_at IS NULL ORDER BY visit_at DESC LIMIT 1'
        );
        $st->execute([$patientId]);
        $v = $st->fetchColumn();
        return $v ? (string) $v : null;
    }
}
