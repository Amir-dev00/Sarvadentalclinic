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

        // Rate limit: max 5 OTPs per mobile per hour
        $rate = $this->db->prepare(
            'SELECT COUNT(*) FROM otp_codes WHERE mobile = :m AND created_at > (NOW() - INTERVAL 1 HOUR)'
        );
        $rate->execute(['m' => $mobile]);
        if ((int) $rate->fetchColumn() >= 5) {
            return ['ok' => false, 'message' => 'تعداد درخواست‌ها بیش از حد مجاز است. بعداً تلاش کنید.'];
        }

        $length = (int) config('app.otp_length', 5);
        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
        $ttl = (int) config('app.otp_ttl', 120);
        $hash = password_hash($code, PASSWORD_DEFAULT);

        $ins = $this->db->prepare(
            'INSERT INTO otp_codes (mobile, code_hash, purpose, max_attempts, expires_at, ip_address)
             VALUES (:m, :h, :p, :max, DATE_ADD(NOW(), INTERVAL :ttl SECOND), :ip)'
        );
        $ins->bindValue('m', $mobile);
        $ins->bindValue('h', $hash);
        $ins->bindValue('p', 'login');
        $ins->bindValue('max', (int) config('app.otp_max_attempts', 5), PDO::PARAM_INT);
        $ins->bindValue('ttl', $ttl, PDO::PARAM_INT);
        $ins->bindValue('ip', $ip);
        $ins->execute();

        $sms = SmsManager::make()->sendOtp($mobile, $code);
        $this->logSms($mobile, 'otp', "کد تأیید: {$code}", $sms);

        $payload = [
            'ok' => true,
            'message' => 'کد تأیید ارسال شد.',
            'expires_in' => $ttl,
            'resend_in' => $cooldown,
        ];
        if (config('app.debug') && (($_ENV['SMS_DRIVER'] ?? 'log') === 'log')) {
            $payload['dev_code'] = $code; // Only in local log-driver mode
        }
        return $payload;
    }

    public function verify(string $mobile, string $code): array
    {
        $mobile = normalize_mobile($mobile);
        if ($mobile === null) {
            return ['ok' => false, 'message' => 'شماره موبایل معتبر نیست.'];
        }

        $stmt = $this->db->prepare(
            'SELECT * FROM otp_codes WHERE mobile = :m AND purpose = :p AND consumed_at IS NULL
             ORDER BY id DESC LIMIT 1 FOR UPDATE'
        );

        $this->db->beginTransaction();
        try {
            $stmt->execute(['m' => $mobile, 'p' => 'login']);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $this->db->rollBack();
                return ['ok' => false, 'message' => 'کد معتبری یافت نشد. دوباره درخواست کنید.'];
            }
            if (strtotime($row['expires_at']) < time()) {
                $this->db->rollBack();
                return ['ok' => false, 'message' => 'کد منقضی شده است.'];
            }
            if ((int) $row['attempts'] >= (int) $row['max_attempts']) {
                $this->db->rollBack();
                return ['ok' => false, 'message' => 'تعداد تلاش‌ها به پایان رسیده است.'];
            }
            if (!password_verify($code, $row['code_hash'])) {
                $upd = $this->db->prepare('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = :id');
                $upd->execute(['id' => $row['id']]);
                $this->db->commit();
                return ['ok' => false, 'message' => 'کد واردشده نادرست است.'];
            }

            $consume = $this->db->prepare('UPDATE otp_codes SET consumed_at = NOW() WHERE id = :id');
            $consume->execute(['id' => $row['id']]);
            $this->db->commit();
            return ['ok' => true, 'mobile' => $mobile];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** @param array<string, mixed> $result */
    private function logSms(string $mobile, string $type, string $body, array $result): void
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO sms_logs (mobile, message_type, message_body, provider, provider_message_id, status, provider_response, sent_at)
                 VALUES (:m, :t, :b, :p, :mid, :s, :r, NOW())'
            );
            $stmt->execute([
                'm' => $mobile,
                't' => $type,
                'b' => $body,
                'p' => $result['provider'] ?? ($_ENV['SMS_DRIVER'] ?? 'log'),
                'mid' => $result['message_id'] ?? null,
                's' => ($result['ok'] ?? false) ? 'sent' : 'failed',
                'r' => json_encode($result, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable) {
        }
    }
}
