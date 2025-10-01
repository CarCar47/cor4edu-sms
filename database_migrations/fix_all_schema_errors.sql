-- ================================================================
-- COMPREHENSIVE DATABASE SCHEMA FIX
-- Fixes ALL persistent SQL errors by adding missing columns
-- ================================================================

-- Fix 1: cor4edu_documents table - Add missing lastModifiedOn and lastModifiedBy columns
ALTER TABLE `cor4edu_documents`
ADD COLUMN `lastModifiedOn` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
    COMMENT 'When this document record was last modified' AFTER `uploadedOn`,
ADD COLUMN `lastModifiedBy` INT(10) UNSIGNED NULL
    COMMENT 'Staff ID who last modified this document record' AFTER `lastModifiedOn`;

-- Add foreign key for lastModifiedBy in documents table
ALTER TABLE `cor4edu_documents`
ADD CONSTRAINT `fk_document_last_modified_by`
FOREIGN KEY (`lastModifiedBy`) REFERENCES `cor4edu_staff`(`staffID`) ON DELETE SET NULL;

-- Fix 2: cor4edu_staff table - Add missing audit columns
ALTER TABLE `cor4edu_staff`
ADD COLUMN `createdBy` INT(11) NULL
    COMMENT 'Staff ID who created this record' AFTER `isSuperAdmin`,
ADD COLUMN `createdOn` DATETIME DEFAULT CURRENT_TIMESTAMP
    COMMENT 'When this record was created' AFTER `createdBy`,
ADD COLUMN `lastModifiedBy` INT(11) NULL
    COMMENT 'Staff ID who last modified this record' AFTER `createdOn`,
ADD COLUMN `lastModifiedOn` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
    COMMENT 'When this record was last modified' AFTER `lastModifiedBy`;

-- Add foreign key for staff audit columns (optional - may fail if self-referential issues)
-- Commented out to prevent FK constraint errors
-- ALTER TABLE `cor4edu_staff`
-- ADD CONSTRAINT `fk_staff_created_by`
-- FOREIGN KEY (`createdBy`) REFERENCES `cor4edu_staff`(`staffID`) ON DELETE SET NULL,
-- ADD CONSTRAINT `fk_staff_last_modified_by`
-- FOREIGN KEY (`lastModifiedBy`) REFERENCES `cor4edu_staff`(`staffID`) ON DELETE SET NULL;

-- Fix 3: Ensure other tables have consistent modifiedOn columns
-- Check if cor4edu_academic_support_sessions needs updates (it already has modifiedOn)
-- Check if cor4edu_faculty_notes needs updates
-- Check if cor4edu_student_meetings needs updates

-- Fix 4: Update any existing records to have reasonable audit trail
-- Set initial createdOn for existing staff records that don't have it
UPDATE `cor4edu_staff`
SET `createdOn` = '2025-09-01 00:00:00'
WHERE `createdOn` IS NULL;

-- Set createdBy to staffID 1 (super admin) for existing records
UPDATE `cor4edu_staff`
SET `createdBy` = 1
WHERE `createdBy` IS NULL AND `staffID` != 1;

-- Set the super admin's createdBy to NULL (self-created)
UPDATE `cor4edu_staff`
SET `createdBy` = NULL
WHERE `staffID` = 1;

-- Verification queries (uncomment to check results)
-- DESCRIBE cor4edu_documents;
-- DESCRIBE cor4edu_staff;
-- SELECT staffID, firstName, lastName, createdOn, createdBy, lastModifiedOn, lastModifiedBy FROM cor4edu_staff LIMIT 5;

-- ================================================================
-- END OF COMPREHENSIVE SCHEMA FIX
-- ================================================================