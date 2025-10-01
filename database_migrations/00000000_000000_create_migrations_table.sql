-- =====================================================
-- Migration Version Tracking Table
-- Industry Standard Pattern (Laravel/Rails/Django)
-- =====================================================
-- Created: 2025-10-01
-- Purpose: Track which database migrations have been applied
-- Reference: https://laravel.com/docs/migrations
-- =====================================================

-- Create migration tracking table
CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `version` VARCHAR(255) NOT NULL COMMENT 'Migration version identifier (timestamp or sequential)',
    `migration_name` VARCHAR(255) NOT NULL COMMENT 'Descriptive name of the migration',
    `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When this migration was applied',
    `batch` INT(11) DEFAULT 1 COMMENT 'Batch number for rollback grouping',
    `checksum` VARCHAR(64) NULL COMMENT 'Optional checksum for migration file integrity',
    PRIMARY KEY (`version`),
    KEY `idx_applied_at` (`applied_at`),
    KEY `idx_batch` (`batch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks applied database migrations (industry standard pattern)';

-- Record the complete schema as baseline migration
-- This establishes the starting point for all future migrations
INSERT INTO `schema_migrations` (`version`, `migration_name`, `batch`)
VALUES
    ('20251001_120000', 'database_complete_schema - Baseline with 14 tables and permission system', 1)
ON DUPLICATE KEY UPDATE
    `migration_name` = VALUES(`migration_name`);

-- =====================================================
-- Migration History (for reference)
-- =====================================================
-- This table allows us to:
-- 1. Track which migrations have been applied
-- 2. Prevent running the same migration twice
-- 3. Enable rollback capabilities
-- 4. Maintain an audit trail of schema changes
-- 5. Support multiple environments (dev/staging/prod)
-- =====================================================
