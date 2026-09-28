<?php

declare(strict_types=1);

return [
    'driver' => $_ENV['SMS_DRIVER'] ?? 'log',
    'api_key' => $_ENV['SMSIR_API_KEY'] ?? ($_ENV['SMS_API_KEY'] ?? ''),
    'line_number' => $_ENV['SMSIR_LINE_NUMBER'] ?? ($_ENV['SMS_SENDER'] ?? ''),
    'otp_template_id' => $_ENV['SMSIR_TEMPLATE_ID'] ?? ($_ENV['SMS_OTP_TEMPLATE'] ?? ''),
    'sender' => $_ENV['SMSIR_LINE_NUMBER'] ?? ($_ENV['SMS_SENDER'] ?? ''),
    'otp_template' => $_ENV['SMS_OTP_TEMPLATE'] ?? '',
];
