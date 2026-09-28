-- Add 19:00 as a bookable visit start time for patients.
-- Slots require: start + duration <= end_time.
-- With 30-minute visits, end_time must be 19:30 for a 19:00 start to appear.
--
-- Run once on production (phpMyAdmin / MySQL):

UPDATE doctor_schedules
SET end_time = '19:30:00'
WHERE end_time = '19:00:00'
  AND is_active = 1;

UPDATE clinic_hours
SET close_time = '19:30:00'
WHERE close_time = '19:00:00'
  AND is_open = 1;

-- Optional: update public "working hours" text if stored in settings
UPDATE settings
SET setting_value = REPLACE(setting_value, '۱۰:۰۰ تا ۱۹:۰۰', '۱۰:۰۰ تا ۱۹:۳۰')
WHERE setting_key = 'working_hours'
  AND setting_value LIKE '%۱۹:۰۰%';
