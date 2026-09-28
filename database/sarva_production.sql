-- =============================================================================
-- Project : Sarva Dental Clinic (کلینیک دندانپزشکی سروا)
-- Purpose : Production database installation for cPanel / phpMyAdmin
-- Source  : Generated from current project schema (migrations 001–005)
-- Charset : utf8mb4 / utf8mb4_unicode_ci
-- Target  : MySQL 8+ / compatible MariaDB
--
-- WARNING : Importing this file DROPS existing Sarva tables in the selected
--           database. Back up any existing data before import.
--
-- NOTE    : Create the empty database in cPanel first, then import this file
--           into that database. This file does not CREATE DATABASE / USE.
--
-- Default Super Admin — change password immediately after first login.
--   Email: admin@sarvadental.ir
-- =============================================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

-- -----------------------------------------------------------------------------
-- Drop existing tables (safe re-import)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `appointment_status_history`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `sms_queue`;
DROP TABLE IF EXISTS `sms_logs`;
DROP TABLE IF EXISTS `sms_automation_rules`;
DROP TABLE IF EXISTS `patient_notes`;
DROP TABLE IF EXISTS `excel_import_rows`;
DROP TABLE IF EXISTS `excel_imports`;
DROP TABLE IF EXISTS `appointments`;
DROP TABLE IF EXISTS `otp_codes`;
DROP TABLE IF EXISTS `doctor_schedules`;
DROP TABLE IF EXISTS `doctor_time_off`;
DROP TABLE IF EXISTS `case_studies`;
DROP TABLE IF EXISTS `blog_posts`;
DROP TABLE IF EXISTS `blog_categories`;
DROP TABLE IF EXISTS `page_sections`;
DROP TABLE IF EXISTS `menu_items`;
DROP TABLE IF EXISTS `menus`;
DROP TABLE IF EXISTS `media`;
DROP TABLE IF EXISTS `gallery_items`;
DROP TABLE IF EXISTS `testimonials`;
DROP TABLE IF EXISTS `faqs`;
DROP TABLE IF EXISTS `page_views`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `clinic_hours`;
DROP TABLE IF EXISTS `sms_templates`;
DROP TABLE IF EXISTS `site_settings`;
DROP TABLE IF EXISTS `services`;
DROP TABLE IF EXISTS `doctors`;
DROP TABLE IF EXISTS `pages`;
DROP TABLE IF EXISTS `patients`;
DROP TABLE IF EXISTS `admin_users`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `schema_migrations`;

-- -----------------------------------------------------------------------------
-- Schema
-- -----------------------------------------------------------------------------

