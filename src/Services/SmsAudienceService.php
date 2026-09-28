<?php

declare(strict_types=1);

namespace Sarva\Services;

use PDO;

/**
 * Server-side SMS recipient builder / patient targeting.
 * Never trusts client-provided ID lists without re-validation.
 */
final class SmsAudienceService
{
    public const MODES = [
        'manual',
        'search',
        'all',
        'filtered',
        'today',
        'tomorrow',
        'range',
        'this_week',
        'next_week',
        'this_month',
        'next_month',
        'doctor',
        'service',
        'new',
        'old',
        'saved',
        'selected',
    ];

    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array<string, string> */
    public static function modeLabels(): array
    {
        return [
            'manual' => 'انتخاب دستی بیماران',
            'search' => 'انتخاب بر اساس جستجو',
            'all' => 'همه بیماران',
            'filtered' => 'بیماران فیلترشده',
            'today' => 'نوبت‌های امروز',
            'tomorrow' => 'نوبت‌های فردا',
            'range' => 'نوبت در بازه زمانی',
            'this_week' => 'نوبت‌های این هفته',
            'next_week' => 'نوبت‌های هفته آینده',
            'this_month' => 'نوبت‌های این ماه',
            'next_month' => 'نوبت‌های ماه آینده',
            'doctor' => 'بیماران یک پزشک',
            'service' => 'بیماران یک خدمت',
            'new' => 'بیماران جدید',
            'old' => 'بیماران قدیمی',
            'saved' => 'گروه ذخیره‌شده',
            'selected' => 'انتخاب‌شده از فهرست',
        ];
    }

    /**
     * Normalize inbound filter payload from GET/POST/JSON.
     *
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    public function normalizeFilters(array $raw): array
    {
        $mode = (string) ($raw['mode'] ?? $raw['preset'] ?? 'manual');
        if ($mode === '' || !in_array($mode, self::MODES, true)) {
            $mode = 'manual';
        }

        $doctorIds = $this->intList($raw['doctor_ids'] ?? $raw['doctor_id'] ?? []);
        $serviceIds = $this->intList($raw['service_ids'] ?? $raw['service_id'] ?? []);
        $statuses = $this->stringList($raw['appointment_statuses'] ?? $raw['appointment_status'] ?? ['confirmed']);
        $allowedStatus = ['awaiting_payment', 'confirmed', 'completed', 'cancelled', 'no_show', 'expired'];
        $statuses = array_values(array_intersect($statuses, $allowedStatus));
        if ($statuses === [] && $this->isAppointmentMode($mode)) {
            $statuses = ['confirmed'];
        }

        return [
            'mode' => $mode,
            'q' => trim((string) ($raw['q'] ?? '')),
            'source' => in_array(($raw['source'] ?? ''), ['imported', 'manual', 'online'], true) ? (string) $raw['source'] : '',
            'status' => in_array(($raw['status'] ?? 'active'), ['active', 'archived', 'all'], true) ? (string) ($raw['status'] ?? 'active') : 'active',
            'first_name' => trim((string) ($raw['first_name'] ?? '')),
            'last_name' => trim((string) ($raw['last_name'] ?? '')),
            'file_number' => trim((string) ($raw['file_number'] ?? '')),
            'mobile' => trim((string) ($raw['mobile'] ?? '')),
            'registered_from' => $this->ymd($raw['registered_from'] ?? null),
            'registered_to' => $this->ymd($raw['registered_to'] ?? null),
            'doctor_ids' => $doctorIds,
            'service_ids' => $serviceIds,
            'appointment_statuses' => $statuses,
            'appt_date_from' => $this->ymd($raw['appt_date_from'] ?? $raw['date_from'] ?? null),
            'appt_date_to' => $this->ymd($raw['appt_date_to'] ?? $raw['date_to'] ?? null),
            'upcoming' => !empty($raw['upcoming']),
            'no_upcoming' => !empty($raw['no_upcoming']),
            'last_visit_from' => $this->ymd($raw['last_visit_from'] ?? null),
            'last_visit_to' => $this->ymd($raw['last_visit_to'] ?? null),
            'no_visit_since' => $this->ymd($raw['no_visit_since'] ?? null),
            'min_appointments' => max(0, (int) ($raw['min_appointments'] ?? 0)),
            'audience_id' => max(0, (int) ($raw['audience_id'] ?? 0)),
            'new_days' => max(1, min(365, (int) ($raw['new_days'] ?? 30))),
            'old_days' => max(30, min(3650, (int) ($raw['old_days'] ?? 180))),
        ];
    }

    /**
     * @param array<string, mixed> $selection
     * @return array{
     *   mode:string,
     *   reason:string,
     *   total_matched:int,
     *   selected:int,
     *   valid_mobile:int,
     *   invalid_mobile:int,
     *   duplicates_removed:int,
     *   final_recipients:int,
     *   exclude_count:int,
     *   select_all_filtered:bool
     * }
     */
    public function summarize(array $selection): array
    {
        $resolved = $this->resolveRecipients($selection, false, 0, 0);
        $mode = (string) ($resolved['mode'] ?? 'manual');
        return [
            'mode' => $mode,
            'reason' => self::modeLabels()[$mode] ?? $mode,
            'total_matched' => (int) ($resolved['total_matched'] ?? 0),
            'selected' => (int) ($resolved['selected'] ?? 0),
            'valid_mobile' => (int) ($resolved['valid_mobile'] ?? 0),
            'invalid_mobile' => (int) ($resolved['invalid_mobile'] ?? 0),
            'duplicates_removed' => (int) ($resolved['duplicates_removed'] ?? 0),
            'final_recipients' => (int) ($resolved['final_recipients'] ?? 0),
            'exclude_count' => count($this->intList($selection['exclude_ids'] ?? [])),
            'select_all_filtered' => !empty($selection['select_all_filtered']),
        ];
    }

