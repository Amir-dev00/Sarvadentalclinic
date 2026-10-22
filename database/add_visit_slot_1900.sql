-- Make 19:00 a bookable visit start time.
-- AppointmentService::availableSlots() only includes starts where:
--   start + duration_minutes <= doctor_schedules.end_time
-- With the default 30-minute visit, end_time must be 19:30 for a 19:00 start.
--
-- Idempotent: only extends rows that currently close exactly at 19:00.
-- Does not reopen closed days, change inactive schedules, or shorten longer days.
--
-- Run once on production after deploying code (phpMyAdmin / MySQL).
-- Do NOT import or replace the production database dump.

UPDATE doctor_schedules
SET end_time = '19:30:00'
WHERE end_time = '19:00:00'
  AND is_active = 1;

UPDATE clinic_hours
SET close_time = '19:30:00'
WHERE close_time = '19:00:00'
  AND is_open = 1;

-- Optional: update public working-hours text stored in site_settings (not a legacy "settings" table).
UPDATE site_settings
SET `value` = REPLACE(`value`, '۱۰:۰۰ تا ۱۹:۰۰', '۱۰:۰۰ تا ۱۹:۳۰')
WHERE `key` = 'working_hours'
  AND `value` LIKE '%۱۹:۰۰%';