-- Table: admin_users
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(10) unsigned NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `login_attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_admin_role` (`role_id`),
  CONSTRAINT `fk_admin_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: appointment_status_history
CREATE TABLE IF NOT EXISTS `appointment_status_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` bigint(20) unsigned NOT NULL,
  `from_status` varchar(50) DEFAULT NULL,
  `to_status` varchar(50) NOT NULL,
  `changed_by_admin` int(10) unsigned DEFAULT NULL,
  `changed_by_patient` int(10) unsigned DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_ash_appt` (`appointment_id`),
  CONSTRAINT `fk_ash_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: appointments
CREATE TABLE IF NOT EXISTS `appointments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` int(10) unsigned NOT NULL,
  `doctor_id` int(10) unsigned NOT NULL,
  `service_id` int(10) unsigned NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `status` enum('awaiting_payment','confirmed','completed','cancelled','no_show','expired') NOT NULL DEFAULT 'awaiting_payment',
  `payment_status` enum('unpaid','pending','paid','failed','refunded') NOT NULL DEFAULT 'unpaid',
  `fee_amount` decimal(12,0) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `hold_expires_at` datetime DEFAULT NULL,
  `reminder_sent_at` datetime DEFAULT NULL,
  `created_by_admin` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_appt_service` (`service_id`),
  KEY `idx_doctor_slot` (`doctor_id`,`starts_at`,`status`),
  KEY `idx_appt_starts` (`starts_at`),
  KEY `idx_appt_patient` (`patient_id`),
  KEY `idx_appt_status` (`status`),
  CONSTRAINT `fk_appt_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `fk_appt_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `fk_appt_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: audit_logs
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_user_id` int(10) unsigned DEFAULT NULL,
  `patient_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(120) NOT NULL,
  `entity_type` varchar(80) DEFAULT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `meta_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta_json`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: blog_categories
CREATE TABLE IF NOT EXISTS `blog_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: blog_posts
CREATE TABLE IF NOT EXISTS `blog_posts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned DEFAULT NULL,
  `author_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `excerpt` text DEFAULT NULL,
  `content` mediumtext DEFAULT NULL,
  `cover` varchar(255) DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` varchar(500) DEFAULT NULL,
  `status` enum('draft','published','scheduled','archived') NOT NULL DEFAULT 'draft',
  `published_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `scheduled_at` datetime DEFAULT NULL,
  `reading_time` int(10) unsigned DEFAULT NULL,
  `seo_title` varchar(255) DEFAULT NULL,
  `seo_description` varchar(500) DEFAULT NULL,
  `canonical_url` varchar(255) DEFAULT NULL,
  `og_title` varchar(255) DEFAULT NULL,
  `og_description` varchar(500) DEFAULT NULL,
  `og_image` varchar(255) DEFAULT NULL,
  `robots_index` tinyint(1) NOT NULL DEFAULT 1,
  `robots_follow` tinyint(1) NOT NULL DEFAULT 1,
  `medical_reviewer` varchar(190) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `fk_bp_cat` (`category_id`),
  KEY `fk_bp_author` (`author_id`),
  KEY `idx_blog_status_pub` (`status`,`published_at`),
  KEY `idx_blog_deleted` (`deleted_at`),
  CONSTRAINT `fk_bp_author` FOREIGN KEY (`author_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bp_cat` FOREIGN KEY (`category_id`) REFERENCES `blog_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: case_studies
CREATE TABLE IF NOT EXISTS `case_studies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` mediumtext DEFAULT NULL,
  `before_image` varchar(255) DEFAULT NULL,
  `after_image` varchar(255) DEFAULT NULL,
  `doctor_id` int(10) unsigned DEFAULT NULL,
  `service_id` int(10) unsigned DEFAULT NULL,
  `case_date` date DEFAULT NULL,
  `status` enum('draft','published') NOT NULL DEFAULT 'published',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `fk_cs_doctor` (`doctor_id`),
  KEY `fk_cs_service` (`service_id`),
  CONSTRAINT `fk_cs_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cs_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: clinic_hours
CREATE TABLE IF NOT EXISTS `clinic_hours` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `weekday` tinyint(3) unsigned NOT NULL,
  `is_open` tinyint(1) NOT NULL DEFAULT 1,
  `open_time` time DEFAULT NULL,
  `close_time` time DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `weekday` (`weekday`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: doctor_schedules
CREATE TABLE IF NOT EXISTS `doctor_schedules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `doctor_id` int(10) unsigned NOT NULL,
  `weekday` tinyint(3) unsigned NOT NULL COMMENT '0=Sunday .. 6=Saturday (PHP date w)',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `slot_duration` int(10) unsigned NOT NULL DEFAULT 30,
  `break_start` time DEFAULT NULL,
  `break_end` time DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_ds_doctor_day` (`doctor_id`,`weekday`),
  CONSTRAINT `fk_ds_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: doctor_time_off
CREATE TABLE IF NOT EXISTS `doctor_time_off` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `doctor_id` int(10) unsigned NOT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dto_range` (`doctor_id`,`start_datetime`,`end_datetime`),
  CONSTRAINT `fk_dto_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: doctors
CREATE TABLE IF NOT EXISTS `doctors` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `specialty` varchar(200) DEFAULT NULL,
  `biography` mediumtext DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `education` text DEFAULT NULL,
  `experience` text DEFAULT NULL,
  `social_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`social_json`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: excel_import_rows
CREATE TABLE IF NOT EXISTS `excel_import_rows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `import_id` int(10) unsigned NOT NULL,
  `row_number` int(10) unsigned NOT NULL,
  `raw_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw_json`)),
  `status` enum('pending','imported','updated','duplicate','skipped','invalid') NOT NULL DEFAULT 'pending',
  `message` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_eir_import` (`import_id`),
  CONSTRAINT `fk_eir_import` FOREIGN KEY (`import_id`) REFERENCES `excel_imports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: excel_imports
CREATE TABLE IF NOT EXISTS `excel_imports` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `uploaded_by` int(10) unsigned DEFAULT NULL,
  `total_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `imported_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `updated_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `duplicate_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `skipped_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `invalid_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `status` enum('uploaded','previewed','completed','failed') NOT NULL DEFAULT 'uploaded',
  `report_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`report_json`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_ei_admin` (`uploaded_by`),
  CONSTRAINT `fk_ei_admin` FOREIGN KEY (`uploaded_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: faqs
CREATE TABLE IF NOT EXISTS `faqs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `question` varchar(500) NOT NULL,
  `answer` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: gallery_items
CREATE TABLE IF NOT EXISTS `gallery_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('image','video') NOT NULL DEFAULT 'image',
  `title` varchar(255) DEFAULT NULL,
  `media_path` varchar(255) NOT NULL,
  `thumb_path` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: media
CREATE TABLE IF NOT EXISTS `media` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(120) NOT NULL,
  `size` int(10) unsigned NOT NULL,
  `path` varchar(255) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `uploaded_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_media_admin` (`uploaded_by`),
  CONSTRAINT `fk_media_admin` FOREIGN KEY (`uploaded_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: menu_items
CREATE TABLE IF NOT EXISTS `menu_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `menu_id` int(10) unsigned NOT NULL,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `label` varchar(150) NOT NULL,
  `url` varchar(255) NOT NULL,
  `target` varchar(20) DEFAULT '_self',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_mi_menu` (`menu_id`),
  KEY `fk_mi_parent` (`parent_id`),
  CONSTRAINT `fk_mi_menu` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mi_parent` FOREIGN KEY (`parent_id`) REFERENCES `menu_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: menus
CREATE TABLE IF NOT EXISTS `menus` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `location` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `location` (`location`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: otp_codes
CREATE TABLE IF NOT EXISTS `otp_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mobile` varchar(15) NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `purpose` varchar(50) NOT NULL DEFAULT 'login',
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `max_attempts` int(10) unsigned NOT NULL DEFAULT 5,
  `expires_at` datetime NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_otp_mobile` (`mobile`,`purpose`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: page_sections
CREATE TABLE IF NOT EXISTS `page_sections` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `page_id` int(10) unsigned NOT NULL,
  `section_key` varchar(120) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `cta_text` varchar(120) DEFAULT NULL,
  `cta_link` varchar(255) DEFAULT NULL,
  `extra_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extra_json`)),
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_page_section` (`page_id`,`section_key`),
  CONSTRAINT `fk_section_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: page_views
CREATE TABLE IF NOT EXISTS `page_views` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `path` varchar(255) NOT NULL,
  `viewed_at` datetime NOT NULL,
  `session_hash` char(64) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pv_path` (`path`),
  KEY `idx_pv_day` (`viewed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: pages
CREATE TABLE IF NOT EXISTS `pages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `template` varchar(100) NOT NULL DEFAULT 'page',
  `status` enum('draft','published') NOT NULL DEFAULT 'published',
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` varchar(500) DEFAULT NULL,
  `og_image` varchar(255) DEFAULT NULL,
  `canonical_url` varchar(255) DEFAULT NULL,
  `robots` varchar(50) DEFAULT 'index,follow',
  `content` mediumtext DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: patient_notes
CREATE TABLE IF NOT EXISTS `patient_notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` int(10) unsigned NOT NULL,
  `admin_user_id` int(10) unsigned DEFAULT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_pn_patient` (`patient_id`),
  CONSTRAINT `fk_pn_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: patients
CREATE TABLE IF NOT EXISTS `patients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `public_code` varchar(32) NOT NULL,
  `file_number` varchar(50) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `mobile` varchar(15) NOT NULL,
  `national_id` varchar(20) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact_name` varchar(150) DEFAULT NULL,
  `emergency_contact_mobile` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `preferred_doctor_id` int(10) unsigned DEFAULT NULL,
  `is_imported` tinyint(1) NOT NULL DEFAULT 0,
  `profile_completed` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_code` (`public_code`),
  UNIQUE KEY `mobile` (`mobile`),
  UNIQUE KEY `uq_patients_file_number` (`file_number`),
  KEY `idx_patients_name` (`first_name`,`last_name`),
  KEY `idx_patients_mobile` (`mobile`),
  KEY `idx_patients_national` (`national_id`),
  KEY `idx_patients_last_name` (`last_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: payments
CREATE TABLE IF NOT EXISTS `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` bigint(20) unsigned NOT NULL,
  `patient_id` int(10) unsigned NOT NULL,
  `amount` decimal(12,0) NOT NULL,
  `provider` varchar(50) NOT NULL,
  `authority` varchar(120) NOT NULL,
  `ref_id` varchar(120) DEFAULT NULL,
  `status` enum('initiated','pending','paid','failed','cancelled') NOT NULL DEFAULT 'initiated',
  `raw_request` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw_request`)),
  `raw_verify` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw_verify`)),
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_authority` (`authority`),
  UNIQUE KEY `uq_payment_ref` (`ref_id`),
  KEY `fk_pay_appt` (`appointment_id`),
  KEY `fk_pay_patient` (`patient_id`),
  CONSTRAINT `fk_pay_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`),
  CONSTRAINT `fk_pay_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: permissions
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `group_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: role_permissions
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` int(10) unsigned NOT NULL,
  `permission_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `fk_rp_perm` (`permission_id`),
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: roles
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: schema_migrations
CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(190) NOT NULL,
  `ran_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: services
