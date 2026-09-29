<?php

declare(strict_types=1);

namespace Sarva\Services;

use PDO;
use PDOException;

/**
 * Deletes whitelisted history/log rows only. Never accepts a table name from the request.
 */
final class HistoryCleanupService
{
    public const CONFIRM_PHRASE = 'حذف همه تاریخچه‌ها';
    public const CHUNK = 1000;

    /** Core tables this service must never delete from. */
    public const PROTECTED_TABLES = [
        'patients',
        'appointments',
        'payments',
        'doctors',
        'services',
        'patient_notes',
        'site_settings',
        'admin_users',
        'media',
        'sms_templates',
        'sms_automation_rules',
    ];

    public const RANGES = [
        '30_days' => 30,
        '90_days' => 90,
        '180_days' => 180,
        '365_days' => 365,
        'all' => null,
    ];

    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return array<string, array{title:string,description:string,table:string,date_column:string}>
     */
    public static function categories(): array
    {
        return [
            'sms_logs' => [
                'title' => 'تاریخچه پیامک‌ها',
                'description' => 'گزارش ارسال‌های پیامک. حذف آن نوبت، بیمار یا تنظیم SMS.ir را تغییر نمی‌دهد. ممکن است ارسال مجدد پیامک تأیید برای همان نوبت دوباره ممکن شود.',
                'table' => 'sms_logs',
                'date_column' => 'created_at',
            ],
            'sms_queue' => [
                'title' => 'صف پیامک (پایان‌یافته)',
                'description' => 'فقط ردیف‌های ارسال‌شده، ناموفق یا لغوشده. پیام‌های در انتظار، در حال ارسال و تلاش مجدد حذف نمی‌شوند.',
                'table' => 'sms_queue',
                'date_column' => 'created_at',
            ],
            'otp_codes' => [
                'title' => 'تاریخچه OTP',
                'description' => 'فقط کدهای مصرف‌شده یا منقضی. کد فعال و هنوز معتبر حذف نمی‌شود.',
                'table' => 'otp_codes',
                'date_column' => 'created_at',
            ],
            'appointment_status_history' => [
                'title' => 'تاریخچه تغییر وضعیت نوبت‌ها',
                'description' => 'فقط ردیف‌های تاریخچه. وضعیت فعلی نوبت، ساعت و بیمار تغییر نمی‌کند.',
                'table' => 'appointment_status_history',
                'date_column' => 'created_at',
            ],
            'audit_logs' => [
                'title' => 'تاریخچه فعالیت مدیران',
                'description' => 'گزارش فعالیت مدیران. پس از پاک‌سازی، یک رکورد تازه از خود این عملیات باقی می‌ماند.',
                'table' => 'audit_logs',
                'date_column' => 'created_at',
            ],
            'sms_automation_runs' => [
                'title' => 'تاریخچه اجرای اتوماسیون‌ها',
                'description' => 'اجراهای روزهای قبل. اجرای امروز حذف نمی‌شود تا یادآوری نوبت دوباره در همان روز ارسال نشود.',
                'table' => 'sms_automation_runs',
                'date_column' => 'run_date',
            ],
            'page_views' => [
                'title' => 'آمار بازدید',
                'description' => 'آمار بازدید صفحات. حذف آن روی بیماران، نوبت‌ها یا تنظیمات اثری ندارد.',
                'table' => 'page_views',
                'date_column' => 'viewed_at',
            ],
        ];
    }

    /** @param list<string> $input @return list<string> */
    public static function normalizeCategories(array $input): array
    {
        $allowed = array_keys(self::categories());
        $out = [];
        foreach ($input as $item) {
            $key = (string) $item;
            if (in_array($key, $allowed, true) && !in_array($key, $out, true)) {
                $out[] = $key;
            }
        }
        return $out;
    }

    public static function rangeDays(?string $range): ?int
    {
        if ($range === null || !array_key_exists($range, self::RANGES)) {
            return null;
        }
        return self::RANGES[$range];
    }

    public static function requiresPhrase(?string $range): bool
    {
        return $range === 'all';
    }

    public static function phraseMatches(string $typed): bool
    {
        return trim($typed) === self::CONFIRM_PHRASE;
    }

    /**
     * Extra safety predicate. Never includes a user-supplied table name.
     */
    public static function safetyWhere(string $category): string
    {
        return match ($category) {
            'sms_queue' => "status IN ('sent','failed','cancelled')",
            'otp_codes' => '(consumed_at IS NOT NULL OR expires_at < NOW())',
            'sms_automation_runs' => 'run_date < CURDATE()',
            'sms_logs', 'appointment_status_history', 'audit_logs', 'page_views' => '1=1',
            default => '0=1',
        };
    }

