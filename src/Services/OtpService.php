<?php

declare(strict_types=1);

namespace Sarva\Services;

use PDO;
use Sarva\Sms\SmsManager;

/**
 * Transactional OTP: immediate SMS.ir Verify send (never sms_queue / cron).
 */
final class OtpService
{
    private const PURPOSE = 'login';

    public function __construct(private readonly PDO $db)
    {
    }

    public function request(string $mobile, string $ip): array
    {
        $requestId = bin2hex(random_bytes(6));
        $tReceived = hrtime(true);
        $receivedAt = date('c');

        $mobile = normalize_mobile($mobile);
        if ($mobile === null) {
            return ['ok' => false, 'message' => 'شماره موبایل معتبر نیست.'];
        }

        $cooldown = (int) config('app.otp_resend_cooldown', 60);
        $stmt = $this->db->prepare(
            'SELECT created_at FROM otp_codes WHERE mobile = :m AND purpose = :p AND consumed_at IS NULL
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['m' => $mobile, 'p' => self::PURPOSE]);
        $last = $stmt->fetchColumn();
        if ($last && (time() - strtotime((string) $last)) < $cooldown) {
            $wait = $cooldown - (time() - strtotime((string) $last));
            return ['ok' => false, 'message' => "لطفاً {$wait} ثانیه دیگر دوباره تلاش کنید.", 'retry_after' => $wait];
        }

        $mobileHourlyLimit = (int) config('app.otp_max_requests_per_hour', 20);
        $rate = $this->db->prepare(
            'SELECT COUNT(*) FROM otp_codes WHERE mobile = :m AND created_at > (NOW() - INTERVAL 1 HOUR)'
        );
        $rate->execute(['m' => $mobile]);
        if ((int) $rate->fetchColumn() >= $mobileHourlyLimit) {
            return ['ok' => false, 'message' => 'تعداد درخواست‌ها بیش از حد مجاز است. بعداً تلاش کنید.'];
        }

        if ($ip !== '') {
            $ipHourlyLimit = (int) config('app.otp_max_requests_per_ip_hour', 100);
            $ipRate = $this->db->prepare(
                'SELECT COUNT(*) FROM otp_codes WHERE ip_address = :ip AND created_at > (NOW() - INTERVAL 1 HOUR)'
            );
            $ipRate->execute(['ip' => $ip]);
            if ((int) $ipRate->fetchColumn() >= $ipHourlyLimit) {
                return ['ok' => false, 'message' => 'تعداد درخواست‌ها از این شبکه بیش از حد مجاز است. بعداً تلاش کنید.'];
            }
        }

        $tGen0 = hrtime(true);
        $code = (string) random_int(100000, 999999);
        $generateMs = (int) round((hrtime(true) - $tGen0) / 1_000_000);
        $generatedAt = date('c');
        $ttl = (int) config('app.otp_ttl', 300);

        // Send FIRST — do not invalidate a still-valid OTP until provider accepts.
        $smsStartedAt = date('c');
        $tSms0 = hrtime(true);
        try {
            $sms = SmsManager::make()->sendOtp($mobile, $code);
        } catch (\Throwable) {
            $smsApiMs = (int) round((hrtime(true) - $tSms0) / 1_000_000);
            $totalMs = (int) round((hrtime(true) - $tReceived) / 1_000_000);
            $this->writeTimingLine([
                'id' => $requestId,
                'mobile' => $this->maskMobile($mobile),
                'request_received_at' => $receivedAt,
                'otp_generated_at' => $generatedAt,
                'otp_saved_at' => null,
                'sms_request_started_at' => $smsStartedAt,
                'sms_provider_response_at' => date('c'),
                'request_finished_at' => date('c'),
                'generate_ms' => $generateMs,
                'database_ms' => 0,
                'sms_api_ms' => $smsApiMs,
                'total_ms' => $totalMs,
                'provider_status' => 'exception',
                'queue' => false,
            ]);
            $this->logSms($mobile, false, [
                'ok' => false,
                'provider' => SmsManager::driver(),
                'error' => 'ارسال پیامک با خطای غیرمنتظره مواجه شد.',
                'smsir_api_ms' => $smsApiMs,
                'app_total_ms' => $totalMs,
            ]);
            return ['ok' => false, 'message' => 'ارسال کد تأیید انجام نشد. لطفاً دوباره تلاش کنید.'];
        }

        $smsApiMs = (int) ($sms['duration_ms'] ?? (int) round((hrtime(true) - $tSms0) / 1_000_000));
        $smsResponseAt = date('c');
        $ok = (bool) ($sms['ok'] ?? false);

        if (!$ok) {
            $totalMs = (int) round((hrtime(true) - $tReceived) / 1_000_000);
            $this->writeTimingLine([
                'id' => $requestId,
                'mobile' => $this->maskMobile($mobile),
                'request_received_at' => $receivedAt,
                'otp_generated_at' => $generatedAt,
                'otp_saved_at' => null,
                'sms_request_started_at' => $smsStartedAt,
                'sms_provider_response_at' => $smsResponseAt,
                'request_finished_at' => date('c'),
                'generate_ms' => $generateMs,
                'database_ms' => 0,
                'sms_api_ms' => $smsApiMs,
                'total_ms' => $totalMs,
                'provider_status' => 'failed',
                'http' => $sms['http'] ?? null,
                'queue' => false,
            ]);
            $this->logSms($mobile, false, $sms + [
                'smsir_api_ms' => $smsApiMs,
                'app_total_ms' => $totalMs,
            ]);
            return [
                'ok' => false,
                'message' => $this->friendlySmsError($sms),
            ];
        }

        // Provider accepted — now persist (HMAC is fast for short-lived OTP).
        $tDb0 = hrtime(true);
        try {
            $hash = $this->hashOtp($code, $mobile);
            $this->db->prepare(
                "UPDATE otp_codes SET consumed_at = NOW()
                 WHERE mobile = :m AND purpose = :p AND consumed_at IS NULL"
            )->execute(['m' => $mobile, 'p' => self::PURPOSE]);

            $ins = $this->db->prepare(
                'INSERT INTO otp_codes (mobile, code_hash, purpose, max_attempts, expires_at, ip_address)
                 VALUES (:m, :h, :p, :max, DATE_ADD(NOW(), INTERVAL :ttl SECOND), :ip)'
            );
            $ins->bindValue('m', $mobile);
            $ins->bindValue('h', $hash);
            $ins->bindValue('p', self::PURPOSE);
            $ins->bindValue('max', (int) config('app.otp_max_attempts', 10), PDO::PARAM_INT);
            $ins->bindValue('ttl', $ttl, PDO::PARAM_INT);
            $ins->bindValue('ip', $ip !== '' ? $ip : null);
            $ins->execute();
        } catch (\Throwable) {
            $databaseMs = (int) round((hrtime(true) - $tDb0) / 1_000_000);
            $totalMs = (int) round((hrtime(true) - $tReceived) / 1_000_000);
            $this->writeTimingLine([
                'id' => $requestId,
                'mobile' => $this->maskMobile($mobile),
                'request_received_at' => $receivedAt,
                'otp_generated_at' => $generatedAt,
                'otp_saved_at' => null,
                'sms_request_started_at' => $smsStartedAt,
                'sms_provider_response_at' => $smsResponseAt,
                'request_finished_at' => date('c'),
                'generate_ms' => $generateMs,
                'database_ms' => $databaseMs,
                'sms_api_ms' => $smsApiMs,
                'total_ms' => $totalMs,
                'provider_status' => 'success_db_failed',
                'queue' => false,
            ]);
            return ['ok' => false, 'message' => 'خطا در ذخیره کد تأیید. دوباره تلاش کنید.'];
        }

        $databaseMs = (int) round((hrtime(true) - $tDb0) / 1_000_000);
        $savedAt = date('c');
        $totalMs = (int) round((hrtime(true) - $tReceived) / 1_000_000);

        $this->writeTimingLine([
            'id' => $requestId,
            'mobile' => $this->maskMobile($mobile),
            'request_received_at' => $receivedAt,
            'otp_generated_at' => $generatedAt,
            'otp_saved_at' => $savedAt,
            'sms_request_started_at' => $smsStartedAt,
            'sms_provider_response_at' => $smsResponseAt,
            'request_finished_at' => date('c'),
            'generate_ms' => $generateMs,
            'database_ms' => $databaseMs,
            'sms_api_ms' => $smsApiMs,
            'total_ms' => $totalMs,
            'provider_status' => 'success',
            'message_id' => $sms['message_id'] ?? null,
            'queue' => false,
        ]);

        $this->logSms($mobile, true, $sms + [
            'smsir_api_ms' => $smsApiMs,
            'app_total_ms' => $totalMs,
            'database_ms' => $databaseMs,
            'generate_ms' => $generateMs,
        ]);

        $payload = [
            'ok' => true,
            'message' => 'کد تأیید ارسال شد.',
            'expires_in' => $ttl,
            'resend_in' => $cooldown,
        ];

        $driver = strtolower((string) (config('sms.driver') ?: ($_ENV['SMS_DRIVER'] ?? 'log')));
        if (config('app.debug') && $driver === 'log') {
            $payload['dev_code'] = $code;
        }

        return $payload;
    }

