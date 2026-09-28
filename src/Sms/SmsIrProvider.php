<?php

declare(strict_types=1);

namespace Sarva\Sms;

use Sarva\Interfaces\SmsProviderInterface;

/**
 * Official SMS.ir REST API v1 (https://api.sms.ir/v1).
 * Auth: X-API-KEY header. Credentials never leave the server.
 */
final class SmsIrProvider implements SmsProviderInterface
{
    private const BASE = 'https://api.sms.ir/v1';

    public function sendMessage(string $mobile, string $message): array
    {
        $line = (int) (config('sms.line_number') ?: ($_ENV['SMSIR_LINE_NUMBER'] ?? 0));
        if ($line <= 0) {
            return ['ok' => false, 'provider' => 'smsir', 'error' => 'شماره خط SMS.ir تنظیم نشده است.', 'permanent' => true];
        }
        return $this->request('/send/bulk', [
            'lineNumber' => $line,
            'MessageText' => $message,
            'Mobiles' => [$mobile],
        ]);
    }

    public function sendOtp(string $mobile, string $code): array
    {
        $templateId = (int) (config('sms.otp_template_id') ?: 0);
        if ($templateId <= 0) {
            return [
                'ok' => false,
                'provider' => 'smsir',
                'error' => 'شناسه قالب OTP تنظیم نشده است.',
                'permanent' => true,
            ];
        }

        // SMS.ir Verify expects Iranian mobiles like 09xxxxxxxxx
        $normalized = normalize_mobile($mobile);
        if ($normalized === null) {
            return [
                'ok' => false,
                'provider' => 'smsir',
                'error' => 'شماره موبایل معتبر نیست.',
                'permanent' => true,
            ];
        }

        return $this->sendTemplate($normalized, $templateId, [
            ['name' => 'CODE', 'value' => $code],
        ]);
    }

    public function sendAppointmentReminder(string $mobile, string $message): array
    {
        return $this->sendMessage($mobile, $message);
    }

    public function sendTemplate(string $mobile, int $templateId, array $parameters): array
    {
        if ($templateId <= 0) {
            return ['ok' => false, 'provider' => 'smsir', 'error' => 'شناسه قالب SMS.ir تنظیم نشده است.', 'permanent' => true];
        }

        $safeParams = [];
        foreach ($parameters as $param) {
            $name = trim((string) ($param['name'] ?? ''));
            $value = trim((string) ($param['value'] ?? ''));
            if ($name === '') {
                continue;
            }
            // SMS.ir verify parameters are length-limited.
            $safeParams[] = [
                'name' => $name,
                'value' => mb_substr($value !== '' ? $value : '-', 0, 25),
            ];
        }

        return $this->request('/send/verify', [
            'mobile' => $mobile,
            'templateId' => $templateId,
            'parameters' => $safeParams,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function request(string $path, array $payload): array
    {
        $apiKey = (string) (config('sms.api_key') ?: ($_ENV['SMSIR_API_KEY'] ?? $_ENV['SMS_API_KEY'] ?? ''));
        if ($apiKey === '') {
            return ['ok' => false, 'provider' => 'smsir', 'error' => 'کلید API پیامک تنظیم نشده است.', 'permanent' => true];
        }

        $ch = curl_init(self::BASE . $path);
        if ($ch === false) {
            return ['ok' => false, 'provider' => 'smsir', 'error' => 'خطا در آماده‌سازی درخواست.', 'permanent' => false];
        }
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'X-API-KEY: ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $raw === false) {
            return ['ok' => false, 'provider' => 'smsir', 'error' => 'اتصال به SMS.ir برقرار نشد.', 'permanent' => false, 'http' => $http];
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'provider' => 'smsir', 'error' => 'پاسخ نامعتبر از SMS.ir', 'permanent' => false, 'http' => $http];
        }

        $status = (int) ($decoded['status'] ?? 0);
        $ok = $status === 1;
        $messageId = null;
        $data = $decoded['data'] ?? null;
        if (is_array($data)) {
            $messageId = $data['messageId'] ?? $data['packId'] ?? null;
            if (isset($data['messageIds'][0])) {
                $messageId = (string) $data['messageIds'][0];
            }
        }

        $permanent = in_array($status, [10, 11, 12, 13], true) || $http === 401 || $http === 403;
        // Never include request headers/API key; only provider status payload (no credentials).
        return [
            'ok' => $ok,
            'provider' => 'smsir',
            'message_id' => $messageId !== null ? (string) $messageId : null,
            'error' => $ok ? null : (string) ($decoded['message'] ?? 'ارسال ناموفق'),
            'permanent' => $permanent,
            'http' => $http,
            'status_code' => $status,
        ];
    }
}