    /**
     * @return array{where:string,params:list<string>}
     */
    public static function eligibility(string $category, ?\DateTimeImmutable $olderThan): array
    {
        if (!isset(self::categories()[$category])) {
            return ['where' => '0=1', 'params' => []];
        }
        $where = self::safetyWhere($category);
        $params = [];
        if ($olderThan !== null) {
            $column = self::categories()[$category]['date_column'];
            $where .= ' AND ' . $column . ' < ?';
            $params[] = $olderThan->format('Y-m-d H:i:s');
        }
        return ['where' => $where, 'params' => $params];
    }

    /**
     * @return array<string, array{total:int,oldest:?string,newest:?string,eligible:int,available:bool}>
     */
    public function stats(?\DateTimeImmutable $olderThan = null): array
    {
        $out = [];
        foreach (self::categories() as $key => $meta) {
            if (!$this->tableExists($meta['table'])) {
                $out[$key] = [
                    'total' => 0,
                    'oldest' => null,
                    'newest' => null,
                    'eligible' => 0,
                    'available' => false,
                ];
                continue;
            }
            $col = $meta['date_column'];
            $row = $this->db->query(
                'SELECT COUNT(*) AS total, MIN(' . $col . ') AS oldest, MAX(' . $col . ') AS newest FROM ' . $meta['table']
            )->fetch(PDO::FETCH_ASSOC) ?: [];
            $eligible = $this->countEligible($key, $olderThan);
            $out[$key] = [
                'total' => (int) ($row['total'] ?? 0),
                'oldest' => isset($row['oldest']) && $row['oldest'] !== null ? (string) $row['oldest'] : null,
                'newest' => isset($row['newest']) && $row['newest'] !== null ? (string) $row['newest'] : null,
                'eligible' => $eligible,
                'available' => true,
            ];
        }
        return $out;
    }

    public function countEligible(string $category, ?\DateTimeImmutable $olderThan): int
    {
        $meta = self::categories()[$category] ?? null;
        if ($meta === null || !$this->tableExists($meta['table'])) {
            return 0;
        }
        $filter = self::eligibility($category, $olderThan);
        $st = $this->db->prepare('SELECT COUNT(*) FROM ' . $meta['table'] . ' WHERE ' . $filter['where']);
        $st->execute($filter['params']);
        return (int) $st->fetchColumn();
    }

    /**
     * @param list<string> $categories
     * @return array<string, int>
     */
    public function cleanupSelected(array $categories, ?\DateTimeImmutable $olderThan): array
    {
        $categories = self::normalizeCategories($categories);
        $deleted = [];
        $audit = in_array('audit_logs', $categories, true);
        foreach ($categories as $category) {
            if ($category === 'audit_logs') {
                continue;
            }
            $deleted[$category] = $this->cleanupCategory($category, $olderThan);
        }
        if ($audit) {
            $deleted['audit_logs'] = $this->cleanupCategory('audit_logs', $olderThan);
        }
        return $deleted;
    }

    public function cleanupCategory(string $category, ?\DateTimeImmutable $olderThan = null): int
    {
        $meta = self::categories()[$category] ?? null;
        if ($meta === null || in_array($meta['table'], self::PROTECTED_TABLES, true)) {
            return 0;
        }
        if (!$this->tableExists($meta['table'])) {
            return 0;
        }
        $filter = self::eligibility($category, $olderThan);
        $sql = 'DELETE FROM ' . $meta['table'] . ' WHERE ' . $filter['where'] . ' LIMIT ' . self::CHUNK;
        $total = 0;
        do {
            $st = $this->db->prepare($sql);
            $st->execute($filter['params']);
            $n = $st->rowCount();
            if ($n < 1) {
                break;
            }
            $total += $n;
        } while ($n === self::CHUNK);
        return $total;
    }

    public static function cutoffForRange(?string $range, \DateTimeImmutable $now): ?\DateTimeImmutable
    {
        $days = self::rangeDays($range);
        if ($days === null) {
            return null;
        }
        return $now->modify('-' . $days . ' days');
    }

    private function tableExists(string $table): bool
    {
        if (!in_array($table, array_column(self::categories(), 'table'), true)) {
            return false;
        }
        try {
            $st = $this->db->query('SHOW TABLES');
            $names = $st ? $st->fetchAll(PDO::FETCH_COLUMN) : [];
            foreach ($names as $name) {
                if (strcasecmp((string) $name, $table) === 0) {
                    return true;
                }
            }
            return false;
        } catch (PDOException) {
            return false;
        }
    }
}
