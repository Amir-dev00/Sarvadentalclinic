<?php

declare(strict_types=1);

namespace Sarva\Services;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Allocates sequential numeric patients.file_number values.
 * Source of truth is the current database (including soft-deleted rows).
 */
final class PatientFileNumberService
{
    private const LOCK_NAME = 'sarva_patient_file_number';
    private const LOCK_TIMEOUT_SECONDS = 15;
    private const MAX_INSERT_ATTEMPTS = 8;

    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * Highest existing numeric file number, including soft-deleted patients.
     * Throws when the database cannot be queried (do not treat that as "start at 1").
     */
    public function highestNumericFileNumber(): int
    {
        try {
            $max = $this->db->query(
                "SELECT COALESCE(MAX(CAST(file_number AS UNSIGNED)), 0)
                 FROM patients
                 WHERE file_number REGEXP '^[0-9]+$'"
            )->fetchColumn();
        } catch (Throwable $e) {
            throw new RuntimeException('خواندن شماره پرونده‌ها ممکن نیست.', 0, $e);
        }

        $highest = (int) $max;

        try {
            $extra = $this->db->query(
                "SELECT file_number FROM patients
                 WHERE file_number IS NOT NULL
                   AND file_number <> ''
                   AND file_number NOT REGEXP '^[0-9]+$'
                   AND file_number REGEXP '[۰-۹٠-٩]'"
            )->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            throw new RuntimeException('خواندن شماره پرونده‌ها ممکن نیست.', 0, $e);
        }

        foreach ($extra as $raw) {
            $normalized = normalize_digits(trim((string) $raw));
            if (preg_match('/^[0-9]+$/', $normalized) === 1) {
                $highest = max($highest, (int) $normalized);
            }
        }

        return $highest;
    }

    /** Suggested next number for UI (not reserved until allocate/insert). */
    public function suggestNext(): string
    {
        return (string) ($this->highestNumericFileNumber() + 1);
    }

    /**
     * Create a newly registered website patient with an allocated file_number.
     *
     * @return array{ok:bool,id?:int,file_number?:string,already_exists?:bool,message?:string}
     */
    public function createRegisteredPatient(string $mobile, string $firstName, string $lastName): array
    {
        $mobile = normalize_mobile($mobile) ?? '';
        if ($mobile === '') {
            return ['ok' => false, 'message' => 'شماره موبایل معتبر نیست.'];
        }

        $existing = $this->db->prepare('SELECT id FROM patients WHERE mobile = ? AND deleted_at IS NULL LIMIT 1');
        $existing->execute([$mobile]);
        $existingId = (int) $existing->fetchColumn();
        if ($existingId > 0) {
            return ['ok' => true, 'id' => $existingId, 'already_exists' => true];
        }

        $lastError = 'ثبت‌نام انجام نشد. دوباره تلاش کنید.';
        for ($attempt = 0; $attempt < self::MAX_INSERT_ATTEMPTS; $attempt++) {
            try {
                return $this->withFileNumberLock(function () use ($mobile, $firstName, $lastName, $existing): array {
                    $existing->execute([$mobile]);
                    $existingId = (int) $existing->fetchColumn();
                    if ($existingId > 0) {
                        return ['ok' => true, 'id' => $existingId, 'already_exists' => true];
                    }

                    $fileNumber = (string) ($this->highestNumericFileNumber() + 1);
                    $publicCode = 'P' . strtoupper(bin2hex(random_bytes(4)));
                    $this->db->prepare(
                        'INSERT INTO patients (public_code, file_number, first_name, last_name, mobile, profile_completed)
                         VALUES (?,?,?,?,?,0)'
                    )->execute([$publicCode, $fileNumber, $firstName, $lastName, $mobile]);

                    return [
                        'ok' => true,
                        'id' => (int) $this->db->lastInsertId(),
                        'file_number' => $fileNumber,
                        'already_exists' => false,
                    ];
                });
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                $isDuplicate = ((string) $e->getCode() === '23000') || str_contains($msg, 'Duplicate');
                if (!$isDuplicate) {
                    throw $e;
                }
                if (str_contains($msg, 'file_number') || str_contains($msg, 'uq_patients_file_number')) {
                    $lastError = 'شماره پرونده همزمان گرفته شد. دوباره تلاش کنید.';
                    continue;
                }
                $existing->execute([$mobile]);
                $existingId = (int) $existing->fetchColumn();
                if ($existingId > 0) {
                    return ['ok' => true, 'id' => $existingId, 'already_exists' => true];
                }
                continue;
            }
        }

        return ['ok' => false, 'message' => $lastError];
    }

    /**
     * Allocate a file number for an admin create when none was supplied.
     * The inserter runs while the advisory lock is held.
     *
     * @param callable(string):int $inserter receives file_number, returns new patient id
     */
    public function createWithAllocatedNumber(callable $inserter): int
    {
        $last = null;
        for ($attempt = 0; $attempt < self::MAX_INSERT_ATTEMPTS; $attempt++) {
            try {
                return $this->withFileNumberLock(function () use ($inserter): int {
                    $fileNumber = (string) ($this->highestNumericFileNumber() + 1);
                    return (int) $inserter($fileNumber);
                });
            } catch (PDOException $e) {
                $last = $e;
                $msg = $e->getMessage();
                if (((string) $e->getCode() === '23000') || str_contains($msg, 'Duplicate')) {
                    if (str_contains($msg, 'file_number') || str_contains($msg, 'uq_patients_file_number')) {
                        continue;
                    }
                }
                throw $e;
            }
        }
        throw $last ?? new RuntimeException('اختصاص شماره پرونده ممکن نیست.');
    }

    /** @template T @param callable():T $callback @return T */
    private function withFileNumberLock(callable $callback): mixed
    {
        $this->acquireLock();
        try {
            return $callback();
        } finally {
            $this->releaseLock();
        }
    }

    private function acquireLock(): void
    {
        $st = $this->db->prepare('SELECT GET_LOCK(?, ?)');
        $st->execute([self::LOCK_NAME, self::LOCK_TIMEOUT_SECONDS]);
        if ((int) $st->fetchColumn() !== 1) {
            throw new RuntimeException('قفل شماره پرونده در دسترس نیست. دوباره تلاش کنید.');
        }
    }

    private function releaseLock(): void
    {
        try {
            $st = $this->db->prepare('SELECT RELEASE_LOCK(?)');
            $st->execute([self::LOCK_NAME]);
        } catch (Throwable) {
        }
    }
}
