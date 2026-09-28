<?php

declare(strict_types=1);

namespace Sarva\Services;

/**
 * Safe SMS template renderer. Unknown placeholders become empty strings.
 */
final class SmsTemplateRenderer
{
    /** @return array<string, string> */
    public static function placeholders(): array
    {
        return [
            'first_name' => 'نام',
            'last_name' => 'نام خانوادگی',
            'full_name' => 'نام کامل',
            'patient_number' => 'شماره پرونده',
            'mobile' => 'موبایل',
            'appointment_date' => 'تاریخ نوبت',
            'appointment_time' => 'ساعت نوبت',
            'doctor_name' => 'نام پزشک',
            'service_name' => 'نام خدمت',
            'clinic_name' => 'نام کلینیک',
            'clinic_phone' => 'تلفن کلینیک',
        ];
    }

    /**
     * @param array<string, mixed> $vars
     */
    public static function render(string $body, array $vars): string
    {
        $known = array_fill_keys(array_keys(self::placeholders()), '');
        $known['CODE'] = '';
        $merged = array_merge($known, $vars);
        $out = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/u', static function (array $m) use ($merged): string {
            $key = $m[1];
            if (!array_key_exists($key, $merged)) {
                return '';
            }
            return (string) $merged[$key];
        }, $body) ?? $body;
        return trim(preg_replace("/[ \t]+\n/", "\n", $out) ?? $out);
    }

    /** Persian SMS typically uses UCS-2 (70 chars / segment). */
    public static function segmentCount(string $message): int
    {
        $len = mb_strlen($message);
        if ($len === 0) {
            return 0;
        }
        $per = 70;
        return (int) max(1, (int) ceil($len / $per));
    }

    /**
     * @param array<string, mixed> $patient
     * @param array<string, mixed>|null $appointment
     * @return array<string, string>
     */
    public static function varsFrom(array $patient, ?array $appointment = null): array
    {
        $first = trim((string) ($patient['first_name'] ?? ''));
        $last = trim((string) ($patient['last_name'] ?? ''));
        $full = trim($first . ' ' . $last);
        $starts = $appointment['starts_at'] ?? null;
        $date = $starts ? to_jalali((string) $starts, 'Y/m/d') : '';
        $time = $starts ? to_jalali((string) $starts, 'H:i') : '';
        return [
            'first_name' => $first,
            'last_name' => $last,
            'full_name' => $full !== '' ? $full : 'بیمار',
            'patient_number' => (string) ($patient['file_number'] ?? $patient['public_code'] ?? ''),
            'mobile' => (string) ($patient['mobile'] ?? ''),
            'appointment_date' => $date,
            'appointment_time' => $time,
            'doctor_name' => (string) ($appointment['doctor_name'] ?? ''),
            'service_name' => (string) ($appointment['service_name'] ?? ''),
            'clinic_name' => (string) setting('clinic_name', 'کلینیک دندانپزشکی سروا'),
            'clinic_phone' => (string) setting('phone', setting('mobile', '')),
        ];
    }
}
