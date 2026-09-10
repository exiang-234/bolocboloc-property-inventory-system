-- BPIS requirements foundation (COA, assets, borrower vetting, inventory periods)
-- Run once in phpMyAdmin or: mysql -u root bpis < database/migrations/001_requirements_foundation.sql

-- Admin: Google federation + Barangay Captain super admin
ALTER TABLE `admin`
  ADD COLUMN IF NOT EXISTS `google_id` VARCHAR(128) NULL DEFAULT NULL AFTER `password`,
  ADD COLUMN IF NOT EXISTS `google_email` VARCHAR(255) NULL DEFAULT NULL AFTER `google_id`,
  ADD COLUMN IF NOT EXISTS `is_super_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `role`;

UPDATE `admin` SET `is_super_admin` = 1 WHERE `role` = 'Barangay Captain';

-- Asset: COA clustering, location, brand/model, acquisition, editable audit remarks
ALTER TABLE `asset`
  ADD COLUMN IF NOT EXISTS `asset_cluster` ENUM('Fixed Assets','Movable Assets') NULL DEFAULT 'Movable Assets' AFTER `category`,
  ADD COLUMN IF NOT EXISTS `location` VARCHAR(255) NULL DEFAULT NULL AFTER `asset_cluster`,
  ADD COLUMN IF NOT EXISTS `brand` VARCHAR(120) NULL DEFAULT NULL AFTER `description`,
  ADD COLUMN IF NOT EXISTS `model` VARCHAR(120) NULL DEFAULT NULL AFTER `brand`,
  ADD COLUMN IF NOT EXISTS `audit_remarks` TEXT NULL DEFAULT NULL AFTER `remarks`,
  ADD COLUMN IF NOT EXISTS `acquisition_mode` ENUM('Purchased','Donation','Transfer','Other') NULL DEFAULT 'Purchased' AFTER `date_acquired`,
  ADD COLUMN IF NOT EXISTS `cheque_number` VARCHAR(80) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `voucher_number` VARCHAR(80) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `cash_amount` DECIMAL(15,2) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `purchase_proof_path` VARCHAR(500) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `service_invoice_path` VARCHAR(500) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `shop_name` VARCHAR(255) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `purchaser_name` VARCHAR(255) NULL DEFAULT NULL;

-- Per-unit tracking (e.g. 50 chairs = 50 rows with photo)
CREATE TABLE IF NOT EXISTS `asset_units` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `asset_id` INT(11) NOT NULL,
  `unit_tag` VARCHAR(120) NOT NULL,
  `brand` VARCHAR(120) NULL,
  `model` VARCHAR(120) NULL,
  `photo_path` VARCHAR(500) NULL,
  `location` VARCHAR(255) NULL,
  `unit_condition` VARCHAR(80) DEFAULT 'SERVICEABLE',
  `audit_remarks` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `asset_id` (`asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Depreciation tracking (COA)
CREATE TABLE IF NOT EXISTS `asset_depreciation_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `asset_id` INT(11) NOT NULL,
  `period_label` VARCHAR(40) NOT NULL,
  `depreciation_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `accumulated_depreciation` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `net_book_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `recorded_by` INT(11) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `asset_id` (`asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Beginning / ending inventory by fiscal period
CREATE TABLE IF NOT EXISTS `inventory_periods` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `period_type` ENUM('Quarterly','Annual') NOT NULL DEFAULT 'Annual',
  `period_label` VARCHAR(60) NOT NULL,
  `period_start` DATE NOT NULL,
  `period_end` DATE NOT NULL,
  `beginning_qty` INT(11) NOT NULL DEFAULT 0,
  `ending_qty` INT(11) NOT NULL DEFAULT 0,
  `beginning_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `ending_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `remarks` TEXT NULL,
  `created_by` INT(11) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inventory_period_lines` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `period_id` INT(11) NOT NULL,
  `asset_id` INT(11) NOT NULL,
  `beginning_qty` INT(11) NOT NULL DEFAULT 0,
  `ending_qty` INT(11) NOT NULL DEFAULT 0,
  `beginning_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `ending_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `condition_note` VARCHAR(255) NULL,
  `audit_remarks` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `period_id` (`period_id`),
  KEY `asset_id` (`asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Asset history snapshots (quarterly / annual)
CREATE TABLE IF NOT EXISTS `asset_history_snapshots` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `asset_id` INT(11) NOT NULL,
  `period_type` ENUM('Quarterly','Annual') NOT NULL,
  `period_label` VARCHAR(60) NOT NULL,
  `quantity` INT(11) NOT NULL DEFAULT 0,
  `condition_note` VARCHAR(255) NULL,
  `photo_path` VARCHAR(500) NULL,
  `remarks` TEXT NULL,
  `snapshot_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `asset_id` (`asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Borrower identity / vetting
CREATE TABLE IF NOT EXISTS `borrower_profiles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `contact_number` VARCHAR(30) NULL,
  `purok` VARCHAR(120) NULL,
  `photo_path` VARCHAR(500) NULL,
  `id_document_path` VARCHAR(500) NULL,
  `vetting_status` ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
  `vetting_notes` TEXT NULL,
  `verified_by` INT(11) NULL,
  `verified_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `borrower_blocklist` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NULL,
  `contact_number` VARCHAR(30) NULL,
  `reason` TEXT NOT NULL,
  `blocked_by` INT(11) NOT NULL,
  `blocked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `email` (`email`),
  KEY `full_name` (`full_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `borrowing_requests`
  ADD COLUMN IF NOT EXISTS `vetting_status` ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending' AFTER `status`,
  ADD COLUMN IF NOT EXISTS `borrower_profile_id` INT(11) NULL DEFAULT NULL AFTER `vetting_status`;