    /**
     * Search / browse patients for the recipient UI (paginated).
     *
     * @param array<string, mixed> $filters
     * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int}
     */
    public function searchPatients(array $filters, int $page = 1, int $perPage = 25): array
    {
        $filters = $this->normalizeFilters($filters);
        $page = max(1, $page);
        $perPage = max(10, min(50, $perPage));
        [$where, $params, $joinAppt] = $this->buildWhere($filters);

        $from = $joinAppt
            ? 'patients p INNER JOIN appointments a ON a.patient_id = p.id AND a.deleted_at IS NULL'
            : 'patients p';

        $countSql = $joinAppt
            ? "SELECT COUNT(DISTINCT p.id) FROM {$from} WHERE {$where}"
            : "SELECT COUNT(*) FROM {$from} WHERE {$where}";
        $count = $this->db->prepare($countSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT p.id, p.public_code, p.file_number, p.first_name, p.last_name, p.mobile, p.is_active, p.is_imported, p.created_at,
                       (SELECT MAX(ax.starts_at) FROM appointments ax
                         WHERE ax.patient_id=p.id AND ax.status IN ('confirmed','completed') AND ax.starts_at < NOW() AND ax.deleted_at IS NULL) last_appointment,
                       (SELECT MIN(ax.starts_at) FROM appointments ax
                         WHERE ax.patient_id=p.id AND ax.status='confirmed' AND ax.starts_at >= NOW() AND ax.deleted_at IS NULL) next_appointment,
                       (SELECT CONCAT(d.first_name,' ',d.last_name) FROM appointments ax
                         JOIN doctors d ON d.id=ax.doctor_id
                         WHERE ax.patient_id=p.id AND ax.deleted_at IS NULL
                         ORDER BY ax.starts_at DESC LIMIT 1) last_doctor
                FROM {$from}
                WHERE {$where}
                GROUP BY p.id
                ORDER BY p.id DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $items[] = $this->mapPatientRow($row, $filters['mode']);
        }
        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int) ceil($total / max(1, $perPage))),
        ];
    }

    /**
     * Preview final recipients (after include/exclude + mobile validation).
     *
     * @param array<string, mixed> $selection
     * @return array{items:list<array<string,mixed>>,total:int,page:int,pages:int,summary:array<string,mixed>}
     */
    public function previewRecipients(array $selection, int $page = 1, int $perPage = 30, string $q = ''): array
    {
        $resolved = $this->resolveRecipients($selection, true, $page, $perPage, $q);
        return [
            'items' => $resolved['items'],
            'total' => (int) $resolved['final_recipients'],
            'page' => (int) $resolved['page'],
            'pages' => (int) $resolved['pages'],
            'summary' => [
                'mode' => $resolved['mode'],
                'reason' => self::modeLabels()[$resolved['mode']] ?? $resolved['mode'],
                'selected' => $resolved['selected'],
                'valid_mobile' => $resolved['valid_mobile'],
                'invalid_mobile' => $resolved['invalid_mobile'],
                'duplicates_removed' => $resolved['duplicates_removed'],
                'final_recipients' => $resolved['final_recipients'],
            ],
        ];
    }

    /**
     * Full recipient rows for enqueue (patient + optional appointment context).
     *
     * @param array<string, mixed> $selection
     * @return list<array<string,mixed>>
     */
    public function recipientsForSend(array $selection, int $limit = 5000): array
    {
        $resolved = $this->resolveRecipients($selection, true, 1, max(1, $limit));
        return $resolved['items'];
    }

    /** Quick counts for chips. */
    public function quickCounts(): array
    {
        $c = static function (PDO $db, string $sql): int {
            try {
                return (int) $db->query($sql)->fetchColumn();
            } catch (\Throwable) {
                return 0;
            }
        };
        return [
            'today' => $c($this->db, "SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE deleted_at IS NULL AND status='confirmed' AND DATE(starts_at)=CURDATE()"),
            'tomorrow' => $c($this->db, "SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE deleted_at IS NULL AND status='confirmed' AND DATE(starts_at)=CURDATE()+INTERVAL 1 DAY"),
            'this_week' => $c($this->db, "SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE deleted_at IS NULL AND status='confirmed' AND YEARWEEK(starts_at,6)=YEARWEEK(CURDATE(),6)"),
            'new' => $c($this->db, "SELECT COUNT(*) FROM patients WHERE deleted_at IS NULL AND is_active=1 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
            'all' => $c($this->db, 'SELECT COUNT(*) FROM patients WHERE deleted_at IS NULL AND is_active=1'),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function listAudiences(): array
    {
        $rows = $this->db->query(
            'SELECT id, name, description, mode, filter_json, created_at, updated_at
             FROM sms_saved_audiences WHERE deleted_at IS NULL ORDER BY name ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['filters'] = json_decode((string) ($row['filter_json'] ?? '{}'), true) ?: [];
            unset($row['filter_json']);
        }
        return $rows;
    }

    /** @param array<string, mixed> $filters */
    public function saveAudience(string $name, array $filters, ?string $description, ?int $id, ?int $adminId): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'message' => 'نام گروه الزامی است.'];
        }
        $filters = $this->normalizeFilters($filters);
        $json = json_encode($filters, JSON_UNESCAPED_UNICODE);
        if ($id) {
            $this->db->prepare(
                'UPDATE sms_saved_audiences SET name=?, description=?, mode=?, filter_json=?, updated_by=? WHERE id=? AND deleted_at IS NULL'
            )->execute([$name, $description, $filters['mode'], $json, $adminId, $id]);
            return ['ok' => true, 'id' => $id];
        }
        $this->db->prepare(
            'INSERT INTO sms_saved_audiences (name, description, mode, filter_json, created_by, updated_by) VALUES (?,?,?,?,?,?)'
        )->execute([$name, $description, $filters['mode'], $json, $adminId, $adminId]);
        return ['ok' => true, 'id' => (int) $this->db->lastInsertId()];
    }

    public function duplicateAudience(int $id, ?int $adminId): array
    {
        $row = $this->findAudience($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'گروه یافت نشد.'];
        }
        return $this->saveAudience($row['name'] . ' (کپی)', $row['filters'], $row['description'] ?? null, null, $adminId);
    }

    public function deleteAudience(int $id): void
    {
        $this->db->prepare('UPDATE sms_saved_audiences SET deleted_at=NOW() WHERE id=?')->execute([$id]);
    }

    public function findAudience(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM sms_saved_audiences WHERE id=? AND deleted_at IS NULL');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['filters'] = json_decode((string) ($row['filter_json'] ?? '{}'), true) ?: [];
        return $row;
    }

    /**
     * Core resolver.
     *
     * @param array<string, mixed> $selection
     * @return array<string, mixed>
     */
    public function resolveRecipients(array $selection, bool $withItems, int $page = 1, int $perPage = 30, string $searchQ = ''): array
    {
        $filters = $this->normalizeFilters($selection['filters'] ?? $selection);
        if (!empty($selection['mode'])) {
            $filters['mode'] = (string) $selection['mode'];
            if (!in_array($filters['mode'], self::MODES, true)) {
                $filters['mode'] = 'manual';
            }
        }

        if ($filters['mode'] === 'saved' && $filters['audience_id'] > 0) {
            $aud = $this->findAudience($filters['audience_id']);
            if ($aud) {
                $saved = $this->normalizeFilters($aud['filters']);
                $saved['mode'] = (string) ($aud['mode'] ?: $saved['mode']);
                $filters = $saved;
            }
        }

        $excludeIds = array_values(array_unique($this->intList($selection['exclude_ids'] ?? [])));
        $includeIds = array_values(array_unique($this->intList($selection['include_ids'] ?? $selection['patient_ids'] ?? [])));
        $selectAll = !empty($selection['select_all_filtered']);

        // Manual / selected without select-all: only include list
        if (in_array($filters['mode'], ['manual', 'selected', 'search'], true) && !$selectAll) {
            $matchedIds = $this->validatePatientIds($includeIds);
            $totalMatched = count($matchedIds);
        } elseif ($selectAll || in_array($filters['mode'], ['all', 'filtered', 'today', 'tomorrow', 'range', 'this_week', 'next_week', 'this_month', 'next_month', 'doctor', 'service', 'new', 'old', 'saved'], true)) {
            $matchedIds = $this->queryMatchedIds($filters);
            $totalMatched = count($matchedIds);
            if (!$selectAll && $includeIds) {
                // Intersection: explicit picks within filter (page select)
                $set = array_flip($matchedIds);
                $matchedIds = array_values(array_filter($includeIds, static fn (int $id): bool => isset($set[$id])));
            } elseif (!$selectAll && !$includeIds && in_array($filters['mode'], ['manual', 'search', 'selected'], true)) {
                $matchedIds = [];
                $totalMatched = 0;
            }
        } else {
            $matchedIds = $this->validatePatientIds($includeIds);
            $totalMatched = count($matchedIds);
        }

        if ($excludeIds) {
            $ex = array_flip($excludeIds);
            $matchedIds = array_values(array_filter($matchedIds, static fn (int $id): bool => !isset($ex[$id])));
        }

        $selected = count($matchedIds);
        $rows = $matchedIds ? $this->loadPatientRows($matchedIds, $filters) : [];

        $seenMobile = [];
        $valid = [];
        $invalid = [];
        $dupes = 0;
        foreach ($rows as $row) {
            $mobile = normalize_mobile((string) ($row['mobile'] ?? ''));
            if ($mobile === null) {
                $invalid[] = $row;
                continue;
            }
            if (isset($seenMobile[$mobile])) {
                $dupes++;
                continue;
            }
            $seenMobile[$mobile] = true;
            $row['mobile_normalized'] = $mobile;
            $row['reason'] = self::modeLabels()[$filters['mode']] ?? $filters['mode'];
            $valid[] = $row;
        }

        $final = count($valid);
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $items = [];
        if ($withItems) {
            $list = $valid;
            if ($searchQ !== '') {
                $q = mb_strtolower($searchQ);
                $list = array_values(array_filter($list, static function (array $r) use ($q): bool {
                    $hay = mb_strtolower(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '') . ' ' . ($r['mobile'] ?? '') . ' ' . ($r['file_number'] ?? '') . ' ' . ($r['public_code'] ?? '')));
                    return str_contains($hay, $q);
                }));
            }
            $pages = max(1, (int) ceil(count($list) / $perPage));
            $slice = array_slice($list, ($page - 1) * $perPage, $perPage);
            foreach ($slice as $r) {
                $items[] = $this->mapPatientRow($r, $filters['mode']);
            }
        } else {
            $pages = 1;
        }

        return [
            'mode' => $filters['mode'],
            'total_matched' => $totalMatched,
            'selected' => $selected,
            'valid_mobile' => $final,
            'invalid_mobile' => count($invalid),
            'duplicates_removed' => $dupes,
            'final_recipients' => $final,
            'patient_ids' => $matchedIds,
            'items' => $items,
            'page' => $page,
            'pages' => $pages,
            'all_valid' => $withItems && $page === 1 && $perPage >= $final ? $valid : null,
            'filters' => $filters,
        ];
    }

    /**
     * Patient IDs matching an SMS Center-style selection payload.
     *
     * @param array<string, mixed> $selection
     * @return list<int>
     */
    public function resolvePatientIds(array $selection): array
    {
        $result = $this->resolveRecipients($selection, false);
        /** @var list<int> $ids */
        $ids = array_values(array_map('intval', $result['patient_ids'] ?? []));
        return $ids;
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<int>
     */
    private function queryMatchedIds(array $filters): array
    {
        [$where, $params, $joinAppt] = $this->buildWhere($filters);
        $from = $joinAppt
            ? 'patients p INNER JOIN appointments a ON a.patient_id = p.id AND a.deleted_at IS NULL'
            : 'patients p';
        $sql = $joinAppt
            ? "SELECT DISTINCT p.id FROM {$from} WHERE {$where} ORDER BY p.id DESC"
            : "SELECT p.id FROM {$from} WHERE {$where} ORDER BY p.id DESC";
        // Soft safety cap for extreme datasets during a single HTTP request
        $sql .= ' LIMIT 20000';
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0:string,1:list<mixed>,2:bool}
     */
    private function buildWhere(array $filters): array
    {
        $mode = (string) ($filters['mode'] ?? 'manual');
        $params = [];
        $joinAppt = $this->isAppointmentMode($mode)
            || !empty($filters['appt_date_from'])
            || !empty($filters['appt_date_to'])
            || ($filters['doctor_ids'] && $mode === 'doctor')
            || ($filters['service_ids'] && $mode === 'service');

        // Base patient scope
        if (($filters['status'] ?? 'active') === 'archived') {
            $where = ['p.deleted_at IS NOT NULL'];
        } elseif (($filters['status'] ?? 'active') === 'all') {
            $where = ['1=1'];
        } else {
            $where = ['p.deleted_at IS NULL', 'p.is_active=1'];
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $mobileNorm = normalize_mobile($q);
            $digits = preg_replace('/\D+/', '', $q) ?? '';
            $parts = ['p.public_code LIKE ?', 'p.file_number LIKE ?', 'p.mobile LIKE ?', 'p.national_id LIKE ?',
                'p.first_name LIKE ?', 'p.last_name LIKE ?', "CONCAT(p.first_name,' ',p.last_name) LIKE ?", 'p.id = ?'];
            $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like, ctype_digit($q) ? (int) $q : 0]);
            if ($mobileNorm) {
                $parts[] = 'p.mobile = ?';
                $params[] = $mobileNorm;
            }
            if (strlen($digits) >= 3) {
                $parts[] = 'p.mobile LIKE ?';
                $params[] = '%' . $digits . '%';
                $parts[] = 'p.file_number LIKE ?';
                $params[] = '%' . $digits . '%';
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }

        if (($fn = trim((string) ($filters['first_name'] ?? ''))) !== '') {
            $where[] = 'p.first_name LIKE ?';
            $params[] = '%' . $fn . '%';
        }
        if (($ln = trim((string) ($filters['last_name'] ?? ''))) !== '') {
            $where[] = 'p.last_name LIKE ?';
            $params[] = '%' . $ln . '%';
        }
        if (($file = trim((string) ($filters['file_number'] ?? ''))) !== '') {
            $where[] = '(p.file_number LIKE ? OR p.public_code LIKE ?)';
            $params[] = '%' . $file . '%';
            $params[] = '%' . $file . '%';
        }
        if (($mob = trim((string) ($filters['mobile'] ?? ''))) !== '') {
            $norm = normalize_mobile($mob);
            $digits = preg_replace('/\D+/', '', $mob) ?? '';
            if ($norm) {
                $where[] = 'p.mobile = ?';
                $params[] = $norm;
            } else {
                $where[] = 'p.mobile LIKE ?';
                $params[] = '%' . $digits . '%';
            }
        }

        $source = (string) ($filters['source'] ?? '');
        if ($source === 'imported') {
            $where[] = 'p.is_imported=1';
        } elseif ($source === 'manual') {
            $where[] = 'p.is_imported=0';
        } elseif ($source === 'online') {
            // Online bookings create non-imported patients with profile_completed; approximate.
            $where[] = 'p.is_imported=0';
        }

        if (!empty($filters['registered_from'])) {
            $where[] = 'DATE(p.created_at) >= ?';
            $params[] = $filters['registered_from'];
        }
        if (!empty($filters['registered_to'])) {
            $where[] = 'DATE(p.created_at) <= ?';
            $params[] = $filters['registered_to'];
        }

        if ($mode === 'new') {
            $where[] = 'p.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
            $params[] = (int) $filters['new_days'];
        }
        if ($mode === 'old') {
            $where[] = 'p.created_at < DATE_SUB(NOW(), INTERVAL ? DAY)';
            $params[] = (int) $filters['old_days'];
            $where[] = "NOT EXISTS (
                SELECT 1 FROM appointments ax
                WHERE ax.patient_id=p.id AND ax.deleted_at IS NULL
                  AND ax.status IN ('confirmed','completed')
                  AND ax.starts_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            )";
            $params[] = (int) $filters['old_days'];
        }

        if (!empty($filters['upcoming'])) {
            $where[] = "EXISTS (SELECT 1 FROM appointments ax WHERE ax.patient_id=p.id AND ax.status='confirmed' AND ax.starts_at>=NOW() AND ax.deleted_at IS NULL)";
        }
        if (!empty($filters['no_upcoming'])) {
            $where[] = "NOT EXISTS (SELECT 1 FROM appointments ax WHERE ax.patient_id=p.id AND ax.status='confirmed' AND ax.starts_at>=NOW() AND ax.deleted_at IS NULL)";
        }
        if (!empty($filters['last_visit_from'])) {
            $where[] = "EXISTS (SELECT 1 FROM appointments ax WHERE ax.patient_id=p.id AND ax.deleted_at IS NULL AND ax.status IN ('confirmed','completed') AND DATE(ax.starts_at) >= ? AND ax.starts_at < NOW())";
            $params[] = $filters['last_visit_from'];
        }
        if (!empty($filters['last_visit_to'])) {
            $where[] = "EXISTS (SELECT 1 FROM appointments ax WHERE ax.patient_id=p.id AND ax.deleted_at IS NULL AND ax.status IN ('confirmed','completed') AND DATE(ax.starts_at) <= ? AND ax.starts_at < NOW())";
            $params[] = $filters['last_visit_to'];
        }
        if (!empty($filters['no_visit_since'])) {
            $where[] = "NOT EXISTS (SELECT 1 FROM appointments ax WHERE ax.patient_id=p.id AND ax.deleted_at IS NULL AND ax.status IN ('confirmed','completed') AND DATE(ax.starts_at) >= ?)";
            $params[] = $filters['no_visit_since'];
        }
        if ((int) ($filters['min_appointments'] ?? 0) > 0) {
            $where[] = '(SELECT COUNT(*) FROM appointments ax WHERE ax.patient_id=p.id AND ax.deleted_at IS NULL) >= ?';
            $params[] = (int) $filters['min_appointments'];
        }

        // Appointment join filters
        if ($joinAppt) {
            $statuses = $filters['appointment_statuses'] ?: ['confirmed'];
            // Never send reminders to cancelled by default in appointment presets
            if ($this->isAppointmentMode($mode)) {
                $statuses = array_values(array_diff($statuses, ['cancelled', 'expired']));
                if ($statuses === []) {
                    $statuses = ['confirmed'];
                }
            }
            $in = implode(',', array_fill(0, count($statuses), '?'));
            $where[] = "a.status IN ({$in})";
            foreach ($statuses as $st) {
                $params[] = $st;
            }

            if ($mode === 'today') {
                $where[] = 'DATE(a.starts_at)=CURDATE()';
            } elseif ($mode === 'tomorrow') {
                $where[] = 'DATE(a.starts_at)=CURDATE()+INTERVAL 1 DAY';
            } elseif ($mode === 'this_week') {
                $where[] = 'YEARWEEK(a.starts_at, 6)=YEARWEEK(CURDATE(), 6)';
            } elseif ($mode === 'next_week') {
                $where[] = 'YEARWEEK(a.starts_at, 6)=YEARWEEK(CURDATE()+INTERVAL 7 DAY, 6)';
            } elseif ($mode === 'this_month') {
                $where[] = 'YEAR(a.starts_at)=YEAR(CURDATE()) AND MONTH(a.starts_at)=MONTH(CURDATE())';
            } elseif ($mode === 'next_month') {
                $where[] = "DATE_FORMAT(a.starts_at,'%Y-%m') = DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 MONTH),'%Y-%m')";
            }

            if (!empty($filters['appt_date_from'])) {
                $where[] = 'DATE(a.starts_at) >= ?';
                $params[] = $filters['appt_date_from'];
            }
            if (!empty($filters['appt_date_to'])) {
                $where[] = 'DATE(a.starts_at) <= ?';
                $params[] = $filters['appt_date_to'];
            }

            if ($filters['doctor_ids']) {
                $in = implode(',', array_fill(0, count($filters['doctor_ids']), '?'));
                $where[] = "a.doctor_id IN ({$in})";
                foreach ($filters['doctor_ids'] as $id) {
                    $params[] = $id;
                }
            } elseif ($mode === 'doctor' && empty($filters['doctor_ids'])) {
                $where[] = '1=0';
            }

            if ($filters['service_ids']) {
                $in = implode(',', array_fill(0, count($filters['service_ids']), '?'));
                $where[] = "a.service_id IN ({$in})";
                foreach ($filters['service_ids'] as $id) {
                    $params[] = $id;
                }
            } elseif ($mode === 'service' && empty($filters['service_ids'])) {
                $where[] = '1=0';
            }
        } else {
            if ($mode === 'doctor' && empty($filters['doctor_ids'])) {
                $where[] = '1=0';
            }
            if ($mode === 'service' && empty($filters['service_ids'])) {
                $where[] = '1=0';
            }
            // Non-appt modes may still filter by doctor/service history
            if ($filters['doctor_ids']) {
                $in = implode(',', array_fill(0, count($filters['doctor_ids']), '?'));
                $where[] = "EXISTS (SELECT 1 FROM appointments ax WHERE ax.patient_id=p.id AND ax.deleted_at IS NULL AND ax.doctor_id IN ({$in}))";
                foreach ($filters['doctor_ids'] as $id) {
                    $params[] = $id;
                }
            }
            if ($filters['service_ids']) {
                $in = implode(',', array_fill(0, count($filters['service_ids']), '?'));
                $where[] = "EXISTS (SELECT 1 FROM appointments ax WHERE ax.patient_id=p.id AND ax.deleted_at IS NULL AND ax.service_id IN ({$in}))";
                foreach ($filters['service_ids'] as $id) {
                    $params[] = $id;
                }
            }
        }

        return [implode(' AND ', $where), $params, $joinAppt];
    }

    private function isAppointmentMode(string $mode): bool
    {
        return in_array($mode, ['today', 'tomorrow', 'range', 'this_week', 'next_week', 'this_month', 'next_month'], true);
    }

    /** @param list<int> $ids @return list<int> */
    private function validatePatientIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->db->prepare("SELECT id FROM patients WHERE deleted_at IS NULL AND is_active=1 AND id IN ({$in})");
        $st->execute($ids);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @param list<int> $ids
     * @param array<string, mixed> $filters
     * @return list<array<string,mixed>>
     */
    private function loadPatientRows(array $ids, array $filters): array
    {
        if (!$ids) {
            return [];
        }
        $chunks = array_chunk($ids, 500);
        $out = [];
        foreach ($chunks as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $st = $this->db->prepare(
                "SELECT p.*,
                        (SELECT MAX(ax.starts_at) FROM appointments ax WHERE ax.patient_id=p.id AND ax.status IN ('confirmed','completed') AND ax.starts_at < NOW() AND ax.deleted_at IS NULL) last_appointment,
                        (SELECT MIN(ax.starts_at) FROM appointments ax WHERE ax.patient_id=p.id AND ax.status='confirmed' AND ax.starts_at >= NOW() AND ax.deleted_at IS NULL) next_appointment
                 FROM patients p WHERE p.id IN ({$in})"
            );
            $st->execute($chunk);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $out[(int) $row['id']] = $row;
            }
        }
        // Preserve order of $ids
        $ordered = [];
        foreach ($ids as $id) {
            if (isset($out[$id])) {
                $ordered[] = $this->attachAppointmentContext($out[$id], $filters);
            }
        }
        return $ordered;
    }

    /** @param array<string, mixed> $patient @param array<string, mixed> $filters */
    private function attachAppointmentContext(array $patient, array $filters): array
    {
        if (!$this->isAppointmentMode((string) ($filters['mode'] ?? ''))) {
            // Prefer next upcoming confirmed for variables
            if (!empty($patient['next_appointment'])) {
                $st = $this->db->prepare(
                    "SELECT a.id appointment_id, a.starts_at, a.status,
                            CONCAT(d.first_name,' ',d.last_name) doctor_name, s.name service_name
                     FROM appointments a
                     JOIN doctors d ON d.id=a.doctor_id
                     JOIN services s ON s.id=a.service_id
                     WHERE a.patient_id=? AND a.status='confirmed' AND a.starts_at>=NOW() AND a.deleted_at IS NULL
                     ORDER BY a.starts_at ASC LIMIT 1"
                );
                $st->execute([(int) $patient['id']]);
                $appt = $st->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($appt) {
                    $patient = array_merge($patient, $appt);
                }
            }
            return $patient;
        }

        [$where, $params, ] = $this->buildWhere($filters);
        // Rebuild focused on this patient + appointment join
        $params2 = [];
        $extra = ['a.patient_id = ?', 'a.deleted_at IS NULL'];
        $params2[] = (int) $patient['id'];
        $statuses = $filters['appointment_statuses'] ?: ['confirmed'];
        $statuses = array_values(array_diff($statuses, ['cancelled', 'expired']));
        if ($statuses === []) {
            $statuses = ['confirmed'];
        }
        $in = implode(',', array_fill(0, count($statuses), '?'));
        $extra[] = "a.status IN ({$in})";
        foreach ($statuses as $st) {
            $params2[] = $st;
        }
        $mode = (string) $filters['mode'];
        if ($mode === 'today') {
            $extra[] = 'DATE(a.starts_at)=CURDATE()';
        } elseif ($mode === 'tomorrow') {
            $extra[] = 'DATE(a.starts_at)=CURDATE()+INTERVAL 1 DAY';
        } elseif ($mode === 'this_week') {
            $extra[] = 'YEARWEEK(a.starts_at, 6)=YEARWEEK(CURDATE(), 6)';
        } elseif ($mode === 'next_week') {
            $extra[] = 'YEARWEEK(a.starts_at, 6)=YEARWEEK(CURDATE()+INTERVAL 7 DAY, 6)';
        } elseif ($mode === 'this_month') {
            $extra[] = 'YEAR(a.starts_at)=YEAR(CURDATE()) AND MONTH(a.starts_at)=MONTH(CURDATE())';
        } elseif ($mode === 'next_month') {
            $extra[] = "DATE_FORMAT(a.starts_at,'%Y-%m') = DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 MONTH),'%Y-%m')";
        }
        if (!empty($filters['appt_date_from'])) {
            $extra[] = 'DATE(a.starts_at) >= ?';
            $params2[] = $filters['appt_date_from'];
        }
        if (!empty($filters['appt_date_to'])) {
            $extra[] = 'DATE(a.starts_at) <= ?';
            $params2[] = $filters['appt_date_to'];
        }
        if ($filters['doctor_ids']) {
            $in = implode(',', array_fill(0, count($filters['doctor_ids']), '?'));
            $extra[] = "a.doctor_id IN ({$in})";
            foreach ($filters['doctor_ids'] as $id) {
                $params2[] = $id;
            }
        }
        if ($filters['service_ids']) {
            $in = implode(',', array_fill(0, count($filters['service_ids']), '?'));
            $extra[] = "a.service_id IN ({$in})";
            foreach ($filters['service_ids'] as $id) {
                $params2[] = $id;
            }
        }
        $sql = 'SELECT a.id appointment_id, a.starts_at, a.status,
                       CONCAT(d.first_name,\' \',d.last_name) doctor_name, s.name service_name
                FROM appointments a
                JOIN doctors d ON d.id=a.doctor_id
                JOIN services s ON s.id=a.service_id
                WHERE ' . implode(' AND ', $extra) . '
                ORDER BY a.starts_at ASC LIMIT 1';
        $st = $this->db->prepare($sql);
        $st->execute($params2);
        $appt = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($appt) {
            $patient = array_merge($patient, $appt);
        }
        unset($where, $params);
        return $patient;
    }

    /** @param array<string, mixed> $row */
    private function mapPatientRow(array $row, string $mode = 'manual'): array
    {
        $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        $mobile = (string) ($row['mobile'] ?? '');
        $norm = $row['mobile_normalized'] ?? normalize_mobile($mobile);
        return [
            'id' => (int) $row['id'],
            'full_name' => $name !== '' ? $name : '—',
            'first_name' => (string) ($row['first_name'] ?? ''),
            'last_name' => (string) ($row['last_name'] ?? ''),
            'file_number' => (string) ($row['file_number'] ?: ($row['public_code'] ?? '')),
            'mobile' => $mobile,
            'mobile_normalized' => $norm,
            'mobile_valid' => $norm !== null,
            'last_appointment' => $row['last_appointment'] ?? null,
            'last_appointment_jalali' => to_jalali($row['last_appointment'] ?? null, 'Y/m/d'),
            'next_appointment' => $row['next_appointment'] ?? ($row['starts_at'] ?? null),
            'next_appointment_jalali' => to_jalali(($row['next_appointment'] ?? $row['starts_at'] ?? null), 'Y/m/d H:i'),
            'doctor_name' => (string) ($row['doctor_name'] ?? $row['last_doctor'] ?? ''),
            'service_name' => (string) ($row['service_name'] ?? ''),
            'appointment_id' => isset($row['appointment_id']) ? (int) $row['appointment_id'] : null,
            'starts_at' => $row['starts_at'] ?? null,
            'is_active' => (int) ($row['is_active'] ?? 1),
            'is_imported' => (int) ($row['is_imported'] ?? 0),
            'status_label' => !empty($row['deleted_at']) ? 'بایگانی' : ((int) ($row['is_active'] ?? 1) ? 'فعال' : 'غیرفعال'),
            'reason' => (string) ($row['reason'] ?? (self::modeLabels()[$mode] ?? $mode)),
        ];
    }

    /** @param mixed $v @return list<int> */
    private function intList(mixed $v): array
    {
        if ($v === null || $v === '' || $v === []) {
            return [];
        }
        if (!is_array($v)) {
            $v = [$v];
        }
        return array_values(array_unique(array_filter(array_map('intval', $v), static fn (int $i): bool => $i > 0)));
    }

    /** @param mixed $v @return list<string> */
    private function stringList(mixed $v): array
    {
        if ($v === null || $v === '') {
            return [];
        }
        if (is_string($v)) {
            $v = array_map('trim', explode(',', $v));
        }
        if (!is_array($v)) {
            return [];
        }
        return array_values(array_filter(array_map(static fn ($x): string => trim((string) $x), $v)));
    }

    private function ymd(mixed $v): string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return '';
        }
        $v = str_replace('T', ' ', $v);
        $ts = strtotime($v);
        return $ts ? date('Y-m-d', $ts) : '';
    }
}
