<?php

declare(strict_types=1);

namespace Sarva\Services;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Allocates sequential numeric patients.file_number values.
 *
 * Semantics (single definition):
 * - "Last allocated (active)" = highest pure-numeric file_number among non-deleted patients.
 * - "Next available" = that value + 1, then the first free number at or above it.
 *
 * Soft-deleted rows still occupy the unique key. If they alone hold the candidate number,
 * their file_number is renamed to ARCH-{id}-{old} so the clinical sequence can continue
 * without deleting the archived patient row.
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
     * Highest pure-numeric file_number among active (non-deleted) patients.
     * Throws when the database cannot be queried (never treat a DB error as "start at 1").
     */
    public function highestNumericFileNumber(): int
    {
        return $this->highestNumericFileNumberScoped(false);
    }

    /**
     * Highest pure-numeric file_number including soft-deleted patients (diagnostics / uniqueness).
     */
    public function highestNumericFileNumberIncludingDeleted(): int
    {
        return $this->highestNumericFileNumberScoped(true);
    }

    /**
     * Next number to allocate: last active numeric + 1, then first free slot.
     * This is the next available number, not "last allocated".
     */
    public function nextAvailableFileNumber(): string
    {
        $candidate = $this->highestNumericFileNumber() + 1;
        if ($candidate < 1) {
            $candidate = 1;
        }

        for ($guard = 0; $guard < 10000; $guard++, $candidate++) {
            $value = (string) $candidate;
            $holders = $this->holdersOfFileNumber($value);
            if ($holders === []) {
                return $value;
            }

            $activeHolders = array_values(array_filter(
                $holders,
                static fn (array $row): bool => empty($row['deleted_at'])
            ));
            if ($activeHolders !== []) {
                continue;
            }

            // Only soft-deleted rows hold this number — free them for the live sequence.
            $this->releaseSoftDeletedFileNumber($value, $holders);
            return $value;
        }

        throw new RuntimeException('شماره پرونده آزاد پیدا نشد.');
    }

    /** Suggested next number for UI (same as next available; not reserved until insert). */
    public function suggestNext(): string
    {
        return $this->nextAvailableFileNumber();
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

                    $fileNumber = $this->nextAvailableFileNumber();
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
                    $fileNumber = $this->nextAvailableFileNumber();
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

    private function highestNumericFileNumberScoped(bool $includeDeleted): int
    {
        $deletedSql = $includeDeleted ? '' : ' AND deleted_at IS NULL';

        try {
            $max = $this->db->query(
                "SELECT COALESCE(MAX(CAST(TRIM(file_number) AS UNSIGNED)), 0)
                 FROM patients
                 WHERE TRIM(file_number) REGEXP '^[0-9]+$'{$deletedSql}"
            )->fetchColumn();
        } catch (Throwable $e) {
            throw new RuntimeException('خواندن شماره پرونده‌ها ممکن نیست.', 0, $e);
        }

        $highest = (int) $max;

        try {
            $extra = $this->db->query(
                "SELECT file_number FROM patients
                 WHERE file_number IS NOT NULL
                   AND TRIM(file_number) <> ''
                   AND TRIM(file_number) NOT REGEXP '^[0-9]+$'
                   AND file_number REGEXP '[۰-۹٠-٩]'{$deletedSql}"
            )->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            throw new RuntimeException('خواندن شماره پرونده‌ها ممکن نیست.', 0, $e);
        }

        foreach ($extra as $raw) {
            $parsed = $this->parsePureNumericFileNumber((string) $raw);
            if ($parsed !== null) {
                $highest = max($highest, $parsed);
            }
        }

        return $highest;
    }

    private function parsePureNumericFileNumber(string $raw): ?int
    {
        $normalized = normalize_digits(trim($raw));
        if ($normalized === '' || preg_match('/^[0-9]+$/', $normalized) !== 1) {
            return null;
        }
        if (strlen($normalized) > 18) {
            return null;
        }
        return (int) $normalized;
    }

    /**
     * @return list<array{id:int,deleted_at:?string,file_number:string}>
     */
    private function holdersOfFileNumber(string $fileNumber): array
    {
        $st = $this->db->prepare(
            'SELECT id, deleted_at, file_number FROM patients WHERE file_number = ?'
        );
        $st->execute([$fileNumber]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'deleted_at' => $row['deleted_at'] !== null ? (string) $row['deleted_at'] : null,
                'file_number' => (string) $row['file_number'],
            ];
        }
        return $out;
    }

    /**
     * @param list<array{id:int,deleted_at:?string,file_number:string}> $holders
     */
    private function releaseSoftDeletedFileNumber(string $fileNumber, array $holders): void
    {
        $st = $this->db->prepare(
            "UPDATE patients
             SET file_number = CONCAT('ARCH-', id, '-', ?)
             WHERE id = ?
               AND deleted_at IS NOT NULL
               AND file_number = ?"
        );
        foreach ($holders as $row) {
            if ($row['deleted_at'] === null) {
                continue;
            }
            $st->execute([$fileNumber, $row['id'], $fileNumber]);
        }
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