    public function verify(string $mobile, string $code): array
    {
        $mobile = normalize_mobile($mobile);
        if ($mobile === null) {
            return ['ok' => false, 'message' => 'شماره موبایل معتبر نیست.'];
        }

        $code = preg_replace('/\D+/', '', normalize_digits($code)) ?? '';
        if ($code === '' || strlen($code) !== 6) {
            return ['ok' => false, 'message' => 'کد تأیید باید ۶ رقم باشد.'];
        }

        $stmt = $this->db->prepare(
            'SELECT * FROM otp_codes WHERE mobile = :m AND purpose = :p AND consumed_at IS NULL
             ORDER BY id DESC LIMIT 1 FOR UPDATE'
        );

        try {
            $this->db->beginTransaction();
            $stmt->execute(['m' => $mobile, 'p' => self::PURPOSE]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $this->db->rollBack();
                return ['ok' => false, 'message' => 'کد معتبری یافت نشد. دوباره درخواست کنید.'];
            }
            if (strtotime((string) $row['expires_at']) < time()) {
                $this->db->prepare('UPDATE otp_codes SET consumed_at = NOW() WHERE id = :id')
                    ->execute(['id' => $row['id']]);
                $this->db->commit();
                return ['ok' => false, 'message' => 'کد تأیید منقضی شده است. کد جدید دریافت کنید.'];
            }
            if ((int) $row['attempts'] >= (int) $row['max_attempts']) {
                $this->db->prepare('UPDATE otp_codes SET consumed_at = NOW() WHERE id = :id')
                    ->execute(['id' => $row['id']]);
                $this->db->commit();
                return ['ok' => false, 'message' => 'تعداد تلاش‌ها به پایان رسیده است.'];
            }
            if (!$this->verifyOtpHash($code, $mobile, (string) $row['code_hash'])) {
                $upd = $this->db->prepare('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = :id');
                $upd->execute(['id' => $row['id']]);
                $attempts = (int) $row['attempts'] + 1;
                $max = (int) $row['max_attempts'];
                if ($attempts >= $max) {
                    $this->db->prepare('UPDATE otp_codes SET consumed_at = NOW() WHERE id = :id')
                        ->execute(['id' => $row['id']]);
                }
                $this->db->commit();
                return ['ok' => false, 'message' => 'کد واردشده صحیح نیست.'];
            }

            $this->db->prepare('UPDATE otp_codes SET consumed_at = NOW() WHERE id = :id')
                ->execute(['id' => $row['id']]);
            $this->db->commit();
            return ['ok' => true, 'mobile' => $mobile];
        } catch (\Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['ok' => false, 'message' => 'خطا در تأیید کد. دوباره تلاش کنید.'];
        }
    }

