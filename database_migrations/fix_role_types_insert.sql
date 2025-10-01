-- Fix role types insertion issue
-- The original migration failed because createdBy=1 doesn't exist

-- First, make createdBy nullable for system-created role types
ALTER TABLE `cor4edu_staff_role_types`
MODIFY COLUMN `createdBy` int(11) NULL COMMENT 'NULL for system-created role types';

-- Insert the 6 default role types with NULL createdBy (system-created)
INSERT INTO `cor4edu_staff_role_types` (`roleTypeName`, `description`, `defaultTabAccess`, `isAdminRole`, `canCreateStaff`, `createdBy`) VALUES
('Admissions', 'Admissions staff can access student information and admissions tabs', '["information", "admissions"]', 'N', 'N', NULL),
('Bursar', 'Bursar staff can access student financial/bursar tab only', '["information", "bursar"]', 'N', 'N', NULL),
('Registrar', 'Registrar staff can access registrar and academic tabs', '["information", "admissions", "bursar", "graduation"]', 'N', 'N', NULL),
('Career Services', 'Career services staff can access career and graduation tabs', '["information", "career"]', 'N', 'N', NULL),
('Faculty', 'Faculty can access academic and graduation tabs', '["information", "academics"]', 'N', 'N', NULL),
('School Admin', 'School administrators have access to all tabs and admin functions', '["information", "admissions", "bursar", "registrar", "academics", "career", "graduation"]', 'Y', 'Y', NULL);

-- Verify the data was inserted
SELECT roleTypeID, roleTypeName, description, isAdminRole FROM cor4edu_staff_role_types ORDER BY roleTypeName;