CREATE TABLE IF NOT EXISTS `services` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `price` decimal(12,0) DEFAULT NULL,
  `duration_minutes` int(10) unsigned DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: site_settings
CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(120) NOT NULL,
  `value` mediumtext DEFAULT NULL,
  `group_name` varchar(80) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sms_automation_rules
CREATE TABLE IF NOT EXISTS `sms_automation_rules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(180) NOT NULL,
  `trigger_type` varchar(40) NOT NULL DEFAULT 'appointment',
  `offset_value` int(10) unsigned NOT NULL DEFAULT 1,
  `offset_unit` enum('hours','days') NOT NULL DEFAULT 'days',
  `send_time` time DEFAULT '18:00:00',
  `template_id` int(10) unsigned NOT NULL,
  `doctor_id` int(10) unsigned DEFAULT NULL,
  `service_id` int(10) unsigned DEFAULT NULL,
  `appointment_statuses` varchar(255) NOT NULL DEFAULT 'confirmed',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_enqueued_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_sar_template` (`template_id`),
  KEY `fk_sar_doctor` (`doctor_id`),
  KEY `fk_sar_service` (`service_id`),
  CONSTRAINT `fk_sar_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sar_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sar_template` FOREIGN KEY (`template_id`) REFERENCES `sms_templates` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sms_logs