    private function hashOtp(string $code, string $mobile): string
    {
        $key = (string) (config('app.key') ?: ($_ENV['APP_KEY'] ?? 'sarva-otp'));
        return 'hmac:' . hash_hmac('sha256', $code . '|' . $mobile, $key);
    }

    private function verifyOtpHash(string $code, string $mobile, string $stored): bool
    {
        if (str_starts_with($stored, 'hmac:')) {
            $expected = $this->hashOtp($code, $mobile);
            return hash_equals($expected, $stored);
        }
        // Legacy bcrypt rows (pre-HMAC)
        return password_verify($code, $stored);
    }

    /** @param array<string, mixed> $sms */
    private function friendlySmsError(array $sms): string
    {
        $error = mb_strtolower((string) ($sms['error'] ?? ''));
        $http = (int) ($sms['http'] ?? 0);
        if ($http === 401 || $http === 403 || str_contains($error, 'کلید') || str_contains($error, 'api')) {
            return 'ارسال کد تأیید انجام نشد. لطفاً دوباره تلاش کنید.';
        }
        if (str_contains($error, 'قالب') || str_contains($error, 'template')) {
            return 'ارسال کد تأیید انجام نشد. لطفاً دوباره تلاش کنید.';
        }
        if (str_contains($error, 'اتصال') || str_contains($error, 'timeout') || $http === 0) {
            return 'ارسال کد تأیید انجام نشد. لطفاً دوباره تلاش کنید.';
        }
        return 'ارسال کد تأیید انجام نشد. لطفاً دوباره تلاش کنید.';
    }

