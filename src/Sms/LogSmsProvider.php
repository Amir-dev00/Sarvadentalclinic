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
        $extra = ['template_id' => (int) config('sms.otp_template_id', 0)];
        // Never write OTP digits to disk in production.
        if (config('app.debug') && strtolower((string) config('app.env', 'local')) !== 'production') {
            $extra['code'] = $code;
        } else {
            $extra['code'] = '[redacted]';
        }
        return $this->log('otp', $mobile, 'OTP verify template', $extra);
    }

    public function sendAppointmentReminder(string $mobile, string $message): array
    {
        return $this->log('reminder', $mobile, $message);
    }

    public function sendTemplate(string $mobile, int $templateId, array $parameters): array
    {
        return $this->log('template', $mobile, 'verify-template', [
            'template_id' => $templateId,
            'parameters' => array_map(static function (array $p): array {
                // Never persist OTP CODE values from template params in log driver extras for otp flows.
                $name = (string) ($p['name'] ?? '');
                $value = (string) ($p['value'] ?? '');
                if (strcasecmp($name, 'CODE') === 0) {
                    $value = '[redacted]';
                }
                return ['name' => $name, 'value' => $value];
            }, $parameters),
        ]);
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
        return [
            'ok' => true,
            'provider' => 'log',
            'message_id' => uniqid('log_', true),
            'duration_ms' => 0,
        ];
    }
}
