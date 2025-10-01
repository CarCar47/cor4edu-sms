-- COR4EDU SMS Financial System Migration
-- Implements comprehensive financial system matching COR4EDU Single functionality
-- Date: September 28, 2025

SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- 1. ENHANCE PROGRAMS TABLE WITH PRICING BREAKDOWN
-- =====================================================

-- Add 6 pricing component fields to cor4edu_programs
ALTER TABLE `cor4edu_programs`
ADD COLUMN `tuitionAmount` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Base tuition cost' AFTER `measurementType`,
ADD COLUMN `fees` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Program fees' AFTER `tuitionAmount`,
ADD COLUMN `booksAmount` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Books cost' AFTER `fees`,
ADD COLUMN `materialsAmount` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Materials cost' AFTER `booksAmount`,
ADD COLUMN `applicationFee` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Application fee' AFTER `materialsAmount`,
ADD COLUMN `miscellaneousCosts` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Miscellaneous costs' AFTER `applicationFee`,
ADD COLUMN `totalCost` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Total cost (calculated by triggers)',
ADD COLUMN `currentPriceId` VARCHAR(50) NULL COMMENT 'Links to current active pricing version' AFTER `totalCost`;

-- Add index for pricing lookups
ALTER TABLE `cor4edu_programs` ADD INDEX `idx_program_price` (`currentPriceId`);

-- =====================================================
-- 2. ENHANCE STUDENTS TABLE FOR ENROLLMENT PRICING
-- =====================================================

-- Add enrollment pricing protection fields to cor4edu_students
ALTER TABLE `cor4edu_students`
ADD COLUMN `enrollmentPriceId` VARCHAR(50) NULL COMMENT 'Locked pricing version for student contract protection' AFTER `programID`,
ADD COLUMN `contractLockedAt` TIMESTAMP NULL COMMENT 'When student pricing was locked in' AFTER `enrollmentPriceId`;

-- Add index for enrollment pricing lookups
ALTER TABLE `cor4edu_students` ADD INDEX `idx_student_enrollment_price` (`enrollmentPriceId`);

-- =====================================================
-- 3. ENHANCE PAYMENTS TABLE FOR PROGRAM VS OTHER
-- =====================================================

-- Add payment type classification and additional fields to cor4edu_payments
ALTER TABLE `cor4edu_payments`
ADD COLUMN `paymentType` ENUM('program','other') DEFAULT 'program' COMMENT 'Program tuition vs other payments' AFTER `paymentMethod`,
ADD COLUMN `appliedToCharges` TEXT NULL COMMENT 'JSON string for charge breakdown (wider MySQL compatibility)' AFTER `notes`,
ADD COLUMN `processedBy` INT(10) UNSIGNED NULL COMMENT 'Staff member who processed payment' AFTER `appliedToCharges`;

-- Add indexes for payment queries
ALTER TABLE `cor4edu_payments` ADD INDEX `idx_payment_type` (`paymentType`);
ALTER TABLE `cor4edu_payments` ADD INDEX `idx_payment_processed_by` (`processedBy`);

-- Add foreign key for processedBy
ALTER TABLE `cor4edu_payments`
ADD CONSTRAINT `fk_payment_processed_by`
FOREIGN KEY (`processedBy`) REFERENCES `cor4edu_staff`(`staffID`);

-- =====================================================
-- 4. CREATE PROGRAM PRICE HISTORY TABLE
-- =====================================================