    /**
     * @param array<string, mixed> $result
     */
    private function logSms(string $mobile, bool $ok, array $result): void
    {
        try {
            $safe = $result;
            unset($safe['api_key'], $safe['headers'], $safe['request']);
            $body = $ok ? 'OTP verify template dispatched (immediate)' : 'OTP send failed';
            $stmt = $this->db->prepare(
                'INSERT INTO sms_logs (mobile, message_type, message_body, provider, provider_message_id, status, provider_response, sent_at)
                 VALUES (:m, :t, :b, :p, :mid, :s, :r, NOW())'
            );
            $stmt->execute([
                'm' => $mobile,
                't' => 'otp',
                'b' => $body,
                'p' => $result['provider'] ?? SmsManager::driver(),
                'mid' => $result['message_id'] ?? null,
                's' => $ok ? 'sent' : 'failed',
                'r' => json_encode([
                    'ok' => $ok,
                    'provider' => $safe['provider'] ?? null,
                    'message_id' => $safe['message_id'] ?? null,
                    'error' => $ok ? null : ($safe['error'] ?? null),
                    'http' => $safe['http'] ?? null,
                    'status_code' => $safe['status_code'] ?? null,
                    'smsir_api_ms' => $safe['smsir_api_ms'] ?? ($safe['duration_ms'] ?? null),
                    'app_total_ms' => $safe['app_total_ms'] ?? null,
                    'generate_ms' => $safe['generate_ms'] ?? null,
                    'database_ms' => $safe['database_ms'] ?? null,
                    'queued' => false,
                ], JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable) {
        }
    }

    /** @param array<string, mixed> $data */
    private function writeTimingLine(array $data): void
    {
        try {
            $root = dirname(__DIR__, 2);
            $dir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $line = sprintf(
                "OTP_REQUEST id=%s mobile=%s generate_ms=%d database_ms=%d sms_api_ms=%d total_ms=%d provider_status=%s queue=0\n",
                (string) ($data['id'] ?? ''),
                (string) ($data['mobile'] ?? ''),
                (int) ($data['generate_ms'] ?? 0),
                (int) ($data['database_ms'] ?? 0),
                (int) ($data['sms_api_ms'] ?? 0),
                (int) ($data['total_ms'] ?? 0),
                (string) ($data['provider_status'] ?? 'unknown')
            );
            $json = json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
            @file_put_contents(
                $dir . DIRECTORY_SEPARATOR . 'otp-timing.log',
                $line . $json,
                FILE_APPEND | LOCK_EX
            );
        } catch (\Throwable) {
        }
    }

    private function maskMobile(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';
        if (strlen($digits) < 8) {
            return '***';
        }
        return substr($digits, 0, 2) . '*****' . substr($digits, -4);
    }
}
