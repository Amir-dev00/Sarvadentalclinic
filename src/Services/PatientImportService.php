<?php

declare(strict_types=1);

namespace Sarva\Services;

use PDO;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Safe Excel patient importer.
 * Default mode: add_new only — never overwrite existing patients.
 *
 * Identity priority: file_number → national_id.
 * Mobile is contact-only and may be shared by family members.
 */
final class PatientImportService
{
    public const MODE_ADD_NEW = 'add_new';
    public const MODE_REVIEW_CHANGES = 'review_changes';
    public const MODE_UPDATE = 'update_existing';

    public function __construct(private readonly PDO $db)
    {
        $this->ensureMobileNotUnique();
        $this->ensureImportSchema();
    }

    /** Allow shared family phone numbers across different پرونده rows. */
    private function ensureMobileNotUnique(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $idx = $this->db->query("SHOW INDEX FROM patients WHERE Column_name='mobile' AND Non_unique=0")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($idx as $row) {
                $name = (string) ($row['Key_name'] ?? '');
                if ($name === '' || $name === 'PRIMARY') {
                    continue;
                }
                $this->db->exec('ALTER TABLE patients DROP INDEX `' . str_replace('`', '``', $name) . '`');
            }
            $any = $this->db->query("SHOW INDEX FROM patients WHERE Column_name='mobile'")->fetch(PDO::FETCH_ASSOC);
            if (!$any) {
                $this->db->exec('ALTER TABLE patients ADD INDEX idx_patients_mobile_lookup (mobile)');
            }
        } catch (\Throwable) {
            // Host may lack ALTER; insert path still handles UNIQUE collisions.
        }
    }

    /** Ensure conflict table + helpful indexes exist (safe on shared hosts). */
    private function ensureImportSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS excel_import_conflicts (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    import_id INT UNSIGNED NOT NULL,
                    `row_number` INT UNSIGNED NOT NULL,
                    match_type VARCHAR(40) DEFAULT NULL,
                    existing_patient_id INT UNSIGNED DEFAULT NULL,
                    excel_json LONGTEXT NULL,
                    existing_json LONGTEXT NULL,
                    status VARCHAR(40) NOT NULL DEFAULT 'pending',
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_eic_import (import_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (\Throwable) {
        }
        try {
            $this->db->exec('ALTER TABLE excel_imports MODIFY status ENUM("uploaded","previewed","processing","completed","failed") NOT NULL DEFAULT "uploaded"');
        } catch (\Throwable) {
        }
    }

    /** @return array{ok:bool, sheets?:array, message?:string, previous_imports?:list<array>, file_hash?:?string} */
    public function inspect(string $absolutePath): array
    {
        if (!is_file($absolutePath)) {
            return ['ok' => false, 'message' => 'فایل یافت نشد.'];
        }
        $hash = hash_file('sha256', $absolutePath) ?: null;
        $previous = [];
        if ($hash) {
            $st = $this->db->prepare(
                'SELECT id, original_name, filename, status, created_at, imported_rows, duplicate_rows, invalid_rows, conflict_rows, total_rows
                 FROM excel_imports WHERE file_hash=? ORDER BY id DESC LIMIT 5'
            );
            $st->execute([$hash]);
            $previous = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        try {
            $reader = IOFactory::createReaderForFile($absolutePath);
            if (method_exists($reader, 'setReadDataOnly')) {
                $reader->setReadDataOnly(true);
            }
            if (method_exists($reader, 'setReadEmptyCells')) {
                $reader->setReadEmptyCells(false);
            }

            // Fast metadata (row counts) without loading every cell.
            $infos = [];
            if (method_exists($reader, 'listWorksheetInfo')) {
                foreach ($reader->listWorksheetInfo($absolutePath) as $info) {
                    $infos[(string) ($info['worksheetName'] ?? '')] = $info;
                }
            }

            // Only read header + a few sample rows — not the whole 2k+ patient file.
            if (method_exists($reader, 'setReadFilter')) {
                $reader->setReadFilter(new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
                    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                    {
                        return $row >= 1 && $row <= 8;
                    }
                });
            }

            $spreadsheet = $reader->load($absolutePath);
            $sheets = [];
            foreach ($spreadsheet->getAllSheets() as $idx => $sheet) {
                $title = $sheet->getTitle();
                $rows = $sheet->toArray(null, true, true, true);
                $header = $rows[1] ?? (reset($rows) ?: []);
                $sample = [];
                foreach ($rows as $rIdx => $row) {
                    if ((int) $rIdx === 1) {
                        continue;
                    }
                    if ($this->rowEmpty($row)) {
                        continue;
                    }
                    $sample[] = $row;
                    if (count($sample) >= 5) {
                        break;
                    }
                }

                $info = $infos[$title] ?? null;
                $totalRows = $info ? max(0, (int) ($info['totalRows'] ?? 0) - 1) : max(0, count($rows) - 1);

                $sheets[] = [
                    'index' => $idx,
                    'title' => $title,
                    'columns' => $header,
                    'letters' => array_keys($header),
                    'sample' => $sample,
                    'row_count' => $totalRows,
                    'guess' => $this->guessMapping($header),
                    'is_patient_sheet' => $this->isPatientSheet($title, $header),
                ];
            }
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return ['ok' => true, 'sheets' => $sheets, 'file_hash' => $hash, 'previous_imports' => $previous];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'خواندن فایل ناموفق بود: ' . $e->getMessage(),
                'sheets' => [],
                'file_hash' => $hash,
                'previous_imports' => $previous,
            ];
        }
    }

    /** Load spreadsheet for import/preview with data-only (no formula calc). */
    private function loadSpreadsheet(string $absolutePath): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $reader = IOFactory::createReaderForFile($absolutePath);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }
        return $reader->load($absolutePath);
    }

    /**
     * @param array<string, mixed> $header
     * @return array<string, string>
     */
    public function guessMapping(array $header): array
    {
        $map = [];
        foreach ($header as $letter => $label) {
            $n = mb_strtolower(trim((string) $label));
            $letter = (string) $letter;
            if ($n === '') {
                continue;
            }
            if (preg_match('/پرونده|file|patient.?no|کد پرونده/', $n)) {
                $map['file_number'] = $letter;
            } elseif (preg_match('/تلفن همراه|موبایل|mobile|همراه/', $n)) {
                $map['mobile'] = $letter;
            } elseif (preg_match('/تلفن ثابت|ثابت|landline|home.?phone/', $n)) {
                $map['landline'] = $letter;
            } elseif (preg_match('/نام خانواد|last.?name|فامیل/', $n)) {
                $map['last_name'] = $letter;
            } elseif (preg_match('/نام پدر|father/', $n)) {
                $map['father_name'] = $letter;
            } elseif (preg_match('/^نام$|first.?name/', $n) && !isset($map['first_name'])) {
                $map['first_name'] = $letter;
            } elseif (preg_match('/سال تولد|birth.?year/', $n)) {
                $map['birth_year_jalali'] = $letter;
            } elseif (preg_match('/تاریخ تولد|birth.?date|تولد/', $n) && !isset($map['birth_year_jalali'])) {
                $map['birth_date'] = $letter;
            } elseif (preg_match('/کد ملی|national|meli/', $n)) {
                $map['national_id'] = $letter;
            } elseif (preg_match('/ایمیل|email/', $n)) {
                $map['email'] = $letter;
            } elseif (preg_match('/آدرس|address/', $n)) {
                $map['address'] = $letter;
            } elseif (preg_match('/معرف|referr/', $n)) {
                $map['referrer'] = $letter;
            } elseif (preg_match('/جنسیت|gender/', $n)) {
                $map['gender'] = $letter;
            }
        }
        return $map;
    }

    /** Default mapping for Sarva «پرونده» sheet. */
    public function sarvaParvandehMapping(): array
    {
        return [
            'file_number' => 'A',
            'first_name' => 'B',
            'last_name' => 'C',
            'father_name' => 'D',
            'birth_year_jalali' => 'E',
            'mobile' => 'F',
            'landline' => 'G',
            'referrer' => 'H',
        ];
    }

    /**
     * @param array<string, string> $mapping
     * @return array{ok:bool, report:array}
     */
    public function preview(string $absolutePath, int $sheetIndex, array $mapping, string $mode = self::MODE_ADD_NEW): array
    {
        return $this->run($absolutePath, $sheetIndex, $mapping, $mode, 0, true);
    }

    /**
     * @param array<string, string> $mapping
     * @return array{ok:bool, report:array}
     */
    public function import(int $importId, string $absolutePath, int $sheetIndex, array $mapping, string $mode = self::MODE_ADD_NEW): array
    {
        if (!in_array($mode, [self::MODE_ADD_NEW, self::MODE_REVIEW_CHANGES, self::MODE_UPDATE], true)) {
            $mode = self::MODE_ADD_NEW;
        }
        return $this->run($absolutePath, $sheetIndex, $mapping, $mode, $importId, false);
    }

    /** Absolute path for per-import NDJSON row cache (shared-host friendly chunks). */
    public function cachePath(int $importId): string
    {
        return dirname(__DIR__, 2) . '/storage/imports/cache_' . $importId . '.ndjson';
    }

    /**
     * One-time prepare: parse Excel once, write lightweight NDJSON cache, no patient inserts yet.
     * Designed for shared cPanel — keeps each HTTP request short.
     *
     * @param array<string, string> $mapping
     * @return array{ok:bool, total:int, cache_rows:int, report:array, message?:string}
     */
    public function prepareBulk(int $importId, string $absolutePath, int $sheetIndex, array $mapping): array
    {
        @set_time_limit(120);
        @ini_set('memory_limit', '256M');

        $report = $this->emptyReport(self::MODE_ADD_NEW);
        $cache = $this->cachePath($importId);
        $dir = dirname($cache);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        try {
            $spreadsheet = $this->loadSpreadsheet($absolutePath);
            $sheet = $spreadsheet->getSheet($sheetIndex);
            $rows = $sheet->toArray(null, false, false, true);
            $worksheet = $sheet->getTitle();
            $report['worksheet'] = $worksheet;
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $sheet);
        } catch (\Throwable $e) {
            $this->markImportFailed($importId, $e->getMessage());
            return ['ok' => false, 'total' => 0, 'cache_rows' => 0, 'report' => $report, 'message' => $e->getMessage()];
        }

        if ($mapping === []) {
            $mapping = $this->guessMapping($rows[1] ?? []);
            if ($this->isPatientSheet($worksheet, $rows[1] ?? [])) {
                $mapping = array_merge($this->sarvaParvandehMapping(), $mapping);
            }
        }

        $fh = fopen($cache, 'wb');
        if ($fh === false) {
            return ['ok' => false, 'total' => 0, 'cache_rows' => 0, 'report' => $report, 'message' => 'امکان ایجاد فایل موقت واردسازی نیست.'];
        }

        $seenFile = [];
        $cacheRows = 0;
        foreach ($rows as $num => $row) {
            if ((int) $num === 1 || $this->rowEmpty($row)) {
                continue;
            }
            $report['total']++;
            $parsed = $this->parseRow($row, $mapping);

            if ($parsed['file_number'] === null && $parsed['mobile'] === null && $parsed['national_id'] === null
                && $parsed['first_name'] === '' && $parsed['last_name'] === '') {
                $report['skipped']++;
                continue;
            }
            if ($parsed['file_number'] === null && $parsed['mobile'] === null && $parsed['national_id'] === null
                && ($parsed['first_name'] === '' || $parsed['last_name'] === '')) {
                $report['invalid']++;
                if (count($report['invalid_preview']) < 15) {
                    $report['invalid_preview'][] = ['row' => $num, 'reason' => 'شناسه کافی نیست'];
                }
                continue;
            }

            $fileKey = (string) ($parsed['file_number'] ?? '');
            if ($fileKey !== '') {
                if (isset($seenFile[$fileKey])) {
                    $report['excel_duplicates']++;
                    $report['duplicates']++;
                    if (count($report['excel_dup_preview']) < 15) {
                        $report['excel_dup_preview'][] = [
                            'row' => $num,
                            'file_number' => $fileKey,
                            'name' => trim(($parsed['first_name'] ?? '') . ' ' . ($parsed['last_name'] ?? '')),
                        ];
                    }
                    continue;
                }
                $seenFile[$fileKey] = true;
            }

            if ($parsed['first_name'] === '') {
                $parsed['first_name'] = 'نامشخص';
            }
            if ($parsed['last_name'] === '') {
                $parsed['last_name'] = 'نامشخص';
            }

            $line = json_encode(['r' => (int) $num, 'p' => $parsed], JSON_UNESCAPED_UNICODE);
            if ($line !== false) {
                fwrite($fh, $line . "\n");
                $cacheRows++;
            }
        }
        fclose($fh);
        unset($rows, $seenFile);

        $state = [
            'report' => $report,
            'cache_rows' => $cacheRows,
            'offset' => 0,
            'worksheet' => $worksheet,
            'mode' => self::MODE_ADD_NEW,
            'prepared_at' => date('c'),
        ];
        $this->safeUpdateImportStatus($importId, $worksheet, self::MODE_ADD_NEW, 'processing');
        $this->persistImportProgress($importId, $report + ['_chunk' => $state], 'processing');

        return [
            'ok' => true,
            'total' => $report['total'],
            'cache_rows' => $cacheRows,
            'report' => $report,
        ];
    }

    /**
     * Process the next chunk of prepared rows (add_new only). Short requests for shared hosting.
     *
     * @return array{ok:bool, done:bool, processed:int, total:int, percent:int, report:array, message?:string}
     */
    public function processChunk(int $importId, int $chunkSize = 200): array
    {
        @set_time_limit(60);
        @ini_set('memory_limit', '256M');
        $chunkSize = max(50, min(400, $chunkSize));

        $st = $this->db->prepare('SELECT * FROM excel_imports WHERE id=?');
        $st->execute([$importId]);
        $import = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$import) {
            return ['ok' => false, 'done' => true, 'processed' => 0, 'total' => 0, 'percent' => 0, 'report' => $this->emptyReport(), 'message' => 'ورود یافت نشد.'];
        }

        $payload = json_decode((string) ($import['report_json'] ?? ''), true) ?: [];
        $chunk = $payload['_chunk'] ?? null;
        $report = $payload;
        unset($report['_chunk']);
        if (!is_array($chunk)) {
            // Resume from older report shape
            $report = array_merge($this->emptyReport(), $report);
            return ['ok' => false, 'done' => true, 'processed' => 0, 'total' => 0, 'percent' => 100, 'report' => $report, 'message' => 'کش واردسازی آماده نیست؛ دوباره شروع کنید.'];
        }

        $report = array_merge($this->emptyReport((string) ($chunk['mode'] ?? self::MODE_ADD_NEW)), $report);
        $cacheRows = (int) ($chunk['cache_rows'] ?? 0);
        $offset = (int) ($chunk['offset'] ?? 0);
        $worksheet = (string) ($chunk['worksheet'] ?? 'پرونده');
        $cache = $this->cachePath($importId);

        if ($cacheRows <= 0 || !is_file($cache)) {
            $this->persistImportProgress($importId, $report, 'completed');
            @unlink($cache);
            return ['ok' => true, 'done' => true, 'processed' => $offset, 'total' => 0, 'percent' => 100, 'report' => $report];
        }

        $byFile = $this->preloadPatientsByFileNumber();
        $byNid = $this->preloadPatientsByNationalId();

        $fh = fopen($cache, 'rb');
        if ($fh === false) {
            return ['ok' => false, 'done' => false, 'processed' => $offset, 'total' => $cacheRows, 'percent' => 0, 'report' => $report, 'message' => 'خواندن کش ناموفق بود.'];
        }

        // Skip already-processed lines.
        for ($i = 0; $i < $offset; $i++) {
            if (fgets($fh) === false) {
                break;
            }
        }

        $processedThis = 0;
        $inTx = false;
        try {
            $this->db->beginTransaction();
            $inTx = true;
        } catch (\Throwable) {
            $inTx = false;
        }

        try {
            while ($processedThis < $chunkSize && ($line = fgets($fh)) !== false) {
                $offset++;
                $processedThis++;
                $item = json_decode(trim($line), true);
                if (!is_array($item) || empty($item['p']) || !is_array($item['p'])) {
                    $report['skipped']++;
                    continue;
                }
                $parsed = $item['p'];
                $rowNum = (int) ($item['r'] ?? $offset);

                $match = $this->matchFromCache(
                    $byFile,
                    $byNid,
                    isset($parsed['file_number']) ? (string) $parsed['file_number'] : null,
                    isset($parsed['national_id']) ? (string) $parsed['national_id'] : null
                );
                if ($match) {
                    $report['duplicates']++;
                    continue;
                }

                $newId = $this->insertPatient($parsed, $importId, $rowNum, $worksheet);
                $report['imported']++;
                if (count($report['new_preview']) < 20) {
                    $report['new_preview'][] = ['row' => $rowNum, 'data' => $parsed, 'id' => $newId];
                }

                $cached = [
                    'id' => $newId,
                    'matched_by' => 'file_number',
                    'conflict' => null,
                    'first_name' => $parsed['first_name'] ?? '',
                    'last_name' => $parsed['last_name'] ?? '',
                    'father_name' => $parsed['father_name'] ?? null,
                    'mobile' => $parsed['mobile'] ?? null,
                    'landline' => $parsed['landline'] ?? null,
                    'referrer' => $parsed['referrer'] ?? null,
                    'national_id' => $parsed['national_id'] ?? null,
                ];
                $fk = (string) ($parsed['file_number'] ?? '');
                if ($fk !== '') {
                    $byFile[$fk] = $cached;
                }
                $nk = (string) ($parsed['national_id'] ?? '');
                if ($nk !== '') {
                    $byNid[$nk] = $cached + ['matched_by' => 'national_id'];
                }
            }
            if ($inTx) {
                $this->db->commit();
                $inTx = false;
            }
        } catch (\Throwable $e) {
            if ($inTx) {
                try {
                    $this->db->rollBack();
                } catch (\Throwable) {
                }
            }
            fclose($fh);
            $this->markImportFailed($importId, $e->getMessage(), $report);
            return [
                'ok' => false,
                'done' => false,
                'processed' => $offset,
                'total' => $cacheRows,
                'percent' => $cacheRows > 0 ? (int) floor(($offset / $cacheRows) * 100) : 0,
                'report' => $report,
                'message' => $e->getMessage(),
            ];
        }
        fclose($fh);

        $done = $offset >= $cacheRows;
        $chunk['offset'] = $offset;
        $save = $report + ['_chunk' => $chunk];
        if ($done) {
            unset($save['_chunk']);
            $this->persistImportProgress($importId, $report, 'completed');
            @unlink($cache);
            audit('patients.import_completed', 'excel_imports', $importId, [
                'imported' => $report['imported'],
                'duplicates' => $report['duplicates'],
                'mode' => self::MODE_ADD_NEW,
                'chunked' => true,
            ]);
        } else {
            $this->persistImportProgress($importId, $save, 'processing');
        }

        $percent = $cacheRows > 0 ? (int) min(100, floor(($offset / $cacheRows) * 100)) : 100;
        return [
            'ok' => true,
            'done' => $done,
            'processed' => $offset,
            'total' => $cacheRows,
            'percent' => $percent,
            'report' => $report,
        ];
    }

    /** @return array<string, mixed> */
    private function emptyReport(string $mode = self::MODE_ADD_NEW): array
    {
        return [
            'total' => 0,
            'imported' => 0,
            'updated' => 0,
            'duplicates' => 0,
            'excel_duplicates' => 0,
            'skipped' => 0,
            'invalid' => 0,
            'conflicts' => 0,
            'new_preview' => [],
            'existing_preview' => [],
            'invalid_preview' => [],
            'conflict_preview' => [],
            'excel_dup_preview' => [],
            'mode' => $mode,
            'worksheet' => '',
        ];
    }

    /**
     * Import radiography index rows as document stubs (optional second pass).
     * Links by unique full-name match only; otherwise stores unlinked.
     */
    public function importRadiographySheet(int $importId, string $absolutePath): array
    {
        $spreadsheet = $this->loadSpreadsheet($absolutePath);
        $sheet = null;
        foreach ($spreadsheet->getAllSheets() as $s) {
            if (str_contains($s->getTitle(), 'رادیو')) {
                $sheet = $s;
                break;
            }
        }
        if (!$sheet) {
            return ['ok' => false, 'message' => 'برگه رادیوگرافی یافت نشد.', 'linked' => 0, 'unlinked' => 0];
        }
        $rows = $sheet->toArray(null, true, true, true);
        $linked = 0;
        $unlinked = 0;
        $ins = $this->db->prepare(
            'INSERT INTO patient_documents
             (patient_id, category, title, description, file_path, original_name, document_date, source, import_id, external_ref, uploaded_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        foreach ($rows as $num => $row) {
            if ((int) $num === 1) {
                continue;
            }
            $photoNo = trim((string) ($row['T'] ?? ''));
            $last = normalize_persian_text(trim((string) ($row['U'] ?? '')));
            $first = normalize_persian_text(trim((string) ($row['V'] ?? '')));
            if ($photoNo === '' && $first === '' && $last === '') {
                continue;
            }
            $patientId = null;
            if ($first !== '' && $last !== '') {
                $patientId = $this->findUniqueByName($first, $last);
            }
            $title = 'رادیوگرافی #' . ($photoNo !== '' ? $photoNo : $num);
            $desc = trim($first . ' ' . $last);
            $ins->execute([
                $patientId,
                'radiography',
                $title,
                $desc !== '' ? $desc : null,
                'import://radiography/' . $photoNo,
                $title,
                null,
                'excel',
                $importId,
                $photoNo !== '' ? 'RAD-' . $photoNo : null,
                null,
            ]);
            if ($patientId) {
                $linked++;
            } else {
                $unlinked++;
            }
        }
        return ['ok' => true, 'linked' => $linked, 'unlinked' => $unlinked];
    }

    /**
     * @param array<string, string> $mapping
     * @return array{ok:bool, report:array, message?:string}
     */
    private function run(string $absolutePath, int $sheetIndex, array $mapping, string $mode, int $importId, bool $dryRun): array
    {
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        $report = [
            'total' => 0,
            'imported' => 0,
            'updated' => 0,
            'duplicates' => 0,
            'excel_duplicates' => 0,
            'skipped' => 0,
            'invalid' => 0,
            'conflicts' => 0,
            'new_preview' => [],
            'existing_preview' => [],
            'invalid_preview' => [],
            'conflict_preview' => [],
            'excel_dup_preview' => [],
            'mode' => $mode,
            'worksheet' => '',
        ];

        try {
            $spreadsheet = $this->loadSpreadsheet($absolutePath);
            $sheet = $spreadsheet->getSheet($sheetIndex);
            // Data only — do not calculate formulas / format (major speed win on large files).
            $rows = $sheet->toArray(null, false, false, true);
            $worksheet = $sheet->getTitle();
            $report['worksheet'] = $worksheet;
            // Free spreadsheet memory before the DB loop.
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $sheet);
        } catch (\Throwable $e) {
            if (!$dryRun && $importId > 0) {
                $this->markImportFailed($importId, $e->getMessage());
            }
            return ['ok' => false, 'report' => $report, 'message' => 'خواندن فایل اکسل ناموفق: ' . $e->getMessage()];
        }

        if ($mapping === []) {
            $mapping = $this->guessMapping($rows[1] ?? []);
            if ($this->isPatientSheet($worksheet, $rows[1] ?? [])) {
                $mapping = array_merge($this->sarvaParvandehMapping(), $mapping);
            }
        }

        // One query instead of thousands of per-row SELECTs.
        $byFile = $this->preloadPatientsByFileNumber();
        $byNid = $this->preloadPatientsByNationalId();

        $rowIns = null;
        $conflictIns = null;
        $inTx = false;
        if (!$dryRun && $importId > 0) {
            // Only persist problem rows — logging every duplicate with full JSON was timing out shared hosts.
            $rowIns = $this->db->prepare(
                'INSERT INTO excel_import_rows (import_id, `row_number`, raw_json, status, message) VALUES (?,?,?,?,?)'
            );
            $conflictIns = $this->db->prepare(
                'INSERT INTO excel_import_conflicts (import_id, `row_number`, match_type, existing_patient_id, excel_json, existing_json, status)
                 VALUES (?,?,?,?,?,?,?)'
            );
            $this->safeUpdateImportStatus($importId, $worksheet, $mode, 'processing');
            try {
                $this->db->beginTransaction();
                $inTx = true;
            } catch (\Throwable) {
                $inTx = false;
            }
        }

        $sinceFlush = 0;
        /** @var array<string, true> */
        $seenFileInExcel = [];
        try {
            foreach ($rows as $num => $row) {
                if ((int) $num === 1) {
                    continue;
                }
                if ($this->rowEmpty($row)) {
                    continue;
                }
                $report['total']++;

                $parsed = $this->parseRow($row, $mapping);

                if ($parsed['file_number'] === null && $parsed['mobile'] === null && $parsed['national_id'] === null
                    && $parsed['first_name'] === '' && $parsed['last_name'] === '') {
                    $report['skipped']++;
                    continue;
                }

                if ($parsed['file_number'] === null && $parsed['mobile'] === null && $parsed['national_id'] === null) {
                    if ($parsed['first_name'] === '' || $parsed['last_name'] === '') {
                        $report['invalid']++;
                        if (count($report['invalid_preview']) < 20) {
                            $report['invalid_preview'][] = ['row' => $num, 'reason' => 'شناسه کافی نیست', 'data' => $parsed];
                        }
                        if (!$dryRun) {
                            $rowIns?->execute([
                                $importId, $num,
                                json_encode($parsed, JSON_UNESCAPED_UNICODE),
                                'invalid',
                                'شناسه کافی (پرونده/موبایل/کدملی) نیست',
                            ]);
                        }
                        continue;
                    }
                }

                // Same شماره پرونده twice in the Excel → one patient only (DB unique key).
                $fileKey = (string) ($parsed['file_number'] ?? '');
                if ($fileKey !== '') {
                    if (isset($seenFileInExcel[$fileKey])) {
                        $report['duplicates']++;
                        $report['excel_duplicates']++;
                        if (count($report['excel_dup_preview']) < 20) {
                            $report['excel_dup_preview'][] = [
                                'row' => $num,
                                'file_number' => $fileKey,
                                'name' => trim(($parsed['first_name'] ?? '') . ' ' . ($parsed['last_name'] ?? '')),
                            ];
                        }
                        continue;
                    }
                    $seenFileInExcel[$fileKey] = true;
                }

                $match = $this->matchFromCache($byFile, $byNid, $parsed['file_number'], $parsed['national_id']);

                if ($match) {
                    $existingId = (int) $match['id'];
                    $diff = $this->diffFields($match, $parsed);

                    if (!empty($match['conflict'])) {
                        $report['conflicts']++;
                        if (count($report['conflict_preview']) < 20) {
                            $report['conflict_preview'][] = [
                                'row' => $num,
                                'reason' => $match['conflict'],
                                'excel' => $parsed,
                                'existing_id' => $existingId,
                            ];
                        }
                        if (!$dryRun) {
                            $payload = json_encode($parsed, JSON_UNESCAPED_UNICODE);
                            $rowIns?->execute([$importId, $num, $payload, 'conflict', $match['conflict']]);
                            $conflictIns?->execute([
                                $importId, $num, 'ambiguous', $existingId, $payload,
                                json_encode(['id' => $existingId, 'matched_by' => $match['matched_by'] ?? null], JSON_UNESCAPED_UNICODE),
                                'pending',
                            ]);
                        }
                        continue;
                    }

                    if ($mode === self::MODE_ADD_NEW) {
                        $report['duplicates']++;
                        if (count($report['existing_preview']) < 15) {
                            $report['existing_preview'][] = [
                                'row' => $num,
                                'patient_id' => $existingId,
                                'match' => $match['matched_by'] ?? 'file_number',
                            ];
                        }
                        continue;
                    }

                    if ($mode === self::MODE_REVIEW_CHANGES) {
                        if ($diff) {
                            $report['conflicts']++;
                            if (count($report['conflict_preview']) < 25) {
                                $report['conflict_preview'][] = [
                                    'row' => $num,
                                    'patient_id' => $existingId,
                                    'diff' => $diff,
                                    'excel' => $parsed,
                                ];
                            }
                            if (!$dryRun) {
                                $payload = json_encode($parsed, JSON_UNESCAPED_UNICODE);
                                $rowIns?->execute([$importId, $num, $payload, 'conflict', 'تفاوت با پرونده موجود']);
                                $conflictIns?->execute([
                                    $importId, $num, 'field_diff', $existingId, $payload,
                                    json_encode(['patient_id' => $existingId, 'diff' => $diff], JSON_UNESCAPED_UNICODE),
                                    'pending',
                                ]);
                            }
                        } else {
                            $report['duplicates']++;
                        }
                        continue;
                    }

                    if (!$dryRun) {
                        $this->updatePatient($existingId, $parsed);
                    }
                    $report['updated']++;
                    $sinceFlush++;
                    continue;
                }

                if ($parsed['first_name'] === '') {
                    $parsed['first_name'] = 'نامشخص';
                }
                if ($parsed['last_name'] === '') {
                    $parsed['last_name'] = 'نامشخص';
                }

                if (count($report['new_preview']) < 20) {
                    $report['new_preview'][] = ['row' => $num, 'data' => $parsed];
                }

                if (!$dryRun) {
                    $newId = $this->insertPatient($parsed, $importId, (int) $num, $worksheet);
                    $cached = [
                        'id' => $newId,
                        'matched_by' => 'file_number',
                        'conflict' => null,
                        'first_name' => $parsed['first_name'],
                        'last_name' => $parsed['last_name'],
                        'father_name' => $parsed['father_name'],
                        'mobile' => $parsed['mobile'],
                        'landline' => $parsed['landline'],
                        'referrer' => $parsed['referrer'],
                        'national_id' => $parsed['national_id'],
                    ];
                    $fileKey = (string) ($parsed['file_number'] ?? '');
                    if ($fileKey !== '') {
                        $byFile[$fileKey] = $cached;
                    }
                    $nidKey = (string) ($parsed['national_id'] ?? '');
                    if ($nidKey !== '') {
                        $byNid[$nidKey] = $cached + ['matched_by' => 'national_id'];
                    }
                    $sinceFlush++;
                }
                $report['imported']++;

                // Commit in chunks so a timeout still keeps partial progress.
                if ($inTx && $sinceFlush >= 150) {
                    $this->db->commit();
                    $this->persistImportProgress($importId, $report, 'processing');
                    $this->db->beginTransaction();
                    $sinceFlush = 0;
                }
            }

            if ($inTx) {
                $this->db->commit();
                $inTx = false;
            }

            if (!$dryRun && $importId > 0) {
                $this->persistImportProgress($importId, $report, 'completed');
                audit('patients.import_completed', 'excel_imports', $importId, [
                    'imported' => $report['imported'],
                    'duplicates' => $report['duplicates'],
                    'mode' => $mode,
                ]);
            }

            return ['ok' => true, 'report' => $report];
        } catch (\Throwable $e) {
            if ($inTx) {
                try {
                    $this->db->rollBack();
                } catch (\Throwable) {
                }
            }
            if (!$dryRun && $importId > 0) {
                $this->persistImportProgress($importId, $report, 'failed');
                $this->markImportFailed($importId, $e->getMessage(), $report);
            }
            return ['ok' => false, 'report' => $report, 'message' => $e->getMessage()];
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function preloadPatientsByFileNumber(): array
    {
        $map = [];
        try {
            $st = $this->db->query(
                "SELECT id, file_number, first_name, last_name, father_name, mobile, landline, referrer, national_id
                 FROM patients WHERE deleted_at IS NULL AND file_number IS NOT NULL AND file_number <> ''"
            );
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $key = (string) $row['file_number'];
                if (isset($map[$key])) {
                    $map[$key]['conflict'] = 'چند بیمار با یک شماره پرونده';
                    continue;
                }
                $map[$key] = $row + [
                    'id' => (int) $row['id'],
                    'matched_by' => 'file_number',
                    'conflict' => null,
                ];
            }
        } catch (\Throwable) {
        }
        return $map;
    }

    /** @return array<string, array<string, mixed>> */
    private function preloadPatientsByNationalId(): array
    {
        $map = [];
        try {
            $st = $this->db->query(
                "SELECT id, file_number, first_name, last_name, father_name, mobile, landline, referrer, national_id
                 FROM patients WHERE deleted_at IS NULL AND national_id IS NOT NULL AND national_id <> ''"
            );
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $key = (string) $row['national_id'];
                if (!isset($map[$key])) {
                    $map[$key] = $row + [
                        'id' => (int) $row['id'],
                        'matched_by' => 'national_id',
                        'conflict' => null,
                    ];
                }
            }
        } catch (\Throwable) {
        }
        return $map;
    }

    /**
     * @param array<string, array<string, mixed>> $byFile
     * @param array<string, array<string, mixed>> $byNid
     * @return array<string, mixed>|null
     */
    private function matchFromCache(array $byFile, array $byNid, ?string $file, ?string $nid): ?array
    {
        if ($file !== null && $file !== '' && isset($byFile[$file])) {
            return $byFile[$file];
        }
        if ($nid !== null && $nid !== '' && isset($byNid[$nid])) {
            return $byNid[$nid];
        }
        return null;
    }

    private function safeUpdateImportStatus(int $importId, string $worksheet, string $mode, string $status): void
    {
        try {
            $this->db->prepare('UPDATE excel_imports SET worksheet_name=?, import_mode=?, status=? WHERE id=?')
                ->execute([$worksheet, $mode, $status, $importId]);
        } catch (\Throwable) {
            try {
                $this->db->prepare('UPDATE excel_imports SET status=? WHERE id=?')->execute(['previewed', $importId]);
            } catch (\Throwable) {
            }
        }
    }

    /** @param array<string, mixed> $report */
    private function persistImportProgress(int $importId, array $report, string $status): void
    {
        try {
            $this->db->prepare(
                'UPDATE excel_imports SET total_rows=?, imported_rows=?, updated_rows=?, duplicate_rows=?, skipped_rows=?, invalid_rows=?, conflict_rows=?, status=?, report_json=? WHERE id=?'
            )->execute([
                $report['total'],
                $report['imported'],
                $report['updated'],
                $report['duplicates'],
                $report['skipped'],
                $report['invalid'],
                $report['conflicts'],
                $status,
                json_encode($report, JSON_UNESCAPED_UNICODE),
                $importId,
            ]);
        } catch (\Throwable) {
            try {
                $this->db->prepare(
                    'UPDATE excel_imports SET total_rows=?, imported_rows=?, updated_rows=?, duplicate_rows=?, skipped_rows=?, invalid_rows=?, status=?, report_json=? WHERE id=?'
                )->execute([
                    $report['total'],
                    $report['imported'],
                    $report['updated'],
                    $report['duplicates'],
                    $report['skipped'],
                    $report['invalid'],
                    $status === 'processing' ? 'previewed' : $status,
                    json_encode($report, JSON_UNESCAPED_UNICODE),
                    $importId,
                ]);
            } catch (\Throwable) {
            }
        }
    }

    /** @param array<string, mixed>|null $report */
    private function markImportFailed(int $importId, string $message, ?array $report = null): void
    {
        $payload = $report ?? [];
        $payload['error'] = mb_substr($message, 0, 500);
        try {
            $this->db->prepare('UPDATE excel_imports SET status=?, report_json=? WHERE id=?')
                ->execute(['failed', json_encode($payload, JSON_UNESCAPED_UNICODE), $importId]);
        } catch (\Throwable) {
        }
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, string> $mapping
     * @return array<string, mixed>
     */
    private function parseRow(array $row, array $mapping): array
    {
        $get = function (string $field) use ($row, $mapping): string {
            if (empty($mapping[$field])) {
                return '';
            }
            return trim((string) ($row[$mapping[$field]] ?? ''));
        };

        $first = normalize_persian_text($get('first_name'));
        $last = normalize_persian_text($get('last_name'));
        $father = normalize_persian_text($get('father_name'));
        $gender = null;
        $firstNorm = preg_replace('/\s+/u', '', $first) ?? '';
        if ($firstNorm === 'خانم' || $firstNorm === 'خانوم') {
            $gender = 'female';
            $first = '';
        } elseif ($firstNorm === 'آقا' || $firstNorm === 'اقا' || $firstNorm === 'اقای' || $firstNorm === 'آقای') {
            $gender = 'male';
            $first = '';
        } elseif (preg_match('/^(آقا|اقا|آقای|اقای)[\s‌]/u', $first)) {
            $gender = 'male';
            $first = normalize_persian_text(preg_replace('/^(آقا|اقا|آقای|اقای)[\s‌]*/u', '', $first) ?? $first);
        }
        $gRaw = mb_strtolower($get('gender'));
        if (in_array($gRaw, ['female', 'زن', 'خانم'], true)) {
            $gender = 'female';
        } elseif (in_array($gRaw, ['male', 'مرد', 'آقا'], true)) {
            $gender = 'male';
        }

        $file = normalize_digits($get('file_number'));
        $file = $file !== '' ? ltrim($file, '0') ?: $file : null;
        // keep original numeric string without decimals
        if ($file !== null && str_contains($file, '.')) {
            $file = (string) (int) round((float) $file);
        }

        $mobile = normalize_mobile(normalize_digits($get('mobile')));
        $landline = normalize_digits($get('landline')) ?: null;
        if ($landline !== null) {
            $landline = mb_substr($landline, 0, 40);
        }
        $nid = normalize_digits($get('national_id')) ?: null;
        if ($nid !== null && !preg_match('/^\d{10}$/', $nid)) {
            $nid = null;
        }

        $by = normalize_digits($get('birth_year_jalali'));
        $birthYear = null;
        $birthDate = null;
        if ($by !== '' && ctype_digit($by)) {
            $y = (int) $by;
            if ($y >= 1 && $y <= 99) {
                $y += 1300; // Jalali short year → 13xx
            }
            if ($y >= 1270 && $y <= 1450) {
                $birthYear = $y;
                // Approximate Gregorian Jan 1 of corresponding year (rough)
                $gy = $y + 621;
                if (checkdate(3, 21, $gy) || checkdate(1, 1, $gy)) {
                    $birthDate = sprintf('%04d-01-01', $gy);
                }
            }
        }
        $bdRaw = $get('birth_date');
        if ($bdRaw !== '') {
            $ts = strtotime(normalize_digits($bdRaw));
            if ($ts) {
                $birthDate = date('Y-m-d', $ts);
            }
        }

        return [
            'file_number' => $file,
            'first_name' => $first,
            'last_name' => $last,
            'father_name' => $father !== '' ? $father : null,
            'mobile' => $mobile,
            'landline' => $landline,
            'national_id' => $nid,
            'email' => $get('email') ?: null,
            'address' => $get('address') ?: null,
            'referrer' => $get('referrer') !== '' ? mb_substr($get('referrer'), 0, 190) : null,
            'gender' => $gender,
            'birth_year_jalali' => $birthYear,
            'birth_date' => $birthDate,
        ];
    }

    /**
     * Match existing patient by file_number (preferred) or national_id.
     * Mobile is never used as identity — family members may share one number.
     *
     * @return array<string, mixed>|null
     */
    private function findExistingDetailed(?string $mobile, ?string $file, ?string $nid): ?array
    {
        unset($mobile); // contact field only

        if ($file) {
            $st = $this->db->prepare('SELECT * FROM patients WHERE file_number=? AND deleted_at IS NULL LIMIT 2');
            $st->execute([$file]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) > 1) {
                return ['id' => (int) $rows[0]['id'], 'matched_by' => 'file_number', 'conflict' => 'چند بیمار با یک شماره پرونده', 'rows' => $rows] + $rows[0];
            }
            if ($rows) {
                return $rows[0] + ['id' => (int) $rows[0]['id'], 'matched_by' => 'file_number', 'conflict' => null];
            }
        }

        if ($nid) {
            $st = $this->db->prepare('SELECT * FROM patients WHERE national_id=? AND deleted_at IS NULL LIMIT 1');
            $st->execute([$nid]);
            $byNid = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($byNid) {
                return $byNid + ['id' => (int) $byNid['id'], 'matched_by' => 'national_id', 'conflict' => null];
            }
        }

        return null;
    }

    /** @param array<string, mixed> $existing @param array<string, mixed> $parsed */
    private function diffFields(array $existing, array $parsed): array
    {
        $diff = [];
        foreach (['first_name', 'last_name', 'father_name', 'mobile', 'landline', 'referrer', 'national_id'] as $k) {
            $a = trim((string) ($existing[$k] ?? ''));
            $b = trim((string) ($parsed[$k] ?? ''));
            if ($b !== '' && $a !== '' && $a !== $b) {
                $diff[$k] = ['existing' => $a, 'excel' => $b];
            }
        }
        return $diff;
    }

    /** @param array<string, mixed> $p */
    private function insertPatient(array $p, int $importId, int $rowNum, string $worksheet): int
    {
        $code = 'P' . strtoupper(bin2hex(random_bytes(4)));
        $file = $p['file_number'];
        if ($file) {
            $chk = $this->db->prepare('SELECT id FROM patients WHERE file_number=? LIMIT 1');
            $chk->execute([$file]);
            if ($chk->fetchColumn()) {
                $file = $file . '-R' . $rowNum;
            }
        }

        $mobile = $p['mobile'];
        $sql = 'INSERT INTO patients
             (public_code, file_number, first_name, last_name, father_name, mobile, landline, national_id,
              birth_date, birth_year_jalali, gender, email, address, referrer,
              is_imported, import_id, import_row_number, import_worksheet, profile_completed, is_active)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?,?,0,1)';
        $params = [
            $code,
            $file,
            $p['first_name'],
            $p['last_name'],
            $p['father_name'],
            $mobile,
            $p['landline'],
            $p['national_id'],
            $p['birth_date'],
            $p['birth_year_jalali'],
            $p['gender'],
            $p['email'],
            $p['address'],
            $p['referrer'],
            $importId > 0 ? $importId : null,
            $rowNum,
            $worksheet,
        ];

        try {
            $this->db->prepare($sql)->execute($params);
        } catch (\PDOException $e) {
            // Last resort if UNIQUE(mobile) still exists on host: keep the patient, drop colliding mobile into secondary.
            if ($mobile && str_contains(strtolower($e->getMessage()), 'mobile')) {
                $params[5] = null; // mobile
                $this->db->prepare($sql)->execute($params);
                $newId = (int) $this->db->lastInsertId();
                try {
                    $this->db->prepare('UPDATE patients SET secondary_mobile=COALESCE(secondary_mobile, ?) WHERE id=?')
                        ->execute([$mobile, $newId]);
                } catch (\Throwable) {
                }
                return $newId;
            }
            throw $e;
        }

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $p */
    private function updatePatient(int $id, array $p): void
    {
        $this->db->prepare(
            'UPDATE patients SET
                first_name=COALESCE(NULLIF(?, ""), first_name),
                last_name=COALESCE(NULLIF(?, ""), last_name),
                father_name=COALESCE(?, father_name),
                mobile=COALESCE(?, mobile),
                landline=COALESCE(?, landline),
                national_id=COALESCE(?, national_id),
                birth_year_jalali=COALESCE(?, birth_year_jalali),
                birth_date=COALESCE(?, birth_date),
                gender=COALESCE(?, gender),
                referrer=COALESCE(?, referrer),
                email=COALESCE(NULLIF(?, ""), email),
                address=COALESCE(NULLIF(?, ""), address),
                is_imported=1
             WHERE id=?'
        )->execute([
            $p['first_name'], $p['last_name'], $p['father_name'], $p['mobile'], $p['landline'],
            $p['national_id'], $p['birth_year_jalali'], $p['birth_date'], $p['gender'], $p['referrer'],
            (string) ($p['email'] ?? ''), (string) ($p['address'] ?? ''), $id,
        ]);
    }

    private function findUniqueByName(string $first, string $last): ?int
    {
        $st = $this->db->prepare(
            'SELECT id FROM patients WHERE first_name=? AND last_name=? AND deleted_at IS NULL LIMIT 2'
        );
        $st->execute([$first, $last]);
        $ids = $st->fetchAll(PDO::FETCH_COLUMN);
        return count($ids) === 1 ? (int) $ids[0] : null;
    }

    /** @param array<string, mixed> $header */
    private function isPatientSheet(string $title, array $header): bool
    {
        if (str_contains($title, 'پرونده')) {
            return true;
        }
        $joined = implode(' ', array_map('strval', $header));
        return str_contains($joined, 'شماره پرونده') && str_contains($joined, 'نام');
    }

    /** @param array<string, mixed> $row */
    private function rowEmpty(array $row): bool
    {
        foreach ($row as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }
        return true;
    }
}
