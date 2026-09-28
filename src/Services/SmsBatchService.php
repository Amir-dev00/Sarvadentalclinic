<?php

declare(strict_types=1);

namespace Sarva\Services;

use PDO;

final class SmsBatchService
{
    public function __construct(
        private readonly PDO $db,
        private readonly SmsAudienceService $audience,
        private readonly SmsService $sms,
    ) {
    }

    /**
     * Create batch + enqueue personalized messages.
     *
     * @param array<string, mixed> $payload
     * @return array{ok:bool,message?:string,batch_id?:int,public_code?:string,queued?:int,summary?:array<string,mixed>}
     */
    public function createAndEnqueue(array $payload, int $adminId): array
    {
        $selection = [
            'mode' => $payload['mode'] ?? 'manual',
            'filters' => $payload['filters'] ?? $payload,
            'include_ids' => $payload['include_ids'] ?? $payload['patient_ids'] ?? [],
            'exclude_ids' => $payload['exclude_ids'] ?? [],
            'select_all_filtered' => !empty($payload['select_all_filtered']),
        ];

        $limit = max(1, min(5000, (int) setting('sms_bulk_limit', 500)));
        $resolved = $this->audience->resolveRecipients($selection, true, 1, $limit);
        $recipients = $resolved['all_valid'] ?? $resolved['items'] ?? [];
        if ($recipients === [] && (int) ($resolved['final_recipients'] ?? 0) > 0) {
            $recipients = $this->audience->recipientsForSend($selection, $limit);
        }

        if (!$recipients) {
            return ['ok' => false, 'message' => 'گیرنده‌ای با شماره موبایل معتبر یافت نشد.'];
        }

        $tplId = (int) ($payload['template_id'] ?? 0);
        $custom = trim((string) ($payload['message'] ?? ''));
        $tpl = null;
        if ($tplId > 0) {
            $st = $this->db->prepare('SELECT * FROM sms_templates WHERE id=? AND deleted_at IS NULL AND is_active=1');
            $st->execute([$tplId]);
            $tpl = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        $bodyTpl = $tpl ? (string) $tpl['body'] : $custom;
        if (trim($bodyTpl) === '') {
            return ['ok' => false, 'message' => 'متن پیامک خالی است.'];
        }

        $sendMode = (($payload['send_mode'] ?? 'now') === 'scheduled') ? 'scheduled' : 'now';
        $scheduledRaw = trim((string) ($payload['scheduled_at'] ?? ''));
        $scheduledAt = date('Y-m-d H:i:s');
        if ($sendMode === 'scheduled') {
            if ($scheduledRaw === '') {
                return ['ok' => false, 'message' => 'زمان ارسال زمان‌بندی‌شده را مشخص کنید.'];
            }
            $ts = strtotime(str_replace('T', ' ', $scheduledRaw));
            if (!$ts || $ts < time() - 60) {
                return ['ok' => false, 'message' => 'زمان ارسال نامعتبر است.'];
            }
            $scheduledAt = date('Y-m-d H:i:s', $ts);
        }

        $purpose = (($payload['purpose'] ?? 'operational') === 'marketing') ? 'marketing' : 'operational';
        $messageType = (string) ($tpl['type'] ?? ($payload['message_type'] ?? 'general'));
        $sample = SmsTemplateRenderer::render($bodyTpl, SmsTemplateRenderer::varsFrom($recipients[0], $this->apptVars($recipients[0])));
        $segments = SmsTemplateRenderer::segmentCount($sample);

        $code = 'SMS-' . (date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4)));
        $this->db->prepare(
            'INSERT INTO sms_batches
             (public_code, admin_user_id, title, mode, audience_json, filter_json, template_id, message_body, message_type, purpose,
              send_mode, scheduled_at, status, total_selected, valid_mobile, invalid_mobile, duplicates_removed,
              queued_count, pending_count, segments_estimate)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $code,
            $adminId,
            trim((string) ($payload['title'] ?? '')) ?: ('ارسال گروهی ' . $code),
            (string) $resolved['mode'],
            json_encode($selection, JSON_UNESCAPED_UNICODE),
            json_encode($resolved['filters'] ?? [], JSON_UNESCAPED_UNICODE),
            $tpl['id'] ?? null,
            $bodyTpl,
            $messageType,
            $purpose,
            $sendMode,
            $scheduledAt,
            'queued',
            (int) $resolved['selected'],
            (int) $resolved['valid_mobile'],
            (int) $resolved['invalid_mobile'],
            (int) $resolved['duplicates_removed'],
            0,
            0,
            $segments,
        ]);
        $batchId = (int) $this->db->lastInsertId();

        $queued = 0;
        foreach ($recipients as $p) {
            $vars = SmsTemplateRenderer::varsFrom($p, $this->apptVars($p));
            $rendered = SmsTemplateRenderer::renderForSend($bodyTpl, $vars);
            if (!$rendered['ok']) {
                SmsTemplateRenderer::logRenderError('batch_enqueue', [
                    'template_id' => isset($tpl['id']) ? (int) $tpl['id'] : null,
                    'batch_id' => $batchId,
                    'patient_id' => (int) $p['id'],
                    'unresolved' => $rendered['unresolved'],
                ]);
                continue;
            }
            $qid = $this->sms->enqueue([
                'patient_id' => (int) $p['id'],
                'appointment_id' => !empty($p['appointment_id']) ? (int) $p['appointment_id'] : null,
                'template_id' => $tpl['id'] ?? null,
                'admin_user_id' => $adminId,
                'batch_id' => $batchId,
                'mobile' => (string) ($p['mobile_normalized'] ?? $p['mobile']),
                'message' => $rendered['message'],
                'message_type' => $messageType,
                'source' => $sendMode === 'scheduled' ? 'scheduled' : 'manual',
                'scheduled_at' => $scheduledAt,
                'appointment_starts_at' => $p['starts_at'] ?? null,
                'idempotency_key' => 'batch:' . $batchId . ':p:' . (int) $p['id'],
            ]);
            if ($qid > 0) {
                $queued++;
            }
        }

        $this->db->prepare(
            'UPDATE sms_batches SET queued_count=?, pending_count=?, status=? WHERE id=?'
        )->execute([$queued, $queued, $queued > 0 ? 'queued' : 'failed', $batchId]);

        audit('sms.batch_create', 'sms_batches', $batchId, [
            'code' => $code,
            'queued' => $queued,
            'mode' => $resolved['mode'],
            'send_mode' => $sendMode,
            'scheduled_at' => $scheduledAt,
        ]);

        return [
            'ok' => true,
            'batch_id' => $batchId,
            'public_code' => $code,
            'queued' => $queued,
            'summary' => [
                'selected' => (int) $resolved['selected'],
                'valid_mobile' => (int) $resolved['valid_mobile'],
                'invalid_mobile' => (int) $resolved['invalid_mobile'],
                'duplicates_removed' => (int) $resolved['duplicates_removed'],
                'final_recipients' => (int) $resolved['final_recipients'],
                'queued' => $queued,
            ],
        ];
    }

    /** Send a single test SMS without creating a full batch queue of recipients. */
    public function testSend(array $payload, int $adminId): array
    {
        $mobile = normalize_mobile((string) ($payload['test_mobile'] ?? ''));
        if ($mobile === null) {
            return ['ok' => false, 'message' => 'شماره موبایل آزمایشی معتبر نیست.'];
        }
        $tplId = (int) ($payload['template_id'] ?? 0);
        $custom = trim((string) ($payload['message'] ?? ''));
        $bodyTpl = $custom;
        if ($tplId > 0) {
            $st = $this->db->prepare('SELECT * FROM sms_templates WHERE id=? AND deleted_at IS NULL');
            $st->execute([$tplId]);
            $tpl = $st->fetch(PDO::FETCH_ASSOC);
            if ($tpl) {
                $bodyTpl = (string) $tpl['body'];
            }
        }
        if (trim($bodyTpl) === '') {
            return ['ok' => false, 'message' => 'متن پیام خالی است.'];
        }

        $samplePatient = null;
        $include = array_map('intval', (array) ($payload['include_ids'] ?? []));
        if ($include) {
            $st = $this->db->prepare('SELECT * FROM patients WHERE id=?');
            $st->execute([$include[0]]);
            $samplePatient = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$samplePatient) {
            $samplePatient = [
                'first_name' => 'آزمایش',
                'last_name' => 'سروا',
                'file_number' => 'PTEST',
                'mobile' => $mobile,
            ];
        }
        $message = SmsTemplateRenderer::render($bodyTpl, SmsTemplateRenderer::varsFrom($samplePatient));
        $qid = $this->sms->enqueue([
            'patient_id' => isset($samplePatient['id']) ? (int) $samplePatient['id'] : null,
            'template_id' => $tplId ?: null,
            'admin_user_id' => $adminId,
            'mobile' => $mobile,
            'message' => $message,
            'message_type' => 'general',
            'source' => 'manual',
            'idempotency_key' => 'test:' . $adminId . ':' . $mobile . ':' . substr(sha1($message), 0, 12) . ':' . time(),
        ]);
        audit('sms.test_send', 'sms_queue', $qid ?: null, ['mobile' => $mobile]);
        return $qid > 0
            ? ['ok' => true, 'message' => 'پیام آزمایشی در صف قرار گرفت.', 'queue_id' => $qid]
            : ['ok' => false, 'message' => 'ثبت پیام آزمایشی ناموفق بود.'];
    }

    public function find(int $id): ?array
    {
        $st = $this->db->prepare(
            "SELECT b.*, CONCAT(u.first_name,' ',u.last_name) admin_name, t.name template_name
             FROM sms_batches b
             LEFT JOIN admin_users u ON u.id=b.admin_user_id
             LEFT JOIN sms_templates t ON t.id=b.template_id
             WHERE b.id=?"
        );
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $st = $this->db->prepare('SELECT id FROM sms_batches WHERE public_code=?');
        $st->execute([$code]);
        $id = (int) $st->fetchColumn();
        return $id ? $this->find($id) : null;
    }

    /** Refresh counters from queue. */
    public function refreshStats(int $batchId): array
    {
        $st = $this->db->prepare(
            "SELECT
                SUM(status IN ('pending','retrying','processing')) pending_count,
                SUM(status='sent') sent_count,
                SUM(status='failed') failed_count,
                SUM(status='cancelled') cancelled_count,
                COUNT(*) total
             FROM sms_queue WHERE batch_id=?"
        );
        $st->execute([$batchId]);
        $c = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $pending = (int) ($c['pending_count'] ?? 0);
        $sent = (int) ($c['sent_count'] ?? 0);
        $failed = (int) ($c['failed_count'] ?? 0);
        $cancelled = (int) ($c['cancelled_count'] ?? 0);
        $status = 'queued';
        if ($pending === 0 && ($sent + $failed + $cancelled) > 0) {
            $status = $failed > 0 && $sent === 0 ? 'failed' : 'completed';
        } elseif ($sent > 0 || $failed > 0) {
            $status = 'processing';
        }
        $this->db->prepare(
            'UPDATE sms_batches SET pending_count=?, sent_count=?, failed_count=?, cancelled_count=?, status=? WHERE id=?'
        )->execute([$pending, $sent, $failed, $cancelled, $status, $batchId]);
        return $this->find($batchId) ?? [];
    }

    public function cancel(int $batchId, int $adminId): array
    {
        $batch = $this->find($batchId);
        if (!$batch) {
            return ['ok' => false, 'message' => 'ارسال گروهی یافت نشد.'];
        }
        $this->db->prepare(
            "UPDATE sms_queue SET status='cancelled', last_error='لغو توسط مدیر'
             WHERE batch_id=? AND status IN ('pending','retrying')"
        )->execute([$batchId]);
        $this->refreshStats($batchId);
        $this->db->prepare("UPDATE sms_batches SET status='cancelled' WHERE id=?")->execute([$batchId]);
        audit('sms.batch_cancel', 'sms_batches', $batchId, ['admin' => $adminId]);
        return ['ok' => true, 'message' => 'ارسال زمان‌بندی‌شده لغو شد.'];
    }

    public function retryFailed(int $batchId, int $adminId): array
    {
        $batch = $this->find($batchId);
        if (!$batch) {
            return ['ok' => false, 'message' => 'ارسال گروهی یافت نشد.'];
        }
        $st = $this->db->prepare("SELECT * FROM sms_queue WHERE batch_id=? AND status='failed'");
        $st->execute([$batchId]);
        $n = 0;
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $this->db->prepare(
                "UPDATE sms_queue SET status='pending', scheduled_at=NOW(), processing_at=NULL, last_error=NULL, attempts=0 WHERE id=?"
            )->execute([(int) $row['id']]);
            $n++;
        }
        $this->refreshStats($batchId);
        audit('sms.batch_retry', 'sms_batches', $batchId, ['retried' => $n, 'admin' => $adminId]);
        return ['ok' => true, 'message' => $n . ' پیام ناموفق دوباره در صف قرار گرفت.', 'retried' => $n];
    }

    /** @return array{items:list<array<string,mixed>>,total:int,page:int,pages:int} */
    public function listBatches(int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(10, min(50, $perPage));
        $total = (int) $this->db->query('SELECT COUNT(*) FROM sms_batches')->fetchColumn();
        $off = ($page - 1) * $perPage;
        $items = $this->db->query(
            "SELECT b.*, CONCAT(u.first_name,' ',u.last_name) admin_name
             FROM sms_batches b LEFT JOIN admin_users u ON u.id=b.admin_user_id
             ORDER BY b.id DESC LIMIT {$perPage} OFFSET {$off}"
        )->fetchAll(PDO::FETCH_ASSOC);
        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /** @return array{items:list<array<string,mixed>>,total:int,page:int,pages:int} */
    public function batchQueueItems(int $batchId, int $page = 1, int $perPage = 40, ?string $status = null): array
    {
        $page = max(1, $page);
        $perPage = max(10, min(100, $perPage));
        $where = ['q.batch_id=?'];
        $params = [$batchId];
        if ($status) {
            $where[] = 'q.status=?';
            $params[] = $status;
        }
        $sqlWhere = implode(' AND ', $where);
        $cnt = $this->db->prepare("SELECT COUNT(*) FROM sms_queue q WHERE {$sqlWhere}");
        $cnt->execute($params);
        $total = (int) $cnt->fetchColumn();
        $off = ($page - 1) * $perPage;
        $st = $this->db->prepare(
            "SELECT q.*, CONCAT(p.first_name,' ',p.last_name) patient_name, p.file_number
             FROM sms_queue q LEFT JOIN patients p ON p.id=q.patient_id
             WHERE {$sqlWhere}
             ORDER BY q.id ASC LIMIT {$perPage} OFFSET {$off}"
        );
        $st->execute($params);
        return [
            'items' => $st->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / max(1, $perPage))),
        ];
    }

    /** @param array<string, mixed> $p */
    private function apptVars(array $p): ?array
    {
        if (empty($p['starts_at']) && empty($p['appointment_id'])) {
            return null;
        }
        return [
            'starts_at' => $p['starts_at'] ?? null,
            'doctor_name' => $p['doctor_name'] ?? '',
            'service_name' => $p['service_name'] ?? '',
        ];
    }
}
