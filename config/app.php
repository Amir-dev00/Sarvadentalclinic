<?php

declare(strict_types=1);

return [
    'name' => $_ENV['APP_NAME'] ?? 'کلینیک دندانپزشکی سروا',
    'name_en' => $_ENV['APP_NAME_EN'] ?? 'Sarva Dental Clinic',
    'env' => $_ENV['APP_ENV'] ?? 'local',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url' => rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/'),
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Asia/Tehran',
    'key' => $_ENV['APP_KEY'] ?? '',
    'locale' => 'fa',
    'otp_length' => (int) ($_ENV['OTP_LENGTH'] ?? 5),
    'otp_ttl' => (int) ($_ENV['SMS_OTP_TTL'] ?? 120),
    'otp_resend_cooldown' => (int) ($_ENV['SMS_RESEND_COOLDOWN'] ?? 60),
    'otp_max_attempts' => (int) ($_ENV['SMS_MAX_ATTEMPTS'] ?? 5),
    'appointment_hold_minutes' => (int) ($_ENV['APPOINTMENT_HOLD_MINUTES'] ?? 15),
    'default_appointment_duration' => (int) ($_ENV['DEFAULT_APPOINTMENT_DURATION'] ?? 30),
    'session' => [
        'name' => $_ENV['SESSION_NAME'] ?? 'sarva_session',
        'lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 7200),
    ],
];
