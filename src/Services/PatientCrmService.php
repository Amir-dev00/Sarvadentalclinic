<?php

declare(strict_types=1);

namespace Sarva\Services;

use PDO;

final class PatientCrmService
{
    public function __construct(private readonly PDO $db)
    {
        $this->ensureMobileNotUnique();
    }

    /** Shared family mobiles are allowed; identity is file_number / national_id. */
    private function ensureMobileNotUnique(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $idx = $this->db->query("SHOW INDEX FROM patients WHERE Column_name='mobile' AND Non_unique=0")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($idx as $row) {
                $name = (string) ($row['Key_name'] ?? '');
                if ($name === '' || $name === 'PRIMARY') {
                    continue;
                }
                $this->db->exec('ALTER TABLE patients DROP INDEX `' . str_replace('`', '``', $name) . '`');
            }
        } catch (\Throwable) {
        }
    }

    /** @param array<string, mixed> $filters */
    public function search(array $filters, int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = max(10, min(50, $perPage));
        $where = ['p.deleted_at IS NULL'];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $q = normalize_persian_text($q);
            $like = '%' . $q . '%';
            $mobileNorm = normalize_mobile($q);
            $digits = preg_replace('/\D+/', '', normalize_digits($q)) ?? '';
            $where[] = '(p.public_code LIKE ? OR p.file_number LIKE ? OR p.mobile LIKE ? OR p.landline LIKE ? OR p.national_id LIKE ?
                OR p.first_name LIKE ? OR p.last_name LIKE ? OR p.father_name LIKE ?
                OR CONCAT(p.first_name, \' \', p.last_name) LIKE ?
                OR p.id = ?'
                . ($mobileNorm ? ' OR p.mobile = ?' : '')
                . (strlen($digits) >= 3 ? ' OR p.mobile LIKE ? OR p.file_number LIKE ? OR p.landline LIKE ?' : '')
                . ')';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = ctype_digit($digits) ? (int) $digits : 0;
            if ($mobileNorm) {
                $params[] = $mobileNorm;
            }
            if (strlen($digits) >= 3) {
                $params[] = '%' . $digits . '%';
                $params[] = '%' . $digits . '%';
                $params[] = '%' . $digits . '%';
            }
        }
        if (($filters['source'] ?? '') === 'imported') {
            $where[] = 'p.is_imported=1';
        } elseif (($filters['source'] ?? '') === 'manual') {
            $where[] = 'p.is_imported=0';
        }
        if (($filters['status'] ?? '') === 'active') {
            $where[] = 'p.is_active=1';
        } elseif (($filters['status'] ?? '') === 'archived') {
            $where = ['p.deleted_at IS NOT NULL'];
            $params = [];
            if ($q !== '') {
                $like = '%' . $q . '%';
                $where[] = '(p.public_code LIKE ? OR p.file_number LIKE ? OR p.mobile LIKE ? OR CONCAT(p.first_name, \' \', p.last_name) LIKE ?)';
                $params = [$like, $like, $like, $like];
            }
        }
        if (!empty($filters['doctor_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM appointments ax WHERE ax.patient_id=p.id AND ax.doctor_id=? AND ax.deleted_at IS NULL)';
            $params[] = (int) $filters['doctor_id'];
        }
        if (!empty($filters['service_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM appointments ax WHERE ax.patient_id=p.id AND ax.service_id=? AND ax.deleted_at IS NULL)';
            $params[] = (int) $filters['service_id'];
        }
        if (!empty($filters['upcoming'])) {
            $where[] = "EXISTS (SELECT 1 FROM appointments ax WHERE ax.patient_id=p.id AND ax.status='confirmed' AND ax.starts_at>=NOW() AND ax.deleted_at IS NULL)";
        }
        if (!empty($filters['registered_from'])) {
            $where[] = 'DATE(p.created_at) >= ?';
            $params[] = $filters['registered_from'];
        }
        if (!empty($filters['registered_to'])) {
            $where[] = 'DATE(p.created_at) <= ?';
            $params[] = $filters['registered_to'];
        }

        $sqlWhere = implode(' AND ', $where);
        $count = $this->db->prepare("SELECT COUNT(*) FROM patients p WHERE {$sqlWhere}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare(
            "SELECT p.*,
                    (SELECT MAX(a.starts_at) FROM appointments a WHERE a.patient_id=p.id AND a.status IN ('confirmed','completed') AND a.starts_at < NOW() AND a.deleted_at IS NULL) last_appointment,
                    (SELECT MIN(a.starts_at) FROM appointments a WHERE a.patient_id=p.id AND a.status='confirmed' AND a.starts_at >= NOW() AND a.deleted_at IS NULL) next_appointment,
                    (SELECT CONCAT(d.first_name,' ',d.last_name) FROM appointments a JOIN doctors d ON d.id=a.doctor_id
                      WHERE a.patient_id=p.id AND a.deleted_at IS NULL ORDER BY a.starts_at DESC LIMIT 1) last_doctor,
                    (SELECT COUNT(*) FROM patient_visits v WHERE v.patient_id=p.id AND v.deleted_at IS NULL) visit_count,
                    (SELECT MAX(v.visit_at) FROM patient_visits v WHERE v.patient_id=p.id AND v.deleted_at IS NULL) last_visit_at
             FROM patients p
             WHERE {$sqlWhere}
             ORDER BY p.id DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function recipientIds(array $filters, array $selectedIds = []): array
    {
        $audience = new SmsAudienceService($this->db);
        $mode = (string) ($filters['preset'] ?? $filters['mode'] ?? '');
        if ($selectedIds) {
            $mode = $mode ?: 'selected';
        }
        $rows = $audience->recipientsForSend([
            'mode' => $mode ?: ($selectedIds ? 'selected' : 'filtered'),
            'filters' => $filters + ['mode' => $mode ?: 'filtered'],
            'include_ids' => $selectedIds,
            'exclude_ids' => [],
            'select_all_filtered' => !$selectedIds,
        ], 5000);
        return array_map(static fn (array $r): int => (int) $r['id'], $rows);
    }

    public function find(int $id, bool $withDeleted = false): ?array
    {
        $sql = 'SELECT * FROM patients WHERE id=?';
        if (!$withDeleted) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Suggest next numeric file_number based on existing پرونده numbers. */
    public function suggestNextFileNumber(): ?string
    {
        try {
            $max = $this->db->query(
                "SELECT MAX(CAST(file_number AS UNSIGNED)) FROM patients
                 WHERE file_number REGEXP '^[0-9]+$' AND deleted_at IS NULL"
            )->fetchColumn();
            if ($max === false || $max === null || (int) $max <= 0) {
                return '1';
            }
            return (string) ((int) $max + 1);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $data */
    public function save(array $data, ?int $id = null): array
    {
        $first = normalize_persian_text(trim((string) ($data['first_name'] ?? '')));
        $last = normalize_persian_text(trim((string) ($data['last_name'] ?? '')));
        $mobileRaw = trim((string) ($data['mobile'] ?? ''));
        $mobile = $mobileRaw !== '' ? normalize_mobile($mobileRaw) : null;
        $file = trim((string) ($data['file_number'] ?? '')) ?: null;
        $nid = trim((string) ($data['national_id'] ?? '')) ?: null;
        if ($mobileRaw !== '' && $mobile === null) {
            return ['ok' => false, 'message' => 'شماره موبایل معتبر نیست.'];
        }
        if ($first === '' || $last === '') {
            return ['ok' => false, 'message' => 'نام و نام خانوادگی الزامی است.'];
        }

        // Mobile may be shared (family). Uniqueness is by file_number / national_id only.
        if ($file) {
            $dup = $this->db->prepare('SELECT id FROM patients WHERE file_number=? AND deleted_at IS NULL AND id<>? LIMIT 1');
            $dup->execute([$file, $id ?? 0]);
            if ($dup->fetchColumn()) {
                return ['ok' => false, 'message' => 'شماره پرونده تکراری است.'];
            }
        }
        if ($nid) {
            $dup = $this->db->prepare('SELECT id FROM patients WHERE national_id=? AND deleted_at IS NULL AND id<>? LIMIT 1');
            $dup->execute([$nid, $id ?? 0]);
            if ($dup->fetchColumn()) {
                return ['ok' => false, 'message' => 'کد ملی تکراری است.'];
            }
        }

        $by = trim((string) ($data['birth_year_jalali'] ?? ''));
        $birthYear = ($by !== '' && ctype_digit(normalize_digits($by))) ? (int) normalize_digits($by) : null;
        if ($birthYear !== null && $birthYear >= 1 && $birthYear <= 99) {
            $birthYear += 1300;
        }
        if ($birthYear !== null && ($birthYear < 1270 || $birthYear > 1450)) {
            $birthYear = null;
        }

        $fields = [
            'first_name' => $first,
            'last_name' => $last,
            'father_name' => normalize_persian_text(trim((string) ($data['father_name'] ?? ''))) ?: null,
            'mobile' => $mobile,
            'landline' => trim((string) ($data['landline'] ?? '')) ?: null,
            'secondary_mobile' => ($sm = trim((string) ($data['secondary_mobile'] ?? ''))) !== '' ? normalize_mobile($sm) : null,
            'file_number' => $file,
            'national_id' => $nid,
            'email' => trim((string) ($data['email'] ?? '')) ?: null,
            'address' => trim((string) ($data['address'] ?? '')) ?: null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'referrer' => trim((string) ($data['referrer'] ?? '')) ?: null,
            'emergency_contact_name' => trim((string) ($data['emergency_contact_name'] ?? '')) ?: null,
            'emergency_contact_mobile' => trim((string) ($data['emergency_contact_mobile'] ?? '')) ?: null,
            'birth_date' => trim((string) ($data['birth_date'] ?? '')) ?: null,
            'birth_year_jalali' => $birthYear,
            'gender' => in_array($data['gender'] ?? '', ['male', 'female', 'other'], true) ? $data['gender'] : null,
            'preferred_doctor_id' => !empty($data['preferred_doctor_id']) ? (int) $data['preferred_doctor_id'] : null,
            'is_active' => isset($data['is_active']) ? 1 : ($id ? null : 1),
            'profile_completed' => ($first !== '' && $last !== '') ? 1 : 0,
        ];

        if ($id) {
            $old = $this->find($id);
            $set = [];
            $vals = [];
            foreach ($fields as $k => $v) {
                if ($k === 'is_active' && $v === null) {
                    continue;
                }
                $set[] = "{$k}=?";
                $vals[] = $v;
            }
            $vals[] = $id;
            $this->db->prepare('UPDATE patients SET ' . implode(',', $set) . ' WHERE id=?')->execute($vals);
            $this->auditSensitive($id, $old, $fields);
            return ['ok' => true, 'id' => $id];
        }

        $code = 'P' . strtoupper(bin2hex(random_bytes(4)));
        $this->db->prepare(
            'INSERT INTO patients (public_code, file_number, first_name, last_name, father_name, mobile, landline, secondary_mobile, national_id, birth_date, birth_year_jalali, gender, email, address,
              emergency_contact_name, emergency_contact_mobile, notes, referrer, preferred_doctor_id, is_imported, profile_completed, is_active)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,1,1)'
        )->execute([
            $code, $file, $first, $last, $fields['father_name'], $mobile, $fields['landline'], $fields['secondary_mobile'], $nid,
            $fields['birth_date'], $fields['birth_year_jalali'], $fields['gender'], $fields['email'],
            $fields['address'], $fields['emergency_contact_name'], $fields['emergency_contact_mobile'], $fields['notes'],
            $fields['referrer'], $fields['preferred_doctor_id'],
        ]);
        $newId = (int) $this->db->lastInsertId();
        audit('patient.created', 'patients', $newId, ['mobile' => $mobile, 'file_number' => $file]);
        return ['ok' => true, 'id' => $newId];
    }

    /** @param array<string, mixed>|null $old @param array<string, mixed> $new */
    private function auditSensitive(int $id, ?array $old, array $new): void
    {
        if (!$old) {
            return;
        }
        $watch = ['mobile', 'file_number', 'national_id'];
        $changes = [];
        foreach ($watch as $k) {
            $a = (string) ($old[$k] ?? '');
            $b = (string) ($new[$k] ?? '');
            if ($a !== $b) {
                $changes[$k] = ['from' => $a, 'to' => $b];
            }
        }
        audit('patient.updated', 'patients', $id, $changes ?: ['fields' => 'profile']);
    }

    public function archive(int $id): void
    {
        $this->db->prepare('UPDATE patients SET deleted_at=NOW(), is_active=0 WHERE id=?')->execute([$id]);
        audit('patient.archived', 'patients', $id);
    }

    public function restore(int $id): void
    {
        $this->db->prepare('UPDATE patients SET deleted_at=NULL, is_active=1 WHERE id=?')->execute([$id]);
        audit('patient.restored', 'patients', $id);
    }

    /**
     * Hard-delete every patient and related clinical/appointment rows so Excel can be re-imported cleanly.
     * @return array{ok:bool, deleted:int, message?:string}
     */
    public function purgeAllPatients(): array
    {
        @set_time_limit(300);
        $deleted = (int) $this->db->query('SELECT COUNT(*) FROM patients')->fetchColumn();
        try {
            $this->db->beginTransaction();

            // Child tables first (FK order). Ignore missing tables on older schemas.
            $this->execIgnore('DELETE ti FROM patient_treatment_items ti INNER JOIN patient_treatment_plans tp ON tp.id = ti.plan_id');
            $this->execIgnore('DELETE FROM patient_treatment_items');
            $this->execIgnore('DELETE FROM patient_treatment_plans');
            $this->execIgnore('DELETE FROM patient_visits');
            $this->execIgnore('DELETE FROM patient_documents');
            $this->execIgnore('DELETE FROM patient_medical_profiles');
            $this->execIgnore('DELETE FROM patient_notes');
            $this->execIgnore('DELETE FROM payments');
            $this->execIgnore('DELETE FROM appointment_status_history');
            $this->execIgnore('UPDATE sms_queue SET appointment_id=NULL, patient_id=NULL');
            $this->execIgnore('UPDATE sms_logs SET appointment_id=NULL, patient_id=NULL');
            $this->execIgnore('DELETE FROM appointments');
            $this->db->exec('DELETE FROM patients');
            $this->db->commit();
        } catch (\Throwable $e) {
            try {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
            } catch (\Throwable) {
            }
            // If deletes already applied outside a usable transaction, verify emptiness.
            $left = (int) $this->db->query('SELECT COUNT(*) FROM patients')->fetchColumn();
            if ($left > 0) {
                return ['ok' => false, 'deleted' => 0, 'message' => $e->getMessage()];
            }
        }

        // DDL auto-commits in MySQL — keep it outside the transaction.
        try {
            $this->db->exec('ALTER TABLE patients AUTO_INCREMENT = 1');
        } catch (\Throwable) {
        }

        audit('patients.purged_all', 'patients', null, ['deleted' => $deleted]);
        return ['ok' => true, 'deleted' => $deleted];
    }

    private function execIgnore(string $sql): void
    {
        try {
            $this->db->exec($sql);
        } catch (\Throwable) {
        }
    }

    public function profileBundle(int $id): array
    {
        $patient = $this->find($id, true);
        if (!$patient) {
            return [];
        }
        $appts = $this->db->prepare(
            "SELECT a.*, s.name service_name, CONCAT(d.first_name,' ',d.last_name) doctor_name
             FROM appointments a
             JOIN services s ON s.id=a.service_id
             JOIN doctors d ON d.id=a.doctor_id
             WHERE a.patient_id=? AND a.deleted_at IS NULL
             ORDER BY a.starts_at DESC LIMIT 50"
        );
        $appts->execute([$id]);
        $pays = $this->db->prepare(
            'SELECT * FROM payments WHERE patient_id=? ORDER BY id DESC LIMIT 30'
        );
        $pays->execute([$id]);
        $sms = $this->db->prepare(
            "SELECT l.*, CONCAT(u.first_name,' ',u.last_name) admin_name
             FROM sms_logs l
             LEFT JOIN admin_users u ON u.id=l.admin_user_id
             WHERE l.patient_id=? ORDER BY l.id DESC LIMIT 40"
        );
        $sms->execute([$id]);
        $notes = $this->db->prepare(
            'SELECT n.*, CONCAT(u.first_name,\' \',u.last_name) admin_name
             FROM patient_notes n LEFT JOIN admin_users u ON u.id=n.admin_user_id
             WHERE n.patient_id=? ORDER BY n.id DESC LIMIT 40'
        );
        $notes->execute([$id]);
        $audit = $this->db->prepare(
            "SELECT * FROM audit_logs WHERE entity_type='patients' AND entity_id=? ORDER BY id DESC LIMIT 40"
        );
        $audit->execute([$id]);
        $c = $this->db->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id=? AND status='completed' AND deleted_at IS NULL");
        $c->execute([$id]);
        $next = $this->db->prepare(
            "SELECT starts_at FROM appointments WHERE patient_id=? AND status='confirmed' AND starts_at>=NOW() AND deleted_at IS NULL ORDER BY starts_at ASC LIMIT 1"
        );
        $next->execute([$id]);
        $last = $this->db->prepare(
            "SELECT starts_at FROM appointments WHERE patient_id=? AND status IN ('confirmed','completed') AND starts_at<NOW() AND deleted_at IS NULL ORDER BY starts_at DESC LIMIT 1"
        );
        $last->execute([$id]);
        $clinical = new PatientClinicalService($this->db);
        $sessionCount = $clinical->visitCount($id);
        $lastVisit = $clinical->lastVisitAt($id);
        $audit2 = $this->db->prepare(
            "SELECT * FROM audit_logs
             WHERE (entity_type='patients' AND entity_id=?)
                OR (entity_type IN ('patient_visits','patient_documents','patient_treatment_plans') AND JSON_EXTRACT(meta_json,'$.patient_id')=?)
             ORDER BY id DESC LIMIT 60"
        );
        // meta_json may not exist — fall back
        try {
            $audit2->execute([$id, $id]);
            $auditRows = $audit2->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            $auditRows = $audit->fetchAll(PDO::FETCH_ASSOC);
        }
        return [
            'patient' => $patient,
            'appointments' => $appts->fetchAll(PDO::FETCH_ASSOC),
            'payments' => $pays->fetchAll(PDO::FETCH_ASSOC),
            'sms' => $sms->fetchAll(PDO::FETCH_ASSOC),
            'notes' => $notes->fetchAll(PDO::FETCH_ASSOC),
            'audit' => $auditRows ?: $audit->fetchAll(PDO::FETCH_ASSOC),
            'visit_count' => (int) $c->fetchColumn(),
            'session_count' => $sessionCount,
            'last_visit_at' => $lastVisit,
            'visits' => $clinical->listVisits($id),
            'medical' => $clinical->medicalProfile($id),
            'documents' => $clinical->listDocuments($id),
            'treatment_plans' => $clinical->listPlans($id),
            'next_appointment' => $next->fetchColumn() ?: null,
            'last_appointment' => $last->fetchColumn() ?: null,
        ];
    }

    /** Quick lookup for global admin search (max 12). */
    public function quickSearch(string $q): array
    {
        $q = normalize_persian_text(trim($q));
        if (mb_strlen($q) < 1) {
            return [];
        }
        $result = $this->search(['q' => $q, 'status' => 'active'], 1, 12);
        $out = [];
        foreach ($result['items'] as $p) {
            $out[] = [
                'id' => (int) $p['id'],
                'name' => trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')),
                'file_number' => (string) ($p['file_number'] ?: $p['public_code'] ?? ''),
                'mobile' => (string) ($p['mobile'] ?? ''),
            ];
        }
        return $out;
    }
}
