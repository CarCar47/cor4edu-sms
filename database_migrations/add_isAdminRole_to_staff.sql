-- Add missing isAdminRole column to staff table
-- This fixes undefined array key errors in bootstrap.php

-- Database: cor4edu_sms
USE `cor4edu_sms`;

-- Add isAdminRole column to cor4edu_staff table
ALTER TABLE `cor4edu_staff`
ADD COLUMN `isAdminRole` ENUM('Y','N') DEFAULT 'N'
COMMENT 'Can access admin functions - copied from role type'
AFTER `roleTypeID`;

-- Update existing users based on their assigned role type
UPDATE `cor4edu_staff` s
JOIN `cor4edu_staff_role_types` rt ON s.roleTypeID = rt.roleTypeID
SET s.isAdminRole = rt.isAdminRole;

-- Update SuperAdmin users (roleTypeID might be NULL but they should be admin)
UPDATE `cor4edu_staff`
SET `isAdminRole` = 'Y'
WHERE `isSuperAdmin` = 'Y';

-- Verify the update worked
-- This should show all staff with their admin status:
-- SELECT staffID, firstName, lastName, username, roleTypeID, isAdminRole, isSuperAdmin FROM cor4edu_staff;