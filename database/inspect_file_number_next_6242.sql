-- Inspect why the next patient file number may appear as 6243 instead of 6242.
-- READ-ONLY diagnostics first. Do NOT run the optional UPDATE until you confirm the row.
-- Does not truncate, reseed, renumber, or touch media.

-- 1) Highest active vs all-rows numeric file numbers
SELECT
  (SELECT COALESCE(MAX(CAST(TRIM(file_number) AS UNSIGNED)), 0)
     FROM patients
    WHERE TRIM(file_number) REGEXP '^[0-9]+$'
      AND deleted_at IS NULL) AS max_active_numeric,
  (SELECT COALESCE(MAX(CAST(TRIM(file_number) AS UNSIGNED)), 0)
     FROM patients
    WHERE TRIM(file_number) REGEXP '^[0-9]+$') AS max_all_numeric;

-- 2) Rows around the disputed range
SELECT id, file_number, first_name, last_name, mobile,
       is_imported, profile_completed, deleted_at, created_at
FROM patients
WHERE file_number IS NOT NULL
  AND TRIM(file_number) REGEXP '^[0-9]+$'
  AND CAST(TRIM(file_number) AS UNSIGNED) BETWEEN 6238 AND 6245
ORDER BY CAST(TRIM(file_number) AS UNSIGNED) DESC, id DESC;

-- 3) Exact holders of 6242 (any digit form)
SELECT id, file_number, first_name, last_name, mobile,
       is_imported, profile_completed, deleted_at, created_at
FROM patients
WHERE TRIM(file_number) IN ('6242', '۶۲۴۲', '٦٢٤٢');

-- Optional corrective action (ONLY after reviewing the SELECT results above):
-- Free an erroneous 6242 so the live sequence continues 6241 → 6242.
-- Prefer renaming soft-deleted holders (keeps the archived patient row):
--
-- UPDATE patients
-- SET file_number = CONCAT('ARCH-', id, '-', file_number)
-- WHERE TRIM(file_number) IN ('6242', '۶۲۴۲', '٦٢٤٢')
--   AND deleted_at IS NOT NULL;
--
-- If 6242 is an active test/auto registration with no clinical history, clear ONLY that id:
--
-- UPDATE patients
-- SET file_number = NULL
-- WHERE id = /* paste id from query 3 */
--   AND TRIM(file_number) IN ('6242', '۶۲۴۲', '٦٢٤٢')
--   AND deleted_at IS NULL
--   AND is_imported = 0
--   AND NOT EXISTS (
--         SELECT 1 FROM appointments a WHERE a.patient_id = patients.id
--   );
