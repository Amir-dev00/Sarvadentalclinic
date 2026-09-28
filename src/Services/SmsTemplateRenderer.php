<?php

declare(strict_types=1);

namespace Sarva\Services;

/**
 * Safe SMS template renderer.
 * Canonical syntax: #variable#
 * Legacy (temporary compatibility): {variable}
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
            'cancellation_reason' => 'دلیل لغو',
        ];
    }

    /** @return list<string> */
    public static function knownKeys(): array
    {
        return array_merge(array_keys(self::placeholders()), ['CODE', 'code']);
    }

    /**
     * Convert legacy {variable} tokens for known keys into canonical #variable#.
     * Unknown braces / unrelated text are left untouched.
     */
    public static function normalizeToCanonical(string $body): string
    {
        $keys = self::knownKeys();
        return preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/u', static function (array $m) use ($keys): string {
            $key = $m[1];
            if (self::isKnownKey($key, $keys)) {
                return '#' . $key . '#';
            }
            return $m[0];
        }, $body) ?? $body;
    }

    /** @deprecated Use normalizeToCanonical() */
    public static function normalizeLegacySyntax(string $body): string
    {
        return self::normalizeToCanonical($body);
    }

    /**
     * @param array<string, mixed> $vars
     */
    public static function render(string $body, array $vars): string
    {
        // Temporary: treat legacy {variable} as #variable# for any token name
        $body = preg_replace('/\{([a-zA-Z0-9_]+)\}/u', '#$1#', $body) ?? $body;

        $known = array_fill_keys(array_keys(self::placeholders()), '');
        $known['CODE'] = '';
        $known['code'] = '';
        $merged = array_merge($known, $vars);
        if (($merged['CODE'] ?? '') === '' && ($merged['code'] ?? '') !== '') {
            $merged['CODE'] = (string) $merged['code'];
        }

        $unknown = [];
        $out = preg_replace_callback('/#([a-zA-Z0-9_]+)#/u', static function (array $m) use ($merged, &$unknown): string {
            $key = $m[1];
            if (array_key_exists($key, $merged)) {
                return (string) $merged[$key];
            }
            foreach ($merged as $k => $v) {
                if (strcasecmp((string) $k, $key) === 0) {
                    return (string) $v;
                }
            }
            $unknown[$key] = $key;
            return '';
        }, $body) ?? $body;

        if ($unknown !== []) {
            self::logEvent('sms_template_unknown_placeholder', [
                'unknown_placeholders' => array_values($unknown),
            ]);
        }

        return trim(preg_replace("/[ \t]+\n/", "\n", $out) ?? $out);
    }

    /**
     * Render and verify no placeholder tokens remain unresolved.
     *
     * @param array<string, mixed> $vars
     * @return array{ok:bool,message:string,unresolved:list<string>}
     */
    public static function renderForSend(string $body, array $vars): array
    {
        $message = self::render($body, $vars);
        $unresolved = self::findUnresolvedPlaceholders($message);
        return [
            'ok' => $unresolved === [],
            'message' => $message,
            'unresolved' => $unresolved,
        ];
    }

    /**
     * @return list<string>
     */
    public static function findUnresolvedPlaceholders(string $text): array
    {
        $found = [];
        if (preg_match_all('/#([a-zA-Z0-9_]+)#|\{([a-zA-Z0-9_]+)\}/u', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $key = ($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? '');
                if ($key !== '') {
                    $found[$key] = $key;
                }
            }
        }
        return array_values($found);
    }

    /** @deprecated Use findUnresolvedPlaceholders() */
    public static function findUnresolvedKnownPlaceholders(string $text): array
    {
        return self::findUnresolvedPlaceholders($text);
    }

    public static function logRenderError(string $context, array $meta = []): void
    {
        self::logEvent('template_render_error', array_merge(['context' => $context], $meta));
    }

    public static function logEvent(string $event, array $meta = []): void
    {
        try {
            $root = dirname(__DIR__, 2);
            $dir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $payload = array_merge([
                'at' => date('c'),
                'event' => $event,
            ], $meta);
            unset(
                $payload['mobile'],
                $payload['full_name'],
                $payload['first_name'],
                $payload['last_name'],
                $payload['api_key'],
                $payload['message'],
                $payload['body']
            );
            @file_put_contents(
                $dir . DIRECTORY_SEPARATOR . 'sms.log',
                json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        } catch (\Throwable) {
        }
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
            'cancellation_reason' => trim((string) ($appointment['cancellation_reason'] ?? '')),
        ];
    }

    /**
     * Build SMS.ir Verify parameters for appointment templates.
     * Keys MUST be uppercase names expected by SMS.ir (no # symbols).
     *
     * @param array<string, mixed> $patient
     * @param array<string, mixed>|null $appointment
     * @return array{FULL_NAME:string,APPOINTMENT_DATE:string,APPOINTMENT_TIME:string}
     */
    public static function buildSmsIrAppointmentParameters(array $patient, ?array $appointment = null): array
    {
        $vars = self::varsFrom($patient, $appointment);
        return [
            'FULL_NAME' => (string) ($vars['full_name'] ?? ''),
            'APPOINTMENT_DATE' => (string) ($vars['appointment_date'] ?? ''),
            'APPOINTMENT_TIME' => (string) ($vars['appointment_time'] ?? ''),
        ];
    }

    /**
     * @param array<string, string> $assoc
     * @return list<array{name:string,value:string}>
     */
    public static function toSmsIrParameterList(array $assoc): array
    {
        $list = [];
        foreach ($assoc as $name => $value) {
            $list[] = [
                'name' => (string) $name,
                'value' => (string) $value,
            ];
        }
        return $list;
    }

    /**
     * @param array<string, string> $assoc
     * @param list<string> $required
     * @return list<string>
     */
    public static function missingSmsIrParameters(array $assoc, array $required = ['FULL_NAME', 'APPOINTMENT_DATE', 'APPOINTMENT_TIME']): array
    {
        $missing = [];
        foreach ($required as $key) {
            if (trim((string) ($assoc[$key] ?? '')) === '') {
                $missing[] = $key;
            }
        }
        return $missing;
    }

    public static function logSmsIrParameterError(array $meta = []): void
    {
        self::logEvent('smsir_template_parameter_error', $meta);
    }

    /**
     * Remove empty optional placeholder lines (e.g. blank #cancellation_reason#) before render.
     */
    public static function stripEmptyOptionalPlaceholders(string $body, array $vars): string
    {
        $optional = ['cancellation_reason'];
        foreach ($optional as $key) {
            $val = trim((string) ($vars[$key] ?? ''));
            if ($val !== '') {
                continue;
            }
            $body = preg_replace('/^[ \t]*#' . preg_quote($key, '/') . '#[ \t]*\r?\n?/mu', '', $body) ?? $body;
            $body = preg_replace('/[ \t]*#' . preg_quote($key, '/') . '#[ \t]*/u', '', $body) ?? $body;
        }
        return $body;
    }

    /** @param list<string> $keys */
    private static function isKnownKey(string $key, array $keys): bool
    {
        if (in_array($key, $keys, true)) {
            return true;
        }
        foreach ($keys as $known) {
            if (strcasecmp($known, $key) === 0) {
                return true;
            }
        }
        return false;
    }
}
