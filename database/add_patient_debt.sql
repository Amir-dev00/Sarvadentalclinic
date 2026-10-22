-- Safe migration: patient debt + automatic reminder cooldown.
-- Does NOT truncate, drop, or renumber patients / file numbers.
-- Run once in phpMyAdmin (or MySQL client) on the live database.
-- Skip any ALTER that errors with "Duplicate column name" (already applied).
-- The app also auto-adds these columns via PatientDebtService::ensureSchema().

ALTER TABLE `patients`
  ADD COLUMN `debt_amount` DECIMAL(14,0) NOT NULL DEFAULT 0 COMMENT 'Outstanding patient debt (IRT)' AFTER `notes`;

ALTER TABLE `patients`
  ADD COLUMN `debt_reminder_sent_at` DATETIME NULL DEFAULT NULL COMMENT 'Last automatic debt reminder send' AFTER `debt_amount`;

-- Defaults in site_settings (automation OFF until admin enables it):
INSERT INTO `site_settings` (`key`, `value`, `group_name`)
VALUES
  ('debt_sms_auto_enabled', '0', 'sms'),
  ('debt_sms_interval_days', '7', 'sms')
ON DUPLICATE KEY UPDATE `key` = `key`;
