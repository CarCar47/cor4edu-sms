-- COR4EDU SMS Database Cleanup
-- Run this in phpMyAdmin to remove problematic tables and fix database issues

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- Drop problematic staff role tables if they exist
DROP TABLE IF EXISTS `cor4edu_staff_role_types`;
DROP TABLE IF EXISTS `cor4edu_staff_tab_access`;
DROP TABLE IF EXISTS `cor4edu_staff_document_requirements`;
DROP TABLE IF EXISTS `cor4edu_staff_document_status`;

-- Remove any roleTypeID column from staff table if it exists
ALTER TABLE `cor4edu_staff` DROP COLUMN IF EXISTS `roleTypeID`;

-- Remove any other problematic columns that might have been added
ALTER TABLE `cor4edu_staff` DROP COLUMN IF EXISTS `phone`;
ALTER TABLE `cor4edu_staff` DROP COLUMN IF EXISTS `address`;
ALTER TABLE `cor4edu_staff` DROP COLUMN IF EXISTS `city`;
ALTER TABLE `cor4edu_staff` DROP COLUMN IF EXISTS `state`;
ALTER TABLE `cor4edu_staff` DROP COLUMN IF EXISTS `zipCode`;
ALTER TABLE `cor4edu_staff` DROP COLUMN IF EXISTS `country`;
ALTER TABLE `cor4edu_staff` DROP COLUMN IF EXISTS `dateOfBirth`;
ALTER TABLE `cor4edu_staff` DROP COLUMN IF EXISTS `emergencyContact`;
ALTER TABLE `cor4edu_staff` DROP COLUMN IF EXISTS `emergencyPhone`;
ALTER TABLE `cor4edu_staff` DROP COLUMN IF EXISTS `teachingPrograms`;
ALTER TABLE `cor4edu_staff` DROP COLUMN IF EXISTS `notes`;

-- Remove any problematic columns from documents table if they exist
ALTER TABLE `cor4edu_documents` DROP COLUMN IF EXISTS `subcategory`;
ALTER TABLE `cor4edu_documents` DROP COLUMN IF EXISTS `expirationDate`;
ALTER TABLE `cor4edu_documents` DROP COLUMN IF EXISTS `replacesDocumentID`;

-- Clean up any broken foreign key constraints
-- (These will fail silently if constraints don't exist)
ALTER TABLE `cor4edu_staff` DROP FOREIGN KEY IF EXISTS `cor4edu_staff_ibfk_1`;
ALTER TABLE `cor4edu_documents` DROP FOREIGN KEY IF EXISTS `cor4edu_documents_ibfk_3`;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Add success message
SELECT 'Database cleanup completed successfully. Problematic tables and columns removed.' AS result;