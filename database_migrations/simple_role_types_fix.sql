-- Simple fix for role types - insert with existing staff ID 1
-- Clear any existing data first (in case there are partial inserts)
DELETE FROM cor4edu_staff_role_types;

-- Insert the 6 default role types using staff ID 1 (which exists)
INSERT INTO `cor4edu_staff_role_types` (`roleTypeName`, `description`, `defaultTabAccess`, `isAdminRole`, `canCreateStaff`, `createdBy`) VALUES
('Admissions', 'Admissions staff can access student information and admissions tabs', '["information", "admissions"]', 'N', 'N', 1),
('Bursar', 'Bursar staff can access student financial/bursar tab only', '["information", "bursar"]', 'N', 'N', 1),
('Registrar', 'Registrar staff can access registrar and academic tabs', '["information", "admissions", "bursar", "graduation"]', 'N', 'N', 1),
('Career Services', 'Career services staff can access career and graduation tabs', '["information", "career"]', 'N', 'N', 1),
('Faculty', 'Faculty can access academic and graduation tabs', '["information", "academics"]', 'N', 'N', 1),
('School Admin', 'School administrators have access to all tabs and admin functions', '["information", "admissions", "bursar", "registrar", "academics", "career", "graduation"]', 'Y', 'Y', 1);

-- Verify the data was inserted
SELECT roleTypeID, roleTypeName, description, isAdminRole, canCreateStaff FROM cor4edu_staff_role_types ORDER BY roleTypeName;