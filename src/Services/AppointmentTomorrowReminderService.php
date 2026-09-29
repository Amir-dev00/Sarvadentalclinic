<?php

declare(strict_types=1);

namespace Sarva\Services;

use PDO;

/**
 * Legacy name only. Tomorrow reminders are scheduled solely by SmsAutomationService
 * from cron/process-sms-automation.php, once per day inside the admin send_time window.
 * This class does not select or enqueue appointments.
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
        return SmsService::smsIrTemplateIdForMessageType(self::MESSAGE_TYPE);
    }

    public function isConfigured(): bool
    {
        return $this->templateId() > 0 && $this->sms->isEnabled();
    }

    /**
     * @return array{queued:int,skipped:int,candidates:int,dry_run:bool,rows:list<array<string,mixed>>,errors:list<string>}
     */
    public function enqueueTomorrow(bool $dryRun = false, int $limit = 0, ?int $onlyAppointmentId = null): array
    {
        unset($dryRun, $limit, $onlyAppointmentId);
        SmsTemplateRenderer::logEvent('TOMORROW_REMINDER_LEGACY_SKIPPED', [
            'reason' => 'canonical_owner_sms_automation_daily_batch',
        ]);
        return [
            'queued' => 0,
            'skipped' => 0,
            'candidates' => 0,
            'dry_run' => false,
            'rows' => [],
            'errors' => ['Tomorrow reminder batch is owned by SmsAutomationService (daily send window). This service no longer enqueues automatically.'],
        ];
    }
}
