<?php

declare(strict_types=1);

return [
    'driver' => $_ENV['SMS_DRIVER'] ?? 'log',
    'api_key' => $_ENV['SMSIR_API_KEY'] ?? ($_ENV['SMS_API_KEY'] ?? ''),
    // Shared SMS.ir sender line for bulk/automation (NOT used by OTP Verify API)
    'line_number' => (string) ($_ENV['SMSIR_LINE_NUMBER'] ?? ($_ENV['SMS_SENDER'] ?? '')),
    // Prefer SMSIR_OTP_TEMPLATE_ID; fall back to legacy SMSIR_TEMPLATE_ID / SMS_OTP_TEMPLATE
    'otp_template_id' => (int) (
        $_ENV['SMSIR_OTP_TEMPLATE_ID']
        ?? $_ENV['SMSIR_TEMPLATE_ID']
        ?? $_ENV['SMS_OTP_TEMPLATE']
        ?? 0
    ),
    // SMS.ir Verify template for automatic "tomorrow" appointment reminders
    'appointment_reminder_template_id' => (int) ($_ENV['SMSIR_APPOINTMENT_REMINDER_TEMPLATE_ID'] ?? 0),
    'sender' => (string) ($_ENV['SMSIR_LINE_NUMBER'] ?? ($_ENV['SMS_SENDER'] ?? '')),
    'otp_template' => $_ENV['SMS_OTP_TEMPLATE'] ?? '',
];
