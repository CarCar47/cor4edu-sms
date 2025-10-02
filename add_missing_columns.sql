-- Add missing columns to Cloud SQL database
-- This fixes the schema drift between local and Cloud

-- Add phone column to cor4edu_staff (if it doesn't exist)
ALTER TABLE `cor4edu_staff`
ADD COLUMN IF NOT EXISTS `phone` VARCHAR(20) NULL AFTER `roleTypeID`,
ADD COLUMN IF NOT EXISTS `address` VARCHAR(255) NULL AFTER `phone`,
ADD COLUMN IF NOT EXISTS `city` VARCHAR(100) NULL AFTER `address`,
ADD COLUMN IF NOT EXISTS `state` VARCHAR(50) NULL AFTER `city`,
ADD COLUMN IF NOT EXISTS `zipCode` VARCHAR(20) NULL AFTER `state`,
ADD COLUMN IF NOT EXISTS `country` VARCHAR(100) DEFAULT 'United States' AFTER `zipCode`,
ADD COLUMN IF NOT EXISTS `dateOfBirth` DATE NULL AFTER `country`,
ADD COLUMN IF NOT EXISTS `emergencyContact` VARCHAR(100) NULL AFTER `dateOfBirth`,
ADD COLUMN IF NOT EXISTS `emergencyPhone` VARCHAR(20) NULL AFTER `emergencyContact`,
ADD COLUMN IF NOT EXISTS `teachingPrograms` JSON NULL COMMENT 'Programs this staff member teaches in' AFTER `emergencyPhone`,
ADD COLUMN IF NOT EXISTS `notes` TEXT NULL COMMENT 'Administrative notes about this staff member' AFTER `teachingPrograms`,
ADD COLUMN IF NOT EXISTS `startDate` DATE AFTER `notes`,
ADD COLUMN IF NOT EXISTS `endDate` DATE AFTER `startDate`;

-- Success message
SELECT 'Missing columns added successfully!' AS Status;
