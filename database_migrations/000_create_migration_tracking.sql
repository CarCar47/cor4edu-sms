-- Migration Tracking System
-- Prevents schema drift by tracking which migrations have been applied
-- Based on industry standard migration patterns (Flyway, Phinx, Laravel)
--
-- Purpose: This table prevents Issues #8, #9, #11 from recurring
-- Those issues were all caused by migrations existing in code but not applied to Cloud SQL
--
-- Usage:
--   1. Every migration gets a unique version number (e.g., 001, 002, 003)
--   2. Before running migration, check if version exists in this table
--   3. After successful migration, insert version record
--   4. Deploy process validates all migrations applied before deploying code
--
-- Created: 2025-10-04
-- Version: 1.0.0

CREATE TABLE IF NOT EXISTS `cor4edu_schema_migrations` (
  `migrationID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `version` VARCHAR(50) NOT NULL COMMENT 'Migration version number (e.g., 001_add_document_tables)',
  `description` VARCHAR(255) DEFAULT NULL COMMENT 'Human-readable description of migration',
  `appliedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When migration was applied',
  `appliedBy` VARCHAR(100) DEFAULT 'system' COMMENT 'Who/what applied the migration',
  `executionTime` INT DEFAULT NULL COMMENT 'How long migration took (seconds)',
  `checksum` VARCHAR(64) DEFAULT NULL COMMENT 'SHA256 of migration SQL for verification',
  PRIMARY KEY (`migrationID`),
  UNIQUE KEY `unique_version` (`version`),
  KEY `idx_appliedAt` (`appliedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks which database migrations have been applied to prevent schema drift';

-- Insert record for this migration itself
INSERT INTO `cor4edu_schema_migrations`
  (`version`, `description`, `appliedBy`)
VALUES
  ('000_create_migration_tracking', 'Create migration tracking table', 'initial_setup')
ON DUPLICATE KEY UPDATE
  description = VALUES(description);

-- Backfill previously applied migrations (manual migrations from Issues #8, #9, #11)
-- These were applied manually to fix production issues
INSERT INTO `cor4edu_schema_migrations`
  (`version`, `description`, `appliedAt`, `appliedBy`)
VALUES
  ('001_fix_permission_tables', 'Fix missing permission tables (Issue #8)', '2025-10-01 00:00:00', 'manual_fix'),
  ('002_add_staff_phone_column', 'Add missing staff.phone column (Issue #9)', '2025-10-01 00:00:00', 'manual_fix'),
  ('003_add_document_requirements', 'Add document requirements tables (Issue #11)', '2025-10-02 00:00:00', 'manual_fix'),
  ('004_create_faculty_notes_system', 'Create faculty notes tables (Issue #11)', '2025-10-02 00:00:00', 'manual_fix')
ON DUPLICATE KEY UPDATE
  description = VALUES(description);

-- Verify installation
SELECT
  COUNT(*) as total_migrations,
  MAX(appliedAt) as last_migration_date
FROM cor4edu_schema_migrations;
