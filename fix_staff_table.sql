-- Fix cor4edu_staff table - Add missing roleTypeID column
USE cor4edu_sms;

-- Add roleTypeID column (will fail silently if column already exists)
ALTER TABLE `cor4edu_staff`
ADD COLUMN `roleTypeID` INT(11) NULL COMMENT 'References cor4edu_staff_role_types' AFTER `department`;

-- Add indexes (will fail silently if they already exist)
ALTER TABLE `cor4edu_staff`
ADD INDEX `idx_staff_role_type` (`roleTypeID`),
ADD INDEX `idx_staff_active_role` (`active`, `roleTypeID`);