CREATE TABLE IF NOT EXISTS `sms_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` int(10) unsigned DEFAULT NULL,
  `appointment_id` bigint(20) unsigned DEFAULT NULL,
  `template_id` int(10) unsigned DEFAULT NULL,
  `automation_rule_id` int(10) unsigned DEFAULT NULL,
  `queue_id` bigint(20) unsigned DEFAULT NULL,
  `mobile` varchar(15) NOT NULL,
  `message_type` varchar(50) NOT NULL,
  `source` varchar(40) NOT NULL DEFAULT 'system',
  `admin_user_id` int(10) unsigned DEFAULT NULL,
  `message_body` text NOT NULL,
  `provider` varchar(50) NOT NULL,
  `provider_message_id` varchar(120) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'queued',
  `attempts` int(10) unsigned NOT NULL DEFAULT 1,
  `last_error` varchar(500) DEFAULT NULL,
  `idempotency_key` varchar(190) DEFAULT NULL,
  `provider_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`provider_response`)),
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sms_idempotency` (`idempotency_key`),
  KEY `idx_sms_appt` (`appointment_id`),
  KEY `idx_sms_mobile` (`mobile`),
  KEY `idx_sms_status` (`status`,`created_at`),
  KEY `idx_sms_type` (`message_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sms_queue
CREATE TABLE IF NOT EXISTS `sms_queue` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` int(10) unsigned DEFAULT NULL,
  `appointment_id` bigint(20) unsigned DEFAULT NULL,
  `template_id` int(10) unsigned DEFAULT NULL,
  `automation_rule_id` int(10) unsigned DEFAULT NULL,
  `admin_user_id` int(10) unsigned DEFAULT NULL,
  `mobile` varchar(15) NOT NULL,
  `rendered_message` text NOT NULL,
  `message_type` varchar(50) NOT NULL DEFAULT 'general',
  `source` varchar(40) NOT NULL DEFAULT 'manual',
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `max_attempts` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `scheduled_at` datetime NOT NULL,
  `processing_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `provider` varchar(50) DEFAULT NULL,
  `provider_message_id` varchar(120) DEFAULT NULL,
  `last_error` varchar(500) DEFAULT NULL,
  `idempotency_key` varchar(190) DEFAULT NULL,
  `appointment_starts_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sms_queue_idempotency` (`idempotency_key`),
  KEY `idx_sms_queue_status` (`status`,`scheduled_at`),
  KEY `idx_sms_queue_patient` (`patient_id`),
  KEY `idx_sms_queue_appt` (`appointment_id`),
  CONSTRAINT `fk_sq_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sq_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sms_templates
CREATE TABLE IF NOT EXISTS `sms_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'custom',
  `body` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: testimonials
CREATE TABLE IF NOT EXISTS `testimonials` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `patient_name` varchar(150) NOT NULL,
  `content` text NOT NULL,
  `rating` tinyint(3) unsigned DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Required seed data (no patients / appointments / OTP / SMS logs)
-- -----------------------------------------------------------------------------
START TRANSACTION;

-- Seed: roles (4 rows)
INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `created_at`) VALUES
(1, 'مدیر کل', 'super_admin', 'دسترسی کامل', '2026-08-29 22:05:49'),
(2, 'پذیرش', 'reception', 'بیماران، نوبت‌ها، پرداخت و پیامک', '2026-08-29 22:05:49'),
(3, 'پزشک', 'doctor', 'نوبت‌ها و بیماران مرتبط', '2026-08-29 22:05:49'),
(4, 'مدیر محتوا', 'content_manager', 'مدیریت محتوای وب‌سایت', '2026-08-29 22:05:49');

-- Seed: permissions (21 rows)
INSERT INTO `permissions` (`id`, `name`, `slug`, `group_name`, `created_at`) VALUES
(1, 'مدیریت تنظیمات', 'settings.manage', 'settings', '2026-08-29 22:05:49'),
(2, 'مدیریت صفحات', 'cms.pages', 'cms', '2026-08-29 22:05:49'),
(3, 'مدیریت خدمات', 'cms.services', 'cms', '2026-08-29 22:05:49'),
(4, 'مدیریت پزشکان', 'cms.doctors', 'cms', '2026-08-29 22:05:49'),
(5, 'مدیریت وبلاگ', 'cms.blog', 'cms', '2026-08-29 22:05:49'),
(6, 'مدیریت گالری', 'cms.gallery', 'cms', '2026-08-29 22:05:49'),
(7, 'مدیریت بیماران', 'patients.manage', 'patients', '2026-08-29 22:05:49'),
(8, 'ورود اکسل بیماران', 'patients.import', 'patients', '2026-08-29 22:05:49'),
(9, 'مدیریت نوبت‌ها', 'appointments.manage', 'appointments', '2026-08-29 22:05:49'),
(10, 'مدیریت پرداخت‌ها', 'payments.manage', 'payments', '2026-08-29 22:05:49'),
(11, 'مشاهده پیامک‌ها', 'sms.view', 'sms', '2026-08-29 22:05:49'),
(12, 'مشاهده آمار', 'analytics.view', 'analytics', '2026-08-29 22:05:49'),
(13, 'مدیریت کاربران ادمین', 'admins.manage', 'admins', '2026-08-29 22:05:49'),
(14, 'مشاهده بیماران', 'patients.view', 'patients', '2026-08-30 12:25:24'),
(15, 'افزودن بیمار', 'patients.create', 'patients', '2026-08-30 12:25:24'),
(16, 'ویرایش بیمار', 'patients.edit', 'patients', '2026-08-30 12:25:24'),
(17, 'بایگانی بیمار', 'patients.archive', 'patients', '2026-08-30 12:25:24'),
(18, 'ارسال پیامک', 'sms.send', 'sms', '2026-08-30 12:25:24'),
(19, 'مدیریت قالب پیامک', 'sms.templates.manage', 'sms', '2026-08-30 12:25:24'),
(20, 'مدیریت پیامک خودکار', 'sms.automation.manage', 'sms', '2026-08-30 12:25:24'),
(21, 'تنظیمات پیامک', 'sms.settings.manage', 'sms', '2026-08-30 12:25:24');

-- Seed: role_permissions (40 rows)
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(1, 11),
(1, 12),
(1, 13),
(1, 14),
(1, 15),
(1, 16),
(1, 17),
(1, 18),
(1, 19),
(1, 20),
(1, 21),
(2, 7),
(2, 8),
(2, 9),
(2, 10),
(2, 11),
(2, 14),
(2, 15),
(2, 16),
(2, 17),
(2, 18),
(3, 7),
(3, 9),
(3, 14),
(4, 1),
(4, 2),
(4, 3),
(4, 4),
(4, 5),
(4, 6);

-- Seed: admin_users (1 rows)
INSERT INTO `admin_users` (`id`, `role_id`, `first_name`, `last_name`, `email`, `password_hash`, `mobile`, `avatar`, `is_active`, `last_login_at`, `login_attempts`, `locked_until`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'مدیر', 'سیستم', 'admin@sarvadental.ir', '$2y$12$IKPQ2iw4JbYfRJRfBdl0xevbIjMpt0JeH9Ge83ZL3Q2Ar9dLtgc3i', NULL, NULL, 1, NULL, 0, NULL, '2026-08-29 22:05:50', '2026-08-30 12:19:33', NULL);

-- Seed: site_settings (19 rows)
INSERT INTO `site_settings` (`id`, `key`, `value`, `group_name`, `updated_at`) VALUES
(1, 'clinic_name', 'کلینیک دندانپزشکی سروا', 'general', '2026-08-29 22:05:50'),
(2, 'clinic_name_en', 'Sarva Dental Clinic', 'general', '2026-08-29 22:05:50'),
(3, 'phone', '021-00000000', 'contact', '2026-08-29 22:05:50'),
(4, 'mobile', '09120000000', 'contact', '2026-08-29 22:05:50'),
(5, 'email', 'info@sarvadental.ir', 'contact', '2026-08-29 22:05:50'),
(6, 'address', 'تهران، ایران', 'contact', '2026-08-29 22:05:50'),
(7, 'working_hours', 'شنبه تا پنجشنبه: ۱۰:۰۰ تا ۱۹:۳۰\nجمعه: تعطیل', 'contact', '2026-08-29 22:05:50'),
(8, 'map_embed', '', 'contact', '2026-08-29 22:05:50'),
(9, 'footer_text', 'مراقبت از لبخند شما با درمان‌های پیشرفته و رویکردی بیمارمحور.', 'footer', '2026-08-29 22:05:50'),
(10, 'timezone', 'Asia/Tehran', 'general', '2026-08-29 22:05:50'),
(11, 'facebook', '', 'social', '2026-08-29 22:05:50'),
(12, 'instagram', '', 'social', '2026-08-29 22:05:50'),
(13, 'twitter', '', 'social', '2026-08-29 22:05:50'),
(14, 'consultation_fee', '500000', 'payment', '2026-08-29 22:05:50'),
(15, 'sms_enabled', '1', 'sms', '2026-08-30 12:25:24'),
(16, 'sms_max_retries', '3', 'sms', '2026-08-30 12:25:24'),
(17, 'sms_retry_delay_minutes', '10', 'sms', '2026-08-30 12:25:24'),
(18, 'sms_default_reminder_time', '18:00', 'sms', '2026-08-30 12:25:24'),
(19, 'sms_bulk_limit', '200', 'sms', '2026-08-30 12:25:24');

-- Seed: sms_templates (5 rows)
INSERT INTO `sms_templates` (`id`, `slug`, `name`, `type`, `body`, `is_active`, `created_at`, `deleted_at`, `updated_at`) VALUES
(1, 'otp', 'کد یکبارمصرف', 'otp', 'کد تأیید کلینیک دندانپزشکی سروا: {CODE}', 1, '2026-08-30 12:25:23', NULL, '2026-08-30 12:25:24'),
(2, 'appointment_reminder', 'یادآوری نوبت', 'appointment_reminder', 'سلام {full_name}\nیادآوری نوبت شما در کلینیک دندانپزشکی سروا:\nفردا ساعت {appointment_time}\nمنتظر حضور شما هستیم.', 1, '2026-08-30 12:25:23', NULL, '2026-08-30 12:25:24'),
(3, 'appointment_confirmed', 'تأیید نوبت', 'appointment_confirmation', 'سلام {full_name}\nنوبت شما در کلینیک دندانپزشکی سروا برای {appointment_date} ساعت {appointment_time} تأیید شد.', 1, '2026-08-30 12:25:23', NULL, '2026-08-30 12:25:24'),
(4, 'appointment_cancelled', 'لغو نوبت', 'appointment_cancellation', 'سلام {full_name}\nنوبت شما در تاریخ {appointment_date} ساعت {appointment_time} لغو شد. برای رزرو مجدد با کلینیک تماس بگیرید.', 1, '2026-08-30 12:25:24', NULL, '2026-08-30 12:25:24'),
(5, 'general_patient', 'پیام عمومی بیمار', 'general', 'سلام {full_name}\n{clinic_name} در خدمت شماست.', 1, '2026-08-30 12:25:24', NULL, '2026-08-30 12:25:24');

-- Seed: pages (12 rows)
INSERT INTO `pages` (`id`, `title`, `slug`, `template`, `status`, `meta_title`, `meta_description`, `og_image`, `canonical_url`, `robots`, `content`, `published_at`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'خانه', 'home', 'home', 'published', 'کلینیک دندانپزشکی سروا', 'مراقبت تخصصی دندانپزشکی در کلینیک سروا', NULL, NULL, 'index,follow', NULL, '2026-08-29 22:05:50', NULL, '2026-08-29 22:05:50', '2026-08-29 22:05:50'),
(2, 'درباره ما', 'about', 'about', 'published', 'درباره کلینیک سروا', 'آشنایی با کلینیک دندانپزشکی سروا', NULL, NULL, 'index,follow', NULL, '2026-08-29 22:05:50', NULL, '2026-08-29 22:05:50', '2026-08-29 22:05:50'),
(3, 'خدمات', 'services', 'services', 'published', 'خدمات دندانپزشکی', 'خدمات تخصصی کلینیک سروا', NULL, NULL, 'index,follow', NULL, '2026-08-29 22:05:50', NULL, '2026-08-29 22:05:50', '2026-08-29 22:05:50'),
(4, 'تیم پزشکی', 'team', 'team', 'published', 'تیم پزشکی سروا', 'پزشکان کلینیک دندانپزشکی سروا', NULL, NULL, 'index,follow', NULL, '2026-08-29 22:05:50', NULL, '2026-08-29 22:05:50', '2026-08-29 22:05:50'),
(5, 'نمونه کارها', 'case-studies', 'case-studies', 'published', 'نمونه کارهای کلینیک سروا', NULL, NULL, NULL, 'index,follow', NULL, '2026-08-29 22:05:50', NULL, '2026-08-29 22:05:50', '2026-08-29 22:05:50'),
(6, 'گالری تصاویر', 'gallery', 'gallery', 'published', 'گالری کلینیک سروا', NULL, NULL, NULL, 'index,follow', NULL, '2026-08-29 22:05:50', NULL, '2026-08-29 22:05:50', '2026-08-29 22:05:50'),
(7, 'گالری ویدیو', 'gallery-videos', 'gallery-videos', 'published', 'ویدیوهای کلینیک سروا', NULL, NULL, NULL, 'index,follow', NULL, '2026-08-29 22:05:50', NULL, '2026-08-29 22:05:50', '2026-08-29 22:05:50'),
(8, 'نظرات بیماران', 'testimonials', 'testimonials', 'published', 'نظرات بیماران', NULL, NULL, NULL, 'index,follow', NULL, '2026-08-29 22:05:50', NULL, '2026-08-29 22:05:50', '2026-08-29 22:05:50'),
(9, 'سؤالات متداول', 'faqs', 'faqs', 'published', 'سؤالات متداول', NULL, NULL, NULL, 'index,follow', NULL, '2026-08-29 22:05:50', NULL, '2026-08-29 22:05:50', '2026-08-29 22:05:50'),
(10, 'وبلاگ', 'blog', 'blog', 'published', 'وبلاگ کلینیک سروا', NULL, NULL, NULL, 'index,follow', NULL, '2026-08-29 22:05:50', NULL, '2026-08-29 22:05:50', '2026-08-29 22:05:50'),
(11, 'تماس با ما', 'contact', 'contact', 'published', 'تماس با کلینیک سروا', NULL, NULL, NULL, 'index,follow', NULL, '2026-08-29 22:05:50', NULL, '2026-08-29 22:05:50', '2026-08-29 22:05:50'),
(12, 'رزرو نوبت', 'appointment', 'appointment', 'published', 'رزرو نوبت آنلاین', NULL, NULL, NULL, 'index,follow', NULL, '2026-08-29 22:05:50', NULL, '2026-08-29 22:05:50', '2026-08-29 22:05:50');

-- Seed: page_sections (3 rows)
INSERT INTO `page_sections` (`id`, `page_id`, `section_key`, `title`, `subtitle`, `description`, `image`, `cta_text`, `cta_link`, `extra_json`, `is_visible`, `sort_order`, `updated_at`) VALUES
(1, 1, 'hero', 'مراقبت مطمئن دندانپزشکی', 'لبخندی سالم‌تر از اینجا آغاز می‌شود', 'تجربه درمان شخصی‌سازی‌شده با تجهیزات مدرن و تیمی متعهد به سلامت و اعتمادبه‌نفس لبخند شما.', 'images/hero-bg-image.png', 'مشاهده خدمات', '/services', NULL, 1, 1, '2026-08-29 22:05:50'),
(2, 1, 'about', 'درباره کلینیک سروا', 'سلامت دهان، آرامش خاطر', 'ما مراقبت ایمن، راحت و قابل‌اعتماد را برای هر بیمار فراهم می‌کنیم.', 'images/about-us-image.jpg', 'بیشتر بدانید', '/about', NULL, 1, 2, '2026-08-29 22:05:50'),
(3, 1, 'services', 'خدمات ما', 'درمان‌های تخصصی', 'مجموعه‌ای کامل از خدمات دندانپزشکی عمومی و زیبایی.', NULL, 'همه خدمات', '/services', NULL, 1, 3, '2026-08-29 22:05:50');

-- Seed: menus (1 rows)
INSERT INTO `menus` (`id`, `name`, `location`, `created_at`) VALUES
(1, 'منوی اصلی', 'main', '2026-08-29 22:05:50');

-- Seed: menu_items (6 rows)
INSERT INTO `menu_items` (`id`, `menu_id`, `parent_id`, `label`, `url`, `target`, `sort_order`, `is_active`) VALUES
(1, 1, NULL, 'خانه', '/', '_self', 1, 1),
(2, 1, NULL, 'خدمات', '/services', '_self', 2, 1),
(3, 1, NULL, 'مقالات', '/articles', '_self', 3, 1),
(4, 1, NULL, 'تیم پزشکی', '/team', '_self', 4, 0),
(5, 1, NULL, 'درباره ما', '/about', '_self', 5, 1),
(6, 1, NULL, 'تماس با ما', '/contact', '_self', 6, 1);

-- Seed: services (6 rows)
INSERT INTO `services` (`id`, `name`, `slug`, `short_description`, `description`, `image`, `icon`, `price`, `duration_minutes`, `meta_title`, `meta_description`, `is_active`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'مراقبت عمومی دندان', 'general-dental-care', 'معاینه، جرم‌گیری و مراقبت‌های پیشگیرانه.', NULL, NULL, 'images/icon-service-item-1.svg', 500000, 30, NULL, NULL, 1, 1, '2026-08-29 22:05:50', '2026-08-29 22:05:50', NULL),
(2, 'زیبایی لبخند', 'cosmetic-smile-care', 'طراحی لبخند و درمان‌های زیبایی.', NULL, NULL, 'images/icon-service-item-2.svg', 500000, 30, NULL, NULL, 1, 2, '2026-08-29 22:05:50', '2026-08-29 22:05:50', NULL),
(3, 'کشیدن دندان', 'tooth-extraction', 'کشیدن ایمن و کنترل‌شده دندان.', NULL, NULL, 'images/icon-service-item-3.svg', 500000, 30, NULL, NULL, 1, 3, '2026-08-29 22:05:50', '2026-08-29 22:05:50', NULL),
(4, 'سفیدکردن دندان', 'teeth-whitening', 'سفیدکردن حرفه‌ای و ایمن.', NULL, NULL, 'images/icon-service-item-4.svg', 500000, 30, NULL, NULL, 1, 4, '2026-08-29 22:05:50', '2026-08-29 22:05:50', NULL),
(5, 'ارتودنسی', 'orthodontics', 'اصلاح نظم دندان‌ها.', NULL, NULL, 'images/icon-service-item-5.svg', 500000, 30, NULL, NULL, 1, 5, '2026-08-29 22:05:50', '2026-08-29 22:05:50', NULL),
(6, 'ایمپلنت دندان', 'dental-implants', 'جایگزینی دندان از دست‌رفته.', NULL, NULL, 'images/icon-service-item-6.svg', 500000, 30, NULL, NULL, 1, 6, '2026-08-29 22:05:50', '2026-08-29 22:05:50', NULL);

-- Seed: doctors (2 rows)
INSERT INTO `doctors` (`id`, `first_name`, `last_name`, `slug`, `specialty`, `biography`, `photo`, `education`, `experience`, `social_json`, `is_active`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'دکتر فرید', 'زمان زاده', 'farid-zamanzadeh', 'دندانپزشک', NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-08-29 22:05:50', '2026-08-29 22:05:50', NULL),
(2, 'دکتر حسن', 'زمان زاده', 'hassan-zamanzadeh', 'دندانپزشک', NULL, NULL, NULL, NULL, NULL, 1, 2, '2026-08-29 22:05:50', '2026-08-29 22:05:50', NULL);

-- Seed: doctor_schedules (12 rows)
INSERT INTO `doctor_schedules` (`id`, `doctor_id`, `weekday`, `start_time`, `end_time`, `slot_duration`, `break_start`, `break_end`, `is_active`) VALUES
(1, 1, 6, '10:00:00', '19:30:00', 30, NULL, NULL, 1),
(2, 1, 0, '10:00:00', '19:30:00', 30, NULL, NULL, 1),
(3, 1, 1, '10:00:00', '19:30:00', 30, NULL, NULL, 1),
(4, 1, 2, '10:00:00', '19:30:00', 30, NULL, NULL, 1),
(5, 1, 3, '10:00:00', '19:30:00', 30, NULL, NULL, 1),
(6, 1, 4, '10:00:00', '19:30:00', 30, NULL, NULL, 1),
(7, 2, 6, '10:00:00', '19:30:00', 30, NULL, NULL, 1),
(8, 2, 0, '10:00:00', '19:30:00', 30, NULL, NULL, 1),
(9, 2, 1, '10:00:00', '19:30:00', 30, NULL, NULL, 1),
(10, 2, 2, '10:00:00', '19:30:00', 30, NULL, NULL, 1),
(11, 2, 3, '10:00:00', '19:30:00', 30, NULL, NULL, 1),
(12, 2, 4, '10:00:00', '19:30:00', 30, NULL, NULL, 1);

-- Seed: clinic_hours (7 rows)
INSERT INTO `clinic_hours` (`id`, `weekday`, `is_open`, `open_time`, `close_time`) VALUES
(1, 0, 1, '10:00:00', '19:30:00'),
(2, 1, 1, '10:00:00', '19:30:00'),
(3, 2, 1, '10:00:00', '19:30:00'),
(4, 3, 1, '10:00:00', '19:30:00'),
(5, 4, 1, '10:00:00', '19:30:00'),
(6, 5, 0, NULL, NULL),
(7, 6, 1, '10:00:00', '19:30:00');

-- Seed: faqs (3 rows)
INSERT INTO `faqs` (`id`, `question`, `answer`, `is_active`, `sort_order`, `created_at`) VALUES
(1, 'چگونه نوبت آنلاین بگیرم؟', 'از بخش رزرو نوبت خدمت، پزشک، تاریخ و ساعت را انتخاب کنید و پس از پرداخت، نوبت تأیید می‌شود.', 1, 1, '2026-08-29 22:05:50'),
(2, 'آیا یادآوری پیامکی ارسال می‌شود؟', 'بله، یک روز قبل از نوبت پیامک یادآوری ارسال می‌شود.', 1, 2, '2026-08-29 22:05:50'),
(3, 'ساعات کاری کلینیک چیست؟', 'شنبه تا پنجشنبه از ساعت ۱۰ تا ۱۹؛ جمعه‌ها تعطیل.', 1, 3, '2026-08-29 22:05:50');

-- Seed: testimonials (8 rows)
INSERT INTO `testimonials` (`id`, `patient_name`, `content`, `rating`, `avatar`, `is_active`, `sort_order`, `created_at`) VALUES
(1, 'نیلوفر احمدی', 'تجربه‌ای آرام و حرفه‌ای. تیم کلینیک سروا بسیار مهربان و دقیق بودند.', 5, NULL, 1, 1, '2026-08-29 22:05:50'),
(2, 'رضا حسینی', 'نوبت‌گیری آنلاین ساده بود و نتیجه درمان عالی.', 5, NULL, 1, 2, '2026-08-29 22:05:50'),
(3, 'سارا محمدی', 'محیط کلینیک تمیز و مدرن است و پزشکان با صبر کامل توضیح می‌دهند.', 5, NULL, 1, 3, '2026-08-29 22:05:50'),
(4, 'امیر کریمی', 'ایمپلنت من بدون درد انجام شد و نتیجه طبیعی و عالی بود.', 5, NULL, 1, 4, '2026-08-29 22:05:50'),
(5, 'مریم رضایی', 'از جرم‌گیری تا مشاوره، همه چیز منظم و حرفه‌ای پیش رفت.', 4, NULL, 1, 5, '2026-08-29 22:05:50'),
(6, 'حسین اکبری', 'برای اولین بار بدون استرس به دندانپزشکی رفتم. واقعاً توصیه می‌کنم.', 5, NULL, 1, 6, '2026-08-29 22:05:50'),
(7, 'زهرا موسوی', 'سفیدکردن دندان‌هایم نتیجه خیلی خوبی داشت و لبخندم طبیعی‌تر شده.', 5, NULL, 1, 7, '2026-08-29 22:05:50'),
(8, 'محمد علیزاده', 'زمان‌بندی نوبت دقیق بود و پذیرش بسیار مؤدب و راهنما بود.', 5, NULL, 1, 8, '2026-08-29 22:05:50');

-- Seed: case_studies (3 rows)
INSERT INTO `case_studies` (`id`, `title`, `slug`, `description`, `before_image`, `after_image`, `doctor_id`, `service_id`, `case_date`, `status`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'سفیدکردن دندان', 'teeth-whitening-result', 'نتیجه سفیدکردن حرفه‌ای دندان در کلینیک سروا.', 'images/transformation-img-before-1.jpg', 'images/transformation-img-after-1.jpg', NULL, NULL, NULL, 'published', 1, '2026-08-29 22:05:50', '2026-08-29 22:05:50'),
(2, 'طراحی لبخند', 'smile-makeover-result', 'طراحی لبخند و بهبود هماهنگی دندان‌ها.', 'images/transformation-img-before-2.jpg', 'images/transformation-img-after-2.jpg', NULL, NULL, NULL, 'published', 2, '2026-08-29 22:05:50', '2026-08-29 22:05:50'),
(3, 'ارتودنسی / الاینر', 'braces-aligners-result', 'اصلاح نظم دندان‌ها با رویکرد ارتودنسی.', 'images/transformation-img-before-3.jpg', 'images/transformation-img-after-3.jpg', NULL, NULL, NULL, 'published', 3, '2026-08-29 22:05:50', '2026-08-29 22:05:50');

-- Seed: blog_categories (6 rows)
INSERT INTO `blog_categories` (`id`, `name`, `slug`, `created_at`, `description`, `is_active`, `sort_order`, `updated_at`, `deleted_at`) VALUES
(1, 'سلامت دهان و دندان', 'oral-health', '2026-08-29 22:05:50', NULL, 1, 1, '2026-08-29 22:05:50', NULL),
(2, 'ایمپلنت', 'implant', '2026-08-29 22:05:50', NULL, 1, 2, '2026-08-29 22:05:50', NULL),
(3, 'زیبایی و طراحی لبخند', 'cosmetic-smile', '2026-08-29 22:05:50', NULL, 1, 3, '2026-08-29 22:05:50', NULL),
(4, 'ارتودنسی', 'orthodontics', '2026-08-29 22:05:50', NULL, 1, 4, '2026-08-29 22:05:50', NULL),
(5, 'مراقبت‌های دندانپزشکی', 'dental-care', '2026-08-29 22:05:50', NULL, 1, 5, '2026-08-29 22:05:50', NULL),
(6, 'آموزش بیماران', 'patient-education', '2026-08-29 22:05:50', NULL, 1, 6, '2026-08-29 22:05:50', NULL);

-- Seed: blog_posts (3 rows)
INSERT INTO `blog_posts` (`id`, `category_id`, `author_id`, `title`, `slug`, `excerpt`, `content`, `cover`, `meta_title`, `meta_description`, `status`, `published_at`, `created_at`, `updated_at`, `scheduled_at`, `reading_time`, `seo_title`, `seo_description`, `canonical_url`, `og_title`, `og_description`, `og_image`, `robots_index`, `robots_follow`, `medical_reviewer`, `deleted_at`, `is_featured`) VALUES
(1, 1, 1, 'چگونه از سلامت دهان و دندان خود مراقبت کنیم؟', 'oral-health-daily-care', 'راهنمای ساده و کاربردی برای مراقبت روزانه از دندان‌ها و لثه، مناسب همه سنین.', '<h2>مراقبت روزانه</h2><p>مسواک‌زدن منظم، استفاده از نخ دندان و مراجعه دوره‌ای به دندانپزشک پایه‌های سلامت دهان هستند.</p><h3>نکات مهم</h3><ul><li>روزانه حداقل دو بار مسواک بزنید.</li><li>از مصرف بیش از حد قند پرهیز کنید.</li><li>هر شش ماه یک‌بار برای معاینه مراجعه کنید.</li></ul><blockquote>پیشگیری، بهترین درمان است.</blockquote>', 'images/post-1.jpg', NULL, NULL, 'published', '2026-08-29 22:05:50', '2026-08-29 22:05:50', '2026-08-29 22:05:50', NULL, 4, 'چگونه از سلامت دهان و دندان خود مراقبت کنیم؟ | کلینیک دندانپزشکی سروا', 'راهنمای ساده و کاربردی برای مراقبت روزانه از دندان‌ها و لثه، مناسب همه سنین.', NULL, NULL, NULL, NULL, 1, 1, NULL, NULL, 0),
(2, 2, 1, 'آشنایی با ایمپلنت دندان؛ آنچه باید بدانید', 'dental-implant-care', 'مروری بر مفاهیم پایه ایمپلنت، مراقبت‌های پس از درمان و سوالات رایج بیماران.', '<h2>ایمپلنت چیست؟</h2><p>ایمپلنت روشی برای جایگزینی دندان از دست‌رفته است که باید تحت نظر دندانپزشک متخصص انجام شود.</p><h3>مراقبت پس از درمان</h3><ol><li>رعایت بهداشت دهان</li><li>اجتناب از فشار زیاد در روزهای ابتدایی</li><li>پیگیری نوبت‌های کنترل</li></ol><p>تصمیم نهایی درباره مناسب بودن ایمپلنت برای هر فرد، پس از معاینه بالینی گرفته می‌شود.</p>', 'images/post-2.jpg', NULL, NULL, 'published', '2026-08-29 22:05:50', '2026-08-29 22:05:50', '2026-08-29 22:05:50', NULL, 5, 'آشنایی با ایمپلنت دندان؛ آنچه باید بدانید | کلینیک دندانپزشکی سروا', 'مروری بر مفاهیم پایه ایمپلنت، مراقبت‌های پس از درمان و سوالات رایج بیماران.', NULL, NULL, NULL, NULL, 1, 1, NULL, NULL, 0),
(3, 3, 1, 'طراحی لبخند؛ گام‌های رسیدن به لبخندی طبیعی', 'smile-design-basics', 'آشنایی با اصول زیبایی دندانپزشکی و نکاتی که قبل از شروع درمان باید در نظر بگیرید.', '<h2>طراحی لبخند</h2><p>طراحی لبخند مجموعه‌ای از درمان‌های زیبایی است که با توجه به فرم صورت، رنگ و نظم دندان‌ها برنامه‌ریزی می‌شود.</p><h3>قبل از شروع</h3><ul><li>معاینه کامل</li><li>بررسی انتظارات واقع‌بینانه</li><li>برنامه‌ریزی مرحله‌ای درمان</li></ul>', 'images/post-3.jpg', NULL, NULL, 'published', '2026-08-29 22:05:50', '2026-08-29 22:05:50', '2026-08-29 22:05:50', NULL, 3, 'طراحی لبخند؛ گام‌های رسیدن به لبخندی طبیعی | کلینیک دندانپزشکی سروا', 'آشنایی با اصول زیبایی دندانپزشکی و نکاتی که قبل از شروع درمان باید در نظر بگیرید.', NULL, NULL, NULL, NULL, 1, 1, NULL, NULL, 0);

-- Seed: sms_automation_rules (1 rows)
INSERT INTO `sms_automation_rules` (`id`, `name`, `trigger_type`, `offset_value`, `offset_unit`, `send_time`, `template_id`, `doctor_id`, `service_id`, `appointment_statuses`, `is_active`, `last_enqueued_at`, `created_at`, `updated_at`) VALUES
(1, 'یادآوری نوبت یک روز قبل', 'appointment', 1, 'days', '18:00:00', 2, NULL, NULL, 'confirmed', 1, NULL, '2026-08-30 12:25:24', '2026-08-30 12:25:24');

-- Seed: schema_migrations (5 rows)
INSERT INTO `schema_migrations` (`id`, `migration`, `ran_at`) VALUES
(1, '001_schema.php', '2026-08-29 22:05:49'),
(2, '002_seed.php', '2026-08-29 22:05:50'),
(3, '003_articles.php', '2026-08-29 22:05:50'),
(4, '004_crm_sms.php', '2026-08-30 12:25:24'),
(5, '005_hide_team_nav.php', '2026-08-30 12:35:12');

COMMIT;

SET FOREIGN_KEY_CHECKS = 1;

-- Import complete.
-- Next: configure DB_* in .env, open /admin/login, change the default admin password.