-- Create versioned pricing system for program pricing history
CREATE TABLE `cor4edu_program_price_history` (
  `priceId` VARCHAR(50) NOT NULL COMMENT 'Unique price version identifier',
  `programID` INT(10) UNSIGNED NOT NULL COMMENT 'Reference to program',
  `tuitionAmount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Base tuition cost',
  `fees` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Program fees',
  `booksAmount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Books cost',
  `materialsAmount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Materials cost',
  `applicationFee` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Application fee',
  `miscellaneousCosts` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Miscellaneous costs',
  `totalCost` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Total cost (calculated by triggers)',
  `effectiveDate` DATE NOT NULL COMMENT 'When this pricing became active',
  `createdBy` INT(10) UNSIGNED NOT NULL COMMENT 'Staff member who created this pricing',
  `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When this record was created',
  `isActive` BOOLEAN DEFAULT FALSE COMMENT 'Whether this is the current active pricing',
  `description` TEXT NULL COMMENT 'Optional description of pricing changes',
  PRIMARY KEY (`priceId`),
  KEY `idx_price_history_program` (`programID`),
  KEY `idx_price_history_effective` (`effectiveDate`),
  KEY `idx_price_history_active` (`isActive`),
  KEY `idx_price_history_created_by` (`createdBy`),
  CONSTRAINT `fk_price_history_program`
    FOREIGN KEY (`programID`) REFERENCES `cor4edu_programs`(`programID`) ON DELETE CASCADE,
  CONSTRAINT `fk_price_history_created_by`
    FOREIGN KEY (`createdBy`) REFERENCES `cor4edu_staff`(`staffID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Versioned pricing system for program pricing history and student contract protection';

-- =====================================================
-- 5. DATA MIGRATION FROM EXISTING PRICING
-- =====================================================

-- Migrate existing pricing data from cor4edu_program_pricing to new structure
-- This preserves existing data while transitioning to new pricing model

-- First, update programs with pricing from existing pricing table (domestic pricing as default)
UPDATE `cor4edu_programs` p
INNER JOIN `cor4edu_program_pricing` pp ON p.programID = pp.programID
SET
  p.tuitionAmount = pp.price,
  p.fees = 0.00,
  p.booksAmount = 0.00,
  p.materialsAmount = 0.00,
  p.applicationFee = 0.00,
  p.miscellaneousCosts = 0.00
WHERE pp.studentType = 'domestic';

-- Create initial price history records for existing programs
INSERT INTO `cor4edu_program_price_history`
  (`priceId`, `programID`, `tuitionAmount`, `fees`, `booksAmount`, `materialsAmount`, `applicationFee`, `miscellaneousCosts`, `effectiveDate`, `createdBy`, `isActive`, `description`)
SELECT
  CONCAT('PRICE_', p.programID, '_INITIAL') as priceId,
  p.programID,
  p.tuitionAmount,
  p.fees,
  p.booksAmount,
  p.materialsAmount,
  p.applicationFee,
  p.miscellaneousCosts,
  COALESCE(pp.effectiveDate, CURDATE()) as effectiveDate,
  COALESCE(pp.createdBy, 1) as createdBy,
  TRUE as isActive,
  'Initial pricing migration from legacy system' as description
FROM `cor4edu_programs` p
LEFT JOIN `cor4edu_program_pricing` pp ON p.programID = pp.programID AND pp.studentType = 'domestic'
WHERE p.active = 'Y';

-- Update programs to link to their initial price history
UPDATE `cor4edu_programs` p
SET p.currentPriceId = CONCAT('PRICE_', p.programID, '_INITIAL')
WHERE p.active = 'Y';

-- Calculate totalCost for existing programs
UPDATE `cor4edu_programs`
SET `totalCost` = `tuitionAmount` + `fees` + `booksAmount` + `materialsAmount` + `applicationFee` + `miscellaneousCosts`;

-- Calculate totalCost for price history records
UPDATE `cor4edu_program_price_history`
SET `totalCost` = `tuitionAmount` + `fees` + `booksAmount` + `materialsAmount` + `applicationFee` + `miscellaneousCosts`;

-- =====================================================
-- 6. UPDATE EXISTING PAYMENT RECORDS
-- =====================================================

-- Set default paymentType for existing payments (assume program payments)
UPDATE `cor4edu_payments`
SET `paymentType` = 'program'
WHERE `paymentType` IS NULL;

-- Set default charge applications for existing payments
-- Using TEXT instead of JSON for wider MySQL compatibility
UPDATE `cor4edu_payments`
SET `appliedToCharges` = CONCAT('[{"chargeType":"tuition","amount":', `amount`, ',"description":"Migrated payment allocation"}]')
WHERE `appliedToCharges` IS NULL AND `amount` > 0;

-- =====================================================
-- 7. CREATE FINANCIAL PERMISSIONS
-- =====================================================

-- Add financial management permissions to the system
INSERT IGNORE INTO `cor4edu_staff_permissions` (`staffID`, `module`, `action`, `allowed`, `createdBy`)
SELECT
  s.staffID,
  'Finance' as module,
  'process_payments' as action,
  CASE WHEN s.isSuperAdmin = 'Y' THEN 'Y' ELSE 'N' END as allowed,
  1 as createdBy
FROM `cor4edu_staff` s
WHERE s.active = 'Y';

INSERT IGNORE INTO `cor4edu_staff_permissions` (`staffID`, `module`, `action`, `allowed`, `createdBy`)
SELECT
  s.staffID,
  'Finance' as module,
  'generate_financial_reports' as action,
  CASE WHEN s.isSuperAdmin = 'Y' THEN 'Y' ELSE 'N' END as allowed,
  1 as createdBy
FROM `cor4edu_staff` s
WHERE s.active = 'Y';

INSERT IGNORE INTO `cor4edu_staff_permissions` (`staffID`, `module`, `action`, `allowed`, `createdBy`)
SELECT
  s.staffID,
  'Finance' as module,
  'view_financial_details' as action,
  CASE WHEN s.isSuperAdmin = 'Y' THEN 'Y' ELSE 'N' END as allowed,
  1 as createdBy
FROM `cor4edu_staff` s
WHERE s.active = 'Y';

-- =====================================================
-- 8. ADD BURSAR TAB ACCESS
-- =====================================================

-- Grant bursar tab access to staff members
INSERT IGNORE INTO `cor4edu_staff_tab_access` (`staffID`, `tabName`, `canView`, `canEdit`, `createdBy`)
SELECT
  s.staffID,
  'bursar' as tabName,
  CASE WHEN s.isSuperAdmin = 'Y' THEN 'Y' ELSE 'N' END as canView,
  CASE WHEN s.isSuperAdmin = 'Y' THEN 'Y' ELSE 'N' END as canEdit,
  1 as createdBy
FROM `cor4edu_staff` s
WHERE s.active = 'Y';

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- MIGRATION COMPLETE
-- =====================================================

-- This migration script implements:
-- 1. Enhanced program pricing with 6-component breakdown
-- 2. Student enrollment pricing protection system
-- 3. Program vs Other payment classification
-- 4. Versioned pricing history system
-- 5. Data migration from existing pricing structure
-- 6. Financial permissions and access controls
-- 7. Complete infrastructure for COR4EDU Single financial system functionality

-- Next steps after running this migration:
-- 1. Implement PaymentGateway, FinancialGateway classes
-- 2. Update program forms with new pricing fields
-- 3. Implement dynamic bursar tab with financial calculations
-- 4. Create payment modals and processing logic
-- 5. Implement financial reporting functionality