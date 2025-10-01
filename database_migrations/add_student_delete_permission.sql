-- =====================================================
-- COR4EDU SMS - Add Student Delete Permission
-- Date: 2025-09-30
-- Description: Adds delete_students permission to system
-- =====================================================

-- Select the database
USE `cor4edu_sms`;

-- Add delete_students permission to system permissions registry
INSERT INTO `cor4edu_system_permissions`
(`permissionID`, `module`, `action`, `name`, `description`, `category`, `requiresAdminRole`, `displayOrder`, `active`, `createdOn`, `createdBy`)
VALUES
(39, 'students', 'delete_students', 'Delete Students', 'Remove students from system (soft delete)', 'student_access', 'Y', 15, 'Y', NOW(), 1);

-- Add delete_students permission to role defaults
-- Only School Admin (roleTypeID=6) gets this permission by default

INSERT INTO `cor4edu_role_permission_defaults`
(`roleTypeID`, `module`, `action`, `allowed`, `description`, `createdOn`, `createdBy`)
VALUES
-- Role 1: Admissions - Cannot delete students
(1, 'students', 'delete_students', 'N', 'Admissions staff cannot delete students', NOW(), 1),

-- Role 2: Bursar - Cannot delete students
(2, 'students', 'delete_students', 'N', 'Bursar staff cannot delete students', NOW(), 1),

-- Role 3: Registrar - Cannot delete students
(3, 'students', 'delete_students', 'N', 'Registrar staff cannot delete students', NOW(), 1),

-- Role 4: Career Services - Cannot delete students
(4, 'students', 'delete_students', 'N', 'Career Services staff cannot delete students', NOW(), 1),

-- Role 5: Faculty - Cannot delete students
(5, 'students', 'delete_students', 'N', 'Faculty cannot delete students', NOW(), 1),

-- Role 6: School Admin - CAN delete students
(6, 'students', 'delete_students', 'Y', 'School Admin can delete students', NOW(), 1);

-- =====================================================
-- Verification Query (Optional - comment out for production)
-- =====================================================
-- SELECT * FROM cor4edu_system_permissions WHERE action = 'delete_students';
-- SELECT * FROM cor4edu_role_permission_defaults WHERE action = 'delete_students';