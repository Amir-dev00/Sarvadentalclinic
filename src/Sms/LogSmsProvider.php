<?php

declare(strict_types=1);

namespace Sarva\Sms;

use Sarva\Interfaces\SmsProviderInterface;

final class LogSmsProvider implements SmsProviderInterface
{
    public function sendMessage(string $mobile, string $message): array
    {
        return $this->log('message', $mobile, $message);
    }

    public function sendOtp(string $mobile, string $code): array
    {
        $message = "کد تأیید کلینیک دندانپزشکی سروا: {$code}";
        return $this->log('otp', $mobile, $message, ['code' => $code]);
    }

    public function sendAppointmentReminder(string $mobile, string $message): array
    {
        return $this->log('reminder', $mobile, $message);
    }

    /** @param array<string, mixed> $extra */
    private function log(string $type, string $mobile, string $message, array $extra = []): array
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $line = json_encode([
            'at' => date('c'),
            'type' => $type,
            'mobile' => $mobile,
            'message' => $message,
            'extra' => $extra,
        ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
        file_put_contents($dir . '/sms.log', $line, FILE_APPEND);
        return ['ok' => true, 'provider' => 'log', 'message_id' => uniqid('log_', true)];
    }
}
