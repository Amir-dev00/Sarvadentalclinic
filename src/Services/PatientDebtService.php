<?php

declare(strict_types=1);

namespace Sarva\Services;

use PDO;
use Sarva\Sms\LogSmsProvider;
use Sarva\Sms\SmsIrProvider;
use Sarva\Sms\SmsManager;

/**
 * Patient outstanding debt + debt-reminder SMS (SMS.ir Verify pattern).
 *
 * Provider-side pattern variable: #DEBT_AMOUNT#
 * API parameter name (no hashes): DEBT_AMOUNT
 */
final class PatientDebtService
{
    public const MESSAGE_TYPE = 'debt_reminder';
    public const PATTERN_TEXT = "کلینیک دندانپزشکی سروا (دکتر زمان زاده)\nلطفا هرچه سریعتر جهت پرداخت بدهی خود اقدام فرمایید.\nمبلغ بدهی : #DEBT_AMOUNT#\nبا تشکر";

    public function __construct(private readonly PDO $db)
    {
        $this->ensureSchema();
    }

    /** Ensure debt columns exist without wiping data. */
    public function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $cols = $this->db->query('SHOW COLUMNS FROM patients')->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $have = array_flip(array_map('strval', $cols));
            if (!isset($have['debt_amount'])) {
                $this->db->exec(
                    "ALTER TABLE patients ADD COLUMN debt_amount DECIMAL(14,0) NOT NULL DEFAULT 0 COMMENT 'Outstanding patient debt (IRT)' AFTER notes"
                );
            }
            $cols2 = $this->db->query('SHOW COLUMNS FROM patients')->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $have2 = array_flip(array_map('strval', $cols2));
            if (!isset($have2['debt_reminder_sent_at'])) {
                $anchor = isset($have2['debt_amount']) ? 'debt_amount' : 'notes';
                $this->db->exec(
                    "ALTER TABLE patients ADD COLUMN debt_reminder_sent_at DATETIME NULL DEFAULT NULL COMMENT 'Last automatic debt reminder' AFTER `{$anchor}`"
                );
            }
        } catch (\Throwable) {
        }

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO site_settings (`key`, `value`, group_name) VALUES (?,?,?) ON DUPLICATE KEY UPDATE `key`=`key`'
            );
            $stmt->execute(['debt_sms_auto_enabled', '0', 'sms']);
            $stmt->execute(['debt_sms_interval_days', '7', 'sms']);
        } catch (\Throwable) {
        }
    }

    public function getDebtAmount(int $patientId): float
    {
        $st = $this->db->prepare('SELECT debt_amount FROM patients WHERE id=? LIMIT 1');
        $st->execute([$patientId]);
        $raw = $st->fetchColumn();
        if ($raw === false) {
            return 0.0;
        }
        return max(0.0, (float) $raw);
    }

    /**
     * Parse and validate debt input. Rejects negatives and non-numeric values.
     *
     * @return array{ok:bool,amount?:float,message?:string}
     */
    public function parseDebtInput(mixed $raw): array
    {
        $str = trim(normalize_digits((string) $raw));
        $str = str_replace([',', ' ', '٬', '،'], '', $str);
        if ($str === '') {
            return ['ok' => true, 'amount' => 0.0];
        }
        if (!preg_match('/^\d+(\.\d+)?$/', $str)) {
            return ['ok' => false, 'message' => 'مبلغ بدهی باید یک عدد معتبر باشد.'];
        }
        $amount = (float) $str;
        if ($amount < 0) {
            return ['ok' => false, 'message' => 'مبلغ بدهی نمی‌تواند منفی باشد.'];
        }
        if ($amount > 99999999999999) {
            return ['ok' => false, 'message' => 'مبلغ بدهی بیش از حد مجاز است.'];
        }
        return ['ok' => true, 'amount' => round($amount, 0)];
    }

    /**
     * @return array{ok:bool,message:string,amount?:float}
     */
    public function updateDebt(int $patientId, mixed $rawAmount, ?int $adminId = null): array
    {
        $parsed = $this->parseDebtInput($rawAmount);
        if (!$parsed['ok']) {
            return ['ok' => false, 'message' => (string) ($parsed['message'] ?? 'مبلغ نامعتبر است.')];
        }
        $amount = (float) $parsed['amount'];

        $st = $this->db->prepare('SELECT id FROM patients WHERE id=? AND deleted_at IS NULL LIMIT 1');
        $st->execute([$patientId]);
        if (!$st->fetchColumn()) {
            return ['ok' => false, 'message' => 'بیمار یافت نشد.'];
        }

        $this->db->prepare('UPDATE patients SET debt_amount=? WHERE id=?')->execute([$amount, $patientId]);
        try {
            audit('patient.debt_update', 'patients', $patientId, [
                'debt_amount' => $amount,
                'admin_id' => $adminId,
            ]);
        } catch (\Throwable) {
        }

        return [
            'ok' => true,
            'message' => 'مبلغ بدهی ذخیره شد.',
            'amount' => $amount,
        ];
    }

    /** Format numeric debt for display / SMS.ir #DEBT_AMOUNT# value. */
    public static function formatDebtForSms(float|int|string $amount): string
    {
        $n = (int) round((float) $amount);
        if ($n < 0) {
            $n = 0;
        }
        return to_persian_digits((string) $n);
    }

    public static function templateId(): int
    {
        return SmsService::smsIrTemplateIdForMessageType(self::MESSAGE_TYPE);
    }

    /**
     * Send debt reminder using the patient's CURRENT debt_amount.
     *
     * @param 'manual'|'automation'|'automatic' $source  Prefer existing log source "automation".
     * @return array{ok:bool,sent:bool,skipped?:bool,reason?:string,message:string,debt?:float}
     */
    public function sendReminder(int $patientId, string $source = 'manual', ?int $adminId = null): array
    {
        // Normalize aliases; sms_logs filter uses "automation".
        if ($source === 'automatic' || $source === 'auto') {
            $source = 'automation';
        }
        $source = $source === 'automation' ? 'automation' : 'manual';

        $st = $this->db->prepare(
            'SELECT id, mobile, first_name, last_name, debt_amount, debt_reminder_sent_at, deleted_at
             FROM patients WHERE id=? LIMIT 1'
        );
        $st->execute([$patientId]);
        $patient = $st->fetch(PDO::FETCH_ASSOC);
        if (!$patient || !empty($patient['deleted_at'])) {
            return ['ok' => false, 'sent' => false, 'message' => 'بیمار یافت نشد.'];
        }

        $debt = max(0.0, (float) ($patient['debt_amount'] ?? 0));
        if ($debt <= 0) {
            return [
                'ok' => false,
                'sent' => false,
                'skipped' => true,
                'reason' => 'no_debt',
                'message' => 'برای این بیمار بدهی ثبت نشده است.',
                'debt' => 0.0,
            ];
        }

        $mobile = normalize_mobile((string) ($patient['mobile'] ?? ''));
        if ($mobile === null) {
            return [
                'ok' => false,
                'sent' => false,
                'skipped' => true,
                'reason' => 'invalid_mobile',
                'message' => 'شماره موبایل بیمار معتبر نیست.',
                'debt' => $debt,
            ];
        }

        if ($source === 'automation' && !$this->isAutomaticEligible($patient)) {
            return [
                'ok' => true,
                'sent' => false,
                'skipped' => true,
                'reason' => 'cooldown',
                'message' => 'فاصله تکرار یادآوری بدهی هنوز سپری نشده است.',
                'debt' => $debt,
            ];
        }

        $templateId = self::templateId();
        if ($templateId <= 0) {
            SmsTemplateRenderer::logEvent('smsir_debt_reminder_template_missing', [
                'patient_id' => $patientId,
                'message_type' => self::MESSAGE_TYPE,
            ]);
            return [
                'ok' => false,
                'sent' => false,
                'message' => 'شناسه قالب پیامک بدهی (SMSIR_DEBT_REMINDER_TEMPLATE_ID) تنظیم نشده است.',
                'debt' => $debt,
            ];
        }

        if ((string) setting('sms_enabled', '1') === '0') {
            return [
                'ok' => false,
                'sent' => false,
                'message' => 'سیستم پیامک غیرفعال است.',
                'debt' => $debt,
            ];
        }

        $formattedDebt = self::formatDebtForSms($debt);
        $params = ['DEBT_AMOUNT' => $formattedDebt];
        $parameterList = SmsTemplateRenderer::toSmsIrParameterList($params);
        $summary = sprintf(
            'یادآوری بدهی (SMS.ir #%d): مبلغ %s',
            $templateId,
            $formattedDebt
        );

        $provider = SmsManager::make();
        if ($provider instanceof SmsIrProvider || $provider instanceof LogSmsProvider) {
            $send = $provider->sendTemplateNow($mobile, $templateId, $parameterList);
        } else {
            $send = $provider->sendTemplate($mobile, $templateId, $parameterList);
        }

        $ok = !empty($send['ok']);
        $messageId = isset($send['message_id']) ? (string) $send['message_id'] : null;
        $this->writeSmsLog([
            'patient_id' => $patientId,
            'mobile' => $mobile,
            'admin_user_id' => $adminId,
            'message' => $summary,
            'message_type' => self::MESSAGE_TYPE,
            'source' => $source,
            'provider_template_id' => $templateId,
            'template_parameters' => $params,
            'rendered_preview' => self::renderPreview($formattedDebt),
        ], $send, $ok);

        if ($ok && $source === 'automation') {
            $this->db->prepare('UPDATE patients SET debt_reminder_sent_at=NOW() WHERE id=?')->execute([$patientId]);
        }

        SmsTemplateRenderer::logEvent('DEBT_REMINDER_SMS', [
            'patient_id' => $patientId,
            'source' => $source,
            'ok' => $ok,
            'debt_amount' => $debt,
            'formatted' => $formattedDebt,
            'template_id' => $templateId,
            'provider' => $send['provider'] ?? SmsManager::driver(),
            'message_id' => $messageId,
        ]);

        if ($ok) {
            return [
                'ok' => true,
                'sent' => true,
                'message' => $source === 'automation'
                    ? 'پیامک بدهی به‌صورت خودکار ارسال شد.'
                    : 'پیامک بدهی ارسال شد.',
                'debt' => $debt,
            ];
        }

        return [
            'ok' => false,
            'sent' => false,
            'message' => (string) ($send['error'] ?? 'ارسال پیامک بدهی ناموفق بود.'),
            'debt' => $debt,
        ];
    }

    /**
     * Bulk manual send for selected patient IDs.
     *
     * @param list<int> $patientIds
     * @return array{sent:int,failed:int,no_debt:int,invalid_mobile:int,missing:int,messages:list<string>}
     */
    public function sendBulkManual(array $patientIds, ?int $adminId = null): array
    {
        $summary = [
            'sent' => 0,
            'failed' => 0,
            'no_debt' => 0,
            'invalid_mobile' => 0,
            'missing' => 0,
            'messages' => [],
        ];
        $seen = [];
        foreach ($patientIds as $rawId) {
            $id = (int) $rawId;
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $result = $this->sendReminder($id, 'manual', $adminId);
            if (!empty($result['sent'])) {
                $summary['sent']++;
                continue;
            }
            $reason = (string) ($result['reason'] ?? '');
            if ($reason === 'no_debt') {
                $summary['no_debt']++;
            } elseif ($reason === 'invalid_mobile') {
                $summary['invalid_mobile']++;
            } elseif (($result['message'] ?? '') === 'بیمار یافت نشد.') {
                $summary['missing']++;
            } else {
                $summary['failed']++;
            }
        }
        return $summary;
    }

    public static function isAutomationEnabled(): bool
    {
        return (string) setting('debt_sms_auto_enabled', '0') === '1';
    }

    public static function intervalDays(): int
    {
        $days = (int) setting('debt_sms_interval_days', '7');
        return max(1, min(365, $days > 0 ? $days : 7));
    }

    /**
     * Cron: send automatic debt reminders for eligible patients.
     * Disabled by default via site setting.
     *
     * @return array{checked:int,sent:int,skipped:int,failed:int}
     */
    public function processAutomaticReminders(int $limit = 40): array
    {
        $stats = ['checked' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0];
        if (!self::isAutomationEnabled()) {
            return $stats;
        }
        if ((string) setting('sms_enabled', '1') === '0') {
            return $stats;
        }
        if (self::templateId() <= 0) {
            return $stats;
        }

        $limit = max(1, min(100, $limit));
        $days = self::intervalDays();
        $stmt = $this->db->prepare(
            "SELECT id FROM patients
             WHERE deleted_at IS NULL
               AND is_active=1
               AND debt_amount > 0
               AND (
                    debt_reminder_sent_at IS NULL
                    OR debt_reminder_sent_at <= DATE_SUB(NOW(), INTERVAL {$days} DAY)
               )
             ORDER BY debt_reminder_sent_at IS NOT NULL ASC, debt_reminder_sent_at ASC, id ASC
             LIMIT {$limit}"
        );
        $stmt->execute();
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        foreach ($ids as $id) {
            $stats['checked']++;
            $result = $this->sendReminder((int) $id, 'automation', null);
            if (!empty($result['sent'])) {
                $stats['sent']++;
            } elseif (!empty($result['skipped'])) {
                $stats['skipped']++;
            } else {
                $stats['failed']++;
            }
        }

        return $stats;
    }

    /** Preview of the provider-rendered body with the current debt value. */
    public static function renderPreview(string $formattedDebt): string
    {
        return str_replace('#DEBT_AMOUNT#', $formattedDebt, self::PATTERN_TEXT);
    }

    /** @param array<string, mixed> $patient */
    private function isAutomaticEligible(array $patient): bool
    {
        if (!self::isAutomationEnabled()) {
            return false;
        }
        $debt = max(0.0, (float) ($patient['debt_amount'] ?? 0));
        if ($debt <= 0) {
            return false;
        }
        $last = $patient['debt_reminder_sent_at'] ?? null;
        if ($last === null || $last === '') {
            return true;
        }
        $ts = strtotime((string) $last);
        if ($ts === false) {
            return true;
        }
        $cooldownSeconds = self::intervalDays() * 86400;
        return (time() - $ts) >= $cooldownSeconds;
    }

    /**
     * @param array{
     *   patient_id:int,
     *   mobile:string,
     *   admin_user_id:?int,
     *   message:string,
     *   message_type:string,
     *   source:string,
     *   provider_template_id:int,
     *   template_parameters:array<string,string>,
     *   rendered_preview?:string
     * } $meta
     * @param array<string, mixed> $send
     */
    private function writeSmsLog(array $meta, array $send, bool $ok): void
    {
        $payload = [
            'provider' => $send['provider'] ?? SmsManager::driver(),
            'message_type' => $meta['message_type'],
            'source' => $meta['source'],
            'message_channel' => $send['message_channel'] ?? 'verify',
            'provider_template_id' => $meta['provider_template_id'],
            'template_parameters' => $meta['template_parameters'],
            'rendered_preview' => $meta['rendered_preview'] ?? null,
            'provider_status' => $send['provider_status'] ?? ($send['status_code'] ?? ($ok ? 1 : 0)),
            'message_id' => $send['message_id'] ?? null,
            'ok' => $ok,
            'error' => $ok ? null : ($send['error'] ?? null),
            'http' => $send['http'] ?? null,
            'duration_ms' => $send['duration_ms'] ?? null,
        ];

        try {
            $this->db->prepare(
                'INSERT INTO sms_logs
                 (patient_id, appointment_id, template_id, automation_rule_id, queue_id, mobile, message_type, source,
                  admin_user_id, message_body, provider, provider_message_id, status, attempts, last_error,
                  provider_response, idempotency_key, sent_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $meta['patient_id'],
                null,
                null,
                null,
                null,
                $meta['mobile'],
                $meta['message_type'],
                $meta['source'],
                $meta['admin_user_id'] ?: null,
                $meta['message'],
                $payload['provider'],
                $payload['message_id'],
                $ok ? 'sent' : 'failed',
                1,
                $ok ? null : mb_substr((string) ($send['error'] ?? ''), 0, 500),
                json_encode($payload, JSON_UNESCAPED_UNICODE),
                null,
                $ok ? date('Y-m-d H:i:s') : null,
            ]);
        } catch (\Throwable) {
        }
    }

    /** Latest debt reminder timestamp for a patient (any source). */
    public function lastDebtReminderAt(int $patientId): ?string
    {
        try {
            $st = $this->db->prepare(
                "SELECT COALESCE(sent_at, created_at) FROM sms_logs
                 WHERE patient_id=? AND message_type=? AND status='sent'
                 ORDER BY id DESC LIMIT 1"
            );
            $st->execute([$patientId, self::MESSAGE_TYPE]);
            $v = $st->fetchColumn();
            return $v !== false && $v !== null && $v !== '' ? (string) $v : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
