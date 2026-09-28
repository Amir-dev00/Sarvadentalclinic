<?php

declare(strict_types=1);

namespace Sarva\Services;

use PDO;
use Sarva\Sms\SmsManager;

final class OtpService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function request(string $mobile, string $ip): array
    {
        $t0 = hrtime(true);
        $this->timingLog('otp_request_received', [
            'mobile' => $this->maskMobile($mobile),
            'ip' => $ip !== '' ? $ip : null,
        ]);

        $mobile = normalize_mobile($mobile);
        if ($mobile === null) {
            return ['ok' => false, 'message' => 'شماره موبایل معتبر نیست.'];
        }

        $cooldown = (int) config('app.otp_resend_cooldown', 60);
        $stmt = $this->db->prepare(
            'SELECT created_at FROM otp_codes WHERE mobile = :m AND purpose = :p AND consumed_at IS NULL
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['m' => $mobile, 'p' => 'login']);
        $last = $stmt->fetchColumn();
        if ($last && (time() - strtotime((string) $last)) < $cooldown) {
            $wait = $cooldown - (time() - strtotime((string) $last));
            return ['ok' => false, 'message' => "لطفاً {$wait} ثانیه دیگر دوباره تلاش کنید.", 'retry_after' => $wait];
        }

        // Rate limit: max 5 OTP requests per mobile per hour
        $rate = $this->db->prepare(
            'SELECT COUNT(*) FROM otp_codes WHERE mobile = :m AND created_at > (NOW() - INTERVAL 1 HOUR)'
        );
        $rate->execute(['m' => $mobile]);
        if ((int) $rate->fetchColumn() >= 5) {
            return ['ok' => false, 'message' => 'تعداد درخواست‌ها بیش از حد مجاز است. بعداً تلاش کنید.'];
        }

        // Soft IP rate limit (shared hosting friendly, no Redis)
        if ($ip !== '') {
            $ipRate = $this->db->prepare(
                'SELECT COUNT(*) FROM otp_codes WHERE ip_address = :ip AND created_at > (NOW() - INTERVAL 1 HOUR)'
            );
            $ipRate->execute(['ip' => $ip]);
            if ((int) $ipRate->fetchColumn() >= 20) {
                return ['ok' => false, 'message' => 'تعداد درخواست‌ها از این شبکه بیش از حد مجاز است. بعداً تلاش کنید.'];
            }
        }

        // Secure 6-digit OTP — never log / never return in production responses
        $code = (string) random_int(100000, 999999);
        $ttl = (int) config('app.otp_ttl', 120);
        // Lower bcrypt cost: OTP is short-lived; default cost was adding unnecessary pre-send delay.
        $hash = password_hash($code, PASSWORD_BCRYPT, ['cost' => 8]);

        try {
            // Invalidate previous unused codes for this mobile so only the latest is valid
            $this->db->prepare(
                "UPDATE otp_codes SET consumed_at = NOW()
                 WHERE mobile = :m AND purpose = :p AND consumed_at IS NULL"
            )->execute(['m' => $mobile, 'p' => 'login']);

            $ins = $this->db->prepare(
                'INSERT INTO otp_codes (mobile, code_hash, purpose, max_attempts, expires_at, ip_address)
                 VALUES (:m, :h, :p, :max, DATE_ADD(NOW(), INTERVAL :ttl SECOND), :ip)'
            );
            $ins->bindValue('m', $mobile);
            $ins->bindValue('h', $hash);
            $ins->bindValue('p', 'login');
            $ins->bindValue('max', (int) config('app.otp_max_attempts', 5), PDO::PARAM_INT);
            $ins->bindValue('ttl', $ttl, PDO::PARAM_INT);
            $ins->bindValue('ip', $ip !== '' ? $ip : null);
            $ins->execute();
            $otpId = (int) $this->db->lastInsertId();
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'خطا در آماده‌سازی کد تأیید. دوباره تلاش کنید.'];
        }

        $preSendMs = (int) round((hrtime(true) - $t0) / 1_000_000);
        $this->timingLog('smsir_api_request_started', [
            'mobile' => $this->maskMobile($mobile),
            'template_id' => (int) config('sms.otp_template_id', 0),
            'app_pre_send_ms' => $preSendMs,
            'via' => 'direct_verify_api',
            'queue' => false,
        ]);

        try {
            // IMMEDIATE send — never uses sms_queue / cron.
            $sms = SmsManager::make()->sendOtp($mobile, $code);
        } catch (\Throwable) {
            $this->invalidateOtpRow($otpId);
            $this->timingLog('smsir_api_exception', [
                'mobile' => $this->maskMobile($mobile),
                'app_pre_send_ms' => $preSendMs,
            ]);
            $this->logSms($mobile, 'otp', false, [
                'ok' => false,
                'provider' => SmsManager::driver(),
                'error' => 'ارسال پیامک با خطای غیرمنتظره مواجه شد.',
            ]);
            return ['ok' => false, 'message' => 'ارسال پیامک ممکن نیست. لطفاً کمی بعد دوباره تلاش کنید.'];
        }

        $apiMs = (int) ($sms['duration_ms'] ?? 0);
        $totalMs = (int) round((hrtime(true) - $t0) / 1_000_000);
        $ok = (bool) ($sms['ok'] ?? false);

        $this->timingLog('smsir_api_response_received', [
            'mobile' => $this->maskMobile($mobile),
            'ok' => $ok,
            'http' => $sms['http'] ?? null,
            'status_code' => $sms['status_code'] ?? null,
            'message_id' => $sms['message_id'] ?? null,
            'app_pre_send_ms' => $preSendMs,
            'smsir_api_ms' => $apiMs,
            'app_total_ms' => $totalMs,
            // If smsir_api_ms is small but phone SMS is late → operator/network after SMS.ir accept
            'note' => 'Phone delivery after SMS.ir accept is outside our app; compare smsir_api_ms vs user-reported delay.',
        ]);

        $this->logSms($mobile, 'otp', $ok, $sms + [
            'app_pre_send_ms' => $preSendMs,
            'smsir_api_ms' => $apiMs,
            'app_total_ms' => $totalMs,
        ]);

        if (!$ok) {
            $this->invalidateOtpRow($otpId);
            return [
                'ok' => false,
                'message' => $this->friendlySmsError($sms),
            ];
        }

        $payload = [
            'ok' => true,
            'message' => 'کد تأیید ارسال شد.',
            'expires_in' => $ttl,
            'resend_in' => $cooldown,
        ];

        // Dev-only convenience: expose code when using the log driver in debug mode.
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
            $stmt->execute(['m' => $mobile, 'p' => 'login']);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $this->db->rollBack();
                return ['ok' => false, 'message' => 'کد معتبری یافت نشد. دوباره درخواست کنید.'];
            }
            if (strtotime((string) $row['expires_at']) < time()) {
                $this->db->prepare('UPDATE otp_codes SET consumed_at = NOW() WHERE id = :id')
                    ->execute(['id' => $row['id']]);
                $this->db->commit();
                return ['ok' => false, 'message' => 'کد منقضی شده است.'];
            }
            if ((int) $row['attempts'] >= (int) $row['max_attempts']) {
                $this->db->prepare('UPDATE otp_codes SET consumed_at = NOW() WHERE id = :id')
                    ->execute(['id' => $row['id']]);
                $this->db->commit();
                return ['ok' => false, 'message' => 'تعداد تلاش‌ها به پایان رسیده است.'];
            }
            if (!password_verify($code, (string) $row['code_hash'])) {
                $upd = $this->db->prepare('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = :id');
                $upd->execute(['id' => $row['id']]);
                $attempts = (int) $row['attempts'] + 1;
                $max = (int) $row['max_attempts'];
                if ($attempts >= $max) {
                    $this->db->prepare('UPDATE otp_codes SET consumed_at = NOW() WHERE id = :id')
                        ->execute(['id' => $row['id']]);
                }
                $this->db->commit();
                return ['ok' => false, 'message' => 'کد واردشده نادرست است.'];
            }

            // Consume immediately — OTP cannot be reused
            $consume = $this->db->prepare('UPDATE otp_codes SET consumed_at = NOW() WHERE id = :id');
            $consume->execute(['id' => $row['id']]);
            $this->db->commit();
            return ['ok' => true, 'mobile' => $mobile];
        } catch (\Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['ok' => false, 'message' => 'خطا در تأیید کد. دوباره تلاش کنید.'];
        }
    }

    private function invalidateOtpRow(int $otpId): void
    {
        if ($otpId <= 0) {
            return;
        }
        try {
            $this->db->prepare('UPDATE otp_codes SET consumed_at = NOW() WHERE id = ? AND consumed_at IS NULL')
                ->execute([$otpId]);
        } catch (\Throwable) {
        }
    }

    /** @param array<string, mixed> $sms */
    private function friendlySmsError(array $sms): string
    {
        $error = mb_strtolower((string) ($sms['error'] ?? ''));
        $http = (int) ($sms['http'] ?? 0);
        if ($http === 401 || $http === 403 || str_contains($error, 'کلید') || str_contains($error, 'api')) {
            return 'سرویس پیامک در دسترس نیست. لطفاً بعداً تلاش کنید.';
        }
        if (str_contains($error, 'قالب') || str_contains($error, 'template')) {
            return 'سرویس پیامک در دسترس نیست. لطفاً بعداً تلاش کنید.';
        }
        if (str_contains($error, 'اتصال') || str_contains($error, 'timeout') || $http === 0) {
            return 'ارتباط با سرویس پیامک برقرار نشد. دوباره تلاش کنید.';
        }
        return 'ارسال کد تأیید ناموفق بود. دوباره تلاش کنید.';
    }

    /**
     * Persist delivery outcome without storing the OTP plaintext.
     *
     * @param array<string, mixed> $result
     */
    private function logSms(string $mobile, string $type, bool $ok, array $result): void
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
                't' => $type,
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
                    'permanent' => $safe['permanent'] ?? null,
                    'app_pre_send_ms' => $safe['app_pre_send_ms'] ?? null,
                    'smsir_api_ms' => $safe['smsir_api_ms'] ?? ($safe['duration_ms'] ?? null),
                    'app_total_ms' => $safe['app_total_ms'] ?? null,
                    'queued' => false,
                ], JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable) {
        }
    }

    /** @param array<string, mixed> $data */
    private function timingLog(string $event, array $data): void
    {
        try {
            $root = dirname(__DIR__, 2);
            $dir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $line = json_encode([
                'at' => date('c'),
                'event' => $event,
                'data' => $data,
            ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
            @file_put_contents($dir . DIRECTORY_SEPARATOR . 'otp-timing.log', $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
        }
    }

    private function maskMobile(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';
        if (strlen($digits) < 7) {
            return '***';
        }
        return substr($digits, 0, 4) . '***' . substr($digits, -2);
    }
}
